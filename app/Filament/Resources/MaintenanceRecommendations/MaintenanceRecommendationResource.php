<?php

namespace App\Filament\Resources\MaintenanceRecommendations;

use App\Filament\Resources\MaintenanceRecommendations\Pages\ListMaintenanceRecommendations;
use App\Filament\Resources\MaintenanceRecommendations\Pages\ViewMaintenanceRecommendation;
use App\Filament\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use App\Services\AuditLogService;
use App\Services\MaintenanceRecommendationApprovalService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MaintenanceRecommendationResource extends Resource
{
    protected static ?string $model = MaintenanceRecommendation::class;

    protected static ?string $navigationLabel = 'Recommendations';

    protected static ?string $modelLabel = 'Recommendation';

    protected static ?string $pluralModelLabel = 'Recommendations';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recommendation Summary')
                    ->schema([
                        TextEntry::make('equipment.equipment_code')->label('Equipment code'),
                        TextEntry::make('equipment.equipment_name')->label('Equipment name'),
                        TextEntry::make('equipment.condition')->label('Condition')->badge(),
                        TextEntry::make('equipment.operational_status')->label('Operational status')->badge(),
                        TextEntry::make('rule_key')->label('Rule key')->badge(),
                        TextEntry::make('title'),
                        TextEntry::make('risk_level')->label('Risk level')->badge()->color(fn (string $state): string => self::riskColor($state)),
                        TextEntry::make('status')->label('Recommendation status')->badge()->color(fn (string $state): string => self::statusColor($state)),
                        TextEntry::make('generated_at')->label('Generated date')->dateTime(),
                    ]),
                Section::make('Why was this recommendation generated?')
                    ->schema([
                        TextEntry::make('why_rule')->label('Rule')->state(fn (MaintenanceRecommendation $record): string => $record->rule_key),
                        TextEntry::make('why_risk')->label('Risk')->state(fn (MaintenanceRecommendation $record): string => $record->risk_level)->badge()->color(fn (string $state): string => self::riskColor($state)),
                        TextEntry::make('why_generated_at')->label('Generated date')->state(fn (MaintenanceRecommendation $record): mixed => $record->generated_at)->dateTime(),
                        TextEntry::make('why_explanation')->label('Explanation')->state(fn (MaintenanceRecommendation $record): string => $record->explanation)->columnSpanFull(),
                    ]),
                Section::make('Suggested Next Action')
                    ->schema([
                        TextEntry::make('suggested_action')
                            ->label('Suggested action')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->suggestedAction())
                            ->badge(),
                        TextEntry::make('suggested_action_type')
                            ->label('Action type')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->getSuggestedActionType()),
                        TextEntry::make('recommended_action')->label('Recommended action')->columnSpanFull(),
                    ]),
                Section::make('Action Workflow')
                    ->schema([
                        TextEntry::make('action_status')
                            ->label('Action status')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->action_status ?: 'Pending')
                            ->badge()
                            ->color(fn (string $state): string => self::actionStatusColor($state)),
                        TextEntry::make('actionedBy.name')->label('Actioned by')->placeholder('Not actioned'),
                        TextEntry::make('actioned_at')->label('Actioned date')->dateTime()->placeholder('Not actioned'),
                        TextEntry::make('action_notes')->label('Action notes')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Linked Work Order')
                    ->schema([
                        TextEntry::make('effective_linked_work_order_number')
                            ->label('Work order number')
                            ->state(fn (MaintenanceRecommendation $record): ?string => $record->effectiveLinkedWorkOrder()?->work_order_number)
                            ->url(fn (MaintenanceRecommendation $record): ?string => $record->effectiveLinkedWorkOrder()
                                ? WorkOrderResource::getUrl('view', ['record' => $record->effectiveLinkedWorkOrder()])
                                : null)
                            ->placeholder('Not linked'),
                        TextEntry::make('effective_linked_work_order_title')
                            ->label('Title')
                            ->state(fn (MaintenanceRecommendation $record): ?string => $record->effectiveLinkedWorkOrder()?->title)
                            ->placeholder('Not linked'),
                        TextEntry::make('effective_linked_work_order_status')
                            ->label('Status')
                            ->state(fn (MaintenanceRecommendation $record): ?string => $record->effectiveLinkedWorkOrder()?->status)
                            ->placeholder('Not linked')
                            ->badge(),
                        TextEntry::make('effective_linked_work_order_assignee')
                            ->label('Assigned technician')
                            ->state(fn (MaintenanceRecommendation $record): ?string => $record->effectiveLinkedWorkOrder()?->assignedTo?->name)
                            ->placeholder('Unassigned'),
                        TextEntry::make('effective_linked_work_order_verified_at')
                            ->label('Verified date')
                            ->state(fn (MaintenanceRecommendation $record): mixed => $record->effectiveLinkedWorkOrder()?->verified_at)
                            ->dateTime()
                            ->placeholder('Not verified'),
                    ]),
                Section::make('Linked Maintenance Request')
                    ->schema([
                        TextEntry::make('linkedMaintenanceRequest.request_number')
                            ->label('Request number')
                            ->url(fn (MaintenanceRecommendation $record): ?string => $record->linkedMaintenanceRequest
                                ? MaintenanceRequestResource::getUrl('view', ['record' => $record->linkedMaintenanceRequest])
                                : null)
                            ->placeholder('Not linked'),
                        TextEntry::make('linkedMaintenanceRequest.status')->label('Status')->placeholder('Not linked')->badge(),
                        TextEntry::make('linkedMaintenanceRequest.severity')->label('Severity')->placeholder('Not linked')->badge(),
                    ]),
                Section::make('Linked Maintenance Schedule')
                    ->schema([
                        TextEntry::make('linkedMaintenanceSchedule.maintenance_type')->label('Maintenance type')->placeholder('Not linked'),
                        TextEntry::make('linkedMaintenanceSchedule.scheduled_date')->label('Scheduled date')->date()->placeholder('Not linked'),
                        TextEntry::make('linkedMaintenanceSchedule.status')->label('Status')->placeholder('Not linked')->badge(),
                    ]),
                Section::make('Review and resolution')
                    ->schema([
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('Not reviewed'),
                        TextEntry::make('reviewed_at')->label('Reviewed date')->dateTime()->placeholder('Not reviewed'),
                        TextEntry::make('resolvedBy.name')->label('Resolved by')->placeholder('Not resolved'),
                        TextEntry::make('resolved_at')->label('Resolved date')->dateTime()->placeholder('Not resolved'),
                        TextEntry::make('metadata')
                            ->label('Metadata')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->metadata === null || $record->metadata === []
                                ? 'None'
                                : json_encode($record->metadata, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                    ]),
                Section::make('Recommendation History')
                    ->schema([
                        TextEntry::make('history_generated')
                            ->label('Generated')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->generated_at?->toDayDateTimeString() ?? 'Not generated'),
                        TextEntry::make('history_reviewed')
                            ->label('Reviewed')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->reviewed_at?->toDayDateTimeString() ?? 'Not reviewed'),
                        TextEntry::make('history_resolved')
                            ->label('Resolved')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->isResolved() && $record->resolved_at ? $record->resolved_at->toDayDateTimeString() : 'Not resolved'),
                        TextEntry::make('history_dismissed')
                            ->label('Dismissed')
                            ->state(fn (MaintenanceRecommendation $record): string => $record->isDismissed() && $record->resolved_at ? $record->resolved_at->toDayDateTimeString() : 'Not dismissed'),
                        TextEntry::make('history_action')
                            ->label('Action workflow')
                            ->state(fn (MaintenanceRecommendation $record): string => ($record->action_status ?: 'Pending').($record->actioned_at ? ' on '.$record->actioned_at->toDayDateTimeString() : '')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['equipment', 'reviewedBy', 'resolvedBy', 'actionedBy', 'linkedWorkOrder.assignedTo', 'linkedMaintenanceSchedule', 'linkedMaintenanceRequest.latestWorkOrder.assignedTo'])
                ->orderByRaw(MaintenanceRecommendation::riskRankSql())
                ->latest('generated_at'))
            ->columns([
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->formatStateUsing(fn (MaintenanceRecommendation $record): string => $record->equipment->equipment_code.' - '.$record->equipment->equipment_name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'equipment',
                        fn (Builder $query): Builder => $query
                            ->where('equipment_code', 'like', "%{$search}%")
                            ->orWhere('equipment_name', 'like', "%{$search}%")
                    ))
                    ->sortable(),
                TextColumn::make('rule_key')
                    ->label('Rule')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('explanation')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('recommended_action')
                    ->label('Recommended action')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('suggested_action')
                    ->label('Suggested action')
                    ->state(fn (MaintenanceRecommendation $record): string => $record->suggestedAction())
                    ->toggleable(),
                TextColumn::make('risk_level')
                    ->label('Risk')
                    ->badge()
                    ->color(fn (string $state): string => self::riskColor($state))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(MaintenanceRecommendation::riskRankSql().' '.$direction)),
                TextColumn::make('status')
                    ->label('Recommendation status')
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('action_status')
                    ->label('Action status')
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'Pending')
                    ->badge()
                    ->color(fn (?string $state): string => self::actionStatusColor($state ?: 'Pending'))
                    ->sortable(),
                TextColumn::make('linkedWorkOrder.work_order_number')
                    ->label('Linked work order')
                    ->state(fn (MaintenanceRecommendation $record): ?string => $record->effectiveLinkedWorkOrder()?->work_order_number)
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('linkedMaintenanceSchedule.maintenance_type')
                    ->label('Linked schedule')
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('linkedMaintenanceRequest.request_number')
                    ->label('Linked request')
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('generated_at')
                    ->label('Generated date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed date')
                    ->dateTime()
                    ->placeholder('Not reviewed')
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Resolved date')
                    ->dateTime()
                    ->placeholder('Not resolved')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('risk_level')
                    ->label('Risk level')
                    ->options(MaintenanceRecommendation::riskLevelOptions()),
                SelectFilter::make('status')
                    ->label('Recommendation status')
                    ->options(MaintenanceRecommendation::statusOptions()),
                SelectFilter::make('action_status')
                    ->label('Action status')
                    ->options(MaintenanceRecommendation::actionStatusOptions()),
                SelectFilter::make('suggested_action_type')
                    ->label('Suggested action type')
                    ->options(MaintenanceRecommendation::suggestedActionTypeOptions()),
                SelectFilter::make('rule_key')
                    ->label('Rule')
                    ->options(MaintenanceRecommendation::ruleKeyOptions()),
                SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->options(fn (): array => Equipment::query()
                        ->orderBy('equipment_code')
                        ->get()
                        ->mapWithKeys(fn (Equipment $equipment): array => [
                            $equipment->id => $equipment->equipment_code.' - '.$equipment->equipment_name,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                Filter::make('generated_at')
                    ->form([
                        DatePicker::make('generated_from')->label('Generated from'),
                        DatePicker::make('generated_until')->label('Generated until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['generated_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('generated_at', '>=', $date))
                        ->when($data['generated_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('generated_at', '<=', $date))),
                Filter::make('reviewed_at')
                    ->form([
                        DatePicker::make('reviewed_from')->label('Reviewed from'),
                        DatePicker::make('reviewed_until')->label('Reviewed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['reviewed_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('reviewed_at', '>=', $date))
                        ->when($data['reviewed_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('reviewed_at', '<=', $date))),
                Filter::make('resolved_at')
                    ->form([
                        DatePicker::make('resolved_from')->label('Resolved from'),
                        DatePicker::make('resolved_until')->label('Resolved until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['resolved_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('resolved_at', '>=', $date))
                        ->when($data['resolved_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('resolved_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                self::markReviewedAction(),
                self::markResolvedAction(),
                self::dismissAction(),
                self::approveAction(),
                self::rejectAction(),
                self::executeAction(),
                self::cancelAction(),
            ]);
    }

    public static function markReviewedAction(): Action
    {
        return Action::make('markReviewed')
            ->label('Mark Reviewed')
            ->icon('heroicon-o-eye')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRecommendation $record): bool => ! $record->isReviewed()
                && ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('review', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record): void {
                $record->markReviewed(auth()->user());
                app(AuditLogService::class)->log('reviewed', 'AI Recommendation', "Recommendation {$record->title} reviewed.", auth()->user(), $record);

                Notification::make()
                    ->title('Recommendation marked reviewed')
                    ->success()
                    ->send();
            });
    }

    public static function markResolvedAction(): Action
    {
        return Action::make('markResolved')
            ->label('Mark Resolved')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRecommendation $record): bool => ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('resolve', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record): void {
                $record->markResolved(auth()->user());
                app(AuditLogService::class)->log('resolved', 'AI Recommendation', "Recommendation {$record->title} resolved.", auth()->user(), $record);

                Notification::make()
                    ->title('Recommendation marked resolved')
                    ->success()
                    ->send();
            });
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon('heroicon-o-x-circle')
            ->requiresConfirmation()
            ->visible(fn (MaintenanceRecommendation $record): bool => ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('dismiss', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record): void {
                $record->dismiss(auth()->user());
                app(AuditLogService::class)->log('dismissed', 'AI Recommendation', "Recommendation {$record->title} dismissed.", auth()->user(), $record);

                Notification::make()
                    ->title('Recommendation dismissed')
                    ->success()
                    ->send();
            });
    }

    public static function approveAction(): Action
    {
        return Action::make('approveAction')
            ->label('Approve Action')
            ->icon('heroicon-o-hand-thumb-up')
            ->form([
                Textarea::make('action_notes')
                    ->label('Action notes'),
            ])
            ->visible(fn (MaintenanceRecommendation $record): bool => ($record->isActionPending()
                || $record->isActionApproved())
                && ! $record->isResolved()
                && ! $record->isDismissed()
                && (auth()->user()?->can('approveAction', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record, array $data): void {
                try {
                    $result = app(MaintenanceRecommendationApprovalService::class)->approve($record, auth()->user(), $data['action_notes'] ?? null);
                    $notification = Notification::make()
                        ->title($result['message']);

                    match ($result['status']) {
                        'success' => $notification->success(),
                        'warning' => $notification->warning(),
                        'info' => $notification->info(),
                        default => $notification->danger(),
                    };

                    if ($result['maintenance_request'] !== null) {
                        $notification
                            ->body('Maintenance request: '.$result['maintenance_request']->request_number)
                            ->actions([
                                Action::make('viewMaintenanceRequest')
                                    ->label('View Maintenance Request')
                                    ->url(MaintenanceRequestResource::getUrl('view', ['record' => $result['maintenance_request']])),
                            ]);
                    }

                    $notification->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('Failed to create maintenance request: '.$exception->getMessage())->danger()->send();
                    throw ValidationException::withMessages(['action_notes' => $exception->getMessage()]);
                }
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('rejectAction')
            ->label('Reject Action')
            ->icon('heroicon-o-no-symbol')
            ->form([
                Textarea::make('action_notes')
                    ->label('Rejection notes')
                    ->required(),
            ])
            ->visible(fn (MaintenanceRecommendation $record): bool => $record->isActionPending()
                && (auth()->user()?->can('rejectAction', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record, array $data): void {
                try {
                    $record->rejectAction(auth()->user(), $data['action_notes'] ?? '');
                    app(AuditLogService::class)->log('action_rejected', 'AI Recommendation', "Recommendation action rejected for {$record->title}.", auth()->user(), $record);
                    Notification::make()->title('Recommendation action rejected')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    throw ValidationException::withMessages(['action_notes' => $exception->getMessage()]);
                }
            });
    }

    public static function executeAction(): Action
    {
        return Action::make('executeAction')
            ->label('Execute Action')
            ->icon('heroicon-o-bolt')
            ->requiresConfirmation()
            ->form([
                Textarea::make('action_notes')
                    ->label('Execution notes'),
            ])
            ->visible(fn (MaintenanceRecommendation $record): bool => $record->isActionApproved()
                && ! $record->isResolved()
                && ! $record->isDismissed()
                && ! $record->linked_work_order_id
                && ! $record->linked_maintenance_schedule_id
                && (auth()->user()?->can('executeAction', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record, array $data): void {
                try {
                    $record->executeAction(auth()->user(), $data['action_notes'] ?? null);
                    app(AuditLogService::class)->log('action_executed', 'AI Recommendation', "Recommendation action executed for {$record->title}.", auth()->user(), $record);
                    Notification::make()->title('Recommendation action processed')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    throw ValidationException::withMessages(['action_notes' => $exception->getMessage()]);
                }
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancelAction')
            ->label('Cancel Action')
            ->icon('heroicon-o-x-circle')
            ->form([
                Textarea::make('action_notes')
                    ->label('Cancellation notes')
                    ->required(),
            ])
            ->visible(fn (MaintenanceRecommendation $record): bool => in_array($record->action_status ?: 'Pending', ['Pending', 'Approved'], true)
                && (auth()->user()?->can('cancelAction', $record) ?? false))
            ->action(function (MaintenanceRecommendation $record, array $data): void {
                try {
                    $record->cancelAction(auth()->user(), $data['action_notes'] ?? '');
                    app(AuditLogService::class)->log('action_cancelled', 'AI Recommendation', "Recommendation action cancelled for {$record->title}.", auth()->user(), $record);
                    Notification::make()->title('Recommendation action cancelled')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    throw ValidationException::withMessages(['action_notes' => $exception->getMessage()]);
                }
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRecommendations::route('/'),
            'view' => ViewMaintenanceRecommendation::route('/{record}'),
        ];
    }

    private static function riskColor(string $state): string
    {
        return match ($state) {
            'Critical' => 'danger',
            'High' => 'warning',
            'Low' => 'gray',
            default => 'info',
        };
    }

    private static function statusColor(string $state): string
    {
        return match ($state) {
            'Resolved' => 'success',
            'Dismissed' => 'gray',
            'Approved' => 'info',
            'Reviewed' => 'info',
            default => 'warning',
        };
    }

    private static function actionStatusColor(string $state): string
    {
        return match ($state) {
            'Approved' => 'info',
            'Executed' => 'success',
            'Rejected', 'Cancelled' => 'danger',
            default => 'warning',
        };
    }
}
