<?php

namespace App\Filament\Resources\WorkOrders;

use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\EvidencesRelationManager;
use App\Models\Equipment;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
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

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $navigationLabel = 'Work Orders';

    protected static ?string $modelLabel = 'Work Order';

    protected static ?string $pluralModelLabel = 'Work Orders';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Work order details')
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
                            ->required()
                            ->disabled(fn (?WorkOrder $record): bool => $record !== null && ! (auth()->user()?->can('assign', $record) ?? false)),
                        Select::make('assigned_to')
                            ->label('Assigned to')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?WorkOrder $record): bool => $record !== null && ! (auth()->user()?->can('assign', $record) ?? false)),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('problem_description')
                            ->label('Problem description')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('priority')
                            ->options(WorkOrder::priorityOptions())
                            ->required()
                            ->rule(Rule::in(WorkOrder::PRIORITIES)),
                        Select::make('status')
                            ->options(WorkOrder::statusOptions())
                            ->required()
                            ->rule(Rule::in(WorkOrder::STATUSES))
                            ->disabled(fn (?WorkOrder $record): bool => $record !== null && ! (auth()->user()?->can('assign', $record) ?? false)),
                        DatePicker::make('due_date')
                            ->label('Due date'),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                    ]),
                Section::make('Work performed')
                    ->schema([
                        Textarea::make('findings')
                            ->columnSpanFull(),
                        Textarea::make('action_performed')
                            ->label('Action performed')
                            ->columnSpanFull(),
                        Textarea::make('completion_remarks')
                            ->label('Completion remarks')
                            ->columnSpanFull(),
                        Select::make('final_equipment_condition')
                            ->label('Final equipment condition')
                            ->options(Equipment::conditionOptions()),
                        Select::make('final_operational_status')
                            ->label('Final operational status')
                            ->options(Equipment::operationalStatusOptions()),
                    ]),
                Section::make('Workflow details')
                    ->schema([
                        Textarea::make('beyond_repair_reason')
                            ->label('Beyond repair reason')
                            ->columnSpanFull(),
                        Textarea::make('recommended_action')
                            ->label('Recommended action')
                            ->columnSpanFull(),
                        Textarea::make('on_hold_reason')
                            ->label('On hold reason')
                            ->columnSpanFull(),
                        Textarea::make('required_parts')
                            ->label('Required parts')
                            ->columnSpanFull(),
                        Textarea::make('rejection_or_cancellation_reason')
                            ->label('Rejection or cancellation reason')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Work order details')
                    ->schema([
                        TextEntry::make('work_order_number')->label('Work order number'),
                        TextEntry::make('maintenanceRequest.request_number')->label('Maintenance request')->placeholder('None'),
                        TextEntry::make('equipment.equipment_code')->label('Equipment code'),
                        TextEntry::make('equipment.equipment_name')->label('Equipment name'),
                        TextEntry::make('createdBy.name')->label('Created by'),
                        TextEntry::make('assignedTo.name')->label('Assigned to')->placeholder('Unassigned'),
                        TextEntry::make('acceptedBy.name')->label('Accepted by')->placeholder('Not accepted'),
                        TextEntry::make('verifiedBy.name')->label('Verified by')->placeholder('Not verified'),
                        TextEntry::make('priority')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('due_date')->label('Due date')->date()->placeholder('None'),
                        TextEntry::make('title'),
                        TextEntry::make('problem_description')->label('Problem description')->columnSpanFull(),
                    ]),
                Section::make('Work details')
                    ->schema([
                        TextEntry::make('findings')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('action_performed')->label('Action performed')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('completion_remarks')->label('Completion remarks')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('final_equipment_condition')->label('Final equipment condition')->placeholder('None'),
                        TextEntry::make('final_operational_status')->label('Final operational status')->placeholder('None'),
                        TextEntry::make('beyond_repair_reason')->label('Beyond repair reason')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('recommended_action')->label('Recommended action')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Workflow dates')
                    ->schema([
                        TextEntry::make('available_at')->label('Available at')->dateTime()->placeholder('Not available'),
                        TextEntry::make('assigned_at')->label('Assigned at')->dateTime()->placeholder('Not assigned'),
                        TextEntry::make('accepted_at')->label('Accepted at')->dateTime()->placeholder('Not accepted'),
                        TextEntry::make('started_at')->label('Started at')->dateTime()->placeholder('Not started'),
                        TextEntry::make('completed_at')->label('Completed at')->dateTime()->placeholder('Not completed'),
                        TextEntry::make('verified_at')->label('Verified at')->dateTime()->placeholder('Not verified'),
                        TextEntry::make('reopened_at')->label('Reopened at')->dateTime()->placeholder('Not reopened'),
                        TextEntry::make('cancelled_at')->label('Cancelled at')->dateTime()->placeholder('Not cancelled'),
                    ]),
                Section::make('Evidence')
                    ->schema([
                        RepeatableEntry::make('evidences')
                            ->label('Evidence')
                            ->schema([
                                ImageEntry::make('image_path')
                                    ->label('Image')
                                    ->disk('public')
                                    ->height(120),
                                TextEntry::make('evidence_type')->label('Evidence type')->badge(),
                                TextEntry::make('caption')->placeholder('None')->columnSpanFull(),
                                TextEntry::make('uploadedBy.name')->label('Uploaded by')->placeholder('Unknown'),
                                TextEntry::make('uploaded_at')->label('Uploaded date')->dateTime(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipment', 'assignedTo'])->latest())
            ->columns([
                TextColumn::make('work_order_number')
                    ->label('Work order number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->formatStateUsing(fn (WorkOrder $record): string => $record->equipment->equipment_code.' - '.$record->equipment->equipment_name)
                    ->searchable(['equipment.equipment_code', 'equipment.equipment_name'])
                    ->sortable(),
                TextColumn::make('assignedTo.name')
                    ->label('Assigned to')
                    ->searchable()
                    ->placeholder('Unassigned'),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due date')
                    ->date()
                    ->placeholder('None')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WorkOrder::statusOptions()),
                SelectFilter::make('priority')
                    ->options(WorkOrder::priorityOptions()),
                SelectFilter::make('assigned_to')
                    ->label('Assigned user')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::assignAction(),
                self::makeAvailableAction(),
                self::acceptAction(),
                self::startAction(),
                self::putOnHoldAction(),
                self::awaitPartsAction(),
                self::submitForVerificationAction(),
                self::completeAction(),
                self::beyondRepairAction(),
                self::verifyAction(),
                self::reopenAction(),
                self::cancelAction(),
            ]);
    }

    public static function assignAction(): Action
    {
        return Action::make('assign')
            ->label('Assign')
            ->icon('heroicon-o-user-plus')
            ->form([
                Select::make('assigned_to')
                    ->label('Assign to')
                    ->options(fn (): array => User::role(['Staff', 'Technician'])->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => auth()->user()?->can('assign', $record) ?? false)
            ->action(function (WorkOrder $record, array $data): void {
                $record->assignTo(User::findOrFail($data['assigned_to']));
                Notification::make()->title('Work order assigned')->success()->send();
            });
    }

    public static function makeAvailableAction(): Action
    {
        return Action::make('makeAvailable')
            ->label('Make Available')
            ->icon('heroicon-o-inbox-stack')
            ->requiresConfirmation()
            ->visible(fn (WorkOrder $record): bool => ! $record->isAvailable()
                && ! $record->isClosed()
                && (auth()->user()?->can('assign', $record) ?? false))
            ->action(function (WorkOrder $record): void {
                $record->makeAvailable();
                Notification::make()->title('Work order made available')->success()->send();
            });
    }

    public static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Accept')
            ->icon('heroicon-o-hand-raised')
            ->requiresConfirmation()
            ->visible(fn (WorkOrder $record): bool => $record->isAvailable()
                && (auth()->user()?->can('accept', $record) ?? false))
            ->action(function (WorkOrder $record): void {
                try {
                    $record->accept(auth()->user());
                    Notification::make()->title('Work order accepted')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function startAction(): Action
    {
        return Action::make('start')
            ->label('Start')
            ->icon('heroicon-o-play')
            ->requiresConfirmation()
            ->visible(fn (WorkOrder $record): bool => in_array($record->status, ['Accepted', 'Assigned'], true)
                && (auth()->user()?->can('updateAssigned', $record) ?? false))
            ->action(function (WorkOrder $record): void {
                try {
                    $record->start();
                    Notification::make()->title('Work order started')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function putOnHoldAction(): Action
    {
        return Action::make('putOnHold')
            ->label('Put On Hold')
            ->icon('heroicon-o-pause-circle')
            ->form([
                Textarea::make('on_hold_reason')
                    ->label('On hold reason')
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => ! $record->isClosed()
                && (auth()->user()?->can('updateAssigned', $record) ?? false))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->putOnHold($data['on_hold_reason'] ?? '');
                    Notification::make()->title('Work order put on hold')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function awaitPartsAction(): Action
    {
        return Action::make('awaitParts')
            ->label('Awaiting Parts')
            ->icon('heroicon-o-cog-6-tooth')
            ->form([
                Textarea::make('required_parts')
                    ->label('Required parts')
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => ! $record->isClosed()
                && (auth()->user()?->can('updateAssigned', $record) ?? false))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->awaitParts($data['required_parts'] ?? '');
                    Notification::make()->title('Work order is awaiting parts')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function submitForVerificationAction(): Action
    {
        return Action::make('submitForVerification')
            ->label('Submit For Verification')
            ->icon('heroicon-o-clipboard-document-check')
            ->form([
                Textarea::make('action_performed')
                    ->label('Action performed')
                    ->required(),
                Select::make('final_equipment_condition')
                    ->label('Final equipment condition')
                    ->options(Equipment::conditionOptions())
                    ->required(),
                Select::make('final_operational_status')
                    ->label('Final operational status')
                    ->options(Equipment::operationalStatusOptions())
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => ! $record->isClosed()
                && (auth()->user()?->can('updateAssigned', $record) ?? false))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->submitForVerification($data);
                    Notification::make()->title('Work order submitted for verification')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->label('Complete')
            ->icon('heroicon-o-check-circle')
            ->form([
                Textarea::make('completion_remarks')
                    ->label('Completion remarks'),
            ])
            ->visible(fn (WorkOrder $record): bool => in_array($record->status, ['For verification', 'In progress'], true)
                && (auth()->user()?->can('updateAssigned', $record) ?? false))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->complete($data['completion_remarks'] ?? null);
                    Notification::make()->title('Work order completed')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function beyondRepairAction(): Action
    {
        return Action::make('beyondRepair')
            ->label('Beyond Repair')
            ->icon('heroicon-o-exclamation-triangle')
            ->form([
                Textarea::make('findings')
                    ->required(),
                Textarea::make('beyond_repair_reason')
                    ->label('Beyond repair reason')
                    ->required(),
                Textarea::make('recommended_action')
                    ->label('Recommended action')
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => ! $record->isClosed()
                && ((auth()->user()?->can('recommendBeyondRepair', $record) ?? false)
                    || (auth()->user()?->can('approveBeyondRepair', $record) ?? false)))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->markBeyondRepair($data);
                    Notification::make()->title('Work order marked beyond repair')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon('heroicon-o-shield-check')
            ->requiresConfirmation()
            ->visible(fn (WorkOrder $record): bool => in_array($record->status, ['For verification', 'Completed', 'Beyond repair'], true)
                && (auth()->user()?->can('verify', $record) ?? false))
            ->action(function (WorkOrder $record): void {
                try {
                    $record->verify(auth()->user());
                    Notification::make()->title('Work order verified')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->icon('heroicon-o-arrow-path')
            ->form([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => in_array($record->status, ['Completed', 'For verification', 'Beyond repair'], true)
                && (auth()->user()?->can('assign', $record) ?? false))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->reopen($data['reason'] ?? null);
                    Notification::make()->title('Work order reopened')->success()->send();
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
                Textarea::make('rejection_or_cancellation_reason')
                    ->label('Cancellation reason')
                    ->required(),
            ])
            ->visible(fn (WorkOrder $record): bool => ! $record->isClosed()
                && (auth()->user()?->can('assign', $record) ?? false))
            ->action(function (WorkOrder $record, array $data): void {
                try {
                    $record->cancel($data['rejection_or_cancellation_reason'] ?? '');
                    Notification::make()->title('Work order cancelled')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', WorkOrder::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', WorkOrder::class) ?? false;
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
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            EvidencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrders::route('/'),
            'view' => ViewWorkOrder::route('/{record}'),
            'edit' => EditWorkOrder::route('/{record}/edit'),
        ];
    }
}
