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
])]
class UserNotificationPreference extends Model
{
    use HasFactory;

    public const CATEGORY_COLUMNS = [
        'Preventive Maintenance' => 'maintenance_reminders',
        'Work Order' => 'work_order_alerts',
        'Maintenance Request' => 'maintenance_request_alerts',
        'AI Recommendation' => 'ai_recommendation_alerts',
        'Equipment Lifecycle' => 'lifecycle_alerts',
        'Warranty' => 'warranty_alerts',
        'Evidence' => 'evidence_alerts',
        'System' => 'system_alerts',
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
        ];
    }
}
