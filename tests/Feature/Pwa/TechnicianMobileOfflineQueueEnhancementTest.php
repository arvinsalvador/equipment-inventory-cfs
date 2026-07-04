<?php

namespace Tests\Feature\Pwa;

use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TechnicianMobileOfflineQueueEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $technician;

    private User $unauthorizedUser;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->administrator = User::factory()->create();
        $this->administrator->assignRole(Role::findByName('Administrator', 'web'));

        $this->technician = User::factory()->create();
        $this->technician->assignRole(Role::findByName('Technician', 'web'));

        $this->unauthorizedUser = User::factory()->create();

        $this->equipment = Equipment::query()->firstOrFail();
    }

    public function test_administrator_and_technician_can_access_technician_mobile_page(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Technician Mobile Dashboard');

        $this->actingAs($this->technician)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Technician Mobile Dashboard');
    }

    public function test_unauthorized_user_cannot_access_technician_mobile_page(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get('/admin/mobile-technician-dashboard')
            ->assertForbidden();
    }

    public function test_technician_mobile_page_renders_dashboard_sections_and_work_order_summary(): void
    {
        WorkOrder::create([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->technician->id,
            'title' => 'Inspect pump station',
            'problem_description' => 'Field inspection required.',
            'priority' => 'High',
            'status' => 'Assigned',
            'due_date' => today()->addDay(),
        ]);

        MaintenanceRequest::create([
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $this->technician->id,
            'problem_description' => 'Noise during operation.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ]);

        $this->actingAs($this->technician)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Assigned Work Orders')
            ->assertSee('Open Work Orders')
            ->assertSee('Overdue Work Orders')
            ->assertSee('Maintenance Requests Needing Action')
            ->assertSee('Evidence Required Items')
            ->assertSee('Offline Status')
            ->assertSee('Pending Offline Sync')
            ->assertSee('Last Sync')
            ->assertSee('Inspect pump station')
            ->assertSee('Noise during operation.')
            ->assertSee('Scan QR Code')
            ->assertSee('View Assigned Work Orders')
            ->assertSee('View Offline Queue');
    }

    public function test_technician_mobile_empty_state_is_helpful(): void
    {
        $this->actingAs($this->technician)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('All assigned technician tasks are currently up to date.')
            ->assertDontSee('No Pending Actions');
    }

    public function test_administrator_and_technician_can_access_offline_queue_page(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/offline-queue')
            ->assertOk()
            ->assertSee('Synchronization Summary');

        $this->actingAs($this->technician)
            ->get('/admin/offline-queue')
            ->assertOk()
            ->assertSee('Synchronization Summary');
    }

    public function test_unauthorized_user_cannot_access_offline_queue_page(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get('/admin/offline-queue')
            ->assertForbidden();
    }

    public function test_offline_queue_renders_sync_dashboard_and_empty_synced_state(): void
    {
        $this->actingAs($this->technician)
            ->get('/admin/offline-queue')
            ->assertOk()
            ->assertSee('Synchronization Summary')
            ->assertSee('Total Pending Offline Actions')
            ->assertSee('Failed Sync Count')
            ->assertSee('Successfully Synced Count')
            ->assertSee('Last Sync Attempt')
            ->assertSee('Queue Items by Type')
            ->assertSee('Work Orders')
            ->assertSee('Maintenance Requests')
            ->assertSee('Evidence Uploads')
            ->assertSee('Equipment Updates')
            ->assertSee('Other Offline Actions')
            ->assertSee('All offline changes have been synchronized. No pending actions.')
            ->assertDontSee('No Pending Actions');
    }

    public function test_offline_sync_regression_protection_remains_in_place(): void
    {
        $serviceWorker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString("'/admin'", $serviceWorker);
        $this->assertStringContainsString("'/filament'", $serviceWorker);
        $this->assertStringContainsString("'/livewire'", $serviceWorker);
        $this->assertStringNotContainsString("'/offline-sync.js'", $serviceWorker);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('offline-sync.js')
            ->assertDontSee('data-offline-sync-status', false);
    }
}
