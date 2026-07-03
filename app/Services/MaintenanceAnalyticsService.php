<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceAnalyticsService
{
    public const PERIODS = [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'last_30_days' => 'Last 30 Days',
        'last_90_days' => 'Last 90 Days',
        'this_year' => 'This Year',
        'all_time' => 'All Time',
    ];

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $period = 'this_month'): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'this_month';
        $range = $this->range($period);

        $executiveKpis = $this->executiveKpis($range);
        $equipmentReliability = $this->equipmentReliability();
        $locationAnalytics = $this->locationAnalytics();

        return [
            'period' => $period,
            'period_label' => self::PERIODS[$period],
            'range_label' => $this->rangeLabel($range),
            'generated_at' => now()->format('Y-m-d H:i'),
            'current_month' => now()->format('F Y'),
            'periods' => self::PERIODS,
            'executive_kpis' => $executiveKpis,
            'equipment_health' => $this->equipmentHealth(),
            'work_orders' => $this->workOrderAnalytics($range),
            'maintenance_schedules' => $this->scheduleAnalytics(),
            'maintenance_requests' => $this->requestAnalytics($range),
            'ai_recommendations' => $this->recommendationAnalytics($range),
            'technician_performance' => $this->technicianPerformance($range),
            'equipment_reliability' => $equipmentReliability,
            'location_analytics' => $locationAnalytics,
            'lifecycle_widgets' => $this->lifecycleWidgets(),
            'recommendation_trend' => $this->recommendationTrend(),
            'workload_trend' => $this->workloadTrend(),
            'insights' => $this->insights($executiveKpis, $equipmentReliability, $locationAnalytics),
        ];
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     * @return array<string, array<string, mixed>>
     */
    private function executiveKpis(array $range): array
    {
        return [
            'total_equipment' => $this->kpi('Total Equipment', Equipment::count(), 'All equipment records in inventory.', 'heroicon-o-cube', 'gray'),
            'active_equipment' => $this->kpi('Active Equipment', Equipment::query()->where('is_archived', false)->count(), 'Inventory assets not archived.', 'heroicon-o-check-circle', 'green'),
            'equipment_under_maintenance' => $this->kpi('Equipment Under Maintenance', Equipment::query()->where('operational_status', 'Under maintenance')->count(), 'Equipment currently marked under maintenance.', 'heroicon-o-wrench-screwdriver', 'amber'),
            'defective_equipment' => $this->kpi('Defective Equipment', Equipment::query()->where('condition', 'Defective')->count(), 'Equipment requiring corrective attention.', 'heroicon-o-exclamation-triangle', 'red'),
            'beyond_repair_equipment' => $this->kpi('Beyond Repair Equipment', Equipment::query()->where('condition', 'Beyond repair')->count(), 'Equipment marked beyond repair.', 'heroicon-o-no-symbol', 'red'),
            'open_work_orders' => $this->kpi('Open Work Orders', WorkOrder::query()->open()->count(), 'Work orders not in a closed status.', 'heroicon-o-clipboard-document-list', 'blue'),
            'completed_work_orders_this_month' => $this->kpi('Completed Work Orders This Month', WorkOrder::query()->completed()->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'Work orders completed in the current month.', 'heroicon-o-check-badge', 'green'),
            'overdue_maintenance' => $this->kpi('Overdue Maintenance', MaintenanceSchedule::query()->overdue()->count(), 'Incomplete schedules past due.', 'heroicon-o-calendar-days', 'red'),
            'open_maintenance_requests' => $this->kpi('Open Maintenance Requests', MaintenanceRequest::query()->open()->count(), 'Requests still awaiting closure.', 'heroicon-o-inbox-stack', 'amber'),
            'critical_ai_recommendations' => $this->kpi('Critical AI Recommendations', $this->periodQuery(MaintenanceRecommendation::query(), $range, 'generated_at')->where('risk_level', 'Critical')->count(), 'Critical recommendations in the selected period.', 'heroicon-o-bolt', 'red'),
            'pending_recommendation_actions' => $this->kpi('Pending Recommendation Actions', $this->periodQuery(MaintenanceRecommendation::query(), $range, 'generated_at')->where('action_status', 'Pending')->count(), 'Recommendation actions awaiting decision.', 'heroicon-o-clock', 'amber'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(string $label, int|float $value, string $description, string $icon, string $color): array
    {
        return compact('label', 'value', 'description', 'icon', 'color');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function equipmentHealth(): array
    {
        $total = max(Equipment::count(), 1);
        $counts = Equipment::query()
            ->select('condition', DB::raw('count(*) as aggregate'))
            ->groupBy('condition')
            ->pluck('aggregate', 'condition');

        $archived = Equipment::query()->where('is_archived', true)->count();

        return collect([
            'Healthy / Good' => (int) ($counts['Good'] ?? 0) + (int) ($counts['New'] ?? 0),
            'Needs inspection' => (int) ($counts['Needs inspection'] ?? 0),
            'Needs maintenance' => (int) ($counts['Needs maintenance'] ?? 0),
            'Defective' => (int) ($counts['Defective'] ?? 0),
            'Beyond repair' => (int) ($counts['Beyond repair'] ?? 0),
            'Archived' => $archived,
        ])->map(fn (int $count, string $label) => [
            'label' => $label,
            'count' => $count,
            'percentage' => round(($count / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     * @return array<string, mixed>
     */
    private function workOrderAnalytics(array $range): array
    {
        $query = $this->periodQuery(WorkOrder::query(), $range);
        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'Completed')->count();
        $statusCounts = (clone $query)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');

        return [
            'total' => $total,
            'statuses' => collect(['Available', 'Assigned', 'Accepted', 'In progress', 'On hold', 'Awaiting parts', 'For verification', 'Completed', 'Beyond repair', 'Cancelled'])
                ->map(fn (string $status) => ['label' => $status, 'count' => (int) ($statusCounts[$status] ?? 0)])
                ->values()
                ->all(),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'average_completion_hours' => $this->averageHours((clone $query)->whereNotNull('completed_at')->get(['created_at', 'completed_at']), 'created_at', 'completed_at'),
            'average_verification_hours' => $this->averageHours((clone $query)->whereNotNull('verified_at')->get(['completed_at', 'verified_at']), 'completed_at', 'verified_at'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleAnalytics(): array
    {
        $totalDue = MaintenanceSchedule::query()->whereDate('scheduled_date', '<=', today())->count();
        $completedDue = MaintenanceSchedule::query()->whereDate('scheduled_date', '<=', today())->completed()->count();

        return [
            'statuses' => [
                ['label' => 'Upcoming', 'count' => MaintenanceSchedule::query()->upcoming()->count()],
                ['label' => 'Due today', 'count' => MaintenanceSchedule::query()->dueToday()->count()],
                ['label' => 'Due this week', 'count' => MaintenanceSchedule::query()->dueSoon()->count()],
                ['label' => 'Overdue', 'count' => MaintenanceSchedule::query()->overdue()->count()],
                ['label' => 'Completed', 'count' => MaintenanceSchedule::query()->completed()->count()],
                ['label' => 'Cancelled', 'count' => MaintenanceSchedule::query()->cancelled()->count()],
                ['label' => 'Rescheduled', 'count' => MaintenanceSchedule::query()->where('status', 'Rescheduled')->count()],
            ],
            'compliance_rate' => $totalDue > 0 ? round(($completedDue / $totalDue) * 100, 1) : 0,
        ];
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     * @return array<string, mixed>
     */
    private function requestAnalytics(array $range): array
    {
        $query = $this->periodQuery(MaintenanceRequest::query(), $range);
        $counts = (clone $query)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $reviewed = (int) $counts->only(['Approved', 'Converted', 'Rejected'])->sum();
        $converted = (int) ($counts['Converted'] ?? 0);

        return [
            'statuses' => collect(['Submitted', 'For review', 'Approved', 'Converted', 'Rejected', 'Cancelled'])
                ->map(fn (string $status) => ['label' => $status, 'count' => (int) ($counts[$status] ?? 0)])
                ->values()
                ->all(),
            'conversion_rate' => $reviewed > 0 ? round(($converted / $reviewed) * 100, 1) : 0,
        ];
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     * @return array<string, mixed>
     */
    private function recommendationAnalytics(array $range): array
    {
        $query = $this->periodQuery(MaintenanceRecommendation::query(), $range, 'generated_at');
        $statusCounts = (clone $query)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');
        $riskCounts = (clone $query)->select('risk_level', DB::raw('count(*) as aggregate'))->groupBy('risk_level')->pluck('aggregate', 'risk_level');
        $actionCounts = (clone $query)->select('action_status', DB::raw('count(*) as aggregate'))->groupBy('action_status')->pluck('aggregate', 'action_status');

        return [
            'generated' => (clone $query)->count(),
            'statuses' => collect(['Open', 'Reviewed', 'Resolved', 'Dismissed'])
                ->map(fn (string $status) => ['label' => $status, 'count' => (int) ($statusCounts[$status] ?? 0)])
                ->values()
                ->all(),
            'risks' => collect(['Critical', 'High', 'Moderate', 'Low'])
                ->map(fn (string $risk) => ['label' => $risk, 'count' => (int) ($riskCounts[$risk] ?? 0)])
                ->values()
                ->all(),
            'actions' => collect(['Pending', 'Approved', 'Executed', 'Rejected', 'Cancelled'])
                ->map(fn (string $status) => ['label' => $status, 'count' => (int) ($actionCounts[$status] ?? 0)])
                ->values()
                ->all(),
            'rules' => (clone $query)
                ->select('rule_key', DB::raw('count(*) as aggregate'))
                ->groupBy('rule_key')
                ->orderByDesc('aggregate')
                ->limit(6)
                ->get()
                ->map(fn ($row) => [
                    'label' => $this->ruleLabel($row->rule_key),
                    'count' => (int) $row->aggregate,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     * @return array<int, array<string, mixed>>
     */
    private function technicianPerformance(array $range): array
    {
        return User::query()
            ->select('users.id', 'users.name')
            ->selectSub($this->periodQuery(WorkOrder::query(), $range)->selectRaw('count(*)')->whereColumn('assigned_to', 'users.id'), 'assigned_count')
            ->selectSub($this->periodQuery(WorkOrder::query(), $range)->selectRaw('count(*)')->whereColumn('accepted_by', 'users.id'), 'accepted_count')
            ->selectSub($this->periodQuery(WorkOrder::query(), $range)->selectRaw('count(*)')->whereColumn('assigned_to', 'users.id')->where('status', 'Completed'), 'completed_count')
            ->selectSub($this->periodQuery(WorkOrder::query(), $range)->selectRaw('count(*)')->whereColumn('assigned_to', 'users.id')->whereNotIn('status', WorkOrder::CLOSED_STATUSES), 'pending_count')
            ->orderByDesc('completed_count')
            ->orderByDesc('assigned_count')
            ->limit(10)
            ->get()
            ->filter(fn (User $user) => ((int) $user->assigned_count + (int) $user->accepted_count + (int) $user->completed_count + (int) $user->pending_count) > 0)
            ->map(fn (User $user) => [
                'name' => $user->name,
                'assigned' => (int) $user->assigned_count,
                'accepted' => (int) $user->accepted_count,
                'completed' => (int) $user->completed_count,
                'pending' => (int) $user->pending_count,
                'average_completion_hours' => $this->averageHours(
                    WorkOrder::query()->where('assigned_to', $user->id)->whereNotNull('completed_at')->get(['created_at', 'completed_at']),
                    'created_at',
                    'completed_at'
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function equipmentReliability(): array
    {
        return Equipment::query()
            ->withCount([
                'workOrders as completed_work_orders_count' => fn (Builder $query) => $query->where('status', 'Completed'),
                'workOrders as open_work_orders_count' => fn (Builder $query) => $query->whereNotIn('status', WorkOrder::CLOSED_STATUSES),
                'maintenanceRequests as maintenance_requests_count',
                'maintenanceRecommendations as ai_recommendations_count',
            ])
            ->orderByDesc('completed_work_orders_count')
            ->orderByDesc('open_work_orders_count')
            ->orderByDesc('maintenance_requests_count')
            ->limit(10)
            ->get()
            ->map(fn (Equipment $equipment) => [
                'equipment_code' => $equipment->equipment_code,
                'equipment_name' => $equipment->equipment_name,
                'completed_work_orders' => $equipment->completed_work_orders_count,
                'open_work_orders' => $equipment->open_work_orders_count,
                'maintenance_requests' => $equipment->maintenance_requests_count,
                'ai_recommendations' => $equipment->ai_recommendations_count,
                'risk_indicator' => $this->equipmentRisk($equipment),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locationAnalytics(): array
    {
        return Location::query()
            ->withCount([
                'equipment as equipment_count',
                'equipment as open_work_orders_count' => fn (Builder $query) => $query->whereHas('workOrders', fn (Builder $query) => $query->whereNotIn('status', WorkOrder::CLOSED_STATUSES)),
                'equipment as maintenance_requests_count' => fn (Builder $query) => $query->whereHas('maintenanceRequests'),
                'equipment as critical_recommendations_count' => fn (Builder $query) => $query->whereHas('maintenanceRecommendations', fn (Builder $query) => $query->where('risk_level', 'Critical')),
            ])
            ->orderByDesc('open_work_orders_count')
            ->orderByDesc('maintenance_requests_count')
            ->orderByDesc('equipment_count')
            ->limit(10)
            ->get()
            ->map(fn (Location $location) => [
                'location' => $location->name,
                'equipment_count' => $location->equipment_count,
                'open_work_orders' => $location->open_work_orders_count,
                'maintenance_requests' => $location->maintenance_requests_count,
                'critical_recommendations' => $location->critical_recommendations_count,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function lifecycleWidgets(): array
    {
        return [
            'replacement_candidates' => EquipmentLifecycleProfile::query()->where('lifecycle_status', 'Replacement Candidate')->count(),
            'critical_health_equipment' => EquipmentLifecycleProfile::query()->where('health_grade', 'Critical')->count(),
            'high_maintenance_assets' => EquipmentLifecycleProfile::query()->where('lifecycle_status', 'High Maintenance')->count(),
            'lowest_health_scores' => EquipmentLifecycleProfile::query()
                ->with('equipment')
                ->whereNotNull('health_score')
                ->orderBy('health_score')
                ->limit(5)
                ->get()
                ->map(fn (EquipmentLifecycleProfile $profile) => [
                    'equipment' => $profile->equipment?->equipment_code.' - '.$profile->equipment?->equipment_name,
                    'value' => $profile->health_score,
                ])
                ->all(),
            'highest_maintenance_cost' => Equipment::query()
                ->withSum('workOrders as maintenance_cost', 'total_cost')
                ->orderByDesc('maintenance_cost')
                ->limit(5)
                ->get()
                ->map(fn (Equipment $equipment) => [
                    'equipment' => "{$equipment->equipment_code} - {$equipment->equipment_name}",
                    'value' => number_format((float) $equipment->maintenance_cost, 2),
                ])
                ->all(),
            'near_end_of_life' => EquipmentLifecycleProfile::query()
                ->with('equipment')
                ->whereNotNull('estimated_remaining_life_months')
                ->where('estimated_remaining_life_months', '<=', 12)
                ->orderBy('estimated_remaining_life_months')
                ->limit(5)
                ->get()
                ->map(fn (EquipmentLifecycleProfile $profile) => [
                    'equipment' => $profile->equipment?->equipment_code.' - '.$profile->equipment?->equipment_name,
                    'value' => $profile->estimated_remaining_life_months,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recommendationTrend(): array
    {
        return $this->lastSixMonths()->map(function (CarbonImmutable $month) {
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();

            return [
                'month' => $month->format('M Y'),
                'generated' => MaintenanceRecommendation::query()->whereBetween('generated_at', [$start, $end])->count(),
                'resolved' => MaintenanceRecommendation::query()->whereBetween('resolved_at', [$start, $end])->count(),
                'executed_actions' => MaintenanceRecommendation::query()->where('action_status', 'Executed')->whereBetween('actioned_at', [$start, $end])->count(),
                'pending_actions' => MaintenanceRecommendation::query()->where('action_status', 'Pending')->whereBetween('generated_at', [$start, $end])->count(),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workloadTrend(): array
    {
        return $this->lastSixMonths()->map(function (CarbonImmutable $month) {
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();

            return [
                'month' => $month->format('M Y'),
                'work_orders_created' => WorkOrder::query()->whereBetween('created_at', [$start, $end])->count(),
                'work_orders_completed' => WorkOrder::query()->whereBetween('completed_at', [$start, $end])->count(),
                'maintenance_requests_submitted' => MaintenanceRequest::query()->whereBetween('created_at', [$start, $end])->count(),
                'preventive_schedules_completed' => MaintenanceSchedule::query()->whereBetween('completed_at', [$start, $end])->count(),
            ];
        })->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $kpis
     * @param  array<int, array<string, mixed>>  $equipmentReliability
     * @param  array<int, array<string, mixed>>  $locationAnalytics
     * @return array<int, string>
     */
    private function insights(array $kpis, array $equipmentReliability, array $locationAnalytics): array
    {
        $critical = (int) $kpis['critical_ai_recommendations']['value'];
        $inProgress = WorkOrder::query()->where('status', 'In progress')->count();
        $overdue = (int) $kpis['overdue_maintenance']['value'];
        $topLocation = $locationAnalytics[0]['location'] ?? 'No location data';
        $topEquipment = $equipmentReliability[0]['equipment_name'] ?? 'No equipment data';

        return [
            "There are {$critical} critical recommendations requiring attention.",
            "{$inProgress} work orders are currently in progress.",
            "{$overdue} preventive maintenance schedules are overdue.",
            "The most active maintenance location is {$topLocation}.",
            "The most frequently repaired equipment is {$topEquipment}.",
        ];
    }

    /**
     * @return array{start: CarbonImmutable|null, end: CarbonImmutable|null}
     */
    private function range(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'last_month' => ['start' => $now->subMonthNoOverflow()->startOfMonth(), 'end' => $now->subMonthNoOverflow()->endOfMonth()],
            'last_30_days' => ['start' => $now->subDays(29)->startOfDay(), 'end' => $now->endOfDay()],
            'last_90_days' => ['start' => $now->subDays(89)->startOfDay(), 'end' => $now->endOfDay()],
            'this_year' => ['start' => $now->startOfYear(), 'end' => $now->endOfYear()],
            'all_time' => ['start' => null, 'end' => null],
            default => ['start' => $now->startOfMonth(), 'end' => $now->endOfMonth()],
        };
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     */
    private function periodQuery(Builder $query, array $range, string $column = 'created_at'): Builder
    {
        if ($range['start'] && $range['end']) {
            $query->whereBetween($column, [$range['start'], $range['end']]);
        }

        return $query;
    }

    /**
     * @param  array{start: CarbonImmutable|null, end: CarbonImmutable|null}  $range
     */
    private function rangeLabel(array $range): string
    {
        if (! $range['start'] || ! $range['end']) {
            return 'All available records';
        }

        return $range['start']->format('M d, Y').' to '.$range['end']->format('M d, Y');
    }

    private function averageHours(Collection $records, string $startColumn, string $endColumn): ?float
    {
        $durations = $records
            ->filter(fn ($record) => $record->{$startColumn} && $record->{$endColumn})
            ->map(fn ($record) => $record->{$startColumn}->diffInMinutes($record->{$endColumn}) / 60);

        return $durations->isNotEmpty() ? round($durations->avg(), 1) : null;
    }

    private function equipmentRisk(Equipment $equipment): string
    {
        if ($equipment->condition === 'Beyond repair' || $equipment->open_work_orders_count > 2) {
            return 'Critical';
        }

        if ($equipment->condition === 'Defective' || $equipment->ai_recommendations_count > 2) {
            return 'High';
        }

        if ($equipment->maintenance_requests_count > 0 || $equipment->completed_work_orders_count > 0) {
            return 'Monitor';
        }

        return 'Stable';
    }

    private function ruleLabel(string $rule): string
    {
        return str($rule)->replace('_', ' ')->title()->toString();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function lastSixMonths(): Collection
    {
        $current = CarbonImmutable::now()->startOfMonth();

        return collect(range(5, 0))->map(fn (int $monthsAgo) => $current->subMonths($monthsAgo));
    }
}
