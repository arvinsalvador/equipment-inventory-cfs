<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecommendationModelTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->user = User::factory()->create();
    }

    public function test_recommendation_can_be_created_with_required_fields(): void
    {
        $recommendation = $this->createRecommendation();

        $this->assertDatabaseHas('maintenance_recommendations', [
            'id' => $recommendation->id,
            'equipment_id' => $this->equipment->id,
            'rule_key' => 'overdue_maintenance',
            'risk_level' => 'High',
            'status' => 'Open',
        ]);
    }

    public function test_recommendation_relationships_are_available(): void
    {
        $recommendation = $this->createRecommendation();

        $this->assertTrue($recommendation->equipment->is($this->equipment));
        $this->assertTrue($this->equipment->maintenanceRecommendations->contains($recommendation));
        $this->assertTrue($this->equipment->openMaintenanceRecommendations->contains($recommendation));
        $this->assertTrue($this->equipment->highRiskRecommendations->contains($recommendation));
    }

    public function test_recommendation_can_be_marked_reviewed_resolved_and_dismissed(): void
    {
        $reviewed = $this->createRecommendation()->markReviewed($this->user);

        $this->assertTrue($reviewed->isReviewed());
        $this->assertSame($this->user->id, $reviewed->reviewed_by);
        $this->assertNotNull($reviewed->reviewed_at);

        $resolved = $this->createRecommendation()->markResolved($this->user);

        $this->assertTrue($resolved->isResolved());
        $this->assertSame($this->user->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);

        $dismissed = $this->createRecommendation()->dismiss($this->user);

        $this->assertTrue($dismissed->isDismissed());
        $this->assertSame($this->user->id, $dismissed->resolved_by);
        $this->assertNotNull($dismissed->resolved_at);
    }

    public function test_risk_levels_and_status_values_are_available(): void
    {
        $this->assertSame(['Low', 'Moderate', 'High', 'Critical'], MaintenanceRecommendation::RISK_LEVELS);
        $this->assertSame(['Open', 'Reviewed', 'Resolved', 'Dismissed'], MaintenanceRecommendation::STATUSES);
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
            'metadata' => ['source' => 'test'],
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-REC-'.uniqid(),
            'equipment_name' => 'Recommendation Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }
}
