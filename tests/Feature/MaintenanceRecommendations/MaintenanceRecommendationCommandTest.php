<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecommendationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_artisan_command_runs_generates_recommendations_and_prints_summary(): void
    {
        $equipment = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);

        $this->artisan('maintenance:generate-recommendations')
            ->expectsOutput('Maintenance recommendations generated.')
            ->expectsOutput('Equipment checked: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('maintenance_recommendations', [
            'equipment_id' => $equipment->id,
            'rule_key' => 'overdue_maintenance',
            'status' => 'Open',
        ]);
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-CMD-'.uniqid(),
            'equipment_name' => 'Command Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }
}
