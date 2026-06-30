<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use App\Services\MaintenanceRecommendationEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MaintenanceRecommendationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->engine = app(MaintenanceRecommendationEngine::class);
    }

    public function test_overdue_maintenance_generates_recommendation(): void
    {
        $equipment = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'overdue_maintenance');
    }

    public function test_due_soon_generates_recommendation_without_duplication_from_overdue(): void
    {
        $dueSoon = $this->createEquipment(['next_maintenance_date' => today()->addDays(3)]);
        $overdue = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);

        $this->engine->generateForEquipment($dueSoon);
        $this->engine->generateForEquipment($overdue);

        $this->assertRecommendationExists($dueSoon, 'due_soon');
        $this->assertRecommendationMissing($overdue, 'due_soon');
        $this->assertRecommendationExists($overdue, 'overdue_maintenance');
    }

    public function test_defective_equipment_without_active_work_order_generates_recommendation(): void
    {
        $equipment = $this->createEquipment(['condition' => 'Defective']);

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'defective_without_work_order');
    }

    public function test_defective_equipment_with_active_work_order_does_not_generate_defective_recommendation(): void
    {
        $equipment = $this->createEquipment(['condition' => 'Defective']);
        $this->createWorkOrder($equipment, ['status' => 'In progress']);

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationMissing($equipment, 'defective_without_work_order');
    }

    public function test_repeated_repairs_generates_recommendation(): void
    {
        $equipment = $this->createEquipment();

        for ($i = 0; $i < 3; $i++) {
            $this->createWorkOrder($equipment, [
                'status' => 'Completed',
                'completed_at' => now()->subDays($i + 1),
            ]);
        }

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'repeated_repairs');
    }

    public function test_no_maintenance_history_generates_recommendation(): void
    {
        $equipment = $this->createEquipment();

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'no_maintenance_history');
    }

    public function test_expiring_warranty_generates_recommendation(): void
    {
        $equipment = $this->createEquipment(['warranty_expiration_date' => today()->addDays(20)]);

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'expiring_warranty');
    }

    public function test_beyond_repair_without_enough_evidence_generates_recommendation(): void
    {
        $equipment = $this->createEquipment(['condition' => 'Beyond repair']);
        $this->createWorkOrder($equipment, ['status' => 'Beyond repair']);

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'beyond_repair_evidence_incomplete');
    }

    public function test_completed_work_without_after_maintenance_evidence_generates_recommendation(): void
    {
        $equipment = $this->createEquipment();
        $this->createWorkOrder($equipment, ['status' => 'Completed']);

        $this->engine->generateForEquipment($equipment);

        $this->assertRecommendationExists($equipment, 'completed_without_after_evidence');
    }

    public function test_existing_open_recommendation_is_updated_instead_of_duplicated(): void
    {
        $equipment = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);
        $recommendation = MaintenanceRecommendation::create([
            'equipment_id' => $equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Old title',
            'explanation' => 'Old explanation.',
            'risk_level' => 'Low',
            'recommended_action' => 'Old action.',
            'generated_at' => now()->subDays(5),
            'status' => 'Open',
        ]);

        $result = $this->engine->generateForEquipment($equipment);

        $this->assertSame(1, $result['updated']);
        $this->assertGreaterThanOrEqual(0, $result['created']);
        $this->assertSame(1, MaintenanceRecommendation::where('equipment_id', $equipment->id)->where('rule_key', 'overdue_maintenance')->count());
        $this->assertSame('Preventive maintenance is overdue', $recommendation->fresh()->title);
    }

    public function test_generate_for_all_equipment_checks_multiple_equipment_records(): void
    {
        $this->createEquipment(['next_maintenance_date' => today()->subDay()]);
        $this->createEquipment(['next_maintenance_date' => today()->addDays(2)]);

        $result = $this->engine->generateForAllEquipment();

        $this->assertSame(2, $result['equipment_checked']);
        $this->assertGreaterThanOrEqual(2, $result['created']);
    }

    private function assertRecommendationExists(Equipment $equipment, string $ruleKey): void
    {
        $this->assertDatabaseHas('maintenance_recommendations', [
            'equipment_id' => $equipment->id,
            'rule_key' => $ruleKey,
            'status' => 'Open',
        ]);
    }

    private function assertRecommendationMissing(Equipment $equipment, string $ruleKey): void
    {
        $this->assertDatabaseMissing('maintenance_recommendations', [
            'equipment_id' => $equipment->id,
            'rule_key' => $ruleKey,
        ]);
    }

    private function createWorkOrder(Equipment $equipment, array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $equipment->id,
            'created_by' => $this->user->id,
            'title' => 'Recommendation work order',
            'problem_description' => 'Recommendation work order problem.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createEvidence(WorkOrder $workOrder, string $type): WorkOrderEvidence
    {
        return WorkOrderEvidence::create([
            'work_order_id' => $workOrder->id,
            'equipment_id' => $workOrder->equipment_id,
            'evidence_type' => $type,
            'image_path' => 'work-orders/evidence/test.jpg',
            'uploaded_by' => $this->user->id,
            'uploaded_at' => now(),
        ]);
    }

    private function createCompletedSchedule(Equipment $equipment): MaintenanceSchedule
    {
        return MaintenanceSchedule::create([
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Preventive maintenance',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => today()->subMonth(),
            'priority' => 'Normal',
            'status' => 'Completed',
            'completed_at' => now()->subMonth(),
            'completed_by' => $this->user->id,
        ]);
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-ENG-'.uniqid(),
            'equipment_name' => 'Engine Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }
}
