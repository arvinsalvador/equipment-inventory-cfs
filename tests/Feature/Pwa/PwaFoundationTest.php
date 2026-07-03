<?php

namespace Tests\Feature\Pwa;

use App\Filament\Pages\MobileTechnicianDashboard;
use App\Models\Equipment;
use App\Models\MaintenanceSchedule;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PwaFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->technician = User::factory()->create();
        $this->technician->assignRole(Role::findByName('Technician', 'web'));
    }

    public function test_manifest_exists_and_contains_required_pwa_metadata(): void
    {
        $path = public_path('manifest.webmanifest');

        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertSame('AI Based Equipment Inventory and Maintenance', $manifest['name']);
        $this->assertSame('AI Equipment', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('portrait', $manifest['orientation']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_exists_and_only_handles_shell_assets(): void
    {
        $path = public_path('service-worker.js');

        $this->assertFileExists($path);

        $serviceWorker = file_get_contents($path);

        $this->assertStringContainsString('/offline', $serviceWorker);
        $this->assertStringContainsString('/manifest.webmanifest', $serviceWorker);
        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringNotContainsString('/admin/mobile-technician-dashboard', $serviceWorker);
        $this->assertStringNotContainsString('/admin/work-orders', $serviceWorker);
    }

    public function test_offline_page_renders_required_copy(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee('Offline Mode')
            ->assertSee('Internet connection unavailable.')
            ->assertSee('Some features require reconnecting.');
    }

    public function test_pwa_install_prompt_bottom_nav_and_qr_shortcut_render_for_authenticated_filament_pages(): void
    {
        $this->actingAs($this->technician)
            ->get('/admin')
            ->assertOk()
            ->assertSee('manifest.webmanifest')
            ->assertSee('Install App')
            ->assertSee('Mobile technician navigation')
            ->assertSee('Scan QR')
            ->assertSee('data-pwa-qr-link', false);
    }

    public function test_qr_scanner_page_renders_camera_readiness_and_pwa_assets(): void
    {
        $this->actingAs($this->technician)
            ->get(route('equipment.scan'))
            ->assertOk()
            ->assertSee('Camera scanning requires HTTPS or localhost')
            ->assertSee('data-pwa-camera-status', false)
            ->assertSee('pwa.js')
            ->assertSee('Start camera scanner');
    }

    public function test_mobile_technician_dashboard_renders_operational_sections(): void
    {
        $equipment = Equipment::query()->firstOrFail();

        WorkOrder::create([
            'equipment_id' => $equipment->id,
            'created_by' => $this->technician->id,
            'assigned_to' => $this->technician->id,
            'title' => 'Inspect weather station',
            'problem_description' => 'Preventive inspection for field equipment.',
            'priority' => 'Normal',
            'status' => 'Assigned',
        ]);

        MaintenanceSchedule::create([
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Preventive inspection',
            'maintenance_frequency' => 'Monthly',
            'assigned_user_id' => $this->technician->id,
            'scheduled_date' => today(),
            'status' => 'Due today',
        ]);

        SystemNotification::create([
            'user_id' => $this->technician->id,
            'title' => 'Work order update',
            'message' => 'A work order needs attention.',
            'notification_type' => 'Reminder',
            'priority' => 'Normal',
            'category' => 'Work Order',
            'generated_at' => now(),
        ]);

        $this->actingAs($this->technician)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Assigned Work Orders')
            ->assertSee('Due Today')
            ->assertSee('Notifications')
            ->assertSee('Recent Equipment')
            ->assertSee('Device Information')
            ->assertSee('Online/Offline')
            ->assertSee('Browser')
            ->assertSee('Install status');
    }

    public function test_mobile_dashboard_livewire_component_renders_responsive_layout(): void
    {
        Livewire::actingAs($this->technician)
            ->test(MobileTechnicianDashboard::class)
            ->assertSee('Mobile technician workspace')
            ->assertSee('Assigned Work Orders')
            ->assertSee('Scan QR');
    }
}
