<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\MaintenanceRecommendations\Pages\ListMaintenanceRecommendations;
use App\Filament\Resources\MaintenanceRecommendations\Pages\ViewMaintenanceRecommendation;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRecommendationDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
    }

    public function test_equipment_view_shows_open_recommendations_with_suggested_action(): void
    {
        $recommendation = $this->createRecommendation([
            'rule_key' => 'defective_without_work_order',
            'risk_level' => 'High',
            'status' => 'Open',
            'action_status' => 'Approved',
        ]);
        $workOrder = WorkOrder::create([
            'equipment_id' => $this->equipment->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'Linked work order',
            'problem_description' => 'Linked problem.',
            'priority' => 'High',
            'status' => 'Available',
        ]);
        $recommendation->update(['linked_work_order_id' => $workOrder->id]);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getRouteKey()])
            ->assertSee('Open recommendations')
            ->assertSee($recommendation->rule_key)
            ->assertSee('Generate Corrective Work Order')
            ->assertSee('Approved')
            ->assertSee($workOrder->work_order_number)
            ->assertSee('Open Recommendation');
    }

    public function test_qr_lookup_shows_open_recommendations_critical_first_without_metadata(): void
    {
        $this->createRecommendation([
            'rule_key' => 'due_soon',
            'risk_level' => 'Moderate',
            'metadata' => ['hidden' => 'secret metadata'],
        ]);
        $this->createRecommendation([
            'rule_key' => 'beyond_repair_evidence_incomplete',
            'risk_level' => 'Critical',
            'action_status' => 'Approved',
            'metadata' => ['hidden' => 'critical metadata'],
        ]);

        $this->actingAs($this->userWithRole('Staff'));

        $response = $this->get(route('equipment.lookup', $this->equipment->qr_identifier))
            ->assertOk()
            ->assertSee('Open Recommendations')
            ->assertSee('Upload Required Evidence')
            ->assertSee('Schedule Preventive Maintenance')
            ->assertSee('Approved')
            ->assertDontSee('critical metadata')
            ->assertDontSee('secret metadata');

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'due_soon'),
            strpos($content, 'beyond_repair_evidence_incomplete')
        );
    }

    public function test_recommendation_detail_shows_decision_preview_and_history(): void
    {
        $reviewer = $this->userWithRole('Administrator');
        $recommendation = $this->createRecommendation([
            'rule_key' => 'completed_without_after_evidence',
            'risk_level' => 'High',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'metadata' => ['work_order_ids' => [1, 2]],
        ]);

        $this->actingAs($reviewer);

        Livewire::test(ViewMaintenanceRecommendation::class, ['record' => $recommendation->getRouteKey()])
            ->assertSee('Why was this recommendation generated?')
            ->assertSee('Suggested Next Action')
            ->assertSee('Upload After-Maintenance Evidence')
            ->assertSee('Action Workflow')
            ->assertSee('Recommendation History')
            ->assertSee('Generated')
            ->assertSee('Reviewed')
            ->assertSee('Metadata');
    }

    public function test_recommendation_filters_and_search_work(): void
    {
        $critical = $this->createRecommendation([
            'rule_key' => 'beyond_repair_evidence_incomplete',
            'risk_level' => 'Critical',
            'title' => 'Critical recommendation',
            'generated_at' => now(),
        ]);
        $this->createRecommendation([
            'rule_key' => 'due_soon',
            'risk_level' => 'Moderate',
            'title' => 'Moderate recommendation',
            'generated_at' => now()->subDays(5),
        ]);

        $this->actingAs($this->userWithRole('Administrator'));

        Livewire::test(ListMaintenanceRecommendations::class)
            ->filterTable('risk_level', 'Critical')
            ->assertCanSeeTableRecords([$critical])
            ->searchTable('Critical recommendation')
            ->assertCanSeeTableRecords([$critical]);
    }

    public function test_default_recommendation_sorting_prioritizes_critical_then_newest(): void
    {
        $moderate = $this->createRecommendation([
            'rule_key' => 'due_soon',
            'risk_level' => 'Moderate',
            'generated_at' => now(),
        ]);
        $critical = $this->createRecommendation([
            'rule_key' => 'beyond_repair_evidence_incomplete',
            'risk_level' => 'Critical',
            'generated_at' => now()->subDay(),
        ]);

        $ordered = MaintenanceRecommendation::query()
            ->orderByRisk()
            ->latest('generated_at')
            ->pluck('id')
            ->all();

        $this->assertLessThan(array_search($moderate->id, $ordered, true), array_search($critical->id, $ordered, true));
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
            'equipment_code' => 'EQ-DISPLAY-'.uniqid(),
            'equipment_name' => 'Display Equipment',
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
