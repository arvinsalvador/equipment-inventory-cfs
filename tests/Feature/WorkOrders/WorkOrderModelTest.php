<?php

namespace Tests\Feature\WorkOrders;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderModelTest extends TestCase
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

    public function test_work_order_can_be_created_with_required_fields(): void
    {
        $workOrder = $this->createWorkOrder();

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Test work order',
            'priority' => 'Normal',
            'status' => 'Available',
        ]);
    }

    public function test_work_order_relationships_are_available(): void
    {
        $request = $this->createMaintenanceRequest(['status' => 'Approved']);
        $workOrder = $this->createWorkOrder(['maintenance_request_id' => $request->id]);

        $this->assertTrue($workOrder->equipment->is($this->equipment));
        $this->assertTrue($workOrder->maintenanceRequest->is($request));
        $this->assertTrue($workOrder->createdBy->is($this->creator));
        $this->assertTrue($this->equipment->workOrders->contains($workOrder));
        $this->assertTrue($this->equipment->openWorkOrders->contains($workOrder));
        $this->assertTrue($this->equipment->activeWorkOrders->contains($workOrder));
        $this->assertTrue($request->workOrders->contains($workOrder));
        $this->assertTrue($request->latestWorkOrder->is($workOrder));
    }

    public function test_work_order_number_is_generated_automatically_and_is_unique(): void
    {
        $first = $this->createWorkOrder();
        $second = $this->createWorkOrder(['title' => 'Second work order']);

        $this->assertMatchesRegularExpression('/^WO-'.now()->format('Ymd').'-\\d{4}$/', $first->work_order_number);
        $this->assertNotSame($first->work_order_number, $second->work_order_number);
    }

    public function test_allowed_priorities_and_statuses_are_available(): void
    {
        $this->assertSame(['Low', 'Normal', 'High', 'Critical'], WorkOrder::PRIORITIES);
        $this->assertSame([
            'Submitted',
            'For review',
            'Approved',
            'Available',
            'Assigned',
            'Accepted',
            'In progress',
            'On hold',
            'Awaiting parts',
            'For verification',
            'Completed',
            'Beyond repair',
            'Reopened',
            'Rejected',
            'Cancelled',
        ], WorkOrder::STATUSES);
    }

    public function test_status_scopes_work(): void
    {
        $available = $this->createWorkOrder(['status' => 'Available']);
        $assigned = $this->createWorkOrder(['status' => 'Assigned']);
        $inProgress = $this->createWorkOrder(['status' => 'In progress']);
        $forVerification = $this->createWorkOrder(['status' => 'For verification']);
        $completed = $this->createWorkOrder(['status' => 'Completed']);
        $beyondRepair = $this->createWorkOrder(['status' => 'Beyond repair']);
        $rejected = $this->createWorkOrder(['status' => 'Rejected']);
        $cancelled = $this->createWorkOrder(['status' => 'Cancelled']);

        $this->assertTrue(WorkOrder::available()->pluck('id')->contains($available->id));
        $this->assertTrue(WorkOrder::assigned()->pluck('id')->contains($assigned->id));
        $this->assertTrue(WorkOrder::inProgress()->pluck('id')->contains($inProgress->id));
        $this->assertTrue(WorkOrder::forVerification()->pluck('id')->contains($forVerification->id));
        $this->assertTrue(WorkOrder::completed()->pluck('id')->contains($completed->id));
        $this->assertTrue(WorkOrder::beyondRepair()->pluck('id')->contains($beyondRepair->id));
        $this->assertTrue(WorkOrder::cancelled()->pluck('id')->contains($cancelled->id));

        $open = WorkOrder::open()->pluck('id');
        $closed = WorkOrder::closed()->pluck('id');

        $this->assertTrue($open->contains($available->id));
        $this->assertFalse($open->contains($completed->id));
        $this->assertFalse($open->contains($beyondRepair->id));
        $this->assertFalse($open->contains($rejected->id));
        $this->assertFalse($open->contains($cancelled->id));
        $this->assertTrue($closed->contains($completed->id));
        $this->assertTrue($closed->contains($beyondRepair->id));
        $this->assertTrue($closed->contains($rejected->id));
        $this->assertTrue($closed->contains($cancelled->id));
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Test work order',
            'problem_description' => 'Test work order problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createMaintenanceRequest(array $overrides = []): MaintenanceRequest
    {
        return MaintenanceRequest::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'submitted_by' => User::factory()->create()->id,
            'problem_description' => 'Request problem.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ], $overrides));
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
}
