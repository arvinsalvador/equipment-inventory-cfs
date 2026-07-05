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

        $this->assertStringContainsString("const CACHE_NAME = 'ai-equipment-pwa-v4'", $serviceWorker);
        $this->assertStringContainsString('/offline', $serviceWorker);
        $this->assertStringContainsString('/manifest.webmanifest', $serviceWorker);
        $this->assertStringContainsString('/pwa.css', $serviceWorker);
        $this->assertStringContainsString('/pwa.js', $serviceWorker);
        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringContainsString("'/admin'", $serviceWorker);
        $this->assertStringContainsString("'/filament'", $serviceWorker);
        $this->assertStringContainsString("'/livewire'", $serviceWorker);
        $this->assertStringContainsString("'/login'", $serviceWorker);
        $this->assertStringContainsString("'/logout'", $serviceWorker);
        $this->assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        $this->assertStringContainsString("fetch(request).catch(() => caches.match('/offline'))", $serviceWorker);
        $this->assertStringContainsString('isBlockedPath(url.pathname)', $serviceWorker);
        $this->assertStringNotContainsString("'/admin/login'", $serviceWorker);
        $this->assertStringNotContainsString("url.pathname === '/admin/login'", $serviceWorker);
        $this->assertStringNotContainsString("'/offline-sync.js'", $serviceWorker);
        $this->assertStringNotContainsString("'/favicon.ico'", $serviceWorker);
        $this->assertStringNotContainsString("url.pathname.startsWith('/build/')", $serviceWorker);
        $this->assertStringNotContainsString('/admin/mobile-technician-dashboard', $serviceWorker);
        $this->assertStringNotContainsString('/admin/work-orders', $serviceWorker);
    }

    public function test_pwa_meta_does_not_globally_load_offline_sync_script(): void
    {
        $meta = file_get_contents(resource_path('views/pwa/meta.blade.php'));

        $this->assertStringContainsString("asset('pwa.js')", $meta);
        $this->assertStringNotContainsString('offline-sync.js', $meta);
    }

    public function test_pwa_scripts_do_not_replace_the_document_body(): void
    {
        $this->assertStringNotContainsString('document.body', file_get_contents(public_path('pwa.js')));
        $this->assertStringNotContainsString('document.body', file_get_contents(public_path('offline-sync.js')));
    }

    public function test_admin_login_returns_filament_login_page_not_offline_shell(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('AI Based Equipment Inventory and Maintenance')
            ->assertSee('manifest.webmanifest')
            ->assertDontSee('offline-sync.js')
            ->assertDontSee('No Pending Actions')
            ->assertDontSee('No pending offline actions.')
            ->assertDontSee('Working Offline')
            ->assertDontSee('Working offline.')
            ->assertDontSee('Offline Workspace')
            ->assertDontSee('data-offline-queue', false)
            ->assertDontSee('data-offline-sync-status', false)
            ->assertDontSee('data-offline-sync-message', false)
            ->assertDontSee('data-pwa-online-status', false)
            ->assertDontSee('Offline Mode')
            ->assertDontSee('Internet connection unavailable.')
            ->assertDontSee('Some features require reconnecting.');
    }

    public function test_offline_page_renders_required_copy(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee('Offline Mode')
            ->assertSee('Internet connection unavailable.')
            ->assertSee('Some features require reconnecting.');
    }

    public function test_public_pwa_assets_are_available(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('pwa.css'));
        $this->assertFileExists(public_path('pwa.js'));
        $this->assertFileExists(public_path('icons/pwa-icon.svg'));
        $this->assertFileExists(public_path('icons/pwa-maskable.svg'));
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
            ->assertSee('data-pwa-qr-link', false)
            ->assertDontSee('offline-sync.js')
            ->assertDontSee('No pending offline actions.')
            ->assertDontSee('No Pending Actions')
            ->assertDontSee('All offline changes have been synchronized. No pending actions.')
            ->assertDontSee('data-offline-sync-status', false)
            ->assertDontSee('data-offline-sync-message', false)
            ->assertDontSee('data-pwa-online-status', false);
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
            ->assertSee('Open Work Orders')
            ->assertSee('Overdue Work Orders')
            ->assertSee('Maintenance Requests Needing Action')
            ->assertSee('Evidence Required')
            ->assertSee('Critical Recommendations')
            ->assertSee('Due Today')
            ->assertSee('Notifications')
            ->assertSee('Offline Status')
            ->assertSee('Pending Offline Sync')
            ->assertSee('Offline Forms');
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
