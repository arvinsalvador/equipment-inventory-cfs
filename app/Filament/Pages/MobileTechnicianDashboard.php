<?php

namespace App\Filament\Pages;

use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\SystemNotification;
use App\Models\WorkOrder;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class MobileTechnicianDashboard extends Page
{
    protected string $view = 'filament.pages.mobile-technician-dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Management';

    protected static ?string $navigationLabel = 'Technician Mobile';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('technician-mobile.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Technician Mobile Dashboard';
    }

    /**
     * @return array<string, mixed>
     */
    public function technicianOverview(): array
    {
        $user = auth()->user();

        $workOrdersQuery = WorkOrder::query()
            ->with('equipment')
            ->when(! $user->can('work-orders.assign'), fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('assigned_to', $user->id)
                    ->orWhere('accepted_by', $user->id)));

        $assignedWorkOrdersQuery = (clone $workOrdersQuery)
            ->whereNotIn('status', WorkOrder::CLOSED_STATUSES);

        $openWorkOrdersQuery = (clone $workOrdersQuery)
            ->open();

        $overdueWorkOrdersQuery = (clone $openWorkOrdersQuery)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());

        $dueSoonWorkOrdersQuery = (clone $openWorkOrdersQuery)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays(7));

        $evidenceRequiredQuery = (clone $workOrdersQuery)
            ->whereIn('status', ['In progress', 'For verification'])
            ->whereDoesntHave('afterMaintenanceEvidences');

        $beyondRepairQuery = (clone $workOrdersQuery)
            ->beyondRepair();

        $maintenanceRequestsQuery = MaintenanceRequest::query()
            ->with('equipment')
            ->when($user->can('maintenance-requests.review'), fn ($query) => $query->whereIn('status', ['Submitted', 'For review']))
            ->when(! $user->can('maintenance-requests.review'), fn ($query) => $query
                ->where('submitted_by', $user->id)
                ->open());

        $recommendationsQuery = MaintenanceRecommendation::query()
            ->with('equipment')
            ->unresolved()
            ->whereIn('risk_level', ['Critical', 'High'])
            ->orderByRisk()
            ->latest('generated_at');

        $scheduleQuery = MaintenanceSchedule::query()
            ->with('equipment')
            ->when(! $user->can('maintenance-schedules.manage'), fn ($query) => $query->where('assigned_user_id', $user->id));

        return [
            'assigned_work_orders_count' => (clone $assignedWorkOrdersQuery)->count(),
            'assigned_work_orders' => (clone $assignedWorkOrdersQuery)
                ->latest('due_date')
                ->latest()
                ->limit(5)
                ->get(),
            'open_work_orders_count' => (clone $openWorkOrdersQuery)->count(),
            'overdue_work_orders_count' => (clone $overdueWorkOrdersQuery)->count(),
            'overdue_work_orders' => (clone $overdueWorkOrdersQuery)
                ->oldest('due_date')
                ->limit(5)
                ->get(),
            'due_soon_work_orders_count' => (clone $dueSoonWorkOrdersQuery)->count(),
            'due_soon_work_orders' => (clone $dueSoonWorkOrdersQuery)
                ->oldest('due_date')
                ->limit(5)
                ->get(),
            'recent_completed_work_orders_count' => (clone $workOrdersQuery)
                ->completed()
                ->whereNotNull('completed_at')
                ->count(),
            'recent_completed_work_orders' => (clone $workOrdersQuery)
                ->completed()
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->limit(5)
                ->get(),
            'maintenance_requests_needing_action_count' => (clone $maintenanceRequestsQuery)->count(),
            'maintenance_requests_needing_action' => (clone $maintenanceRequestsQuery)
                ->latest()
                ->limit(5)
                ->get(),
            'evidence_required_count' => (clone $evidenceRequiredQuery)->count(),
            'evidence_required' => (clone $evidenceRequiredQuery)
                ->latest()
                ->limit(5)
                ->get(),
            'beyond_repair_count' => (clone $beyondRepairQuery)->count(),
            'beyond_repair_items' => (clone $beyondRepairQuery)
                ->latest()
                ->limit(5)
                ->get(),
            'critical_recommendations_count' => (clone $recommendationsQuery)->count(),
            'critical_recommendations' => (clone $recommendationsQuery)
                ->limit(5)
                ->get(),
            'due_today_count' => (clone $scheduleQuery)
                ->dueToday()
                ->count(),
            'due_today' => (clone $scheduleQuery)
                ->dueToday()
                ->latest('scheduled_date')
                ->limit(5)
                ->get(),
            'due_soon_schedules_count' => (clone $scheduleQuery)
                ->dueSoon()
                ->count(),
            'unread_notifications_count' => SystemNotification::query()
                ->visibleTo($user)
                ->active()
                ->unread()
                ->count(),
            'notifications' => SystemNotification::query()
                ->visibleTo($user)
                ->active()
                ->unread()
                ->latest('generated_at')
                ->limit(5)
                ->get(),
            'recent_equipment' => $this->recentEquipment(),
            'can_create_maintenance_request' => $user->can('maintenance-requests.submit'),
        ];
    }

    /**
     * @return Collection<int, Equipment>
     */
    private function recentEquipment(): Collection
    {
        if (! (auth()->user()?->can('equipment.view') ?? false)) {
            return new Collection;
        }

        return Equipment::query()
            ->with(['category', 'currentLocation'])
            ->active()
            ->latest()
            ->limit(5)
            ->get();
    }
}
