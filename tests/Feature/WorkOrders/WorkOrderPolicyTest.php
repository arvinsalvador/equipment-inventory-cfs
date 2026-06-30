<?php

namespace Tests\Feature\WorkOrders;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->creator = User::factory()->create();
    }

    public function test_administrator_can_manage_work_orders_based_on_permissions(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $workOrder = $this->createWorkOrder(['status' => 'Available']);

        $this->assertTrue($administrator->can('viewAny', WorkOrder::class));
        $this->assertTrue($administrator->can('view', $workOrder));
        $this->assertTrue($administrator->can('create', WorkOrder::class));
        $this->assertTrue($administrator->can('assign', $workOrder));
        $this->assertTrue($administrator->can('update', $workOrder));
        $this->assertTrue($administrator->can('verify', $workOrder));
        $this->assertTrue($administrator->can('approveBeyondRepair', $workOrder));
    }

    public function test_staff_can_view_accept_available_and_update_assigned_work_orders(): void
    {
        $staff = $this->userWithRole('Staff');
        $available = $this->createWorkOrder(['status' => 'Available']);
        $assigned = $this->createWorkOrder([
            'status' => 'Assigned',
            'assigned_to' => $staff->id,
        ]);

        $this->assertTrue($staff->can('view', $available));
        $this->assertTrue($staff->can('accept', $available));
        $this->assertTrue($staff->can('updateAssigned', $assigned));
        $this->assertTrue($staff->can('update', $assigned));
        $this->assertFalse($staff->can('assign', $available));
        $this->assertFalse($staff->can('verify', $assigned));
    }

    public function test_technician_can_view_accept_update_assigned_and_recommend_beyond_repair(): void
    {
        $technician = $this->userWithRole('Technician');
        $available = $this->createWorkOrder(['status' => 'Available']);
        $assigned = $this->createWorkOrder([
            'status' => 'Assigned',
            'assigned_to' => $technician->id,
        ]);

        $this->assertTrue($technician->can('view', $available));
        $this->assertTrue($technician->can('accept', $available));
        $this->assertTrue($technician->can('updateAssigned', $assigned));
        $this->assertTrue($technician->can('recommendBeyondRepair', $assigned));
        $this->assertFalse($technician->can('assign', $available));
        $this->assertFalse($technician->can('verify', $assigned));
    }

    public function test_staff_and_technician_cannot_assign_or_verify_work_orders(): void
    {
        $staff = $this->userWithRole('Staff');
        $technician = $this->userWithRole('Technician');
        $workOrder = $this->createWorkOrder();

        $this->assertFalse($staff->can('assign', $workOrder));
        $this->assertFalse($technician->can('assign', $workOrder));
        $this->assertFalse($staff->can('verify', $workOrder));
        $this->assertFalse($technician->can('verify', $workOrder));
    }

    public function test_technician_cannot_verify_own_work_even_with_verify_permission(): void
    {
        $technician = $this->userWithRole('Technician');
        $technician->givePermissionTo('work-orders.verify');
        $workOrder = $this->createWorkOrder([
            'status' => 'Completed',
            'accepted_by' => $technician->id,
        ]);

        $this->assertFalse($technician->can('verify', $workOrder));
    }

    public function test_unauthorized_user_cannot_view_work_orders(): void
    {
        $user = User::factory()->create();
        $workOrder = $this->createWorkOrder();

        $this->assertFalse($user->can('viewAny', WorkOrder::class));
        $this->assertFalse($user->can('view', $workOrder));
    }

    public function test_permanent_delete_is_denied(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $workOrder = $this->createWorkOrder();

        $this->assertFalse($administrator->can('delete', $workOrder));
        $this->assertFalse($administrator->can('forceDelete', $workOrder));
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Policy work order',
            'problem_description' => 'Policy problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createEquipment(): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create([
            'equipment_code' => 'EQ-WO-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
