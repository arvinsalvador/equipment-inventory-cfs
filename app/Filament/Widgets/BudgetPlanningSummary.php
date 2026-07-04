<?php

namespace App\Filament\Widgets;

use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\EquipmentLifecycleProfile;
use Filament\Widgets\Widget;

class BudgetPlanningSummary extends Widget
{
    protected string $view = 'filament.widgets.budget-planning-summary';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 7;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', BudgetPlan::class) ?? false;
    }

    /**
     * @return array<string, string|int>
     */
    public function metrics(): array
    {
        $year = now()->year;

        return [
            'Estimated Budget' => number_format((float) BudgetPlan::query()->where('fiscal_year', $year)->sum('total_estimated_budget'), 2),
            'Critical Cost' => number_format((float) BudgetPlanItem::query()->where('priority', 'Critical')->sum('estimated_cost'), 2),
            'Replacement Candidates' => EquipmentLifecycleProfile::query()->where('lifecycle_status', 'Replacement Candidate')->count(),
            'Approved Plans' => BudgetPlan::query()->where('status', 'Approved')->count(),
            'Pending Review' => BudgetPlan::query()->where('status', 'Under Review')->count(),
        ];
    }
}
