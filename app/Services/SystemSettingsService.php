<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingsService
{
    private const CACHE_KEY = 'system_settings.all';

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->cachedSettings()[$group][$key] ?? $default;
    }

    public function set(string $group, string $key, mixed $value, ?string $type = null): SystemSetting
    {
        $setting = SystemSetting::query()->firstOrNew([
            'group' => $group,
            'key' => $key,
        ]);

        if ($type !== null) {
            $setting->value_type = $type;
        } elseif (! $setting->exists) {
            $setting->value_type = 'string';
        }

        $setting->setTypedValue($value);
        $setting->save();
        $this->clearCache();

        return $setting->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        return $this->cachedSettings()[$group] ?? [];
    }

    public function seedDefaultSettings(): void
    {
        foreach ($this->defaultSettings() as $group => $settings) {
            foreach ($settings as $key => $attributes) {
                $setting = SystemSetting::query()->updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'value_type' => $attributes['value_type'],
                        'label' => $attributes['label'],
                        'description' => $attributes['description'] ?? null,
                        'is_public' => $attributes['is_public'] ?? false,
                        'is_editable' => $attributes['is_editable'] ?? true,
                        'metadata' => $attributes['metadata'] ?? null,
                    ],
                );

                if ($setting->value === null) {
                    $setting->setTypedValue($attributes['value'] ?? null)->save();
                }
            }
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getPublicSettings(): array
    {
        return SystemSetting::query()
            ->public()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn ($settings) => $settings->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => $setting->getTypedValue(),
            ])->all())
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function defaultSettings(): array
    {
        return [
            'system_identity' => [
                'system_name' => ['value' => 'AI-Based Smart Equipment Inventory and Maintenance Recommendation System', 'value_type' => 'string', 'label' => 'System name', 'is_public' => true],
                'short_name' => ['value' => 'CFS Equipment Inventory', 'value_type' => 'string', 'label' => 'Short name', 'is_public' => true],
                'campus_name' => ['value' => 'SNSU Del Carmen Campus', 'value_type' => 'string', 'label' => 'Campus name', 'is_public' => true],
                'university_name' => ['value' => 'Surigao del Norte State University', 'value_type' => 'string', 'label' => 'University name', 'is_public' => true],
                'office_name' => ['value' => 'Climate Field School', 'value_type' => 'string', 'label' => 'Office name', 'is_public' => true],
                'address' => ['value' => 'Del Carmen, Surigao del Norte', 'value_type' => 'text', 'label' => 'Address', 'is_public' => true],
                'contact_email' => ['value' => null, 'value_type' => 'string', 'label' => 'Contact email', 'is_public' => true],
                'contact_number' => ['value' => null, 'value_type' => 'string', 'label' => 'Contact number', 'is_public' => true],
            ],
            'maintenance_defaults' => [
                'default_maintenance_frequency' => ['value' => 'Monthly', 'value_type' => 'string', 'label' => 'Default maintenance frequency'],
                'overdue_threshold_days' => ['value' => 0, 'value_type' => 'integer', 'label' => 'Overdue threshold days'],
                'due_soon_days' => ['value' => 7, 'value_type' => 'integer', 'label' => 'Due soon days'],
                'default_work_order_priority' => ['value' => 'Normal', 'value_type' => 'string', 'label' => 'Default work order priority'],
                'require_completion_evidence' => ['value' => true, 'value_type' => 'boolean', 'label' => 'Require completion evidence'],
                'require_beyond_repair_evidence' => ['value' => true, 'value_type' => 'boolean', 'label' => 'Require beyond-repair evidence'],
            ],
            'notification_defaults' => [
                'in_app_notifications_enabled' => ['value' => true, 'value_type' => 'boolean', 'label' => 'Enable in-app notifications'],
                'email_notifications_enabled' => ['value' => false, 'value_type' => 'boolean', 'label' => 'Enable email notifications'],
                'daily_digest_enabled' => ['value' => false, 'value_type' => 'boolean', 'label' => 'Enable daily digest'],
                'weekly_digest_enabled' => ['value' => false, 'value_type' => 'boolean', 'label' => 'Enable weekly digest'],
            ],
            'report_defaults' => [
                'report_header_name' => ['value' => 'CFS Equipment Inventory and Maintenance', 'value_type' => 'string', 'label' => 'Report header name'],
                'report_footer_text' => ['value' => 'Generated by the equipment inventory and maintenance system.', 'value_type' => 'text', 'label' => 'Report footer text'],
                'default_report_format' => ['value' => 'html', 'value_type' => 'string', 'label' => 'Default report format'],
                'include_generated_by' => ['value' => true, 'value_type' => 'boolean', 'label' => 'Include generated by'],
            ],
            'pwa_defaults' => [
                'app_display_name' => ['value' => 'CFS Equipment Inventory', 'value_type' => 'string', 'label' => 'App display name', 'is_public' => true],
                'theme_color' => ['value' => '#d97706', 'value_type' => 'string', 'label' => 'Theme color', 'is_public' => true],
                'background_color' => ['value' => '#f8fafc', 'value_type' => 'string', 'label' => 'Background color', 'is_public' => true],
                'offline_message' => ['value' => 'Some features require reconnecting.', 'value_type' => 'text', 'label' => 'Offline message', 'is_public' => true],
            ],
            'audit_defaults' => [
                'audit_enabled' => ['value' => true, 'value_type' => 'boolean', 'label' => 'Enable audit logging'],
                'audit_export_enabled' => ['value' => true, 'value_type' => 'boolean', 'label' => 'Enable audit export'],
                'audit_retention_days' => ['value' => 365, 'value_type' => 'integer', 'label' => 'Audit retention days'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cachedSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn ($settings) => $settings->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => $setting->getTypedValue(),
            ])->all())
            ->all());
    }
}
