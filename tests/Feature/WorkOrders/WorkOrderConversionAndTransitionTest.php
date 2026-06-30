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
use InvalidArgumentException;
use Tests\TestCase;

class WorkOrderConversionAndTransitionTest extends TestCase
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

    public function test_approved_maintenance_request_can_create_work_order(): void
    {
        $request = $this->createMaintenanceRequest([
            'problem_description' => 'Approved problem.',
            'severity' => 'High',
            'status' => 'Approved',
        ]);

        $workOrder = $request->createWorkOrder($this->creator);

        $this->assertSame($request->id, $workOrder->maintenance_request_id);
        $this->assertSame($request->equipment_id, $workOrder->equipment_id);
        $this->assertSame('Approved problem.', $workOrder->problem_description);
        $this->assertSame('High', $workOrder->priority);
        $this->assertTrue($workOrder->isAvailable());
        $this->assertNotNull($workOrder->available_at);
        $this->assertTrue($request->fresh()->isConverted());
        $this->assertSame($this->creator->id, $request->fresh()->converted_by);
    }

    public function test_submitted_maintenance_request_cannot_create_work_order(): void
    {
        $request = $this->createMaintenanceRequest(['status' => 'Submitted']);

        $this->expectException(InvalidArgumentException::class);
        $request->createWorkOrder($this->creator);
    }

    public function test_converted_maintenance_request_cannot_create_duplicate_work_orders(): void
    {
        $request = $this->createMaintenanceRequest(['status' => 'Approved']);

        $request->createWorkOrder($this->creator);

        try {
            $request->fresh()->createWorkOrder($this->creator);
            $this->fail('Converted request should not create another work order.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, WorkOrder::where('maintenance_request_id', $request->id)->count());
        }
    }

    public function test_request_severity_maps_to_work_order_priority(): void
    {
        foreach (['Low' => 'Low', 'Moderate' => 'Normal', 'High' => 'High', 'Critical' => 'Critical'] as $severity => $priority) {
            $request = $this->createMaintenanceRequest([
                'severity' => $severity,
                'status' => 'Approved',
            ]);

            $this->assertSame($priority, $request->createWorkOrder($this->creator)->priority);
        }
    }

    public function test_assignment_availability_acceptance_and_start_transitions_work(): void
    {
        $assignee = User::factory()->create();
        $workOrder = $this->createWorkOrder(['status' => 'Submitted']);

        $available = $workOrder->makeAvailable();
        $this->assertTrue($available->isAvailable());
        $this->assertNotNull($available->available_at);

        $assigned = $available->assignTo($assignee);
        $this->assertTrue($assigned->isAssigned());
        $this->assertSame($assignee->id, $assigned->assigned_to);
        $this->assertNotNull($assigned->assigned_at);

        $accepted = $assigned->accept($assignee);
        $this->assertTrue($accepted->isAccepted());
        $this->assertSame($assignee->id, $accepted->accepted_by);
        $this->assertNotNull($accepted->accepted_at);

        $started = $accepted->start();
        $this->assertTrue($started->isInProgress());
        $this->assertNotNull($started->started_at);
    }

    public function test_hold_parts_and_verification_submission_require_foundation_fields(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);

        $this->expectInvalidArgument(fn () => $workOrder->putOnHold(''));
        $this->assertSame('On hold', $workOrder->putOnHold('Waiting for access.')->status);

        $this->expectInvalidArgument(fn () => $workOrder->awaitParts(''));
        $this->assertSame('Awaiting parts', $workOrder->awaitParts('Replacement fuse.')->status);

        $this->expectInvalidArgument(fn () => $workOrder->submitForVerification([
            'action_performed' => '',
            'final_equipment_condition' => 'Good',
            'final_operational_status' => 'Available',
        ]));

        $submitted = $workOrder->submitForVerification([
            'action_performed' => 'Replaced fuse.',
            'final_equipment_condition' => 'Good',
            'final_operational_status' => 'Available',
        ]);

        $this->assertTrue($submitted->isForVerification());
    }

    public function test_complete_sets_completed_status_and_updates_equipment_final_state(): void
    {
        $workOrder = $this->createWorkOrder([
            'status' => 'For verification',
            'final_equipment_condition' => 'Needs maintenance',
            'final_operational_status' => 'Under maintenance',
        ]);

        $completed = $workOrder->complete('Completed after verification.');

        $this->assertTrue($completed->isCompleted());
        $this->assertNotNull($completed->completed_at);
        $this->assertSame('Completed after verification.', $completed->completion_remarks);
        $this->assertSame('Needs maintenance', $this->equipment->fresh()->condition);
        $this->assertSame('Under maintenance', $this->equipment->fresh()->operational_status);
    }

    public function test_beyond_repair_requires_fields_and_updates_equipment_state(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);

        $this->expectInvalidArgument(fn () => $workOrder->markBeyondRepair([
            'findings' => 'Failed inspection.',
            'beyond_repair_reason' => '',
            'recommended_action' => 'Replace unit.',
        ]));

        $beyondRepair = $workOrder->markBeyondRepair([
            'findings' => 'Failed inspection.',
            'beyond_repair_reason' => 'Repair costs exceed replacement.',
            'recommended_action' => 'Replace unit.',
        ]);

        $this->assertTrue($beyondRepair->isBeyondRepair());
        $this->assertSame('Beyond repair', $this->equipment->fresh()->condition);
        $this->assertSame('Unavailable', $this->equipment->fresh()->operational_status);
    }

    public function test_verify_reopen_and_cancel_transitions_work(): void
    {
        $verifier = User::factory()->create();
        $workOrder = $this->createWorkOrder(['status' => 'For verification']);

        $verified = $workOrder->verify($verifier);
        $this->assertTrue($verified->isCompleted());
        $this->assertSame($verifier->id, $verified->verified_by);
        $this->assertNotNull($verified->verified_at);

        $this->expectInvalidArgument(fn () => $verified->reopen(''));

        $reopened = $verified->reopen('Issue returned.');
        $this->assertSame('Reopened', $reopened->status);
        $this->assertNotNull($reopened->reopened_at);

        $this->expectInvalidArgument(fn () => $reopened->cancel(''));

        $cancelled = $reopened->cancel('Duplicate request.');
        $this->assertTrue($cancelled->isCancelled());
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Duplicate request.', $cancelled->rejection_or_cancellation_reason);
    }

    public function test_technician_cannot_verify_own_accepted_work_in_transition(): void
    {
        $technician = User::factory()->create();
        $workOrder = $this->createWorkOrder([
            'status' => 'Completed',
            'accepted_by' => $technician->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $workOrder->verify($technician);
    }

    private function expectInvalidArgument(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
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
