<?php

namespace App\Filament\Resources\BudgetPlans;

use App\Filament\Resources\BudgetPlans\Pages\CreateBudgetPlan;
use App\Filament\Resources\BudgetPlans\Pages\EditBudgetPlan;
use App\Filament\Resources\BudgetPlans\Pages\ListBudgetPlans;
use App\Filament\Resources\BudgetPlans\Pages\ViewBudgetPlan;
use App\Filament\Resources\BudgetPlans\RelationManagers\ItemsRelationManager;
use App\Models\BudgetPlan;
use App\Services\BudgetForecastingService;
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
use InvalidArgumentException;

class BudgetPlanResource extends Resource
{
    protected static ?string $model = BudgetPlan::class;

    protected static ?string $navigationLabel = 'Budget Plans';

    protected static ?string $modelLabel = 'Budget Plan';

    protected static ?string $pluralModelLabel = 'Budget Plans';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Lifecycle';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Budget plan')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('fiscal_year')
                            ->label('Fiscal year')
                            ->numeric()
                            ->integer()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->default(now()->year)
                            ->required(),
                        DatePicker::make('budget_date')
                            ->label('Budget Date')
                            ->default(now())
                            ->required(),
                        Select::make('funds')
                            ->label('Funds')
                            ->options(BudgetPlan::fundOptions())
                            ->searchable()
                            ->preload(),
                        TextInput::make('purchase_order_number')
                            ->label('Purchase Order Number (PO #)')
                            ->maxLength(255),
                        TextInput::make('total_estimated_budget')
                            ->label('Total estimated budget')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('description')
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
                Section::make('Budget plan')
                    ->schema([
                        TextEntry::make('plan_number')->label('Plan number'),
                        TextEntry::make('title'),
                        TextEntry::make('fiscal_year')->label('Fiscal year'),
                        TextEntry::make('budget_date')->label('Budget Date')->date()->placeholder('None'),
                        TextEntry::make('funds')->label('Funds')->placeholder('None'),
                        TextEntry::make('purchase_order_number')->label('Purchase Order Number (PO #)')->placeholder('None'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('total_estimated_budget')->label('Total estimated budget')->money('PHP'),
                        TextEntry::make('preparedBy.name')->label('Prepared by'),
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('Not reviewed'),
                        TextEntry::make('approvedBy.name')->label('Approved by')->placeholder('Not approved'),
                        TextEntry::make('reviewed_at')->label('Reviewed date')->dateTime()->placeholder('Not reviewed'),
                        TextEntry::make('approved_at')->label('Approved date')->dateTime()->placeholder('Not approved'),
                        TextEntry::make('description')->placeholder('None')->columnSpanFull(),
                        TextEntry::make('remarks')->placeholder('None')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['preparedBy', 'approvedBy'])->orderByDesc('budget_date')->latest('created_at'))
            ->columns([
                TextColumn::make('plan_number')->label('Plan Number')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('fiscal_year')->label('Fiscal Year')->sortable(),
                TextColumn::make('budget_date')->label('Budget Date')->date()->sortable(),
                TextColumn::make('funds')->label('Funds')->badge()->sortable(),
                TextColumn::make('purchase_order_number')->label('PO #')->searchable()->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('total_estimated_budget')->label('Total Estimated Budget')->money('PHP')->sortable(),
                TextColumn::make('preparedBy.name')->label('Prepared By')->searchable()->sortable(),
                TextColumn::make('approvedBy.name')->label('Approved By')->placeholder('Not approved')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Created Date')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('fiscal_year')->label('Fiscal Year')->options(fn (): array => BudgetPlan::query()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->all()),
                SelectFilter::make('status')->options(BudgetPlan::statusOptions()),
                SelectFilter::make('funds')->label('Funds')->options(BudgetPlan::fundOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::generateForecastAction(),
                self::recalculateTotalAction(),
                self::submitForReviewAction(),
                self::approveAction(),
                self::rejectAction(),
                self::cancelAction(),
            ]);
    }

    public static function generateForecastAction(): Action
    {
        return Action::make('generateForecastItems')
            ->label('Generate Forecast Items')
            ->icon('heroicon-o-sparkles')
            ->requiresConfirmation()
            ->visible(fn (BudgetPlan $record): bool => auth()->user()?->can('manageItems', $record) ?? false)
            ->action(function (BudgetPlan $record): void {
                $summary = app(BudgetForecastingService::class)->addForecastItemsToPlan($record);

                Notification::make()
                    ->title('Forecast generated')
                    ->body("Added {$summary['added']} item(s), skipped {$summary['skipped']} duplicate item(s).")
                    ->success()
                    ->send();
            });
    }

    public static function recalculateTotalAction(): Action
    {
        return Action::make('recalculateTotal')
            ->label('Recalculate Total')
            ->icon('heroicon-o-calculator')
            ->requiresConfirmation()
            ->visible(fn (BudgetPlan $record): bool => auth()->user()?->can('manageItems', $record) ?? false)
            ->action(function (BudgetPlan $record): void {
                $record->recalculateTotal();
                Notification::make()->title('Budget total recalculated')->success()->send();
            });
    }

    public static function submitForReviewAction(): Action
    {
        return Action::make('submitForReview')
            ->label('Submit for Review')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->visible(fn (BudgetPlan $record): bool => auth()->user()?->can('submitForReview', $record) ?? false)
            ->action(function (BudgetPlan $record): void {
                try {
                    $record->submitForReview(auth()->user());
                    Notification::make()->title('Budget plan submitted for review')->success()->send();
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
            ->visible(fn (BudgetPlan $record): bool => auth()->user()?->can('approve', $record) ?? false)
            ->action(function (BudgetPlan $record): void {
                try {
                    $record->approve(auth()->user());
                    Notification::make()->title('Budget plan approved')->success()->send();
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
                Textarea::make('remarks')->label('Remarks'),
            ])
            ->visible(fn (BudgetPlan $record): bool => auth()->user()?->can('reject', $record) ?? false)
            ->action(function (BudgetPlan $record, array $data): void {
                try {
                    $record->reject(auth()->user(), $data['remarks'] ?? null);
                    Notification::make()->title('Budget plan rejected')->success()->send();
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
                Textarea::make('remarks')->label('Remarks'),
            ])
            ->visible(fn (BudgetPlan $record): bool => auth()->user()?->can('cancel', $record) ?? false)
            ->action(function (BudgetPlan $record, array $data): void {
                try {
                    $record->cancel($data['remarks'] ?? null);
                    Notification::make()->title('Budget plan cancelled')->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('viewAny', BudgetPlan::class) ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', BudgetPlan::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', BudgetPlan::class) ?? false;
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
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgetPlans::route('/'),
            'create' => CreateBudgetPlan::route('/create'),
            'view' => ViewBudgetPlan::route('/{record}'),
            'edit' => EditBudgetPlan::route('/{record}/edit'),
        ];
    }
}
