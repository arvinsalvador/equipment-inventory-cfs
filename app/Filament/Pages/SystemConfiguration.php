<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Services\AuditLogService;
use App\Services\SystemSettingsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SystemConfiguration extends Page
{
    protected string $view = 'filament.pages.system-configuration';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?string $navigationLabel = 'System Configuration';

    protected static ?int $navigationSort = 20;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $settings = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('system-settings.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(SystemSettingsService $settingsService): void
    {
        $settingsService->seedDefaultSettings();
        $this->loadSettings();
    }

    public function save(SystemSettingsService $settingsService, AuditLogService $auditLogService): void
    {
        abort_unless(auth()->user()?->can('system-settings.update'), 403);

        foreach ($this->settingsForDisplay() as $group => $settings) {
            foreach ($settings as $setting) {
                if (! $setting->isEditable()) {
                    continue;
                }

                $value = $this->settings[$group][$setting->key] ?? null;
                $parsedValue = $this->parseValue($setting, $value);
                $oldValue = $setting->getTypedValue();

                if ($oldValue === $parsedValue) {
                    continue;
                }

                $updated = $settingsService->set($group, $setting->key, $parsedValue, $setting->value_type);

                $auditLogService->log(
                    'updated',
                    'System Configuration',
                    "System setting {$group}.{$setting->key} updated.",
                    auth()->user(),
                    $updated,
                    ['value' => $oldValue],
                    ['value' => $updated->getTypedValue()],
                );
            }
        }

        $this->loadSettings();

        Notification::make()
            ->title('System configuration saved')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'System Configuration';
    }

    /**
     * @return array<string, Collection<int, SystemSetting>>
     */
    public function settingsForDisplay(): array
    {
        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group')
            ->all();
    }

    public function groupLabel(string $group): string
    {
        return str($group)->replace('_', ' ')->title()->toString();
    }

    public function inputType(SystemSetting $setting): string
    {
        return match ($setting->value_type) {
            'integer', 'decimal' => 'number',
            'date' => 'date',
            'time' => 'time',
            default => 'text',
        };
    }

    private function loadSettings(): void
    {
        $this->settings = [];

        foreach ($this->settingsForDisplay() as $group => $settings) {
            foreach ($settings as $setting) {
                $value = $setting->getTypedValue();
                $this->settings[$group][$setting->key] = $setting->isJson()
                    ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    : $value;
            }
        }
    }

    private function parseValue(SystemSetting $setting, mixed $value): mixed
    {
        if ($setting->isBoolean()) {
            return (bool) $value;
        }

        if ($setting->isJson()) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            $decoded = json_decode((string) $value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    "settings.{$setting->group}.{$setting->key}" => 'The JSON value is invalid.',
                ]);
            }

            return $decoded;
        }

        return match ($setting->value_type) {
            'integer' => $value === null || $value === '' ? null : (int) $value,
            'decimal' => $value === null || $value === '' ? null : (float) $value,
            default => $value,
        };
    }
}
