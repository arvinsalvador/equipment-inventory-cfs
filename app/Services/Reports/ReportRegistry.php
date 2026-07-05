<?php

namespace App\Services\Reports;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\EquipmentLocationHistory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReportRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'equipment-inventory' => $this->definition('Equipment Inventory Report', 'Complete equipment register with archive, status, location, and ownership details.', ['csv', 'print'], ['category', 'location', 'condition', 'operational_status', 'archived_status']),
            'equipment-by-category' => $this->definition('Equipment by Category', 'Grouped active, archived, and total equipment counts by category.', ['csv', 'print']),
            'equipment-by-location' => $this->definition('Equipment by Location', 'Grouped active, archived, and total equipment counts by current location.', ['csv', 'print']),
            'equipment-by-condition' => $this->definition('Equipment by Condition', 'Equipment totals grouped by condition.', ['csv', 'print']),
            'maintenance-schedules' => $this->definition('Maintenance Schedule Report', 'Scheduled maintenance workload with due dates, owners, priority, and completion data.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'assigned_user', 'status', 'priority']),
            'overdue-maintenance' => $this->definition('Overdue Maintenance Report', 'Incomplete maintenance schedules past their scheduled date.', ['csv', 'print'], ['equipment', 'assigned_user', 'priority']),
            'maintenance-requests' => $this->definition('Maintenance Request Report', 'Submitted maintenance requests with review and conversion dates.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'submitted_by', 'severity', 'status']),
            'work-orders' => $this->definition('Work Order Report', 'Work order lifecycle report with assignment, priority, and verification dates.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'assigned_user', 'priority', 'status']),
            'completed-work-orders' => $this->definition('Completed Work Orders Report', 'Completed work orders with final equipment state and action performed.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'assigned_user', 'final_condition']),
            'beyond-repair-equipment' => $this->definition('Beyond-Repair Equipment Report', 'Equipment marked beyond repair with findings, recommendations, and evidence counts.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'category', 'location']),
            'ai-recommendations' => $this->definition('AI Recommendation Report', 'Rule-based maintenance recommendations with risk, status, and action state.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'risk_level', 'rule', 'recommendation_status', 'action_status']),
            'equipment-transfer-history' => $this->definition('Equipment Transfer History Report', 'Equipment movement history by origin, destination, transfer user, and date.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'from_location', 'to_location', 'transferred_by']),
            'equipment-maintenance-history' => $this->definition('Equipment Maintenance History Report', 'Combined schedule, request, and work order maintenance activity history.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'activity_type']),
            'equipment-lifecycle' => $this->definition('Equipment Lifecycle Report', 'Health score, lifecycle status, replacement recommendation, repair count, and maintenance cost by equipment.', ['csv', 'print'], ['health_grade', 'lifecycle_status', 'replacement_recommendation', 'category', 'location']),
            'asset-action-requests' => $this->definition('Asset Action Request Report', 'Replacement, procurement, disposal, repair, and inspection workflow tracking.', ['csv', 'print'], ['date_from', 'date_to', 'equipment', 'requested_by', 'request_type', 'priority', 'status']),
            'budget-plans' => $this->definition('Budget Plan Report', 'Budget plans by fiscal year, status, preparer, approval, and estimated budget.', ['csv', 'print'], ['fiscal_year', 'status']),
            'budget-plan-items' => $this->definition('Budget Plan Item Report', 'Budget plan item forecast by fiscal year, equipment, item type, priority, cost, and status.', ['csv', 'print'], ['fiscal_year', 'equipment', 'item_type', 'priority', 'status']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $slug): array
    {
        return $this->all()[$slug] ?? throw new InvalidArgumentException('Unknown report.');
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function columns(string $slug, bool $respectSelection = true): array
    {
        $columns = match ($slug) {
            'equipment-inventory' => $this->columnsFrom([
                'equipment_code' => 'Equipment code', 'property_number' => 'Property number', 'equipment_name' => 'Equipment name', 'category' => 'Category', 'location' => 'Current location', 'brand' => 'Brand', 'model' => 'Model', 'serial_number' => 'Serial number', 'condition' => 'Condition', 'operational_status' => 'Operational status', 'custodian' => 'Custodian', 'acquisition_date' => 'Acquisition date', 'warranty_expiration_date' => 'Warranty expiration date', 'archived_status' => 'Archived status',
            ]),
            'equipment-by-category' => $this->columnsFrom(['category' => 'Category', 'active_count' => 'Active equipment count', 'archived_count' => 'Archived equipment count', 'total_count' => 'Total equipment count']),
            'equipment-by-location' => $this->columnsFrom(['location' => 'Location', 'active_count' => 'Active equipment count', 'archived_count' => 'Archived equipment count', 'total_count' => 'Total equipment count']),
            'equipment-by-condition' => $this->columnsFrom(['condition' => 'Condition', 'equipment_count' => 'Equipment count']),
            'maintenance-schedules' => $this->columnsFrom(['equipment' => 'Equipment', 'maintenance_type' => 'Maintenance type', 'frequency' => 'Frequency', 'scheduled_date' => 'Scheduled date', 'assigned_user' => 'Assigned user', 'priority' => 'Priority', 'status' => 'Status', 'completed_date' => 'Completed date']),
            'overdue-maintenance' => $this->columnsFrom(['equipment' => 'Equipment', 'maintenance_type' => 'Maintenance type', 'scheduled_date' => 'Scheduled date', 'assigned_user' => 'Assigned user', 'priority' => 'Priority', 'days_overdue' => 'Days overdue', 'status' => 'Status']),
            'maintenance-requests' => $this->columnsFrom(['request_number' => 'Request number', 'equipment' => 'Equipment', 'submitted_by' => 'Submitted by', 'severity' => 'Severity', 'status' => 'Status', 'created_date' => 'Created date', 'reviewed_date' => 'Reviewed date', 'converted_date' => 'Converted date']),
            'work-orders' => $this->columnsFrom(['work_order_number' => 'Work order number', 'equipment' => 'Equipment', 'created_by' => 'Created by', 'assigned_to' => 'Assigned to', 'priority' => 'Priority', 'status' => 'Status', 'created_date' => 'Created date', 'started_date' => 'Started date', 'completed_date' => 'Completed date', 'verified_date' => 'Verified date']),
            'completed-work-orders' => $this->columnsFrom(['work_order_number' => 'Work order number', 'equipment' => 'Equipment', 'assigned_to' => 'Assigned to', 'completed_date' => 'Completed date', 'verified_date' => 'Verified date', 'final_equipment_condition' => 'Final equipment condition', 'final_operational_status' => 'Final operational status', 'action_performed' => 'Action performed']),
            'beyond-repair-equipment' => $this->columnsFrom(['equipment_code' => 'Equipment code', 'equipment_name' => 'Equipment name', 'category' => 'Category', 'location' => 'Location', 'work_order_number' => 'Work order number', 'findings' => 'Findings', 'beyond_repair_reason' => 'Beyond-repair reason', 'recommended_action' => 'Recommended action', 'evidence_count' => 'Evidence count', 'date_marked' => 'Date marked']),
            'ai-recommendations' => $this->columnsFrom(['equipment' => 'Equipment', 'rule' => 'Rule', 'title' => 'Title', 'risk_level' => 'Risk level', 'suggested_action' => 'Suggested action', 'action_status' => 'Action status', 'recommendation_status' => 'Recommendation status', 'generated_date' => 'Generated date', 'reviewed_date' => 'Reviewed date', 'resolved_date' => 'Resolved date']),
            'equipment-transfer-history' => $this->columnsFrom(['equipment' => 'Equipment', 'from_location' => 'From location', 'to_location' => 'To location', 'transferred_by' => 'Transferred by', 'transfer_date' => 'Transfer date', 'remarks' => 'Remarks']),
            'equipment-maintenance-history' => $this->columnsFrom(['date' => 'Date', 'equipment' => 'Equipment', 'activity_type' => 'Activity type', 'reference_number' => 'Reference number', 'status' => 'Status', 'actor' => 'Performed/Submitted/Assigned by', 'summary' => 'Summary']),
            'equipment-lifecycle' => $this->columnsFrom(['equipment_code' => 'Equipment code', 'equipment_name' => 'Equipment name', 'category' => 'Category', 'location' => 'Location', 'health_score' => 'Health score', 'health_grade' => 'Health grade', 'lifecycle_status' => 'Lifecycle status', 'replacement_recommendation' => 'Replacement recommendation', 'estimated_remaining_life' => 'Estimated remaining life', 'estimated_end_of_life_date' => 'Estimated end-of-life date', 'repair_count' => 'Repair count', 'total_maintenance_cost' => 'Total maintenance cost', 'last_calculated_date' => 'Last calculated date']),
            'asset-action-requests' => $this->columnsFrom(['request_number' => 'Request number', 'equipment' => 'Equipment', 'request_type' => 'Request type', 'priority' => 'Priority', 'status' => 'Status', 'estimated_cost' => 'Estimated cost', 'requested_by' => 'Requested by', 'approved_by' => 'Approved by', 'created_date' => 'Created date', 'completed_date' => 'Completed date']),
            'budget-plans' => $this->columnsFrom(['plan_number' => 'Plan number', 'fiscal_year' => 'Fiscal year', 'status' => 'Status', 'total_estimated_budget' => 'Total estimated budget', 'prepared_by' => 'Prepared by', 'approved_by' => 'Approved by']),
            'budget-plan-items' => $this->columnsFrom(['fiscal_year' => 'Fiscal year', 'equipment' => 'Equipment', 'item_type' => 'Item type', 'priority' => 'Priority', 'estimated_cost' => 'Estimated cost', 'status' => 'Status', 'forecast_reason' => 'Forecast reason']),
            default => throw new InvalidArgumentException('Unknown report.'),
        };

        return $respectSelection ? $this->selectedColumns($columns) : $columns;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(string $slug, array $filters = []): Collection
    {
        return match ($slug) {
            'equipment-inventory' => $this->equipmentInventory($filters),
            'equipment-by-category' => $this->equipmentByCategory(),
            'equipment-by-location' => $this->equipmentByLocation(),
            'equipment-by-condition' => $this->equipmentByCondition(),
            'maintenance-schedules' => $this->maintenanceSchedules($filters),
            'overdue-maintenance' => $this->overdueMaintenance($filters),
            'maintenance-requests' => $this->maintenanceRequests($filters),
            'work-orders' => $this->workOrders($filters),
            'completed-work-orders' => $this->completedWorkOrders($filters),
            'beyond-repair-equipment' => $this->beyondRepairEquipment($filters),
            'ai-recommendations' => $this->aiRecommendations($filters),
            'equipment-transfer-history' => $this->transferHistory($filters),
            'equipment-maintenance-history' => $this->maintenanceHistory($filters),
            'equipment-lifecycle' => $this->equipmentLifecycle($filters),
            'asset-action-requests' => $this->assetActionRequests($filters),
            'budget-plans' => $this->budgetPlans($filters),
            'budget-plan-items' => $this->budgetPlanItems($filters),
            default => throw new InvalidArgumentException('Unknown report.'),
        };
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function filterOptions(): array
    {
        return [
            'equipment' => Equipment::query()->orderBy('equipment_code')->pluck('equipment_name', 'id')->all(),
            'category' => EquipmentCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'location' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'from_location' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'to_location' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'condition' => array_combine(Equipment::CONDITIONS, Equipment::CONDITIONS),
            'operational_status' => array_combine(Equipment::OPERATIONAL_STATUSES, Equipment::OPERATIONAL_STATUSES),
            'archived_status' => ['active' => 'Active only', 'archived' => 'Archived only'],
            'assigned_user' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'submitted_by' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'requested_by' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'transferred_by' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'status' => array_combine(array_unique(array_merge(MaintenanceSchedule::STATUSES, MaintenanceRequest::STATUSES, WorkOrder::STATUSES, AssetActionRequest::STATUSES, BudgetPlan::STATUSES, BudgetPlanItem::STATUSES)), array_unique(array_merge(MaintenanceSchedule::STATUSES, MaintenanceRequest::STATUSES, WorkOrder::STATUSES, AssetActionRequest::STATUSES, BudgetPlan::STATUSES, BudgetPlanItem::STATUSES))),
            'priority' => array_combine(array_unique(array_merge(WorkOrder::PRIORITIES, BudgetPlanItem::PRIORITIES)), array_unique(array_merge(WorkOrder::PRIORITIES, BudgetPlanItem::PRIORITIES))),
            'severity' => array_combine(MaintenanceRequest::SEVERITIES, MaintenanceRequest::SEVERITIES),
            'risk_level' => array_combine(MaintenanceRecommendation::RISK_LEVELS, MaintenanceRecommendation::RISK_LEVELS),
            'rule' => array_combine(MaintenanceRecommendation::RULE_KEYS, MaintenanceRecommendation::RULE_KEYS),
            'recommendation_status' => array_combine(MaintenanceRecommendation::STATUSES, MaintenanceRecommendation::STATUSES),
            'action_status' => array_combine(MaintenanceRecommendation::ACTION_STATUSES, MaintenanceRecommendation::ACTION_STATUSES),
            'final_condition' => array_combine(Equipment::CONDITIONS, Equipment::CONDITIONS),
            'activity_type' => ['Maintenance schedule' => 'Maintenance schedule', 'Maintenance request' => 'Maintenance request', 'Work order' => 'Work order'],
            'health_grade' => array_combine(EquipmentLifecycleProfile::HEALTH_GRADES, EquipmentLifecycleProfile::HEALTH_GRADES),
            'lifecycle_status' => array_combine(EquipmentLifecycleProfile::LIFECYCLE_STATUSES, EquipmentLifecycleProfile::LIFECYCLE_STATUSES),
            'replacement_recommendation' => array_combine(EquipmentLifecycleProfile::REPLACEMENT_RECOMMENDATIONS, EquipmentLifecycleProfile::REPLACEMENT_RECOMMENDATIONS),
            'request_type' => array_combine(AssetActionRequest::REQUEST_TYPES, AssetActionRequest::REQUEST_TYPES),
            'fiscal_year' => BudgetPlan::query()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->all(),
            'item_type' => array_combine(BudgetPlanItem::ITEM_TYPES, BudgetPlanItem::ITEM_TYPES),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function appliedFilterLabels(string $slug, array $filters): array
    {
        $definition = $this->get($slug);
        $options = $this->filterOptions();

        return collect($definition['filters'])
            ->filter(fn (string $filter) => filled($filters[$filter] ?? null))
            ->mapWithKeys(fn (string $filter) => [$this->filterLabel($filter) => $options[$filter][$filters[$filter]] ?? $filters[$filter]])
            ->all();
    }

    private function definition(string $name, string $description, array $actions, array $filters = []): array
    {
        return compact('name', 'description', 'actions', 'filters');
    }

    private function columnsFrom(array $columns): array
    {
        return collect($columns)->map(fn (string $label, string $key) => compact('key', 'label'))->values()->all();
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @return array<int, array{key: string, label: string}>
     */
    private function selectedColumns(array $columns): array
    {
        $selected = request()->query('columns', []);

        if (! is_array($selected) || $selected === []) {
            return $columns;
        }

        $selected = array_values(array_filter($selected, 'is_string'));
        $filtered = collect($columns)
            ->filter(fn (array $column): bool => in_array($column['key'], $selected, true))
            ->values()
            ->all();

        return $filtered === [] ? $columns : $filtered;
    }

    private function filterLabel(string $filter): string
    {
        return str($filter)->replace('_', ' ')->title()->toString();
    }

    private function equipmentLabel(?Equipment $equipment): string
    {
        return $equipment ? "{$equipment->equipment_code} - {$equipment->equipment_name}" : '';
    }

    private function dateValue(mixed $value, string $format = 'Y-m-d'): string
    {
        return $value instanceof \DateTimeInterface ? $value->format($format) : '';
    }

    private function applyDateRange(Builder $query, array $filters, string $column): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '<=', $date));
    }

    private function equipmentInventory(array $filters): Collection
    {
        return Equipment::query()
            ->with(['category', 'currentLocation'])
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->where('equipment_category_id', $id))
            ->when($filters['location'] ?? null, fn ($query, $id) => $query->where('current_location_id', $id))
            ->when($filters['condition'] ?? null, fn ($query, $value) => $query->where('condition', $value))
            ->when($filters['operational_status'] ?? null, fn ($query, $value) => $query->where('operational_status', $value))
            ->when(($filters['archived_status'] ?? null) === 'active', fn ($query) => $query->where('is_archived', false))
            ->when(($filters['archived_status'] ?? null) === 'archived', fn ($query) => $query->where('is_archived', true))
            ->orderBy('equipment_code')
            ->get()
            ->map(fn (Equipment $equipment) => [
                'equipment_code' => $equipment->equipment_code,
                'property_number' => $equipment->property_number,
                'equipment_name' => $equipment->equipment_name,
                'category' => $equipment->category?->name,
                'location' => $equipment->currentLocation?->name,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'condition' => $equipment->condition,
                'operational_status' => $equipment->operational_status,
                'custodian' => $equipment->custodian,
                'acquisition_date' => $this->dateValue($equipment->acquisition_date),
                'warranty_expiration_date' => $this->dateValue($equipment->warranty_expiration_date),
                'archived_status' => $equipment->is_archived ? 'Archived' : 'Active',
            ]);
    }

    private function equipmentByCategory(): Collection
    {
        return EquipmentCategory::query()
            ->leftJoin('equipment', 'equipment_categories.id', '=', 'equipment.equipment_category_id')
            ->select('equipment_categories.name as category')
            ->selectRaw('sum(case when equipment.is_archived = 0 then 1 else 0 end) as active_count')
            ->selectRaw('sum(case when equipment.is_archived = 1 then 1 else 0 end) as archived_count')
            ->selectRaw('count(equipment.id) as total_count')
            ->groupBy('equipment_categories.id', 'equipment_categories.name')
            ->orderBy('equipment_categories.name')
            ->get()
            ->map(fn ($row) => (array) $row->getAttributes());
    }

    private function equipmentByLocation(): Collection
    {
        return Location::query()
            ->leftJoin('equipment', 'locations.id', '=', 'equipment.current_location_id')
            ->select('locations.name as location')
            ->selectRaw('sum(case when equipment.is_archived = 0 then 1 else 0 end) as active_count')
            ->selectRaw('sum(case when equipment.is_archived = 1 then 1 else 0 end) as archived_count')
            ->selectRaw('count(equipment.id) as total_count')
            ->groupBy('locations.id', 'locations.name')
            ->orderBy('locations.name')
            ->get()
            ->map(fn ($row) => (array) $row->getAttributes());
    }

    private function equipmentByCondition(): Collection
    {
        return Equipment::query()
            ->select('condition')
            ->selectRaw('count(*) as equipment_count')
            ->groupBy('condition')
            ->orderBy('condition')
            ->get()
            ->map(fn ($row) => (array) $row->getAttributes());
    }

    private function maintenanceSchedules(array $filters): Collection
    {
        $query = MaintenanceSchedule::query()->with(['equipment', 'assignedUser'])->orderBy('scheduled_date');
        $this->applyDateRange($query, $filters, 'scheduled_date');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['assigned_user'] ?? null, fn ($query, $id) => $query->where('assigned_user_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->get()
            ->map(fn (MaintenanceSchedule $schedule) => [
                'equipment' => $this->equipmentLabel($schedule->equipment),
                'maintenance_type' => $schedule->maintenance_type,
                'frequency' => $schedule->maintenance_frequency,
                'scheduled_date' => $this->dateValue($schedule->scheduled_date),
                'assigned_user' => $schedule->assignedUser?->name,
                'priority' => $schedule->priority,
                'status' => $schedule->status,
                'completed_date' => $this->dateValue($schedule->completed_at, 'Y-m-d H:i'),
            ]);
    }

    private function overdueMaintenance(array $filters): Collection
    {
        return MaintenanceSchedule::query()
            ->with(['equipment', 'assignedUser'])
            ->overdue()
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['assigned_user'] ?? null, fn ($query, $id) => $query->where('assigned_user_id', $id))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (MaintenanceSchedule $schedule) => [
                'equipment' => $this->equipmentLabel($schedule->equipment),
                'maintenance_type' => $schedule->maintenance_type,
                'scheduled_date' => $this->dateValue($schedule->scheduled_date),
                'assigned_user' => $schedule->assignedUser?->name,
                'priority' => $schedule->priority,
                'days_overdue' => (int) $schedule->scheduled_date->diffInDays(today()),
                'status' => $schedule->displayStatus(),
            ]);
    }

    private function maintenanceRequests(array $filters): Collection
    {
        $query = MaintenanceRequest::query()->with(['equipment', 'submittedBy'])->orderByDesc('created_at');
        $this->applyDateRange($query, $filters, 'created_at');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['submitted_by'] ?? null, fn ($query, $id) => $query->where('submitted_by', $id))
            ->when($filters['severity'] ?? null, fn ($query, $value) => $query->where('severity', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->get()
            ->map(fn (MaintenanceRequest $request) => [
                'request_number' => $request->request_number,
                'equipment' => $this->equipmentLabel($request->equipment),
                'submitted_by' => $request->submittedBy?->name,
                'severity' => $request->severity,
                'status' => $request->status,
                'created_date' => $this->dateValue($request->created_at, 'Y-m-d H:i'),
                'reviewed_date' => $this->dateValue($request->reviewed_at, 'Y-m-d H:i'),
                'converted_date' => $this->dateValue($request->converted_at, 'Y-m-d H:i'),
            ]);
    }

    private function workOrders(array $filters): Collection
    {
        $query = WorkOrder::query()->with(['equipment', 'createdBy', 'assignedTo'])->orderByDesc('created_at');
        $this->applyDateRange($query, $filters, 'created_at');

        return $this->applyWorkOrderFilters($query, $filters)
            ->get()
            ->map(fn (WorkOrder $workOrder) => [
                'work_order_number' => $workOrder->work_order_number,
                'equipment' => $this->equipmentLabel($workOrder->equipment),
                'created_by' => $workOrder->createdBy?->name,
                'assigned_to' => $workOrder->assignedTo?->name,
                'priority' => $workOrder->priority,
                'status' => $workOrder->status,
                'created_date' => $this->dateValue($workOrder->created_at, 'Y-m-d H:i'),
                'started_date' => $this->dateValue($workOrder->started_at, 'Y-m-d H:i'),
                'completed_date' => $this->dateValue($workOrder->completed_at, 'Y-m-d H:i'),
                'verified_date' => $this->dateValue($workOrder->verified_at, 'Y-m-d H:i'),
            ]);
    }

    private function completedWorkOrders(array $filters): Collection
    {
        $query = WorkOrder::query()->with(['equipment', 'assignedTo'])->completed()->orderByDesc('completed_at');
        $this->applyDateRange($query, $filters, 'completed_at');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['assigned_user'] ?? null, fn ($query, $id) => $query->where('assigned_to', $id))
            ->when($filters['final_condition'] ?? null, fn ($query, $value) => $query->where('final_equipment_condition', $value))
            ->get()
            ->map(fn (WorkOrder $workOrder) => [
                'work_order_number' => $workOrder->work_order_number,
                'equipment' => $this->equipmentLabel($workOrder->equipment),
                'assigned_to' => $workOrder->assignedTo?->name,
                'completed_date' => $this->dateValue($workOrder->completed_at, 'Y-m-d H:i'),
                'verified_date' => $this->dateValue($workOrder->verified_at, 'Y-m-d H:i'),
                'final_equipment_condition' => $workOrder->final_equipment_condition,
                'final_operational_status' => $workOrder->final_operational_status,
                'action_performed' => $workOrder->action_performed,
            ]);
    }

    private function beyondRepairEquipment(array $filters): Collection
    {
        $query = WorkOrder::query()
            ->with(['equipment.category', 'equipment.currentLocation'])
            ->withCount('evidences')
            ->beyondRepair()
            ->orderByDesc('updated_at');
        $this->applyDateRange($query, $filters, 'updated_at');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->whereHas('equipment', fn ($query) => $query->where('equipment_category_id', $id)))
            ->when($filters['location'] ?? null, fn ($query, $id) => $query->whereHas('equipment', fn ($query) => $query->where('current_location_id', $id)))
            ->get()
            ->map(fn (WorkOrder $workOrder) => [
                'equipment_code' => $workOrder->equipment?->equipment_code,
                'equipment_name' => $workOrder->equipment?->equipment_name,
                'category' => $workOrder->equipment?->category?->name,
                'location' => $workOrder->equipment?->currentLocation?->name,
                'work_order_number' => $workOrder->work_order_number,
                'findings' => $workOrder->findings,
                'beyond_repair_reason' => $workOrder->beyond_repair_reason,
                'recommended_action' => $workOrder->recommended_action,
                'evidence_count' => $workOrder->evidences_count,
                'date_marked' => $this->dateValue($workOrder->updated_at, 'Y-m-d H:i'),
            ]);
    }

    private function aiRecommendations(array $filters): Collection
    {
        $query = MaintenanceRecommendation::query()->with('equipment')->orderByDesc('generated_at');
        $this->applyDateRange($query, $filters, 'generated_at');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['risk_level'] ?? null, fn ($query, $value) => $query->where('risk_level', $value))
            ->when($filters['rule'] ?? null, fn ($query, $value) => $query->where('rule_key', $value))
            ->when($filters['recommendation_status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['action_status'] ?? null, fn ($query, $value) => $query->where('action_status', $value))
            ->get()
            ->map(fn (MaintenanceRecommendation $recommendation) => [
                'equipment' => $this->equipmentLabel($recommendation->equipment),
                'rule' => str($recommendation->rule_key)->replace('_', ' ')->title()->toString(),
                'title' => $recommendation->title,
                'risk_level' => $recommendation->risk_level,
                'suggested_action' => $recommendation->getSuggestedActionLabel(),
                'action_status' => $recommendation->action_status,
                'recommendation_status' => $recommendation->status,
                'generated_date' => $this->dateValue($recommendation->generated_at, 'Y-m-d H:i'),
                'reviewed_date' => $this->dateValue($recommendation->reviewed_at, 'Y-m-d H:i'),
                'resolved_date' => $this->dateValue($recommendation->resolved_at, 'Y-m-d H:i'),
            ]);
    }

    private function transferHistory(array $filters): Collection
    {
        $query = EquipmentLocationHistory::query()->with(['equipment', 'fromLocation', 'toLocation', 'transferredBy'])->orderByDesc('transferred_at');
        $this->applyDateRange($query, $filters, 'transferred_at');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['from_location'] ?? null, fn ($query, $id) => $query->where('from_location_id', $id))
            ->when($filters['to_location'] ?? null, fn ($query, $id) => $query->where('to_location_id', $id))
            ->when($filters['transferred_by'] ?? null, fn ($query, $id) => $query->where('transferred_by', $id))
            ->get()
            ->map(fn (EquipmentLocationHistory $history) => [
                'equipment' => $this->equipmentLabel($history->equipment),
                'from_location' => $history->fromLocation?->name,
                'to_location' => $history->toLocation?->name,
                'transferred_by' => $history->transferredBy?->name,
                'transfer_date' => $this->dateValue($history->transferred_at, 'Y-m-d H:i'),
                'remarks' => $history->remarks,
            ]);
    }

    private function maintenanceHistory(array $filters): Collection
    {
        $rows = collect()
            ->merge($this->maintenanceSchedules($filters)->map(fn (array $row) => [
                'date' => $row['scheduled_date'],
                'equipment' => $row['equipment'],
                'activity_type' => 'Maintenance schedule',
                'reference_number' => $row['maintenance_type'],
                'status' => $row['status'],
                'actor' => $row['assigned_user'],
                'summary' => $row['frequency'].' '.$row['priority'].' schedule',
            ]))
            ->merge($this->maintenanceRequests($filters)->map(fn (array $row) => [
                'date' => $row['created_date'],
                'equipment' => $row['equipment'],
                'activity_type' => 'Maintenance request',
                'reference_number' => $row['request_number'],
                'status' => $row['status'],
                'actor' => $row['submitted_by'],
                'summary' => $row['severity'].' request',
            ]))
            ->merge($this->workOrders($filters)->map(fn (array $row) => [
                'date' => $row['created_date'],
                'equipment' => $row['equipment'],
                'activity_type' => 'Work order',
                'reference_number' => $row['work_order_number'],
                'status' => $row['status'],
                'actor' => $row['assigned_to'] ?: $row['created_by'],
                'summary' => $row['priority'].' work order',
            ]));

        return $rows
            ->when($filters['activity_type'] ?? null, fn (Collection $rows, string $type) => $rows->where('activity_type', $type))
            ->sortByDesc('date')
            ->values();
    }

    private function equipmentLifecycle(array $filters): Collection
    {
        return Equipment::query()
            ->with(['category', 'currentLocation', 'lifecycleProfile'])
            ->withCount(['completedWorkOrders as repair_count'])
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->where('equipment_category_id', $id))
            ->when($filters['location'] ?? null, fn ($query, $id) => $query->where('current_location_id', $id))
            ->when($filters['health_grade'] ?? null, fn ($query, $value) => $query->whereHas('lifecycleProfile', fn ($query) => $query->where('health_grade', $value)))
            ->when($filters['lifecycle_status'] ?? null, fn ($query, $value) => $query->whereHas('lifecycleProfile', fn ($query) => $query->where('lifecycle_status', $value)))
            ->when($filters['replacement_recommendation'] ?? null, fn ($query, $value) => $query->whereHas('lifecycleProfile', fn ($query) => $query->where('replacement_recommendation', $value)))
            ->orderBy('equipment_code')
            ->get()
            ->map(fn (Equipment $equipment) => [
                'equipment_code' => $equipment->equipment_code,
                'equipment_name' => $equipment->equipment_name,
                'category' => $equipment->category?->name,
                'location' => $equipment->currentLocation?->name,
                'health_score' => $equipment->lifecycleProfile?->health_score,
                'health_grade' => $equipment->lifecycleProfile?->health_grade,
                'lifecycle_status' => $equipment->lifecycleProfile?->lifecycle_status,
                'replacement_recommendation' => $equipment->lifecycleProfile?->replacement_recommendation,
                'estimated_remaining_life' => $equipment->lifecycleProfile?->estimated_remaining_life_months,
                'estimated_end_of_life_date' => $this->dateValue($equipment->lifecycleProfile?->estimated_end_of_life_date),
                'repair_count' => $equipment->repair_count,
                'total_maintenance_cost' => number_format($equipment->maintenanceCostTotal(), 2),
                'last_calculated_date' => $this->dateValue($equipment->lifecycleProfile?->last_calculated_at, 'Y-m-d H:i'),
            ]);
    }

    private function assetActionRequests(array $filters): Collection
    {
        $query = AssetActionRequest::query()->with(['equipment', 'requestedBy', 'approvedBy'])->orderByDesc('created_at');
        $this->applyDateRange($query, $filters, 'created_at');

        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['requested_by'] ?? null, fn ($query, $id) => $query->where('requested_by', $id))
            ->when($filters['request_type'] ?? null, fn ($query, $value) => $query->where('request_type', $value))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->get()
            ->map(fn (AssetActionRequest $request) => [
                'request_number' => $request->request_number,
                'equipment' => $this->equipmentLabel($request->equipment),
                'request_type' => $request->request_type,
                'priority' => $request->priority,
                'status' => $request->status,
                'estimated_cost' => $request->estimated_cost,
                'requested_by' => $request->requestedBy?->name,
                'approved_by' => $request->approvedBy?->name,
                'created_date' => $this->dateValue($request->created_at, 'Y-m-d H:i'),
                'completed_date' => $this->dateValue($request->completed_at, 'Y-m-d H:i'),
            ]);
    }

    private function budgetPlans(array $filters): Collection
    {
        return BudgetPlan::query()
            ->with(['preparedBy', 'approvedBy'])
            ->when($filters['fiscal_year'] ?? null, fn ($query, $year) => $query->where('fiscal_year', $year))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('fiscal_year')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BudgetPlan $plan) => [
                'plan_number' => $plan->plan_number,
                'fiscal_year' => $plan->fiscal_year,
                'status' => $plan->status,
                'total_estimated_budget' => $plan->total_estimated_budget,
                'prepared_by' => $plan->preparedBy?->name,
                'approved_by' => $plan->approvedBy?->name,
            ]);
    }

    private function budgetPlanItems(array $filters): Collection
    {
        return BudgetPlanItem::query()
            ->with(['budgetPlan', 'equipment'])
            ->when($filters['fiscal_year'] ?? null, fn ($query, $year) => $query->whereHas('budgetPlan', fn ($query) => $query->where('fiscal_year', $year)))
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['item_type'] ?? null, fn ($query, $value) => $query->where('item_type', $value))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BudgetPlanItem $item) => [
                'fiscal_year' => $item->budgetPlan?->fiscal_year,
                'equipment' => $this->equipmentLabel($item->equipment),
                'item_type' => $item->item_type,
                'priority' => $item->priority,
                'estimated_cost' => $item->estimated_cost,
                'status' => $item->status,
                'forecast_reason' => $item->forecast_reason,
            ]);
    }

    private function applyWorkOrderFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['equipment'] ?? null, fn ($query, $id) => $query->where('equipment_id', $id))
            ->when($filters['assigned_user'] ?? null, fn ($query, $id) => $query->where('assigned_to', $id))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value));
    }
}
