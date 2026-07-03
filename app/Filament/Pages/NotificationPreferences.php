<?php

namespace App\Filament\Pages;

use App\Models\UserNotificationPreference;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NotificationPreferences extends Page
{
    protected string $view = 'filament.pages.notification-preferences';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'System Management';

    protected static ?string $navigationLabel = 'Notification Preferences';

    protected static ?int $navigationSort = 40;

    public bool $maintenance_reminders = true;

    public bool $work_order_alerts = true;

    public bool $maintenance_request_alerts = true;

    public bool $ai_recommendation_alerts = true;

    public bool $lifecycle_alerts = true;

    public bool $warranty_alerts = true;

    public bool $evidence_alerts = true;

    public bool $system_alerts = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access admin panel') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $preference = auth()->user()->notificationPreference()->firstOrCreate([]);

        foreach (array_values(UserNotificationPreference::CATEGORY_COLUMNS) as $field) {
            $this->{$field} = (bool) $preference->{$field};
        }
    }

    public function save(): void
    {
        auth()->user()->notificationPreference()->updateOrCreate([], [
            'maintenance_reminders' => $this->maintenance_reminders,
            'work_order_alerts' => $this->work_order_alerts,
            'maintenance_request_alerts' => $this->maintenance_request_alerts,
            'ai_recommendation_alerts' => $this->ai_recommendation_alerts,
            'lifecycle_alerts' => $this->lifecycle_alerts,
            'warranty_alerts' => $this->warranty_alerts,
            'evidence_alerts' => $this->evidence_alerts,
            'system_alerts' => $this->system_alerts,
        ]);

        Notification::make()
            ->title('Notification preferences saved')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'Notification Preferences';
    }
}
