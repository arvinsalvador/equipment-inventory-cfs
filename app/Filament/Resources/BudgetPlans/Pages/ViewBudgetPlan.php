<?php

namespace App\Filament\Resources\BudgetPlans\Pages;

use App\Filament\Resources\BudgetPlans\BudgetPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBudgetPlan extends ViewRecord
{
    protected static string $resource = BudgetPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BudgetPlanResource::generateForecastAction(),
            BudgetPlanResource::recalculateTotalAction(),
            BudgetPlanResource::submitForReviewAction(),
            BudgetPlanResource::approveAction(),
            BudgetPlanResource::rejectAction(),
            BudgetPlanResource::cancelAction(),
            EditAction::make(),
        ];
    }
}
