<?php

namespace Tests\Feature\Audit;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use App\Services\AuditLogService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentCategory $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->category = EquipmentCategory::create(['name' => 'Audit Category', 'is_active' => true]);
        $this->location = Location::create(['name' => 'Audit Room', 'type' => 'Room', 'is_active' => true]);
    }

    public function test_audit_log_model_supports_user_system_json_and_scopes(): void
    {
        $user = $this->userWithRole('Administrator');

        $userLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'module' => 'Equipment',
            'entity_type' => Equipment::class,
            'entity_id' => 10,
            'description' => 'Equipment updated.',
            'old_values' => ['condition' => 'Good'],
            'new_values' => ['condition' => 'Fair'],
            'metadata' => ['source' => 'test'],
            'created_at' => now(),
        ]);

        $systemLog = AuditLog::create([
            'action' => 'system',
            'module' => 'System',
            'description' => 'System audit event.',
            'created_at' => now()->subDay(),
        ]);

        $this->assertTrue($userLog->user->is($user));
        $this->assertNull($systemLog->user);
        $this->assertTrue($systemLog->isSystemAction());
        $this->assertTrue($userLog->isUserAction());
        $this->assertSame('Good', $userLog->old_values['condition']);
        $this->assertSame('Fair', $userLog->new_values['condition']);
        $this->assertSame('test', $userLog->metadata['source']);
        $this->assertSame('Equipment: updated - Equipment updated.', $userLog->summary());
        $this->assertTrue(AuditLog::forUser($user)->first()->is($userLog));
        $this->assertTrue(AuditLog::module('Equipment')->first()->is($userLog));
        $this->assertTrue(AuditLog::action('updated')->first()->is($userLog));
        $this->assertTrue(AuditLog::entity(Equipment::class, 10)->first()->is($userLog));
        $this->assertTrue(AuditLog::dateRange(now()->subHour(), now()->addHour())->first()->is($userLog));
        $this->assertTrue(AuditLog::recent()->first()->is($userLog));
    }

    public function test_audit_log_service_records_actions_and_allows_null_user(): void
    {
        $service = app(AuditLogService::class);
        $equipment = $this->createEquipment();

        $service->logCreate($equipment, 'Equipment', null);
        $service->logUpdate($equipment, 'Equipment', null, ['condition' => 'Good'], ['condition' => 'Fair']);
        $service->logDelete($equipment, 'Equipment', null);
        $service->logExport('Reports', 'Equipment report exported.', null);
        $service->logApproval('Maintenance Request', 'Request approved.', null);
        $service->logSystem('System', 'Nightly task completed.');

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'module' => 'Equipment', 'user_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'module' => 'Equipment']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'module' => 'Equipment']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exported', 'module' => 'Reports']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approved', 'module' => 'Maintenance Request']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system', 'module' => 'System']);
    }

    public function test_audit_trail_authorization_and_pages(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $staff = $this->userWithRole('Staff');
        $technician = $this->userWithRole('Technician');
        $plainUser = User::factory()->create();
        $auditLog = AuditLog::create([
            'user_id' => $administrator->id,
            'action' => 'viewed',
            'module' => 'Reports',
            'description' => 'Report viewed.',
            'created_at' => now(),
        ]);

        $this->assertTrue($administrator->can('audit.view'));
        $this->assertTrue($administrator->can('audit.export'));
        $this->assertFalse($staff->can('audit.view'));
        $this->assertFalse($staff->can('audit.export'));
        $this->assertFalse($technician->can('audit.view'));

        $this->actingAs($administrator)
            ->get(AuditLogResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Audit Trail')
            ->assertSee('Reports');

        $this->actingAs($administrator)
            ->get(AuditLogResource::getUrl('view', ['record' => $auditLog]))
            ->assertOk()
            ->assertSee('Report viewed.');

        $this->actingAs($staff)->get(AuditLogResource::getUrl('index'))->assertForbidden();
        $this->actingAs($technician)->get(AuditLogResource::getUrl('index'))->assertForbidden();
        $this->actingAs($plainUser)->get(AuditLogResource::getUrl('index'))->assertForbidden();
    }

    public function test_audit_table_search_filters_newest_first_and_export_action(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $old = AuditLog::create([
            'user_id' => $administrator->id,
            'action' => 'created',
            'module' => 'Equipment',
            'description' => 'Older equipment audit.',
            'created_at' => now()->subDay(),
        ]);
        $new = AuditLog::create([
            'user_id' => $administrator->id,
            'action' => 'exported',
            'module' => 'Reports',
            'description' => 'Newest report audit.',
            'created_at' => now(),
        ]);

        $this->actingAs($administrator);

        Livewire::test(ListAuditLogs::class)
            ->assertCanSeeTableRecords([$new, $old], inOrder: true);

        Livewire::test(ListAuditLogs::class)
            ->searchTable('Newest report')
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$old]);

        Livewire::test(ListAuditLogs::class)
            ->filterTable('module', 'Equipment')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$new]);

        Livewire::test(ListAuditLogs::class)
            ->callAction('exportCsv');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'exported',
            'module' => 'Audit Trail',
            'description' => 'Audit trail CSV exported.',
        ]);
    }

    public function test_major_activity_integrations_create_audit_logs(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        $equipment = $this->createEquipment(['equipment_code' => 'EQ-AUD-INT']);

        $workOrder = WorkOrder::create([
            'equipment_id' => $equipment->id,
            'created_by' => $administrator->id,
            'title' => 'Audit work order',
            'problem_description' => 'Audit work order problem.',
            'priority' => 'Normal',
            'status' => 'Available',
            'available_at' => now(),
        ]);
        $workOrder->accept($administrator);

        $request = MaintenanceRequest::create([
            'equipment_id' => $equipment->id,
            'submitted_by' => $administrator->id,
            'problem_description' => 'Audit maintenance request.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ]);
        $request->approve($administrator, 'Approved for audit test.');

        WorkOrderEvidence::create([
            'work_order_id' => $workOrder->id,
            'equipment_id' => $equipment->id,
            'evidence_type' => 'Before maintenance',
            'image_path' => 'work-orders/evidence/audit.jpg',
            'uploaded_by' => $administrator->id,
            'uploaded_at' => now(),
        ]);

        $recommendation = MaintenanceRecommendation::create([
            'equipment_id' => $equipment->id,
            'rule_key' => 'defective_without_work_order',
            'title' => 'Audit recommendation',
            'explanation' => 'Audit recommendation explanation.',
            'risk_level' => 'High',
            'recommended_action' => 'Generate corrective work order.',
            'generated_at' => now(),
            'status' => 'Open',
            'suggested_action_type' => 'monitor_only',
        ]);
        $recommendation->approveAction($administrator);
        $recommendation->executeAction($administrator, 'Executed for audit test.');

        $this->actingAs($administrator)
            ->get(route('reports.csv', 'equipment-inventory'))
            ->assertOk();

        $this->actingAs($administrator)
            ->postJson(route('offline-sync.actions.store'), [
                'client_id' => 'audit-offline-1',
                'type' => 'equipment.status_update',
                'payload' => [
                    'equipment_id' => $equipment->id,
                    'condition' => 'Fair',
                ],
                'base_updated_at' => $equipment->fresh()->updated_at?->toJSON(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'module' => 'Equipment']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'status_changed', 'module' => 'Work Order']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'status_changed', 'module' => 'Maintenance Request']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'uploaded', 'module' => 'Evidence']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'action_executed', 'module' => 'AI Recommendation']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exported', 'module' => 'Reports']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'submitted', 'module' => 'PWA Offline Sync']);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($roleName, 'web'));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEquipment(array $attributes = []): Equipment
    {
        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-AUD-'.str()->random(6),
            'equipment_name' => 'Audit Equipment',
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
            'is_archived' => false,
        ], $attributes));
    }
}
