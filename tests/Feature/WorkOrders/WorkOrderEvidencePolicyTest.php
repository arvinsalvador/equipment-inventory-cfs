<?php

namespace Tests\Feature\WorkOrders;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkOrderEvidencePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->creator = $this->userWithRole('Administrator');
    }

    public function test_administrator_can_view_and_upload_evidence(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $evidence = $this->createEvidence($this->createWorkOrder());

        $this->assertTrue($administrator->can('view', $evidence));
        $this->assertTrue($administrator->can('upload', $evidence));
    }

    public function test_staff_can_upload_evidence_to_assigned_or_accepted_work_order(): void
    {
        $staff = $this->userWithRole('Staff');

        $assignedEvidence = $this->createEvidence($this->createWorkOrder(['assigned_to' => $staff->id]));
        $acceptedEvidence = $this->createEvidence($this->createWorkOrder(['accepted_by' => $staff->id]));

        $this->assertTrue($staff->can('upload', $assignedEvidence));
        $this->assertTrue($staff->can('upload', $acceptedEvidence));
    }

    public function test_staff_cannot_upload_evidence_to_unrelated_completed_or_cancelled_work_order(): void
    {
        $staff = $this->userWithRole('Staff');

        $unrelated = $this->createEvidence($this->createWorkOrder());
        $completed = $this->createEvidence($this->createWorkOrder(['assigned_to' => $staff->id, 'status' => 'Completed']));
        $cancelled = $this->createEvidence($this->createWorkOrder(['assigned_to' => $staff->id, 'status' => 'Cancelled']));

        $this->assertFalse($staff->can('upload', $unrelated));
        $this->assertFalse($staff->can('upload', $completed));
        $this->assertFalse($staff->can('upload', $cancelled));
    }

    public function test_technician_can_upload_evidence_to_assigned_or_accepted_work_order(): void
    {
        $technician = $this->userWithRole('Technician');

        $assignedEvidence = $this->createEvidence($this->createWorkOrder(['assigned_to' => $technician->id]));
        $acceptedEvidence = $this->createEvidence($this->createWorkOrder(['accepted_by' => $technician->id]));

        $this->assertTrue($technician->can('upload', $assignedEvidence));
        $this->assertTrue($technician->can('upload', $acceptedEvidence));
    }

    public function test_technician_cannot_upload_evidence_to_unrelated_completed_or_cancelled_work_order(): void
    {
        $technician = $this->userWithRole('Technician');

        $unrelated = $this->createEvidence($this->createWorkOrder());
        $completed = $this->createEvidence($this->createWorkOrder(['assigned_to' => $technician->id, 'status' => 'Completed']));
        $cancelled = $this->createEvidence($this->createWorkOrder(['assigned_to' => $technician->id, 'status' => 'Cancelled']));

        $this->assertFalse($technician->can('upload', $unrelated));
        $this->assertFalse($technician->can('upload', $completed));
        $this->assertFalse($technician->can('upload', $cancelled));
    }

    public function test_unauthorized_user_cannot_upload_evidence(): void
    {
        $user = User::factory()->create();
        $evidence = $this->createEvidence($this->createWorkOrder());

        $this->assertFalse($user->can('upload', $evidence));
    }

    private function createEvidence(WorkOrder $workOrder): WorkOrderEvidence
    {
        return WorkOrderEvidence::create([
            'work_order_id' => $workOrder->id,
            'equipment_id' => $workOrder->equipment_id,
            'evidence_type' => 'Before maintenance',
            'image_path' => 'work-orders/evidence/test.jpg',
            'uploaded_by' => $this->creator->id,
            'uploaded_at' => now(),
        ]);
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Evidence policy work order',
            'problem_description' => 'Evidence policy problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-EV-'.uniqid(),
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
