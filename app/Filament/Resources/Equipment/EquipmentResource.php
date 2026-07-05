<?php

namespace App\Filament\Resources\Equipment;

use App\Filament\Resources\AssetActionRequests\AssetActionRequestResource;
use App\Filament\Resources\Equipment\Pages\CreateEquipment;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\MaintenanceRecommendations\MaintenanceRecommendationResource;
use App\Models\AssetActionRequest;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Services\EquipmentLifecycleAnalyzer;
use App\Services\EquipmentQrCodeGenerator;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static ?string $navigationLabel = 'Equipment';

    protected static ?string $modelLabel = 'Equipment';

    protected static ?string $pluralModelLabel = 'Equipment';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic details')
                    ->schema([
                        FileUpload::make('photo_path')
                            ->label('Equipment photo')
                            ->disk('public')
                            ->directory('equipment/photos')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->imagePreviewHeight('160')
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                        TextInput::make('equipment_code')
                            ->label('Equipment code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('property_number')
                            ->label('Property number')
                            ->maxLength(255),
                        TextInput::make('equipment_name')
                            ->label('Equipment name')
                            ->required()
                            ->maxLength(255),
                        Select::make('equipment_category_id')
                            ->label('Category')
                            ->options(fn (): array => EquipmentCategory::active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        TextInput::make('brand')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label('Serial number')
                            ->maxLength(255),
                    ]),
                Section::make('Assignment and status')
                    ->schema([
                        Select::make('current_location_id')
                            ->label('Current location')
                            ->options(fn (): array => Location::active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('location_transfer_remarks')
                            ->label('Location transfer remarks')
                            ->columnSpanFull(),
                        TextInput::make('custodian')
                            ->maxLength(255),
                        Select::make('condition')
                            ->options(Equipment::conditionOptions())
                            ->required()
                            ->rule(Rule::in(Equipment::CONDITIONS)),
                        Select::make('operational_status')
                            ->label('Operational status')
                            ->options(Equipment::operationalStatusOptions())
                            ->required()
                            ->rule(Rule::in(Equipment::OPERATIONAL_STATUSES)),
                        Toggle::make('is_archived')
                            ->label('Is archived')
                            ->required()
                            ->default(false)
                            ->disabled(fn (?Equipment $record): bool => $record === null
                                ? ! auth()->user()?->can('equipment.archive')
                                : Gate::denies('archive', $record))
                            ->dehydrated(fn (?Equipment $record): bool => $record === null
                                ? auth()->user()?->can('equipment.archive') ?? false
                                : Gate::allows('archive', $record)),
                    ]),
                Section::make('Acquisition and maintenance')
                    ->schema([
                        DatePicker::make('acquisition_date')
                            ->label('Acquisition date'),
                        TextInput::make('acquisition_cost')
                            ->label('Acquisition cost')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('maintenance_frequency')
                            ->label('Maintenance frequency')
                            ->maxLength(255),
                        DatePicker::make('last_maintenance_date')
                            ->label('Last maintenance date'),
                        DatePicker::make('next_maintenance_date')
                            ->label('Next maintenance date'),
                        DatePicker::make('warranty_expiration_date')
                            ->label('Warranty expiration date'),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic equipment details')
                    ->schema([
                        ImageEntry::make('normalized_photo_path')
                            ->label('Equipment photo')
                            ->disk('public')
                            ->state(fn (Equipment $record): ?string => $record->filamentPhotoImageState())
                            ->height(180)
                            ->visible(fn (Equipment $record): bool => filled($record->filamentPhotoImageState())),
                        TextEntry::make('equipment_code')->label('Equipment code'),
                        TextEntry::make('property_number')->label('Property number')->placeholder('None'),
                        TextEntry::make('equipment_name')->label('Equipment name'),
                        TextEntry::make('category.name')->label('Category'),
                        TextEntry::make('currentLocation.name')->label('Current location'),
                        TextEntry::make('qr_identifier')->label('QR identifier')->placeholder('Not assigned'),
                        TextEntry::make('qr_lookup_url')
                            ->label('QR lookup URL')
                            ->state(fn (Equipment $record): string => $record->getQrLookupUrl()),
                        ImageEntry::make('qr_code_url')
                            ->label('QR code')
                            ->disk('public')
                            ->visibility('public')
                            ->state(fn (Equipment $record): ?string => $record->qr_code_url)
                            ->height(180)
                            ->visible(fn (Equipment $record): bool => filled($record->qr_code_url)),
                        TextEntry::make('qr_code_generated_at')
                            ->label('QR generated at')
                            ->dateTime()
                            ->placeholder('Not generated'),
                        TextEntry::make('condition')->badge(),
                        TextEntry::make('operational_status')->label('Operational status')->badge(),
                    ]),
                Section::make('Maintenance and archive details')
                    ->schema([
                        TextEntry::make('last_maintenance_date')->label('Last maintenance date')->date()->placeholder('None'),
                        TextEntry::make('next_maintenance_date')->label('Next maintenance date')->date()->placeholder('None'),
                        TextEntry::make('warranty_expiration_date')->label('Warranty expiration date')->date()->placeholder('None'),
                        TextEntry::make('is_archived')
                            ->label('Archive status')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Archived' : 'Active')
                            ->badge(),
                        TextEntry::make('archived_at')->label('Archived at')->dateTime()->placeholder('Not archived'),
                        TextEntry::make('archivedBy.name')->label('Archived by')->placeholder('Not archived'),
                        TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Maintenance schedules')
                    ->schema([
                        RepeatableEntry::make('maintenanceSchedules')
                            ->label('Schedules')
                            ->schema([
                                TextEntry::make('maintenance_type')->label('Maintenance type'),
                                TextEntry::make('maintenance_frequency')->label('Frequency'),
                                TextEntry::make('scheduled_date')->label('Scheduled date')->date(),
                                TextEntry::make('priority')->badge(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('assignedUser.name')->label('Assigned user')->placeholder('Unassigned'),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Maintenance requests')
                    ->schema([
                        RepeatableEntry::make('maintenanceRequests')
                            ->label('Requests')
                            ->schema([
                                TextEntry::make('request_number')->label('Request number'),
                                TextEntry::make('submittedBy.name')->label('Submitted by'),
                                TextEntry::make('severity')->badge(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('created_at')->label('Created date')->dateTime(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Work orders')
                    ->schema([
                        RepeatableEntry::make('workOrders')
                            ->label('Work orders')
                            ->schema([
                                TextEntry::make('work_order_number')->label('Work order number'),
                                TextEntry::make('assignedTo.name')->label('Assigned to')->placeholder('Unassigned'),
                                TextEntry::make('priority')->badge(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('created_at')->label('Created date')->dateTime(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Open recommendations')
                    ->schema([
                        RepeatableEntry::make('openMaintenanceRecommendations')
                            ->label('Recommendations')
                            ->schema([
                                TextEntry::make('rule_key')->label('Rule')->badge(),
                                TextEntry::make('risk_level')
                                    ->label('Risk')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Critical' => 'danger',
                                        'High' => 'warning',
                                        'Low' => 'gray',
                                        default => 'info',
                                    }),
                                TextEntry::make('suggested_action')
                                    ->label('Suggested Action')
                                    ->state(fn (MaintenanceRecommendation $record): string => $record->suggestedAction()),
                                TextEntry::make('action_status')
                                    ->label('Action Status')
                                    ->state(fn (MaintenanceRecommendation $record): string => $record->action_status ?: 'Pending')
                                    ->badge(),
                                TextEntry::make('status')->label('Recommendation Status')->badge(),
                                TextEntry::make('linkedWorkOrder.work_order_number')->label('Linked Work Order')->placeholder('None'),
                                TextEntry::make('linkedMaintenanceSchedule.maintenance_type')->label('Linked Schedule')->placeholder('None'),
                                TextEntry::make('generated_at')->label('Generated date')->dateTime(),
                                TextEntry::make('view_recommendation')
                                    ->label('View Recommendation')
                                    ->state('Open Recommendation')
                                    ->url(fn (MaintenanceRecommendation $record): string => MaintenanceRecommendationResource::getUrl('view', ['record' => $record])),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (): bool => auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false),
                Section::make('Lifecycle analysis')
                    ->schema([
                        TextEntry::make('lifecycleProfile.health_score')->label('Health score')->placeholder('Not calculated')->badge(),
                        TextEntry::make('lifecycleProfile.health_grade')->label('Health grade')->placeholder('Not calculated')->badge(),
                        TextEntry::make('lifecycleProfile.lifecycle_status')->label('Lifecycle status')->placeholder('Not calculated')->badge(),
                        TextEntry::make('lifecycleProfile.replacement_recommendation')->label('Replacement recommendation')->placeholder('Not calculated')->badge(),
                        TextEntry::make('lifecycleProfile.estimated_remaining_life_months')->label('Estimated remaining life (months)')->placeholder('Unknown'),
                        TextEntry::make('lifecycleProfile.estimated_end_of_life_date')->label('Estimated end-of-life date')->date()->placeholder('Unknown'),
                        TextEntry::make('maintenance_cost_total')
                            ->label('Total maintenance cost')
                            ->state(fn (Equipment $record): string => number_format($record->maintenanceCostTotal(), 2)),
                        TextEntry::make('repair_count')
                            ->label('Repair count')
                            ->state(fn (Equipment $record): int => $record->repairCount()),
                        TextEntry::make('lifecycleProfile.last_calculated_at')->label('Last calculated date')->dateTime()->placeholder('Not calculated'),
                        TextEntry::make('lifecycleProfile.replacement_reason')->label('Replacement reason')->placeholder('Not calculated')->columnSpanFull(),
                        TextEntry::make('lifecycle_scoring_details')
                            ->label('Scoring details')
                            ->state(function (Equipment $record): string {
                                $details = $record->lifecycleProfile?->metadata['score_details'] ?? [];

                                if ($details === []) {
                                    return 'No scoring details available.';
                                }

                                return collect($details)
                                    ->map(fn (array $detail): string => "{$detail['factor']}: -{$detail['points']} ({$detail['reason']})")
                                    ->implode("\n");
                            })
                            ->placeholder('No scoring details available.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Equipment $record): bool => auth()->user()?->can('view', $record) ?? false),
                Section::make('Asset action requests')
                    ->schema([
                        RepeatableEntry::make('assetActionRequests')
                            ->label('Requests')
                            ->schema([
                                TextEntry::make('request_number')->label('Request number'),
                                TextEntry::make('request_type')->label('Request type')->badge(),
                                TextEntry::make('priority')->badge(),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('estimated_cost')->label('Estimated cost')->money('PHP')->placeholder('None'),
                                TextEntry::make('requestedBy.name')->label('Requested by'),
                                TextEntry::make('created_at')->label('Created date')->dateTime(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Equipment $record): bool => (auth()->user()?->can('viewAny', AssetActionRequest::class) ?? false)
                        && $record->assetActionRequests()->exists()),
                Section::make('Location transfer history')
                    ->schema([
                        RepeatableEntry::make('locationHistories')
                            ->label('Transfers')
                            ->schema([
                                TextEntry::make('fromLocation.name')->label('From location')->placeholder('None'),
                                TextEntry::make('toLocation.name')->label('To location'),
                                TextEntry::make('transferredBy.name')->label('Transferred by')->placeholder('Unknown'),
                                TextEntry::make('transferred_at')->label('Transfer date/time')->dateTime(),
                                TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('is_archived')->latest('created_at'))
            ->columns([
                ImageColumn::make('normalized_photo_path')
                    ->label('Photo')
                    ->disk('public')
                    ->state(fn (Equipment $record): ?string => $record->filamentPhotoImageState())
                    ->height(44)
                    ->square(),
                TextColumn::make('equipment_code')
                    ->label('Equipment code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('property_number')
                    ->label('Property number')
                    ->searchable()
                    ->placeholder('None'),
                TextColumn::make('equipment_name')
                    ->label('Equipment name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('currentLocation.name')
                    ->label('Current location'),
                TextColumn::make('condition')
                    ->badge(),
                TextColumn::make('operational_status')
                    ->label('Operational status')
                    ->badge(),
                TextColumn::make('next_maintenance_date')
                    ->label('Next maintenance date')
                    ->date()
                    ->sortable(),
                ImageColumn::make('qr_code_url')
                    ->label('QR code')
                    ->disk('public')
                    ->visibility('public')
                    ->state(fn (Equipment $record): ?string => $record->qr_code_url)
                    ->height(44)
                    ->square()
                    ->visible(fn (): bool => auth()->user()?->can('equipment.view') ?? false),
                TextColumn::make('qr_code_path')
                    ->label('QR status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Generated' : 'Missing'),
                TextColumn::make('is_archived')
                    ->label('Archived status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Archived' : 'Active'),
                TextColumn::make('created_at')
                    ->label('Created date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('equipment_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                SelectFilter::make('current_location_id')
                    ->label('Current location')
                    ->relationship('currentLocation', 'name'),
                SelectFilter::make('condition')
                    ->options(Equipment::conditionOptions()),
                SelectFilter::make('operational_status')
                    ->label('Operational status')
                    ->options(Equipment::operationalStatusOptions()),
                TernaryFilter::make('is_archived')
                    ->label('Archived status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::openQrLookupAction(),
                self::openQrCodeFileAction(),
                self::generateQrCodeAction(),
                self::recalculateLifecycleAction(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->visible(fn (Equipment $record): bool => ! $record->is_archived && auth()->user()?->can('archive', $record))
                    ->action(function (Equipment $record): void {
                        $record->update([
                            'is_archived' => true,
                            'archived_at' => now(),
                            'archived_by' => auth()->id(),
                        ]);
                    }),
            ]);
    }

    public static function generateQrCodeAction(): Action
    {
        return Action::make('generateQrCode')
            ->label(fn (Equipment $record): string => $record->qr_code_path ? 'Regenerate QR Code' : 'Generate QR Code')
            ->icon('heroicon-o-qr-code')
            ->requiresConfirmation()
            ->visible(fn (Equipment $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Equipment $record): void {
                app(EquipmentQrCodeGenerator::class)->generate($record);

                Notification::make()
                    ->title('QR code generated')
                    ->success()
                    ->send();
            });
    }

    public static function openQrLookupAction(): Action
    {
        return Action::make('openQrLookup')
            ->label('Open QR Lookup')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (Equipment $record): string => $record->getQrLookupUrl())
            ->openUrlInNewTab()
            ->visible(fn (Equipment $record): bool => auth()->user()?->can('view', $record) ?? false);
    }

    public static function openQrCodeFileAction(): Action
    {
        return Action::make('openQrCodeFile')
            ->label('Open QR Code File')
            ->icon('heroicon-o-document-arrow-down')
            ->url(fn (Equipment $record): string => $record->getQrCodeUrl() ?? '#')
            ->openUrlInNewTab()
            ->visible(fn (Equipment $record): bool => filled($record->getQrCodeUrl()) && (auth()->user()?->can('view', $record) ?? false));
    }

    public static function recalculateLifecycleAction(): Action
    {
        return Action::make('recalculateLifecycle')
            ->label('Recalculate Lifecycle')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(fn (Equipment $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Equipment $record): void {
                app(EquipmentLifecycleAnalyzer::class)->analyze($record);

                Notification::make()
                    ->title('Lifecycle analysis recalculated')
                    ->success()
                    ->send();
            });
    }

    public static function createAssetActionRequestAction(): Action
    {
        return Action::make('createAssetActionRequest')
            ->label('Create Asset Action Request')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->url(fn (Equipment $record): string => AssetActionRequestResource::getUrl('create').'?equipment_id='.$record->id)
            ->visible(fn (Equipment $record): bool => auth()->user()?->can('create', AssetActionRequest::class) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', Equipment::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Equipment::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Equipment::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'equipment_code',
            'property_number',
            'equipment_name',
            'brand',
            'model',
            'serial_number',
            'custodian',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipment::route('/'),
            'create' => CreateEquipment::route('/create'),
            'view' => ViewEquipment::route('/{record}'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }
}
