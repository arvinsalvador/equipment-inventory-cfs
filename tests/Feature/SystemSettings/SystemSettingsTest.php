<?php

namespace Tests\Feature\SystemSettings;

use App\Filament\Pages\SystemConfiguration;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingsSeeder::class);
    }

    public function test_system_setting_model_supports_typed_values_scopes_and_unique_keys(): void
    {
        $string = SystemSetting::create(['group' => 'test', 'key' => 'string', 'value' => 'hello', 'value_type' => 'string']);
        $integer = SystemSetting::create(['group' => 'test', 'key' => 'integer', 'value' => '7', 'value_type' => 'integer']);
        $decimal = SystemSetting::create(['group' => 'test', 'key' => 'decimal', 'value' => '7.25', 'value_type' => 'decimal']);
        $boolean = SystemSetting::create(['group' => 'test', 'key' => 'boolean', 'value' => '1', 'value_type' => 'boolean', 'is_public' => true]);
        $json = SystemSetting::create(['group' => 'test', 'key' => 'json', 'value' => '{"enabled":true}', 'value_type' => 'json']);

        $this->assertSame('hello', $string->getTypedValue());
        $this->assertSame(7, $integer->getTypedValue());
        $this->assertSame(7.25, $decimal->getTypedValue());
        $this->assertTrue($boolean->getTypedValue());
        $this->assertSame(['enabled' => true], $json->getTypedValue());
        $this->assertTrue($boolean->isBoolean());
        $this->assertTrue($json->isJson());
        $this->assertTrue($boolean->isPublic());
        $this->assertTrue($json->isEditable());
        $this->assertSame($boolean->id, SystemSetting::public()->firstWhere('key', 'boolean')->id);
        $this->assertSame($string->id, SystemSetting::group('test')->editable()->firstWhere('key', 'string')->id);

        $this->expectException(QueryException::class);
        SystemSetting::create(['group' => 'test', 'key' => 'string', 'value' => 'duplicate']);
    }

    public function test_system_settings_service_get_set_groups_defaults_and_public_settings(): void
    {
        $service = app(SystemSettingsService::class);

        $this->assertSame('fallback', $service->get('missing', 'value', 'fallback'));

        $service->set('custom', 'enabled', true, 'boolean');
        $service->set('custom', 'count', 12, 'integer');
        $service->set('custom', 'payload', ['mode' => 'safe'], 'json');

        $this->assertTrue($service->get('custom', 'enabled'));
        $this->assertSame(12, $service->get('custom', 'count'));
        $this->assertSame(['mode' => 'safe'], $service->get('custom', 'payload'));
        $this->assertSame([
            'count' => 12,
            'enabled' => true,
            'payload' => ['mode' => 'safe'],
        ], $service->getGroup('custom'));

        $public = $service->getPublicSettings();

        $this->assertArrayHasKey('system_identity', $public);
        $this->assertArrayHasKey('system_name', $public['system_identity']);
        $this->assertArrayHasKey('pwa_defaults', $public);
    }

    public function test_default_settings_seeder_is_idempotent_and_seeds_required_groups(): void
    {
        $initialCount = SystemSetting::count();

        $this->seed(SystemSettingsSeeder::class);

        $this->assertSame($initialCount, SystemSetting::count());

        foreach ([
            'system_identity',
            'maintenance_defaults',
            'notification_defaults',
            'report_defaults',
            'pwa_defaults',
            'audit_defaults',
        ] as $group) {
            $this->assertDatabaseHas('system_settings', ['group' => $group]);
        }

        $this->assertDatabaseHas('system_settings', ['group' => 'system_identity', 'key' => 'system_name']);
        $this->assertDatabaseHas('system_settings', ['group' => 'audit_defaults', 'key' => 'audit_retention_days']);
    }

    public function test_system_configuration_authorization(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $staff = $this->userWithRole('Staff');
        $technician = $this->userWithRole('Technician');
        $plainUser = User::factory()->create();

        $this->assertTrue($administrator->can('system-settings.view'));
        $this->assertTrue($administrator->can('system-settings.update'));
        $this->assertFalse($staff->can('system-settings.view'));
        $this->assertFalse($technician->can('system-settings.view'));

        $this->actingAs($administrator)
            ->get(SystemConfiguration::getUrl())
            ->assertOk()
            ->assertSee('System Configuration')
            ->assertSee('System Identity')
            ->assertSee('Maintenance Defaults')
            ->assertSee('Notification Defaults')
            ->assertSee('Report Defaults')
            ->assertSee('Pwa Defaults')
            ->assertSee('Audit Defaults');

        $this->actingAs($staff)->get(SystemConfiguration::getUrl())->assertForbidden();
        $this->actingAs($technician)->get(SystemConfiguration::getUrl())->assertForbidden();
        $this->actingAs($plainUser)->get(SystemConfiguration::getUrl())->assertForbidden();
    }

    public function test_administrator_can_update_settings_and_changes_are_audited(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(SystemConfiguration::class)
            ->set('settings.system_identity.system_name', 'Updated Equipment System')
            ->set('settings.maintenance_defaults.due_soon_days', 10)
            ->set('settings.notification_defaults.email_notifications_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $service = app(SystemSettingsService::class);

        $this->assertSame('Updated Equipment System', $service->get('system_identity', 'system_name'));
        $this->assertSame(10, $service->get('maintenance_defaults', 'due_soon_days'));
        $this->assertTrue($service->get('notification_defaults', 'email_notifications_enabled'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'module' => 'System Configuration',
            'description' => 'System setting system_identity.system_name updated.',
        ]);
    }

    public function test_runtime_reads_report_and_offline_settings(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $service = app(SystemSettingsService::class);

        $service->set('report_defaults', 'report_header_name', 'Configured Report Header', 'string');
        $service->set('report_defaults', 'report_footer_text', 'Configured report footer.', 'text');
        $service->set('pwa_defaults', 'offline_message', 'Configured offline message.', 'text');

        $this->actingAs($administrator)
            ->get(route('reports.show', 'equipment-inventory'))
            ->assertOk()
            ->assertSee('Configured Report Header');

        $this->actingAs($administrator)
            ->get(route('reports.print', 'equipment-inventory'))
            ->assertOk()
            ->assertSee('Configured report footer.');

        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('Configured offline message.');
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }
}
