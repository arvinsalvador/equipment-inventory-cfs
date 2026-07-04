<?php

namespace App\Filament\Resources\BudgetPlans\Pages;

use App\Filament\Resources\BudgetPlans\BudgetPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBudgetPlan extends CreateRecord
{
    protected static string $resource = BudgetPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['prepared_by'] = auth()->id();
        $data['status'] = 'Draft';

        return $data;
    }
}
