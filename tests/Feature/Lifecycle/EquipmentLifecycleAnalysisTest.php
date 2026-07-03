<?php

namespace Tests\Feature\Lifecycle;

use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\EquipmentLifecycleAnalyzer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentLifecycleAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private EquipmentCategory $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->category = EquipmentCategory::create(['name' => 'Computers']);
        $this->location = Location::create(['name' => 'Lifecycle Lab', 'type' => 'Room']);
    }

    public function test_lifecycle_profile_model_relationship_and_work_order_costs(): void
    {
        $equipment = $this->createEquipment();
        $profile = EquipmentLifecycleProfile::create([
            'equipment_id' => $equipment->id,
            'health_score' => 92,
            'health_grade' => 'Excellent',
            'lifecycle_status' => 'Active',
            'replacement_recommendation' => 'Continue Maintenance',
        ]);
        $workOrder = $this->createWorkOrder($equipment, [
            'labor_cost' => 100,
            'parts_cost' => 50.25,
            'external_service_cost' => 20,
        ]);

        $this->assertTrue($profile->equipment->is($equipment));
        $this->assertTrue($equipment->fresh()->lifecycleProfile->is($profile));
        $this->assertSame('170.25', $workOrder->fresh()->total_cost);
        $this->assertSame('170.25', $workOrder->calculateTotalCost());
        $this->assertSame(1, $equipment->repairCount());
        $this->assertSame(170.25, $equipment->maintenanceCostTotal());
    }

    public function test_health_score_lifecycle_status_and_recommendation_rules(): void
    {
        $analyzer = app(EquipmentLifecycleAnalyzer::class);
        $newEquipment = $this->createEquipment(['condition' => 'New', 'acquisition_date' => now()->subMonths(3)->toDateString()]);
        $defective = $this->createEquipment(['condition' => 'Defective']);
        $beyondRepair = $this->createEquipment(['condition' => 'Beyond repair', 'operational_status' => 'Unavailable']);
        $retired = $this->createEquipment(['operational_status' => 'Retired']);
        $aging = $this->createEquipment(['acquisition_date' => now()->subYears(4)->toDateString()]);

        $this->createOverdueSchedule($defective);
        $this->createRecommendation($defective, ['risk_level' => 'Critical']);
        foreach (range(1, 3) as $index) {
            $this->createWorkOrder($defective, ['completed_at' => now()->subDays($index), 'total_cost' => null]);
        }

        $this->assertGreaterThanOrEqual(90, $analyzer->calculateHealthScore($newEquipment));
        $this->assertLessThan(60, $analyzer->calculateHealthScore($defective));
        $this->assertSame(0, $analyzer->calculateHealthScore($beyondRepair));
        $this->assertSame('New', $analyzer->determineLifecycleStatus($newEquipment, $analyzer->calculateHealthScore($newEquipment)));
        $this->assertSame('High Maintenance', $analyzer->determineLifecycleStatus($defective, 50));
        $this->assertSame('Replacement Candidate', $analyzer->determineLifecycleStatus($defective, 35));
        $this->assertSame('Retired', $analyzer->determineLifecycleStatus($retired, 20));
        $this->assertSame('Aging', $analyzer->determineLifecycleStatus($aging, 80));
        $this->assertSame('Dispose Equipment', $analyzer->determineReplacementRecommendation($beyondRepair, 10)['recommendation']);
        $this->assertSame('Repair', $analyzer->determineReplacementRecommendation($defective, 55)['recommendation']);
        $this->assertSame('Continue Monitoring', $analyzer->determineReplacementRecommendation($this->createEquipment(['condition' => 'Fair']), 65)['recommendation']);
        $this->assertSame('Continue Maintenance', $analyzer->determineReplacementRecommendation($newEquipment, 90)['recommendation']);
    }

    public function test_useful_life_estimation_and_command_create_profiles(): void
    {
        $equipment = $this->createEquipment(['acquisition_date' => now()->subYears(2)->toDateString()]);
        $missingDate = $this->createEquipment(['acquisition_date' => null]);
        $analyzer = app(EquipmentLifecycleAnalyzer::class);

        $life = $analyzer->estimateRemainingLife($equipment);
        $missingLife = $analyzer->estimateRemainingLife($missingDate);

        $this->assertSame(5, $life['expected_useful_life_years']);
        $this->assertNotNull($life['estimated_end_of_life_date']);
        $this->assertNull($missingLife['estimated_end_of_life_date']);
        $this->assertNull($missingLife['estimated_remaining_life_months']);

        $this->artisan('equipment:analyze-lifecycle')
            ->assertSuccessful()
            ->expectsOutputToContain('Equipment analyzed:');

        $this->assertSame(2, EquipmentLifecycleProfile::count());
    }

    public function test_equipment_lifecycle_view_and_recalculate_action(): void
    {
        $equipment = $this->createEquipment(['equipment_name' => 'Lifecycle View Equipment']);
        app(EquipmentLifecycleAnalyzer::class)->analyze($equipment);

        $this->actingAs($this->administrator);

        Livewire::test(ViewEquipment::class, ['record' => $equipment->getRouteKey()])
            ->assertSee('Lifecycle analysis')
            ->assertSee('Health score')
            ->assertSee('Replacement recommendation');

        Livewire::test(ListEquipment::class)
            ->callTableAction('recalculateLifecycle', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($equipment->fresh()->lifecycleProfile?->last_calculated_at);

        $this->actingAs($this->userWithRole('Technician'));

        Livewire::test(ListEquipment::class)
            ->assertTableActionHidden('recalculateLifecycle', $equipment);
    }

    public function test_lifecycle_report_filters_csv_and_print_render(): void
    {
        $equipment = $this->createEquipment(['equipment_name' => 'Lifecycle Report Equipment']);
        app(EquipmentLifecycleAnalyzer::class)->analyze($equipment);
        $profile = $equipment->fresh()->lifecycleProfile;

        $this->actingAs($this->administrator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Equipment Lifecycle Report');

        $this->actingAs($this->administrator)
            ->get(route('reports.show', ['report' => 'equipment-lifecycle', 'lifecycle_status' => $profile->lifecycle_status]))
            ->assertOk()
            ->assertSee('Lifecycle Report Equipment')
            ->assertSee($profile->lifecycle_status);

        $csv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', ['report' => 'equipment-lifecycle', 'health_grade' => $profile->health_grade]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Equipment code', $csv);
        $this->assertStringContainsString('Lifecycle Report Equipment', $csv);

        $this->actingAs($this->administrator)
            ->get(route('reports.print', 'equipment-lifecycle'))
            ->assertOk()
            ->assertSee('Equipment Lifecycle Report');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-LC-'.uniqid(),
            'equipment_name' => 'Lifecycle Equipment',
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
            'acquisition_date' => now()->subYears(1)->toDateString(),
        ], $overrides));
    }

    private function createWorkOrder(Equipment $equipment, array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $equipment->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->administrator->id,
            'title' => 'Lifecycle repair',
            'problem_description' => 'Lifecycle repair work order',
            'priority' => 'Normal',
            'status' => 'Completed',
            'completed_at' => now(),
        ], $overrides));
    }

    private function createOverdueSchedule(Equipment $equipment): MaintenanceSchedule
    {
        return MaintenanceSchedule::create([
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Lifecycle preventive maintenance',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => now()->subDay()->toDateString(),
            'assigned_user_id' => $this->administrator->id,
            'priority' => 'High',
            'status' => 'Upcoming',
        ]);
    }

    private function createRecommendation(Equipment $equipment, array $overrides = []): MaintenanceRecommendation
    {
        return MaintenanceRecommendation::create(array_merge([
            'equipment_id' => $equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Lifecycle recommendation',
            'explanation' => 'Lifecycle recommendation explanation',
            'risk_level' => 'High',
            'recommended_action' => 'Schedule maintenance.',
            'generated_at' => now(),
            'status' => 'Open',
            'action_status' => 'Pending',
        ], $overrides));
    }
}
