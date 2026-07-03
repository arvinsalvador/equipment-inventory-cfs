<?php

namespace App\Filament\Pages;

use App\Models\Equipment;
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
        return auth()->user()?->can('access admin panel') ?? false;
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

        $assignedWorkOrdersQuery = WorkOrder::query()
            ->with('equipment')
            ->whereNotIn('status', WorkOrder::CLOSED_STATUSES)
            ->where(fn ($query) => $query
                ->where('assigned_to', $user->id)
                ->orWhere('accepted_by', $user->id));

        return [
            'assigned_work_orders_count' => (clone $assignedWorkOrdersQuery)->count(),
            'assigned_work_orders' => (clone $assignedWorkOrdersQuery)
                ->latest('due_date')
                ->latest()
                ->limit(5)
                ->get(),
            'due_today_count' => MaintenanceSchedule::query()
                ->dueToday()
                ->where('assigned_user_id', $user->id)
                ->count(),
            'due_today' => MaintenanceSchedule::query()
                ->with('equipment')
                ->dueToday()
                ->where('assigned_user_id', $user->id)
                ->latest('scheduled_date')
                ->limit(5)
                ->get(),
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
