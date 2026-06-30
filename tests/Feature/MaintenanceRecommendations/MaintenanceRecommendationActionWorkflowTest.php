<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MaintenanceRecommendationActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->administrator = User::factory()->create();
    }

    public function test_action_fields_defaults_mapping_and_relationships_work(): void
    {
        $recommendation = $this->createRecommendation(['rule_key' => 'defective_without_work_order']);

        $this->assertSame('Pending', $recommendation->action_status);
        $this->assertTrue($recommendation->isActionPending());
        $this->assertSame('generate_corrective_work_order', $recommendation->getSuggestedActionType());
        $this->assertSame('Generate Corrective Work Order', $recommendation->getSuggestedActionLabel());

        $recommendation->approveAction($this->administrator, 'Approved.');
        $workOrder = $recommendation->executeAction($this->administrator)->linkedWorkOrder;

        $this->assertTrue($recommendation->fresh()->actionedBy->is($this->administrator));
        $this->assertTrue($recommendation->fresh()->linkedWorkOrder->is($workOrder));
    }

    public function test_approve_reject_and_cancel_action_workflow_rules(): void
    {
        $approved = $this->createRecommendation()->approveAction($this->administrator, 'Approved.');
        $this->assertTrue($approved->isActionApproved());
        $this->assertSame($this->administrator->id, $approved->actioned_by);
        $this->assertNotNull($approved->actioned_at);

        $this->expectInvalidArgument(fn () => $this->createRecommendation()->rejectAction($this->administrator, ''));
        $rejected = $this->createRecommendation()->rejectAction($this->administrator, 'Not appropriate.');
        $this->assertTrue($rejected->isActionRejected());

        $this->expectInvalidArgument(fn () => $this->createRecommendation()->cancelAction($this->administrator, ''));
        $cancelled = $this->createRecommendation()->cancelAction($this->administrator, 'Duplicate.');
        $this->assertTrue($cancelled->isActionCancelled());
    }

    public function test_execute_requires_approved_and_blocks_invalid_states_and_duplicates(): void
    {
        $this->expectInvalidArgument(fn () => $this->createRecommendation()->executeAction($this->administrator));
        $this->expectInvalidArgument(fn () => $this->createRecommendation()->rejectAction($this->administrator, 'No.')->executeAction($this->administrator));
        $this->expectInvalidArgument(fn () => $this->createRecommendation()->cancelAction($this->administrator, 'No.')->executeAction($this->administrator));
        $this->expectInvalidArgument(fn () => $this->createRecommendation(['status' => 'Resolved'])->approveAction($this->administrator)->executeAction($this->administrator));
        $this->expectInvalidArgument(fn () => $this->createRecommendation(['status' => 'Dismissed'])->approveAction($this->administrator)->executeAction($this->administrator));

        $executed = $this->createRecommendation(['rule_key' => 'due_soon'])->approveAction($this->administrator)->executeAction($this->administrator);
        $this->assertTrue($executed->isActionExecuted());
        $this->expectInvalidArgument(fn () => $executed->executeAction($this->administrator));
    }

    public function test_schedule_actions_create_and_link_maintenance_schedules_with_priority_mapping(): void
    {
        $cases = [
            ['overdue_maintenance', 'Preventive Maintenance', 'Monthly', 'Critical', 'Critical'],
            ['due_soon', 'Preventive Maintenance', 'Monthly', 'High', 'High'],
            ['no_maintenance_history', 'Initial Inspection', 'As needed', 'Moderate', 'Normal'],
            ['expiring_warranty', 'Warranty Inspection', 'As needed', 'Low', 'Low'],
        ];

        foreach ($cases as [$rule, $type, $frequency, $risk, $priority]) {
            $recommendation = $this->createRecommendation(['rule_key' => $rule, 'risk_level' => $risk])
                ->approveAction($this->administrator)
                ->executeAction($this->administrator);

            $schedule = $recommendation->linkedMaintenanceSchedule;

            $this->assertInstanceOf(MaintenanceSchedule::class, $schedule);
            $this->assertSame($type, $schedule->maintenance_type);
            $this->assertSame($frequency, $schedule->maintenance_frequency);
            $this->assertSame($priority, $schedule->priority);
            $this->assertSame('Upcoming', $schedule->status);
        }
    }

    public function test_work_order_actions_create_and_link_work_orders(): void
    {
        $corrective = $this->createRecommendation(['rule_key' => 'defective_without_work_order'])
            ->approveAction($this->administrator)
            ->executeAction($this->administrator);
        $inspection = $this->createRecommendation(['rule_key' => 'repeated_repairs', 'risk_level' => 'High'])
            ->approveAction($this->administrator)
            ->executeAction($this->administrator);

        $this->assertInstanceOf(WorkOrder::class, $corrective->linkedWorkOrder);
        $this->assertSame('Available', $corrective->linkedWorkOrder->status);
        $this->assertSame($this->administrator->id, $corrective->linkedWorkOrder->created_by);

        $this->assertInstanceOf(WorkOrder::class, $inspection->linkedWorkOrder);
        $this->assertStringStartsWith('Inspection Required - ', $inspection->linkedWorkOrder->title);
        $this->assertSame('High', $inspection->linkedWorkOrder->priority);
    }

    public function test_monitor_only_executes_without_creating_records(): void
    {
        $recommendation = $this->createRecommendation([
            'rule_key' => 'unknown_rule',
            'suggested_action_type' => 'monitor_only',
        ])->approveAction($this->administrator)->executeAction($this->administrator);

        $this->assertTrue($recommendation->isActionExecuted());
        $this->assertNull($recommendation->linked_work_order_id);
        $this->assertNull($recommendation->linked_maintenance_schedule_id);
    }

    public function test_evidence_actions_do_not_fake_uploads_and_can_link_related_work_order(): void
    {
        $workOrder = WorkOrder::create([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->administrator->id,
            'title' => 'Evidence work order',
            'problem_description' => 'Needs evidence.',
            'priority' => 'High',
            'status' => 'In progress',
        ]);

        $recommendation = $this->createRecommendation([
            'rule_key' => 'beyond_repair_evidence_incomplete',
            'metadata' => ['work_order_ids' => [$workOrder->id]],
        ])->approveAction($this->administrator)->executeAction($this->administrator);

        $this->assertTrue($recommendation->isActionApproved());
        $this->assertFalse($recommendation->isActionExecuted());
        $this->assertSame($workOrder->id, $recommendation->linked_work_order_id);
        $this->assertDatabaseCount('work_order_evidences', 0);
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

    private function createRecommendation(array $overrides = []): MaintenanceRecommendation
    {
        return MaintenanceRecommendation::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Preventive maintenance is overdue',
            'explanation' => 'The equipment maintenance date has already passed.',
            'risk_level' => 'High',
            'recommended_action' => 'Schedule maintenance immediately.',
            'generated_at' => now(),
            'status' => 'Open',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-ACTION-'.uniqid(),
            'equipment_name' => 'Action Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }
}
