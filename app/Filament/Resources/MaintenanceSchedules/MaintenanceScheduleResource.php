<?php

namespace App\Filament\Resources\MaintenanceSchedules;

use App\Filament\Resources\MaintenanceSchedules\Pages\CreateMaintenanceSchedule;
use App\Filament\Resources\MaintenanceSchedules\Pages\EditMaintenanceSchedule;
use App\Filament\Resources\MaintenanceSchedules\Pages\ListMaintenanceSchedules;
use App\Filament\Resources\MaintenanceSchedules\Pages\ViewMaintenanceSchedule;
use App\Models\Equipment;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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

class MaintenanceScheduleResource extends Resource
{
    protected static ?string $model = MaintenanceSchedule::class;

    protected static ?string $navigationLabel = 'Maintenance Schedules';

    protected static ?string $modelLabel = 'Maintenance Schedule';

    protected static ?string $pluralModelLabel = 'Maintenance Schedules';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule details')
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
                        TextInput::make('maintenance_type')
                            ->label('Maintenance type')
                            ->required()
                            ->maxLength(255),
                        Select::make('maintenance_frequency')
                            ->label('Maintenance frequency')
                            ->options(MaintenanceSchedule::frequencyOptions())
                            ->required()
                            ->rule(Rule::in(MaintenanceSchedule::FREQUENCIES)),
                        DatePicker::make('scheduled_date')
                            ->label('Scheduled date')
                            ->required(),
                        Select::make('assigned_user_id')
                            ->label('Assigned user')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('priority')
                            ->options(MaintenanceSchedule::priorityOptions())
                            ->default('Normal')
                            ->required()
                            ->rule(Rule::in(MaintenanceSchedule::PRIORITIES)),
                        Select::make('status')
                            ->options(MaintenanceSchedule::statusOptions())
                            ->default('Upcoming')
                            ->required()
                            ->rule(Rule::in(MaintenanceSchedule::STATUSES)),
                        Textarea::make('checklist_instructions')
                            ->label('Checklist or instructions')
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
                Section::make('Schedule details')
                    ->schema([
                        TextEntry::make('equipment.equipment_code')->label('Equipment code'),
                        TextEntry::make('equipment.equipment_name')->label('Equipment name'),
                        TextEntry::make('maintenance_type')->label('Maintenance type'),
                        TextEntry::make('maintenance_frequency')->label('Maintenance frequency'),
                        TextEntry::make('scheduled_date')->label('Scheduled date')->date(),
                        TextEntry::make('assignedUser.name')->label('Assigned user')->placeholder('Unassigned'),
                        TextEntry::make('priority')->badge(),
                        TextEntry::make('display_status')
                            ->label('Status')
                            ->state(fn (MaintenanceSchedule $record): string => $record->displayStatus())
                            ->badge(),
                        TextEntry::make('checklist_instructions')->label('Checklist or instructions')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Completion and cancellation')
                    ->schema([
                        TextEntry::make('completed_at')->label('Completed date')->dateTime()->placeholder('Not completed'),
                        TextEntry::make('completedBy.name')->label('Completed by')->placeholder('Not completed'),
                        TextEntry::make('completion_remarks')->label('Completion remarks')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('rescheduled_from')->label('Rescheduled from')->date()->placeholder('Not rescheduled'),
                        TextEntry::make('cancelled_at')->label('Cancelled date')->dateTime()->placeholder('Not cancelled'),
                        TextEntry::make('cancelledBy.name')->label('Cancelled by')->placeholder('Not cancelled'),
                        TextEntry::make('cancellation_reason')->label('Cancellation reason')->placeholder('None')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipment', 'assignedUser'])->orderBy('scheduled_date'))
            ->columns([
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->formatStateUsing(fn (MaintenanceSchedule $record): string => $record->equipment->equipment_code.' - '.$record->equipment->equipment_name)
                    ->searchable(['equipment.equipment_code', 'equipment.equipment_name'])
                    ->sortable(),
                TextColumn::make('maintenance_type')
                    ->label('Maintenance type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('maintenance_frequency')
                    ->label('Maintenance frequency')
                    ->badge()
                    ->sortable(),
                TextColumn::make('scheduled_date')
                    ->label('Scheduled date')
                    ->date()
                    ->sortable()
                    ->color(fn (MaintenanceSchedule $record): string => $record->isOverdue() ? 'danger' : 'gray'),
                TextColumn::make('assignedUser.name')
                    ->label('Assigned user')
                    ->searchable()
                    ->placeholder('Unassigned'),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'Critical' => 'danger',
                        'High' => 'warning',
                        'Low' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('display_status')
                    ->label('Status')
                    ->state(fn (MaintenanceSchedule $record): string => $record->displayStatus())
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('status', $direction))
                    ->color(fn (string $state): string => match ($state) {
                        'Overdue' => 'danger',
                        'Due today' => 'warning',
                        'Due soon' => 'info',
                        'Completed' => 'success',
                        'Cancelled' => 'gray',
                        default => 'primary',
                    }),
                TextColumn::make('completed_at')
                    ->label('Completed date')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not completed'),
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
                SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->relationship('equipment', 'equipment_code')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('assigned_user_id')
                    ->label('Assigned user')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('maintenance_frequency')
                    ->label('Maintenance frequency')
                    ->options(MaintenanceSchedule::frequencyOptions()),
                SelectFilter::make('priority')
                    ->options(MaintenanceSchedule::priorityOptions()),
                SelectFilter::make('status')
                    ->options(MaintenanceSchedule::statusOptions()),
                SelectFilter::make('due_group')
                    ->label('Due group')
                    ->options([
                        'due_soon' => 'Due soon',
                        'due_today' => 'Due today',
                        'overdue' => 'Overdue',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'due_soon' => $query->dueSoon(),
                        'due_today' => $query->dueToday(),
                        'overdue' => $query->overdue(),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::completeAction(),
                self::rescheduleAction(),
                self::cancelAction(),
            ]);
    }

    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->label('Complete')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->form([
                Textarea::make('completion_remarks')
                    ->label('Completion remarks')
                    ->required(),
            ])
            ->visible(fn (MaintenanceSchedule $record): bool => ! $record->isCompleted()
                && ! $record->isCancelled()
                && (auth()->user()?->can('complete', $record) ?? false))
            ->action(function (MaintenanceSchedule $record, array $data): void {
                try {
                    $record->complete(auth()->user(), $data['completion_remarks'] ?? null);

                    Notification::make()
                        ->title('Maintenance schedule completed')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule')
            ->icon('heroicon-o-calendar')
            ->form([
                DatePicker::make('scheduled_date')
                    ->label('New scheduled date')
                    ->required(),
                Textarea::make('remarks'),
            ])
            ->visible(fn (MaintenanceSchedule $record): bool => ! $record->isCompleted()
                && ! $record->isCancelled()
                && (auth()->user()?->can('reschedule', $record) ?? false))
            ->action(function (MaintenanceSchedule $record, array $data): void {
                try {
                    $record->reschedule($data['scheduled_date'], $data['remarks'] ?? null);

                    Notification::make()
                        ->title('Maintenance schedule rescheduled')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
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
                Textarea::make('cancellation_reason')
                    ->label('Cancellation reason')
                    ->required(),
            ])
            ->visible(fn (MaintenanceSchedule $record): bool => ! $record->isCompleted()
                && ! $record->isCancelled()
                && (auth()->user()?->can('cancel', $record) ?? false))
            ->action(function (MaintenanceSchedule $record, array $data): void {
                try {
                    $record->cancel($data['cancellation_reason'] ?? '', auth()->user());

                    Notification::make()
                        ->title('Maintenance schedule cancelled')
                        ->success()
                        ->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceSchedule::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceSchedule::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', MaintenanceSchedule::class) ?? false;
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
            'index' => ListMaintenanceSchedules::route('/'),
            'create' => CreateMaintenanceSchedule::route('/create'),
            'view' => ViewMaintenanceSchedule::route('/{record}'),
            'edit' => EditMaintenanceSchedule::route('/{record}/edit'),
        ];
    }
}
