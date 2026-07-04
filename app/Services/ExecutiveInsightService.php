<?php

namespace App\Services;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Equipment;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExecutiveInsightService
{
    public const RISK_LEVELS = [
        'Critical',
        'High',
        'Moderate',
        'Low',
    ];

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'generated_at' => now()->format('Y-m-d H:i'),
            'kpis' => $this->getKpis(),
            'asset_summary' => $this->getAssetSummary(),
            'maintenance_summary' => $this->getMaintenanceSummary(),
            'lifecycle_summary' => $this->getLifecycleSummary(),
            'budget_summary' => $this->getBudgetSummary(),
            'recommendation_summary' => $this->getRecommendationSummary(),
            'risk_matrix' => $this->getRiskPriorityMatrix(),
            'replacement_forecast' => $this->getReplacementForecastSummary(),
            'maintenance_burden' => $this->getMaintenanceBurdenSummary(),
            'top_risks' => $this->getTopRisks(),
            'strategic_insights' => $this->getStrategicInsights(),
            'action_items' => $this->getExecutiveActionItems(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssetSummary(): array
    {
        return [
            'total_equipment' => Equipment::count(),
            'active_equipment' => Equipment::query()->where('is_archived', false)->count(),
            'total_acquisition_value' => (float) Equipment::query()->sum('acquisition_cost'),
            'beyond_repair_equipment' => Equipment::query()->where('condition', 'Beyond repair')->count(),
            'average_health_score' => round((float) EquipmentLifecycleProfile::query()->whereNotNull('health_score')->avg('health_score'), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMaintenanceSummary(): array
    {
        $total = WorkOrder::count();
        $completed = WorkOrder::query()->where('status', 'Completed')->count();

        return [
            'open_work_orders' => WorkOrder::query()->open()->count(),
            'overdue_maintenance' => MaintenanceSchedule::query()->overdue()->count(),
            'pending_verification' => WorkOrder::query()->where('status', 'For verification')->count(),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'repeated_repair_equipment' => Equipment::query()->whereHas('completedWorkOrders', fn (Builder $query) => $query, '>=', 3)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLifecycleSummary(): array
    {
        return [
            'replacement_candidates' => EquipmentLifecycleProfile::query()->where('lifecycle_status', 'Replacement Candidate')->count(),
            'critical_lifecycle_equipment' => EquipmentLifecycleProfile::query()->where('health_grade', 'Critical')->orWhere('health_score', '<', 40)->count(),
            'high_maintenance_assets' => EquipmentLifecycleProfile::query()->where('lifecycle_status', 'High Maintenance')->count(),
            'beyond_repair_equipment' => Equipment::query()->where('condition', 'Beyond repair')->count(),
            'average_health_score' => round((float) EquipmentLifecycleProfile::query()->whereNotNull('health_score')->avg('health_score'), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBudgetSummary(): array
    {
        return [
            'approved_budget_plans' => BudgetPlan::query()->where('status', 'Approved')->count(),
            'pending_budget_plans' => BudgetPlan::query()->whereIn('status', ['Draft', 'Prepared', 'Under Review'])->count(),
            'total_proposed_budget' => (float) BudgetPlan::query()->sum('total_estimated_budget'),
            'total_approved_budget' => (float) BudgetPlan::query()->where('status', 'Approved')->sum('total_estimated_budget'),
            'estimated_replacement_budget' => (float) BudgetPlanItem::query()->where('item_type', 'Replacement')->sum('estimated_cost'),
            'critical_budget_items' => BudgetPlanItem::query()->where('priority', 'Critical')->count(),
            'deferred_budget_items' => BudgetPlanItem::query()->where('status', 'Deferred')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecommendationSummary(): array
    {
        return [
            'open_recommendations' => MaintenanceRecommendation::query()->where('status', 'Open')->count(),
            'critical_recommendations' => MaintenanceRecommendation::query()->where('risk_level', 'Critical')->whereIn('status', ['Open', 'Reviewed'])->count(),
            'approved_actions' => MaintenanceRecommendation::query()->where('action_status', 'Approved')->count(),
            'executed_actions' => MaintenanceRecommendation::query()->where('action_status', 'Executed')->count(),
            'rejected_actions' => MaintenanceRecommendation::query()->where('action_status', 'Rejected')->count(),
            'pending_actions' => MaintenanceRecommendation::query()->where('action_status', 'Pending')->count(),
            'common_rules' => $this->commonRecommendationRules(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopRisks(): array
    {
        return Equipment::query()
            ->with(['lifecycleProfile', 'currentLocation'])
            ->withCount([
                'workOrders as open_work_orders_count' => fn (Builder $query) => $query->whereNotIn('status', WorkOrder::CLOSED_STATUSES),
                'maintenanceRecommendations as open_recommendations_count' => fn (Builder $query) => $query->whereIn('status', ['Open', 'Reviewed']),
                'completedWorkOrders as completed_repairs_count',
            ])
            ->orderByDesc('open_recommendations_count')
            ->orderByDesc('open_work_orders_count')
            ->limit(10)
            ->get()
            ->map(fn (Equipment $equipment) => [
                'equipment' => "{$equipment->equipment_code} - {$equipment->equipment_name}",
                'location' => $equipment->currentLocation?->name ?? 'Unassigned',
                'risk_level' => $this->equipmentRiskLevel($equipment),
                'health_score' => $equipment->lifecycleProfile?->health_score,
                'open_recommendations' => $equipment->open_recommendations_count,
                'open_work_orders' => $equipment->open_work_orders_count,
                'completed_repairs' => $equipment->completed_repairs_count,
            ])
            ->filter(fn (array $row) => $row['open_recommendations'] > 0 || $row['open_work_orders'] > 0 || in_array($row['risk_level'], ['Critical', 'High'], true))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getStrategicInsights(): array
    {
        $asset = $this->getAssetSummary();
        $maintenance = $this->getMaintenanceSummary();
        $lifecycle = $this->getLifecycleSummary();
        $budget = $this->getBudgetSummary();
        $recommendations = $this->getRecommendationSummary();
        $topRisk = $this->getTopRisks()[0]['equipment'] ?? 'No high-risk equipment identified';

        return [
            "{$lifecycle['replacement_candidates']} equipment items are replacement candidates.",
            "{$maintenance['open_work_orders']} work orders are open and {$maintenance['pending_verification']} are pending verification.",
            "{$budget['pending_budget_plans']} budget plans are pending review or preparation.",
            "{$recommendations['critical_recommendations']} critical AI recommendations require management attention.",
            "The highest-risk equipment is {$topRisk}.",
            'Total asset acquisition value is PHP '.number_format($asset['total_acquisition_value'], 2).'.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRiskPriorityMatrix(): array
    {
        return collect(self::RISK_LEVELS)
            ->map(fn (string $risk): array => [
                'risk_level' => $risk,
                'equipment_count' => $this->equipmentCountForRisk($risk),
                'open_recommendations' => MaintenanceRecommendation::query()->where('risk_level', $risk)->whereIn('status', ['Open', 'Reviewed'])->count(),
                'open_work_orders' => $this->workOrderCountForRisk($risk),
                'estimated_budget_impact' => $this->budgetImpactForRisk($risk),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getReplacementForecastSummary(): array
    {
        $year = now()->year;

        return [
            'replacement_candidates_this_year' => EquipmentLifecycleProfile::query()
                ->where('lifecycle_status', 'Replacement Candidate')
                ->whereYear('estimated_end_of_life_date', $year)
                ->count(),
            'replacement_candidates_next_year' => EquipmentLifecycleProfile::query()
                ->where('lifecycle_status', 'Replacement Candidate')
                ->whereYear('estimated_end_of_life_date', $year + 1)
                ->count(),
            'estimated_replacement_cost' => (float) BudgetPlanItem::query()->where('item_type', 'Replacement')->sum('estimated_cost'),
            'critical_lifecycle_equipment' => EquipmentLifecycleProfile::query()->where('health_grade', 'Critical')->orWhere('health_score', '<', 40)->count(),
            'beyond_repair_equipment' => Equipment::query()->where('condition', 'Beyond repair')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMaintenanceBurdenSummary(): array
    {
        $total = WorkOrder::count();
        $completed = WorkOrder::query()->where('status', 'Completed')->count();

        return [
            'most_frequently_repaired_equipment' => $this->mostFrequentlyRepairedEquipment(),
            'locations_with_most_work_orders' => $this->locationsWithMostWorkOrders(),
            'technicians_with_highest_workload' => $this->techniciansWithHighestWorkload(),
            'equipment_with_repeated_repairs' => $this->equipmentWithRepeatedRepairs(),
            'work_order_completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getExecutiveActionItems(): array
    {
        $items = [];

        if (EquipmentLifecycleProfile::query()->where('lifecycle_status', 'Replacement Candidate')->exists()) {
            $items[] = 'Review replacement candidates.';
        }

        if (BudgetPlan::query()->whereIn('status', ['Prepared', 'Under Review'])->exists()) {
            $items[] = 'Approve pending budget plans.';
        }

        if (WorkOrder::query()->open()->exists()) {
            $items[] = 'Assign overdue work orders.';
        }

        if (MaintenanceRecommendation::query()->where('risk_level', 'Critical')->whereIn('status', ['Open', 'Reviewed'])->exists()) {
            $items[] = 'Resolve critical AI recommendations.';
        }

        if (MaintenanceSchedule::query()->overdue()->exists()) {
            $items[] = 'Schedule overdue maintenance.';
        }

        return $items ?: ['Continue monitoring equipment, maintenance, lifecycle, budget, and recommendation indicators.'];
    }

    /**
     * @return array<int, array{label: string, value: string|int|float, description: string}>
     */
    private function getKpis(): array
    {
        $asset = $this->getAssetSummary();
        $maintenance = $this->getMaintenanceSummary();
        $lifecycle = $this->getLifecycleSummary();
        $budget = $this->getBudgetSummary();
        $recommendations = $this->getRecommendationSummary();

        return [
            $this->kpi('Total Equipment', $asset['total_equipment'], 'All equipment records.'),
            $this->kpi('Total Asset Acquisition Value', 'PHP '.number_format($asset['total_acquisition_value'], 2), 'Known acquisition cost total.'),
            $this->kpi('Estimated Replacement Budget', 'PHP '.number_format($budget['estimated_replacement_budget'], 2), 'Replacement budget plan items.'),
            $this->kpi('Open Work Orders', $maintenance['open_work_orders'], 'Work orders not closed.'),
            $this->kpi('Overdue Maintenance', $maintenance['overdue_maintenance'], 'Incomplete schedules past due.'),
            $this->kpi('Critical Recommendations', $recommendations['critical_recommendations'], 'Critical open or reviewed recommendations.'),
            $this->kpi('Replacement Candidates', $lifecycle['replacement_candidates'], 'Lifecycle replacement candidates.'),
            $this->kpi('Pending Budget Plans', $budget['pending_budget_plans'], 'Draft, prepared, or under-review budget plans.'),
            $this->kpi('Pending Asset Action Requests', AssetActionRequest::query()->pending()->count(), 'Asset action requests still open.'),
            $this->kpi('Equipment Health Average', $asset['average_health_score'], 'Average lifecycle health score.'),
        ];
    }

    /**
     * @return array{label: string, value: string|int|float, description: string}
     */
    private function kpi(string $label, string|int|float $value, string $description): array
    {
        return compact('label', 'value', 'description');
    }

    private function equipmentRiskLevel(Equipment $equipment): string
    {
        $profile = $equipment->lifecycleProfile;

        if ($equipment->condition === 'Beyond repair' || $profile?->health_grade === 'Critical' || ($profile?->health_score !== null && $profile->health_score < 40)) {
            return 'Critical';
        }

        if ($equipment->condition === 'Defective' || in_array($profile?->health_grade, ['Poor'], true) || $profile?->lifecycle_status === 'Replacement Candidate') {
            return 'High';
        }

        if ($equipment->condition === 'Fair' || $profile?->health_grade === 'Fair' || $profile?->lifecycle_status === 'High Maintenance') {
            return 'Moderate';
        }

        return 'Low';
    }

    private function equipmentCountForRisk(string $risk): int
    {
        return Equipment::query()
            ->with('lifecycleProfile')
            ->get()
            ->filter(fn (Equipment $equipment) => $this->equipmentRiskLevel($equipment) === $risk)
            ->count();
    }

    private function workOrderCountForRisk(string $risk): int
    {
        $priorities = match ($risk) {
            'Critical' => ['Critical'],
            'High' => ['High'],
            'Moderate' => ['Normal'],
            default => ['Low'],
        };

        return WorkOrder::query()->open()->whereIn('priority', $priorities)->count();
    }

    private function budgetImpactForRisk(string $risk): float
    {
        $priorities = match ($risk) {
            'Critical' => ['Critical'],
            'High' => ['High'],
            'Moderate' => ['Normal'],
            default => ['Low'],
        };

        return (float) BudgetPlanItem::query()->whereIn('priority', $priorities)->sum('estimated_cost');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function commonRecommendationRules(): array
    {
        return MaintenanceRecommendation::query()
            ->select('rule_key', DB::raw('count(*) as aggregate'))
            ->groupBy('rule_key')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'rule' => str((string) $row->rule_key)->replace('_', ' ')->title()->toString(),
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mostFrequentlyRepairedEquipment(): array
    {
        return Equipment::query()
            ->withCount(['completedWorkOrders as repair_count'])
            ->orderByDesc('repair_count')
            ->limit(10)
            ->get()
            ->filter(fn (Equipment $equipment) => $equipment->repair_count > 0)
            ->map(fn (Equipment $equipment) => [
                'label' => "{$equipment->equipment_code} - {$equipment->equipment_name}",
                'count' => $equipment->repair_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locationsWithMostWorkOrders(): array
    {
        return Location::query()
            ->withCount(['equipment as work_order_count' => fn (Builder $query) => $query->whereHas('workOrders')])
            ->orderByDesc('work_order_count')
            ->limit(10)
            ->get()
            ->filter(fn (Location $location) => $location->work_order_count > 0)
            ->map(fn (Location $location) => [
                'label' => $location->name,
                'count' => $location->work_order_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function techniciansWithHighestWorkload(): array
    {
        return User::query()
            ->select('users.id', 'users.name')
            ->selectSub(WorkOrder::query()->selectRaw('count(*)')->whereColumn('assigned_to', 'users.id')->whereNotIn('status', WorkOrder::CLOSED_STATUSES), 'workload_count')
            ->orderByDesc('workload_count')
            ->limit(10)
            ->get()
            ->filter(fn (User $user) => (int) $user->workload_count > 0)
            ->map(fn (User $user) => [
                'label' => $user->name,
                'count' => (int) $user->workload_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function equipmentWithRepeatedRepairs(): array
    {
        return collect($this->mostFrequentlyRepairedEquipment())
            ->filter(fn (array $row) => $row['count'] >= 3)
            ->values()
            ->all();
    }
}
