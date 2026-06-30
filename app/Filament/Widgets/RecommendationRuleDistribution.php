<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class RecommendationRuleDistribution extends Widget
{
    protected string $view = 'filament.widgets.recommendation-rule-distribution';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(MaintenanceRecommendation::RULE_KEYS)
            ->mapWithKeys(fn (string $rule): array => [
                $this->labelForRule($rule) => MaintenanceRecommendation::query()->where('rule_key', $rule)->count(),
            ])
            ->all();
    }

    private function labelForRule(string $rule): string
    {
        return match ($rule) {
            'overdue_maintenance' => 'Overdue Maintenance',
            'due_soon' => 'Due Soon',
            'defective_without_work_order' => 'Defective Without Work Order',
            'repeated_repairs' => 'Repeated Repairs',
            'no_maintenance_history' => 'No Maintenance History',
            'expiring_warranty' => 'Expiring Warranty',
            'beyond_repair_evidence_incomplete' => 'Beyond-Repair Evidence Incomplete',
            'completed_without_after_evidence' => 'Completed Without After Evidence',
            default => str($rule)->replace('_', ' ')->title()->toString(),
        };
    }
}
