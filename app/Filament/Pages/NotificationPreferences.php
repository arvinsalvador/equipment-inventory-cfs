<?php

namespace App\Filament\Pages;

use App\Models\UserNotificationPreference;
use App\Services\BrowserPushPreparationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;

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

    public bool $email_notifications_enabled = false;

    public bool $immediate_critical_email_enabled = false;

    public bool $daily_digest_email_enabled = false;

    public bool $weekly_digest_email_enabled = false;

    public ?string $digest_time = null;

    public ?string $digest_day_of_week = null;

    public bool $browser_push_enabled = false;

    public bool $critical_browser_push_enabled = false;

    public bool $maintenance_browser_push_enabled = false;

    public bool $work_order_browser_push_enabled = false;

    public bool $ai_recommendation_browser_push_enabled = false;

    public bool $lifecycle_browser_push_enabled = false;

    public bool $warranty_browser_push_enabled = false;

    public bool $evidence_browser_push_enabled = false;

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

        $this->email_notifications_enabled = (bool) $preference->email_notifications_enabled;
        $this->immediate_critical_email_enabled = (bool) $preference->immediate_critical_email_enabled;
        $this->daily_digest_email_enabled = (bool) $preference->daily_digest_email_enabled;
        $this->weekly_digest_email_enabled = (bool) $preference->weekly_digest_email_enabled;
        $this->digest_time = $preference->digest_time;
        $this->digest_day_of_week = $preference->digest_day_of_week;
        $this->browser_push_enabled = (bool) $preference->browser_push_enabled;
        $this->critical_browser_push_enabled = (bool) $preference->critical_browser_push_enabled;
        $this->maintenance_browser_push_enabled = (bool) $preference->maintenance_browser_push_enabled;
        $this->work_order_browser_push_enabled = (bool) $preference->work_order_browser_push_enabled;
        $this->ai_recommendation_browser_push_enabled = (bool) $preference->ai_recommendation_browser_push_enabled;
        $this->lifecycle_browser_push_enabled = (bool) $preference->lifecycle_browser_push_enabled;
        $this->warranty_browser_push_enabled = (bool) $preference->warranty_browser_push_enabled;
        $this->evidence_browser_push_enabled = (bool) $preference->evidence_browser_push_enabled;
    }

    public function save(): void
    {
        $this->validate([
            'digest_time' => ['nullable', 'date_format:H:i'],
            'digest_day_of_week' => ['nullable', Rule::in(UserNotificationPreference::DIGEST_DAYS)],
        ]);

        auth()->user()->notificationPreference()->updateOrCreate([], [
            'maintenance_reminders' => $this->maintenance_reminders,
            'work_order_alerts' => $this->work_order_alerts,
            'maintenance_request_alerts' => $this->maintenance_request_alerts,
            'ai_recommendation_alerts' => $this->ai_recommendation_alerts,
            'lifecycle_alerts' => $this->lifecycle_alerts,
            'warranty_alerts' => $this->warranty_alerts,
            'evidence_alerts' => $this->evidence_alerts,
            'system_alerts' => $this->system_alerts,
            'email_notifications_enabled' => $this->email_notifications_enabled,
            'immediate_critical_email_enabled' => $this->immediate_critical_email_enabled,
            'daily_digest_email_enabled' => $this->daily_digest_email_enabled,
            'weekly_digest_email_enabled' => $this->weekly_digest_email_enabled,
            'digest_time' => $this->digest_time,
            'digest_day_of_week' => $this->digest_day_of_week,
            'browser_push_enabled' => $this->browser_push_enabled,
            'critical_browser_push_enabled' => $this->critical_browser_push_enabled,
            'maintenance_browser_push_enabled' => $this->maintenance_browser_push_enabled,
            'work_order_browser_push_enabled' => $this->work_order_browser_push_enabled,
            'ai_recommendation_browser_push_enabled' => $this->ai_recommendation_browser_push_enabled,
            'lifecycle_browser_push_enabled' => $this->lifecycle_browser_push_enabled,
            'warranty_browser_push_enabled' => $this->warranty_browser_push_enabled,
            'evidence_browser_push_enabled' => $this->evidence_browser_push_enabled,
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

    /**
     * @return array{enabled:bool,active_subscriptions:int,ready:bool,status:string}
     */
    public function browserPushReadiness(): array
    {
        return app(BrowserPushPreparationService::class)->getReadinessForUser(auth()->user());
    }
}
