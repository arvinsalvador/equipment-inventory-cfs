<?php

namespace App\Filament\Widgets;

use App\Models\SystemNotification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class NotificationOverview extends Widget
{
    protected string $view = 'filament.widgets.notification-overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', SystemNotification::class) ?? false;
    }

    public function unreadCount(): int
    {
        return $this->baseQuery()->unread()->count();
    }

    public function criticalCount(): int
    {
        return $this->baseQuery()->critical()->unread()->count();
    }

    public function highPriorityCount(): int
    {
        return $this->baseQuery()->highPriority()->unread()->count();
    }

    /**
     * @return Collection<int, SystemNotification>
     */
    public function latestNotifications(): Collection
    {
        return $this->baseQuery()
            ->orderByRaw('case when read_at is null then 0 else 1 end')
            ->latest('generated_at')
            ->limit(5)
            ->get();
    }

    private function baseQuery()
    {
        return SystemNotification::query()
            ->visibleTo(auth()->user())
            ->active();
    }
}
