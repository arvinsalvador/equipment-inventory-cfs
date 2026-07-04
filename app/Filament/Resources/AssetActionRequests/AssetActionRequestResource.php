<?php

namespace App\Filament\Resources\AssetActionRequests;

use App\Filament\Resources\AssetActionRequests\Pages\CreateAssetActionRequest;
use App\Filament\Resources\AssetActionRequests\Pages\EditAssetActionRequest;
use App\Filament\Resources\AssetActionRequests\Pages\ListAssetActionRequests;
use App\Filament\Resources\AssetActionRequests\Pages\ViewAssetActionRequest;
use App\Models\AssetActionRequest;
use App\Models\Equipment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class AssetActionRequestResource extends Resource
{
    protected static ?string $model = AssetActionRequest::class;

    protected static ?string $navigationLabel = 'Asset Action Requests';

    protected static ?string $modelLabel = 'Asset Action Request';

    protected static ?string $pluralModelLabel = 'Asset Action Requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Lifecycle';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

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
                        Select::make('request_type')
                            ->label('Request type')
                            ->options(AssetActionRequest::requestTypeOptions())
                            ->required()
                            ->rule(Rule::in(AssetActionRequest::REQUEST_TYPES)),
                        Select::make('priority')
                            ->options(AssetActionRequest::priorityOptions())
                            ->required()
                            ->default('Normal')
                            ->rule(Rule::in(AssetActionRequest::PRIORITIES)),
                        Select::make('status')
                            ->options(AssetActionRequest::statusOptions())
                            ->default('Draft')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('estimated_cost')
                            ->label('Estimated cost')
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('reason')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('justification')
                            ->columnSpanFull(),
                        Textarea::make('recommended_action')
                            ->label('Recommended action')
                            ->columnSpanFull(),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->schema([
                        TextEntry::make('request_number')->label('Request number'),
                        TextEntry::make('equipment.equipment_code')->label('Equipment code'),
                        TextEntry::make('equipment.equipment_name')->label('Equipment name'),
                        TextEntry::make('request_type')->label('Request type')->badge(),
                        TextEntry::make('priority')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('estimated_cost')->label('Estimated cost')->money('PHP')->placeholder('None'),
                        TextEntry::make('reason')->columnSpanFull(),
                        TextEntry::make('justification')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('recommended_action')->label('Recommended action')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Workflow')
                    ->schema([
                        TextEntry::make('requestedBy.name')->label('Requested by'),
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('Not reviewed'),
                        TextEntry::make('reviewed_at')->label('Reviewed date')->dateTime()->placeholder('Not reviewed'),
                        TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('Not approved'),
                        TextEntry::make('approved_at')->label('Approved date')->dateTime()->placeholder('Not approved'),
                        TextEntry::make('rejected_at')->label('Rejected date')->dateTime()->placeholder('Not rejected'),
                        TextEntry::make('rejection_reason')->label('Rejection reason')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('completedBy.name')->label('Completed by')->placeholder('Not completed'),
                        TextEntry::make('completed_at')->label('Completed date')->dateTime()->placeholder('Not completed'),
                        TextEntry::make('cancellation_reason')->label('Cancellation reason')->placeholder('None')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipment', 'requestedBy'])->latest())
            ->columns([
                TextColumn::make('request_number')->label('Request Number')->searchable()->sortable(),
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->formatStateUsing(fn (AssetActionRequest $record): string => $record->equipment->equipment_code.' - '.$record->equipment->equipment_name)
                    ->searchable(['equipment.equipment_code', 'equipment.equipment_name'])
                    ->sortable(),
                TextColumn::make('request_type')->label('Request Type')->badge()->sortable(),
                TextColumn::make('priority')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('requestedBy.name')->label('Requested By')->searchable()->sortable(),
                TextColumn::make('estimated_cost')->label('Estimated Cost')->money('PHP')->sortable(),
                TextColumn::make('created_at')->label('Created Date')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('request_type')->label('Request Type')->options(AssetActionRequest::requestTypeOptions()),
                SelectFilter::make('priority')->options(AssetActionRequest::priorityOptions()),
                SelectFilter::make('status')->options(AssetActionRequest::statusOptions()),
                SelectFilter::make('equipment_id')->label('Equipment')->relationship('equipment', 'equipment_code')->searchable()->preload(),
                SelectFilter::make('requested_by')->label('Requested By')->relationship('requestedBy', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::submitAction(),
                self::markUnderReviewAction(),
                self::approveAction(),
                self::rejectAction(),
                self::cancelAction(),
                self::completeAction(),
            ]);
    }

    public static function submitAction(): Action
    {
        return Action::make('submit')
            ->label('Submit')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->visible(fn (AssetActionRequest $record): bool => auth()->user()?->can('submit', $record) ?? false)
            ->action(function (AssetActionRequest $record): void {
                try {
                    $record->submit();
                    Notification::make()->title('Asset action request submitted')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function markUnderReviewAction(): Action
    {
        return Action::make('markUnderReview')
            ->label('Mark Under Review')
            ->icon('heroicon-o-eye')
            ->requiresConfirmation()
            ->visible(fn (AssetActionRequest $record): bool => auth()->user()?->can('review', $record) ?? false)
            ->action(function (AssetActionRequest $record): void {
                try {
                    $record->markUnderReview(auth()->user());
                    Notification::make()->title('Asset action request under review')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->visible(fn (AssetActionRequest $record): bool => auth()->user()?->can('approve', $record) ?? false)
            ->action(function (AssetActionRequest $record): void {
                try {
                    $record->approve(auth()->user());
                    Notification::make()->title('Asset action request approved')->success()->send();
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
            ->form([
                Textarea::make('rejection_reason')->label('Rejection reason')->required(),
            ])
            ->visible(fn (AssetActionRequest $record): bool => auth()->user()?->can('reject', $record) ?? false)
            ->action(function (AssetActionRequest $record, array $data): void {
                try {
                    $record->reject(auth()->user(), $data['rejection_reason'] ?? '');
                    Notification::make()->title('Asset action request rejected')->success()->send();
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
            ->form([
                Textarea::make('cancellation_reason')->label('Cancellation reason'),
            ])
            ->visible(fn (AssetActionRequest $record): bool => auth()->user()?->can('cancel', $record) ?? false)
            ->action(function (AssetActionRequest $record, array $data): void {
                try {
                    $record->cancel($data['cancellation_reason'] ?? null);
                    Notification::make()->title('Asset action request cancelled')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->label('Complete')
            ->icon('heroicon-o-flag')
            ->form([
                Textarea::make('remarks')->label('Completion remarks'),
            ])
            ->visible(fn (AssetActionRequest $record): bool => auth()->user()?->can('complete', $record) ?? false)
            ->action(function (AssetActionRequest $record, array $data): void {
                try {
                    $record->complete(auth()->user(), $data['remarks'] ?? null);
                    Notification::make()->title('Asset action request completed')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', AssetActionRequest::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', AssetActionRequest::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', AssetActionRequest::class) ?? false;
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
            'index' => ListAssetActionRequests::route('/'),
            'create' => CreateAssetActionRequest::route('/create'),
            'view' => ViewAssetActionRequest::route('/{record}'),
            'edit' => EditAssetActionRequest::route('/{record}/edit'),
        ];
    }
}
