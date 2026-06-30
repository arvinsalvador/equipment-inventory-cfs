<?php

namespace Tests\Feature\WorkOrders;

use App\Filament\Resources\WorkOrders\Pages\ListWorkOrders;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkOrderResourceTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $creator;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->creator = $this->userWithRole('Administrator');
        $this->workOrder = $this->createWorkOrder();
    }

    public function test_administrator_can_access_work_order_pages(): void
    {
        $this->actingAs($this->creator);

        $this->get('/admin/work-orders')->assertSuccessful();
        $this->get("/admin/work-orders/{$this->workOrder->id}")->assertSuccessful();
        $this->get("/admin/work-orders/{$this->workOrder->id}/edit")->assertSuccessful();
    }

    public function test_work_order_manual_create_page_is_unavailable(): void
    {
        $this->actingAs($this->creator);

        $this->get('/admin/work-orders/create')->assertNotFound();
    }

    public function test_administrator_can_assign_work_order(): void
    {
        $technician = $this->userWithRole('Technician');
        $this->actingAs($this->creator);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('assign', $this->workOrder, data: [
                'assigned_to' => $technician->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->workOrder->refresh();

        $this->assertSame('Assigned', $this->workOrder->status);
        $this->assertSame($technician->id, $this->workOrder->assigned_to);
        $this->assertNotNull($this->workOrder->assigned_at);
    }

    public function test_staff_and_technician_can_accept_available_work_order(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $user = $this->userWithRole($role);
            $workOrder = $this->createWorkOrder(['status' => 'Available']);

            $this->actingAs($user);

            Livewire::test(ListWorkOrders::class)
                ->callTableAction('accept', $workOrder)
                ->assertHasNoTableActionErrors();

            $this->assertSame('Accepted', $workOrder->fresh()->status);
            $this->assertSame($user->id, $workOrder->fresh()->accepted_by);
            $this->assertNotNull($workOrder->fresh()->accepted_at);
        }
    }

    public function test_assigned_user_can_start_work_order(): void
    {
        $technician = $this->userWithRole('Technician');
        $workOrder = $this->createWorkOrder([
            'status' => 'Assigned',
            'assigned_to' => $technician->id,
        ]);

        $this->actingAs($technician);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('start', $workOrder)
            ->assertHasNoTableActionErrors();

        $this->assertSame('In progress', $workOrder->fresh()->status);
        $this->assertNotNull($workOrder->fresh()->started_at);
    }

    public function test_assigned_user_can_submit_for_verification(): void
    {
        $technician = $this->userWithRole('Technician');
        $workOrder = $this->createWorkOrder([
            'status' => 'In progress',
            'assigned_to' => $technician->id,
        ]);

        $this->actingAs($technician);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('submitForVerification', $workOrder, data: [
                'action_performed' => 'Cleaned and tested.',
                'final_equipment_condition' => 'Good',
                'final_operational_status' => 'Available',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('For verification', $workOrder->fresh()->status);
        $this->assertSame('Cleaned and tested.', $workOrder->fresh()->action_performed);
    }

    public function test_administrator_can_verify_work_order(): void
    {
        $workOrder = $this->createWorkOrder([
            'status' => 'For verification',
            'action_performed' => 'Cleaned and tested.',
            'completion_remarks' => 'Ready to close.',
            'final_equipment_condition' => 'Good',
            'final_operational_status' => 'Available',
        ]);
        $this->createEvidence($workOrder, 'After maintenance');

        $this->actingAs($this->creator);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('verify', $workOrder)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Completed', $workOrder->fresh()->status);
        $this->assertSame($this->creator->id, $workOrder->fresh()->verified_by);
        $this->assertNotNull($workOrder->fresh()->verified_at);
    }

    public function test_technician_cannot_verify_own_work(): void
    {
        $technician = $this->userWithRole('Technician');
        $technician->givePermissionTo('work-orders.verify');
        $workOrder = $this->createWorkOrder([
            'status' => 'Completed',
            'accepted_by' => $technician->id,
        ]);

        $this->actingAs($technician);

        Livewire::test(ListWorkOrders::class)
            ->assertTableActionHidden('verify', $workOrder);
    }

    public function test_administrator_can_mark_work_order_beyond_repair(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-1.jpg');
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-2.jpg');

        $this->actingAs($this->creator);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('beyondRepair', $workOrder, data: [
                'findings' => 'Main board failed.',
                'beyond_repair_reason' => 'Parts unavailable.',
                'recommended_action' => 'Replace equipment.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Beyond repair', $workOrder->fresh()->status);
        $this->assertSame('Beyond repair', $this->equipment->fresh()->condition);
        $this->assertSame('Unavailable', $this->equipment->fresh()->operational_status);
    }

    public function test_technician_can_recommend_beyond_repair_when_assigned(): void
    {
        $technician = $this->userWithRole('Technician');
        $workOrder = $this->createWorkOrder([
            'status' => 'In progress',
            'assigned_to' => $technician->id,
        ]);
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/tech-beyond-1.jpg');
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/tech-beyond-2.jpg');

        $this->actingAs($technician);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('beyondRepair', $workOrder, data: [
                'findings' => 'Compressor is seized.',
                'beyond_repair_reason' => 'Repair exceeds equipment value.',
                'recommended_action' => 'Procure replacement.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Beyond repair', $workOrder->fresh()->status);
    }

    public function test_work_order_view_shows_evidence_validation_status(): void
    {
        $workOrder = $this->createWorkOrder([
            'action_performed' => 'Cleaned and tested.',
            'completion_remarks' => 'Ready to close.',
            'final_equipment_condition' => 'Good',
            'final_operational_status' => 'Available',
        ]);
        $this->createEvidence($workOrder, 'After maintenance');

        $this->actingAs($this->creator);

        $this->get("/admin/work-orders/{$workOrder->id}")
            ->assertSuccessful()
            ->assertSee('After-maintenance evidence count')
            ->assertSee('Completion requirements status')
            ->assertSee('Beyond-repair requirements status');
    }

    public function test_complete_action_shows_validation_errors_when_evidence_is_missing(): void
    {
        $technician = $this->userWithRole('Technician');
        $workOrder = $this->createWorkOrder([
            'status' => 'In progress',
            'assigned_to' => $technician->id,
        ]);

        $this->actingAs($technician);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('complete', $workOrder, data: [
                'action_performed' => 'Cleaned and tested.',
                'completion_remarks' => 'Ready to close.',
                'final_equipment_condition' => 'Good',
                'final_operational_status' => 'Available',
            ])
            ->assertHasTableActionErrors(['completion_remarks']);

        $this->assertSame('In progress', $workOrder->fresh()->status);
    }

    public function test_beyond_repair_action_shows_validation_errors_when_evidence_is_missing(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);

        $this->actingAs($this->creator);

        Livewire::test(ListWorkOrders::class)
            ->callTableAction('beyondRepair', $workOrder, data: [
                'findings' => 'Main board failed.',
                'beyond_repair_reason' => 'Parts unavailable.',
                'recommended_action' => 'Replace equipment.',
            ])
            ->assertHasTableActionErrors(['findings']);

        $this->assertSame('In progress', $workOrder->fresh()->status);
    }

    public function test_staff_and_technician_cannot_assign_or_verify_work_orders(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $user = $this->userWithRole($role);
            $workOrder = $this->createWorkOrder(['status' => 'For verification']);

            $this->actingAs($user);

            Livewire::test(ListWorkOrders::class)
                ->assertTableActionHidden('assign', $workOrder)
                ->assertTableActionHidden('verify', $workOrder);
        }
    }

    public function test_unauthorized_user_cannot_access_work_order_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/work-orders')->assertForbidden();
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Resource work order',
            'problem_description' => 'Resource work order problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createEvidence(WorkOrder $workOrder, string $type, string $path = 'work-orders/evidence/test.jpg'): WorkOrderEvidence
    {
        return WorkOrderEvidence::create([
            'work_order_id' => $workOrder->id,
            'equipment_id' => $workOrder->equipment_id,
            'evidence_type' => $type,
            'image_path' => $path,
            'uploaded_by' => $this->creator->id,
            'uploaded_at' => now(),
        ]);
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-WO-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
