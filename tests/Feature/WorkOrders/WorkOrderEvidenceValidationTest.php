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
use InvalidArgumentException;
use Tests\TestCase;

class WorkOrderEvidenceValidationTest extends TestCase
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

    public function test_work_order_counts_required_evidence_types(): void
    {
        $workOrder = $this->createWorkOrder();
        $this->createEvidence($workOrder, 'After maintenance', 'work-orders/evidence/after.jpg');
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-1.jpg');
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-2.jpg');

        $this->assertSame(1, $workOrder->afterMaintenanceEvidenceCount());
        $this->assertSame(2, $workOrder->beyondRepairEvidenceCount());
        $this->assertTrue($workOrder->hasAfterMaintenanceEvidence());
    }

    public function test_completion_evidence_requirement_helper_uses_fields_and_after_maintenance_evidence(): void
    {
        $workOrder = $this->createCompletionReadyWorkOrder();

        $this->assertFalse($workOrder->hasRequiredCompletionEvidence());

        $this->createEvidence($workOrder, 'After maintenance');

        $this->assertTrue($workOrder->hasRequiredCompletionEvidence());
    }

    public function test_beyond_repair_requirement_helper_requires_two_evidence_records(): void
    {
        $workOrder = $this->createBeyondRepairReadyWorkOrder();

        $this->assertFalse($workOrder->hasRequiredBeyondRepairEvidence());

        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-1.jpg');
        $this->assertFalse($workOrder->hasRequiredBeyondRepairEvidence());

        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-2.jpg');
        $this->assertTrue($workOrder->hasRequiredBeyondRepairEvidence());
    }

    public function test_completion_validation_errors_include_all_missing_requirements(): void
    {
        $workOrder = $this->createWorkOrder();

        $this->assertSame([
            'Action performed is required.',
            'Final equipment condition is required.',
            'Final operational status is required.',
            'Completion remarks are required.',
            'At least one After maintenance evidence is required.',
        ], $workOrder->completionValidationErrors());
    }

    public function test_beyond_repair_validation_errors_include_all_missing_requirements(): void
    {
        $workOrder = $this->createWorkOrder();

        $this->assertSame([
            'Findings are required.',
            'Beyond-repair reason is required.',
            'Recommended action is required.',
            'At least two Beyond-repair evidence records are required.',
        ], $workOrder->beyondRepairValidationErrors());
    }

    public function test_complete_is_blocked_without_after_maintenance_evidence(): void
    {
        $workOrder = $this->createCompletionReadyWorkOrder(['status' => 'For verification']);

        $this->expectInvalidArgument(fn () => $workOrder->complete('Ready to close.'));

        $this->assertSame('For verification', $workOrder->fresh()->status);
        $this->assertNull($workOrder->fresh()->completed_at);
    }

    public function test_complete_is_blocked_without_completion_remarks(): void
    {
        $workOrder = $this->createCompletionReadyWorkOrder(['status' => 'For verification', 'completion_remarks' => null]);
        $this->createEvidence($workOrder, 'After maintenance');

        $this->expectInvalidArgument(fn () => $workOrder->complete(null));

        $this->assertSame('For verification', $workOrder->fresh()->status);
    }

    public function test_complete_succeeds_with_required_fields_and_after_maintenance_evidence(): void
    {
        $workOrder = $this->createCompletionReadyWorkOrder(['status' => 'In progress']);
        $this->createEvidence($workOrder, 'After maintenance');

        $completed = $workOrder->complete('Completed with evidence.');

        $this->assertTrue($completed->isCompleted());
        $this->assertSame('Completed with evidence.', $completed->completion_remarks);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_verify_is_blocked_if_completion_requirements_are_missing(): void
    {
        $verifier = User::factory()->create();
        $workOrder = $this->createCompletionReadyWorkOrder(['status' => 'For verification']);

        $this->expectInvalidArgument(fn () => $workOrder->verify($verifier));

        $this->assertSame('For verification', $workOrder->fresh()->status);
        $this->assertNull($workOrder->fresh()->verified_by);
    }

    public function test_verify_succeeds_when_completion_requirements_are_complete(): void
    {
        $verifier = User::factory()->create();
        $workOrder = $this->createCompletionReadyWorkOrder(['status' => 'For verification']);
        $this->createEvidence($workOrder, 'After maintenance');

        $verified = $workOrder->verify($verifier);

        $this->assertTrue($verified->isCompleted());
        $this->assertSame($verifier->id, $verified->verified_by);
        $this->assertNotNull($verified->verified_at);
    }

    public function test_beyond_repair_is_blocked_without_required_evidence_count(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);

        $this->expectInvalidArgument(fn () => $workOrder->markBeyondRepair([
            'findings' => 'Failed inspection.',
            'beyond_repair_reason' => 'Repair costs exceed replacement.',
            'recommended_action' => 'Replace unit.',
        ]));

        $this->assertSame('In progress', $workOrder->fresh()->status);
    }

    public function test_beyond_repair_is_blocked_with_only_one_evidence_record(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);
        $this->createEvidence($workOrder, 'Beyond-repair evidence');

        $this->expectInvalidArgument(fn () => $workOrder->markBeyondRepair([
            'findings' => 'Failed inspection.',
            'beyond_repair_reason' => 'Repair costs exceed replacement.',
            'recommended_action' => 'Replace unit.',
        ]));

        $this->assertSame('In progress', $workOrder->fresh()->status);
    }

    public function test_beyond_repair_succeeds_with_required_fields_and_two_evidences(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-1.jpg');
        $this->createEvidence($workOrder, 'Beyond-repair evidence', 'work-orders/evidence/beyond-2.jpg');

        $beyondRepair = $workOrder->markBeyondRepair([
            'findings' => 'Failed inspection.',
            'beyond_repair_reason' => 'Repair costs exceed replacement.',
            'recommended_action' => 'Replace unit.',
        ]);

        $this->assertTrue($beyondRepair->isBeyondRepair());
        $this->assertSame('Beyond repair', $this->equipment->fresh()->condition);
        $this->assertSame('Unavailable', $this->equipment->fresh()->operational_status);
    }

    public function test_on_hold_and_awaiting_parts_requirements_still_work(): void
    {
        $workOrder = $this->createWorkOrder(['status' => 'In progress']);

        $this->expectInvalidArgument(fn () => $workOrder->putOnHold(''));
        $this->assertSame('On hold', $workOrder->putOnHold('Waiting for access.')->status);

        $this->expectInvalidArgument(fn () => $workOrder->awaitParts(''));
        $this->assertSame('Awaiting parts', $workOrder->awaitParts('Replacement fuse.')->status);
    }

    private function createCompletionReadyWorkOrder(array $overrides = []): WorkOrder
    {
        return $this->createWorkOrder(array_merge([
            'status' => 'In progress',
            'action_performed' => 'Cleaned and tested.',
            'completion_remarks' => 'Ready to close.',
            'final_equipment_condition' => 'Good',
            'final_operational_status' => 'Available',
        ], $overrides));
    }

    private function createBeyondRepairReadyWorkOrder(array $overrides = []): WorkOrder
    {
        return $this->createWorkOrder(array_merge([
            'status' => 'In progress',
            'findings' => 'Failed inspection.',
            'beyond_repair_reason' => 'Repair costs exceed replacement.',
            'recommended_action' => 'Replace unit.',
        ], $overrides));
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

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Validation work order',
            'problem_description' => 'Validation work order problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-VAL-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }
}
