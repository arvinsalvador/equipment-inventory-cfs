<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRecommendationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceRecommendation $recommendation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->recommendation = $this->createRecommendation();
    }

    public function test_administrator_can_view_and_review_recommendations(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->assertTrue($administrator->can('viewAny', MaintenanceRecommendation::class));
        $this->assertTrue($administrator->can('view', $this->recommendation));
        $this->assertTrue($administrator->can('review', $this->recommendation));
        $this->assertTrue($administrator->can('resolve', $this->recommendation));
        $this->assertTrue($administrator->can('dismiss', $this->recommendation));
    }

    public function test_staff_and_technician_can_view_but_cannot_review_by_default(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('view', $this->recommendation));
            $this->assertFalse($user->can('review', $this->recommendation));
            $this->assertFalse($user->can('resolve', $this->recommendation));
            $this->assertFalse($user->can('dismiss', $this->recommendation));
        }
    }

    public function test_unauthorized_user_cannot_view_recommendations(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('viewAny', MaintenanceRecommendation::class));
        $this->assertFalse($user->can('view', $this->recommendation));
    }

    public function test_permanent_delete_is_denied(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->assertFalse($administrator->can('delete', $this->recommendation));
        $this->assertFalse($administrator->can('forceDelete', $this->recommendation));
    }

    private function createRecommendation(): MaintenanceRecommendation
    {
        $equipment = $this->createEquipment();

        return MaintenanceRecommendation::create([
            'equipment_id' => $equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Preventive maintenance is overdue',
            'explanation' => 'The equipment maintenance date has already passed.',
            'risk_level' => 'High',
            'recommended_action' => 'Schedule maintenance immediately.',
            'generated_at' => now(),
            'status' => 'Open',
        ]);
    }

    private function createEquipment(): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create([
            'equipment_code' => 'EQ-REC-'.uniqid(),
            'equipment_name' => 'Recommendation Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
