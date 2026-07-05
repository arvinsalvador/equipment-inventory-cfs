<?php

namespace App\Filament\Resources\MaintenanceRequests;

use App\Filament\Concerns\HasSafeRelationshipSearch;
use App\Filament\Resources\MaintenanceRequests\Pages\CreateMaintenanceRequest;
use App\Filament\Resources\MaintenanceRequests\Pages\EditMaintenanceRequest;
use App\Filament\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Filament\Resources\MaintenanceRequests\Pages\ViewMaintenanceRequest;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Services\AuditLogService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MaintenanceRequestResource extends Resource
{
    use HasSafeRelationshipSearch;

    protected static ?string $model = MaintenanceRequest::class;

    protected static ?string $navigationLabel = 'Maintenance Requests';

    protected static ?string $modelLabel = 'Maintenance Request';

    protected static ?string $pluralModelLabel = 'Maintenance Requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request details')
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Equipment')
                            ->options(fn (): array => Equipment::query()
                                ->orderBy('equipment_code')
                                ->get()
                                ->mapWithKeys(fn (Equipment $equipment): array => [
                                    $equipment->id => $equipment->equipment_code.' - '.$equipment->equipment_name,
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('problem_description')
                            ->label('Problem description')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('severity')
                            ->options(MaintenanceRequest::severityOptions())
                            ->required()
                            ->rule(Rule::in(MaintenanceRequest::SEVERITIES)),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request details')
                    ->schema([
                        TextEntry::make('request_number')->label('Request number'),
                        TextEntry::make('equipment.equipment_code')->label('Equipment code'),
                        TextEntry::make('equipment.equipment_name')->label('Equipment name'),
                        TextEntry::make('submittedBy.name')->label('Submitted by'),
                        TextEntry::make('severity')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('problem_description')->label('Problem description')->columnSpanFull(),
                        TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Review details')
                    ->schema([
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('Not reviewed'),
                        TextEntry::make('reviewed_at')->label('Reviewed date')->dateTime()->placeholder('Not reviewed'),
                        TextEntry::make('review_remarks')->label('Review remarks')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('rejectedBy.name')->label('Rejected by')->placeholder('Not rejected'),
                        TextEntry::make('rejected_at')->label('Rejected date')->dateTime()->placeholder('Not rejected'),
                        TextEntry::make('rejection_reason')->label('Rejection reason')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('convertedBy.name')->label('Converted by')->placeholder('Not converted'),
                        TextEntry::make('converted_at')->label('Converted date')->dateTime()->placeholder('Not converted'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipment', 'submittedBy'])->latest())
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->formatStateUsing(fn (MaintenanceRequest $record): string => $record->equipment->equipment_code.' - '.$record->equipment->equipment_name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchEquipment($query, $search))
                    ->sortable(),
                TextColumn::make('submittedBy.name')
                    ->label('Submitted by')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchUserRelation($query, $search, 'submittedBy'))
                    ->sortable(),
                TextColumn::make('problem_description')
                    ->label('Problem description')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('severity')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable()
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed date')
                    ->dateTime()
                    ->placeholder('Not reviewed')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MaintenanceRequest::statusOptions()),
                SelectFilter::make('severity')
                    ->options(MaintenanceRequest::severityOptions()),
                SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->relationship('equipment', 'equipment_code')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::approveAction(),
                self::rejectAction(),
                self::convertAction(),
                self::cancelAction(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->form([
                Textarea::make('review_remarks')
                    ->label('Review remarks'),
            ])
            ->visible(fn (MaintenanceRequest $record): bool => in_array($record->status, ['Submitted', 'For review'], true)
                && (auth()->user()?->can('approve', $record) ?? false))
            ->action(function (MaintenanceRequest $record, array $data): void {
                try {
                    $record->approve(auth()->user(), $data['review_remarks'] ?? null);
                    app(AuditLogService::class)->logApproval('Maintenance Request', "Maintenance request {$record->request_number} approved.", auth()->user(), $record);

                    Notification::make()
                        ->title('Maintenance request approved')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-no-symbol')
            ->requiresConfirmation()
            ->form([
                Textarea::make('rejection_reason')
                    ->label('Rejection reason')
                    ->required(),
            ])
            ->visible(fn (MaintenanceRequest $record): bool => in_array($record->status, ['Submitted', 'For review'], true)
                && (auth()->user()?->can('reject', $record) ?? false))
            ->action(function (MaintenanceRequest $record, array $data): void {
                try {
                    $record->reject($data['rejection_reason'] ?? '', auth()->user());
                    app(AuditLogService::class)->log('rejected', 'Maintenance Request', "Maintenance request {$record->request_number} rejected.", auth()->user(), $record);

                    Notification::make()
                        ->title('Maintenance request rejected')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function convertAction(): Action
    {
        return Action::make('convertToWorkOrder')
            ->label('Convert to Work Order')
            ->icon('heroicon-o-arrow-right-circle')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRequest $record): bool => $record->isApproved()
                && ! $record->workOrders()->exists()
                && (auth()->user()?->can('convert', $record) ?? false))
            ->action(function (MaintenanceRequest $record): void {
                try {
                    $workOrder = $record->createWorkOrder(auth()->user());
                    app(AuditLogService::class)->log('converted', 'Maintenance Request', "Maintenance request {$record->request_number} converted to work order {$workOrder->work_order_number}.", auth()->user(), $record, null, null, [
                        'work_order_id' => $workOrder->id,
                    ]);

                    Notification::make()
                        ->title('Work order created')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->requiresConfirmation()
            ->form([
                Textarea::make('remarks')
                    ->label('Cancellation remarks')
                    ->required(),
            ])
            ->visible(fn (MaintenanceRequest $record): bool => ! $record->isApproved()
                && ! $record->isClosed()
                && (auth()->user()?->can('cancel', $record) ?? false))
            ->action(function (MaintenanceRequest $record, array $data): void {
                try {
                    $record->cancel($data['remarks'] ?? null);
                    app(AuditLogService::class)->log('cancelled', 'Maintenance Request', "Maintenance request {$record->request_number} cancelled.", auth()->user(), $record);

                    Notification::make()
                        ->title('Maintenance request cancelled')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRequest::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRequest::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', MaintenanceRequest::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'create' => CreateMaintenanceRequest::route('/create'),
            'view' => ViewMaintenanceRequest::route('/{record}'),
            'edit' => EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }
}
