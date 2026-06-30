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
use Tests\TestCase;

class WorkOrderEvidenceModelTest extends TestCase
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
        $this->creator = User::factory()->create();
        $this->workOrder = $this->createWorkOrder();
    }

    public function test_evidence_can_be_created_with_required_fields(): void
    {
        $evidence = $this->createEvidence();

        $this->assertDatabaseHas('work_order_evidences', [
            'id' => $evidence->id,
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'evidence_type' => 'Before maintenance',
            'image_path' => 'work-orders/evidence/test.jpg',
            'uploaded_by' => $this->creator->id,
        ]);
    }

    public function test_evidence_relationships_are_available(): void
    {
        $evidence = $this->createEvidence([
            'evidence_type' => 'After maintenance',
        ]);

        $this->assertTrue($evidence->workOrder->is($this->workOrder));
        $this->assertTrue($evidence->equipment->is($this->equipment));
        $this->assertTrue($evidence->uploadedBy->is($this->creator));
        $this->assertTrue($this->workOrder->evidences->contains($evidence));
        $this->assertTrue($this->workOrder->afterMaintenanceEvidences->contains($evidence));
        $this->assertTrue($this->equipment->workOrderEvidences->contains($evidence));
    }

    public function test_beyond_repair_evidence_relationship_is_available(): void
    {
        $evidence = $this->createEvidence([
            'evidence_type' => 'Beyond-repair evidence',
        ]);

        $this->assertTrue($this->workOrder->beyondRepairEvidences->contains($evidence));
        $this->assertTrue(WorkOrderEvidence::byType('Beyond-repair evidence')->pluck('id')->contains($evidence->id));
    }

    public function test_evidence_type_supports_allowed_values(): void
    {
        $this->assertSame([
            'Before maintenance',
            'During maintenance',
            'After maintenance',
            'Damage evidence',
            'Beyond-repair evidence',
            'Parts evidence',
            'Inspection evidence',
            'Other evidence',
        ], WorkOrderEvidence::EVIDENCE_TYPES);
    }

    private function createEvidence(array $overrides = []): WorkOrderEvidence
    {
        return WorkOrderEvidence::create(array_merge([
            'work_order_id' => $this->workOrder->id,
            'equipment_id' => $this->equipment->id,
            'evidence_type' => 'Before maintenance',
            'image_path' => 'work-orders/evidence/test.jpg',
            'caption' => 'Initial condition.',
            'uploaded_by' => $this->creator->id,
            'uploaded_at' => now(),
        ], $overrides));
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->creator->id,
            'title' => 'Evidence work order',
            'problem_description' => 'Evidence work order problem.',
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
}
