<?php

namespace App\Filament\Resources\BudgetPlans\RelationManagers;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Equipment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Budget Plan Items';

    protected static bool $isReadOnly = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->preload(),
                Select::make('asset_action_request_id')
                    ->label('Asset Action Request')
                    ->options(fn (): array => AssetActionRequest::query()
                        ->orderByDesc('created_at')
                        ->get()
                        ->mapWithKeys(fn (AssetActionRequest $request): array => [
                            $request->id => $request->request_number.' - '.$request->request_type,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                Select::make('item_type')
                    ->label('Item type')
                    ->options(BudgetPlanItem::itemTypeOptions())
                    ->required()
                    ->rule(Rule::in(BudgetPlanItem::ITEM_TYPES)),
                Select::make('priority')
                    ->options(BudgetPlanItem::priorityOptions())
                    ->default('Normal')
                    ->required()
                    ->rule(Rule::in(BudgetPlanItem::PRIORITIES)),
                TextInput::make('estimated_cost')
                    ->label('Estimated cost')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Select::make('status')
                    ->options(BudgetPlanItem::statusOptions())
                    ->default('Proposed')
                    ->required()
                    ->rule(Rule::in(BudgetPlanItem::STATUSES)),
                TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('target_period')
                    ->label('Target period')
                    ->maxLength(255),
                Textarea::make('justification')
                    ->columnSpanFull(),
                Textarea::make('forecast_reason')
                    ->label('Forecast reason')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('equipment.equipment_code')
                    ->label('Equipment')
                    ->placeholder('None')
                    ->formatStateUsing(fn (BudgetPlanItem $record): ?string => $record->equipment ? $record->equipment->equipment_code.' - '.$record->equipment->equipment_name : null),
                TextColumn::make('assetActionRequest.request_number')->label('Asset Action Request')->placeholder('None'),
                TextColumn::make('item_type')->label('Item Type')->badge()->sortable(),
                TextColumn::make('priority')->badge()->sortable(),
                TextColumn::make('estimated_cost')->label('Estimated Cost')->money('PHP')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('target_period')->label('Target Period')->placeholder('None'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => $this->canManageItems())
                    ->after(fn (): BudgetPlan => $this->getOwnerRecord()->recalculateTotal()),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (): bool => $this->canManageItems())
                    ->after(fn (): BudgetPlan => $this->getOwnerRecord()->recalculateTotal()),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->canManageItems())
                    ->after(fn (): BudgetPlan => $this->getOwnerRecord()->recalculateTotal()),
            ]);
    }

    protected function canManageItems(): bool
    {
        /** @var BudgetPlan $plan */
        $plan = $this->getOwnerRecord();

        return auth()->user()?->can('manageItems', $plan) ?? false;
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canManageItems();
    }
}
