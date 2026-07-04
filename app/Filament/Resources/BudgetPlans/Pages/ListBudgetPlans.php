<?php

namespace App\Filament\Resources\BudgetPlans\Pages;

use App\Filament\Resources\BudgetPlans\BudgetPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBudgetPlans extends ListRecords
{
    protected static string $resource = BudgetPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
