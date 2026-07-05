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

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $technician;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(SampleEquipmentSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole(Role::findByName('Staff', 'web'));

        $this->technician = User::factory()->create();
        $this->technician->assignRole(Role::findByName('Technician', 'web'));

        $this->equipment = Equipment::query()->firstOrFail();
    }

    public function test_offline_queue_page_and_dashboard_render_sync_indicators(): void
    {
        $this->actingAs($this->technician)
            ->get('/admin/offline-queue')
            ->assertOk()
            ->assertSee('Offline Queue')
            ->assertSee('Synchronization Summary')
            ->assertSee('Pending Queue')
            ->assertSee('Failed Syncs')
            ->assertSee('Successfully Synchronized')
            ->assertSee('Local Drafts')
            ->assertSee('Sync Now')
            ->assertSee('Retry Failed')
            ->assertSee('Working offline.')
            ->assertSee('Changes will sync when connection returns.')
            ->assertSee('All offline changes have been synchronized. No pending actions.')
            ->assertSee('Connection Status')
            ->assertSee('Total Pending Offline Actions')
            ->assertSee('Failed Sync Count')
            ->assertSee('Last Sync Attempt')
            ->assertSee('offline-sync.js')
            ->assertSee('data-offline-sync-message', false);

        $this->actingAs($this->technician)
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Technician Mobile Dashboard')
            ->assertSee('Assigned Work Orders')
            ->assertSee('Open Work Orders')
            ->assertSee('Overdue Work Orders')
            ->assertSee('Pending Offline Sync')
            ->assertSee('Offline Forms')
            ->assertSee('Working offline.')
            ->assertSee('Changes will sync when connection returns.')
            ->assertSee('All offline changes have been synchronized. No pending actions.')
            ->assertSee('Connection:')
            ->assertSee('Pending Offline Actions:')
            ->assertSee('Failed Syncs:')
            ->assertSee('Last Sync:')
            ->assertSee('offline-sync.js')
            ->assertSee('data-offline-form', false);
    }

    public function test_offline_sync_javascript_contains_queue_draft_retry_remove_and_duplicate_prevention(): void
    {
        $script = file_get_contents(public_path('offline-sync.js'));

        $this->assertStringContainsString('queueItem', $script);
        $this->assertStringContainsString('saveDraft', $script);
        $this->assertStringContainsString('processQueue', $script);
        $this->assertStringContainsString('hasOfflineSyncSurface', $script);
        $this->assertStringContainsString('[data-offline-sync-surface]', $script);
        $this->assertStringContainsString('if (!hasOfflineSyncSurface())', $script);
        $this->assertStringContainsString('[data-offline-form]', $script);
        $this->assertStringContainsString('[data-offline-queue-list]', $script);
        $this->assertStringContainsString('data-offline-retry', $script);
        $this->assertStringContainsString('data-offline-remove', $script);
        $this->assertStringContainsString("existing.status === 'synced'", $script);
        $this->assertStringContainsString('Server version changed. Please review before resubmitting.', $script);
        $this->assertStringContainsString('Pending Synchronization', $script);
        $this->assertStringContainsString('Sync Complete', $script);
        $this->assertStringContainsString('Sync Failed', $script);
        $this->assertStringContainsString('Working offline. Changes will sync when connection returns.', $script);
        $this->assertStringContainsString('Online — pending actions ready to sync.', $script);
        $this->assertStringContainsString('All offline actions synchronized.', $script);
        $this->assertStringContainsString('All offline changes have been synchronized. No pending actions.', $script);
        $this->assertStringContainsString('Synchronized', $script);
        $this->assertStringNotContainsString('No Pending Actions', $script);
        $this->assertStringContainsString('data-offline-last-sync-short', $script);
        $this->assertStringContainsString('window.addEventListener(\'storage\'', $script);
        $this->assertStringContainsString('data-offline-sync-message', $script);
        $this->assertStringContainsString('data-offline-type-count', $script);
        $this->assertStringContainsString('claimOfflineSyncClick', $script);
        $this->assertStringContainsString('event.stopImmediatePropagation', $script);
        $this->assertStringNotContainsString('document.write', $script);
        $this->assertStringNotContainsString('document.body', $script);
    }

    public function test_service_worker_does_not_cache_offline_sync_or_authenticated_pages(): void
    {
        $serviceWorker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringNotContainsString("'/offline-sync.js'", $serviceWorker);
        $this->assertStringContainsString("'/offline-sync'", $serviceWorker);
        $this->assertStringContainsString("'/admin'", $serviceWorker);
        $this->assertStringContainsString("'/livewire'", $serviceWorker);
        $this->assertStringContainsString("'/filament'", $serviceWorker);
        $this->assertStringContainsString('isBlockedPath(url.pathname)', $serviceWorker);
        $this->assertStringContainsString('event.respondWith(networkOnly(request))', $serviceWorker);
        $this->assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        $this->assertStringNotContainsString('/admin/offline-queue', $serviceWorker);
        $this->assertStringNotContainsString('/admin/mobile-technician-dashboard', $serviceWorker);
        $this->assertStringNotContainsString('synchronized', strtolower($serviceWorker));
    }

    public function test_maintenance_request_creation_sync_respects_permissions(): void
    {
        $payload = [
            'client_id' => 'offline-request-1',
            'type' => 'maintenance_request.create',
            'payload' => [
                'equipment_id' => $this->equipment->id,
                'problem_description' => 'Offline reported issue.',
                'severity' => 'Moderate',
                'remarks' => 'Captured while offline.',
            ],
        ];

        $this->actingAs($this->technician)
            ->postJson(route('offline-sync.actions.store'), $payload)
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->postJson(route('offline-sync.actions.store'), $payload)
            ->assertOk()
            ->assertJson([
                'status' => 'synced',
                'record_type' => 'maintenance_request',
                'client_id' => 'offline-request-1',
            ]);

        $this->assertTrue(MaintenanceRequest::where('problem_description', 'Offline reported issue.')->exists());
    }

    public function test_work_order_completion_sync_and_conflict_safeguard(): void
    {
        $workOrder = WorkOrder::create([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->staff->id,
            'assigned_to' => $this->technician->id,
            'title' => 'Offline completion',
            'problem_description' => 'Test work order.',
            'priority' => 'Normal',
            'status' => 'Assigned',
        ]);

        $this->actingAs($this->technician)
            ->postJson(route('offline-sync.actions.store'), [
                'client_id' => 'offline-work-order-1',
                'type' => 'work_order.completion_update',
                'base_updated_at' => now()->subDay()->toISOString(),
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'action_performed' => 'Checked equipment offline.',
                ],
            ])
            ->assertStatus(409)
            ->assertJson(['message' => 'Server version changed. Please review before resubmitting.']);

        $this->actingAs($this->technician)
            ->postJson(route('offline-sync.actions.store'), [
                'client_id' => 'offline-work-order-2',
                'type' => 'work_order.completion_update',
                'base_updated_at' => $workOrder->fresh()->updated_at->toISOString(),
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'action_performed' => 'Checked equipment offline.',
                    'completion_remarks' => 'Ready for verification after evidence upload.',
                ],
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'synced',
                'record_type' => 'work_order',
                'client_id' => 'offline-work-order-2',
            ]);

        $this->assertSame('Checked equipment offline.', $workOrder->fresh()->action_performed);
    }

    public function test_equipment_status_note_and_evidence_metadata_sync(): void
    {
        $workOrder = WorkOrder::create([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->staff->id,
            'assigned_to' => $this->technician->id,
            'title' => 'Offline evidence',
            'problem_description' => 'Test work order.',
            'priority' => 'Normal',
            'status' => 'Assigned',
        ]);

        $this->actingAs($this->staff)
            ->postJson(route('offline-sync.actions.store'), [
                'client_id' => 'offline-equipment-1',
                'type' => 'equipment.status_update',
                'base_updated_at' => $this->equipment->updated_at->toISOString(),
                'payload' => [
                    'equipment_id' => $this->equipment->id,
                    'condition' => 'Needs maintenance',
                    'operational_status' => 'Under maintenance',
                    'remarks' => 'Offline status update.',
                ],
            ])
            ->assertOk()
            ->assertJson(['record_type' => 'equipment']);

        $this->assertSame('Under maintenance', $this->equipment->fresh()->operational_status);

        $this->actingAs($this->technician)
            ->postJson(route('offline-sync.actions.store'), [
                'client_id' => 'offline-evidence-1',
                'type' => 'evidence.metadata',
                'base_updated_at' => $workOrder->updated_at->toISOString(),
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'evidence_type' => 'After maintenance',
                    'caption' => 'Photo captured offline.',
                    'file_name' => 'after.jpg',
                ],
            ])
            ->assertOk()
            ->assertJson([
                'record_type' => 'work_order_evidence_metadata',
                'upload_deferred' => true,
            ]);

        $this->actingAs($this->technician)
            ->postJson(route('offline-sync.actions.store'), [
                'client_id' => 'offline-note-1',
                'type' => 'maintenance_note.create',
                'base_updated_at' => $workOrder->fresh()->updated_at->toISOString(),
                'payload' => [
                    'work_order_id' => $workOrder->id,
                    'note' => 'Offline maintenance note.',
                ],
            ])
            ->assertOk()
            ->assertJson(['record_type' => 'work_order_note']);

        $this->assertStringContainsString('Offline maintenance note.', $workOrder->fresh()->remarks);
    }
}
