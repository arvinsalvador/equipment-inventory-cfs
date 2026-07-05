<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'maintenance_reminders',
    'work_order_alerts',
    'maintenance_request_alerts',
    'ai_recommendation_alerts',
    'lifecycle_alerts',
    'warranty_alerts',
    'evidence_alerts',
    'system_alerts',
    'email_notifications_enabled',
    'immediate_critical_email_enabled',
    'daily_digest_email_enabled',
    'weekly_digest_email_enabled',
    'digest_time',
    'digest_day_of_week',
    'browser_push_enabled',
    'critical_browser_push_enabled',
    'maintenance_browser_push_enabled',
    'work_order_browser_push_enabled',
    'maintenance_request_browser_push_enabled',
    'ai_recommendation_browser_push_enabled',
    'lifecycle_browser_push_enabled',
    'warranty_browser_push_enabled',
    'evidence_browser_push_enabled',
    'budget_browser_push_enabled',
    'asset_action_browser_push_enabled',
    'executive_browser_push_enabled',
])]
class UserNotificationPreference extends Model
{
    use HasFactory;

    public const DIGEST_DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    public const CATEGORY_COLUMNS = [
        'Preventive Maintenance' => 'maintenance_reminders',
        'Work Order' => 'work_order_alerts',
        'Maintenance Request' => 'maintenance_request_alerts',
        'AI Recommendation' => 'ai_recommendation_alerts',
        'Equipment Lifecycle' => 'lifecycle_alerts',
        'Warranty' => 'warranty_alerts',
        'Evidence' => 'evidence_alerts',
        'Budget' => 'system_alerts',
        'Asset Action' => 'system_alerts',
        'Executive' => 'system_alerts',
        'System' => 'system_alerts',
    ];

    public const BROWSER_PUSH_CATEGORY_COLUMNS = [
        'Preventive Maintenance' => 'maintenance_browser_push_enabled',
        'Work Order' => 'work_order_browser_push_enabled',
        'Maintenance Request' => 'maintenance_request_browser_push_enabled',
        'AI Recommendation' => 'ai_recommendation_browser_push_enabled',
        'Equipment Lifecycle' => 'lifecycle_browser_push_enabled',
        'Warranty' => 'warranty_browser_push_enabled',
        'Evidence' => 'evidence_browser_push_enabled',
        'Budget' => 'budget_browser_push_enabled',
        'Asset Action' => 'asset_action_browser_push_enabled',
        'Executive' => 'executive_browser_push_enabled',
        'System' => 'critical_browser_push_enabled',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wantsCategory(string $category): bool
    {
        $column = self::CATEGORY_COLUMNS[$category] ?? 'system_alerts';

        return (bool) $this->{$column};
    }

    public function wantsImmediateCriticalEmail(): bool
    {
        return $this->email_notifications_enabled && $this->immediate_critical_email_enabled;
    }

    public function wantsDailyDigestEmail(): bool
    {
        return $this->email_notifications_enabled && $this->daily_digest_email_enabled;
    }

    public function wantsWeeklyDigestEmail(): bool
    {
        return $this->email_notifications_enabled && $this->weekly_digest_email_enabled;
    }

    public function wantsCriticalBrowserPush(): bool
    {
        return $this->browser_push_enabled && $this->critical_browser_push_enabled;
    }

    public function wantsBrowserPushCategory(string $category): bool
    {
        $column = self::BROWSER_PUSH_CATEGORY_COLUMNS[$category] ?? null;

        return $this->browser_push_enabled && $column !== null && (bool) $this->{$column};
    }

    protected function casts(): array
    {
        return [
            'maintenance_reminders' => 'boolean',
            'work_order_alerts' => 'boolean',
            'maintenance_request_alerts' => 'boolean',
            'ai_recommendation_alerts' => 'boolean',
            'lifecycle_alerts' => 'boolean',
            'warranty_alerts' => 'boolean',
            'evidence_alerts' => 'boolean',
            'system_alerts' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'immediate_critical_email_enabled' => 'boolean',
            'daily_digest_email_enabled' => 'boolean',
            'weekly_digest_email_enabled' => 'boolean',
            'browser_push_enabled' => 'boolean',
            'critical_browser_push_enabled' => 'boolean',
            'maintenance_browser_push_enabled' => 'boolean',
            'work_order_browser_push_enabled' => 'boolean',
            'maintenance_request_browser_push_enabled' => 'boolean',
            'ai_recommendation_browser_push_enabled' => 'boolean',
            'lifecycle_browser_push_enabled' => 'boolean',
            'warranty_browser_push_enabled' => 'boolean',
            'evidence_browser_push_enabled' => 'boolean',
            'budget_browser_push_enabled' => 'boolean',
            'asset_action_browser_push_enabled' => 'boolean',
            'executive_browser_push_enabled' => 'boolean',
        ];
    }
}
