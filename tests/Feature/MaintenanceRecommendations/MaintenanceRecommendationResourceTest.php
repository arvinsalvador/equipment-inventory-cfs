<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Filament\Resources\MaintenanceRecommendations\Pages\ListMaintenanceRecommendations;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRecommendationResourceTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private MaintenanceRecommendation $recommendation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->recommendation = $this->createRecommendation();
    }

    public function test_authorized_users_can_access_recommendation_list(): void
    {
        foreach (['Administrator', 'Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get('/admin/maintenance-recommendations')->assertSuccessful();
        }
    }

    public function test_unauthorized_user_cannot_access_recommendation_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/maintenance-recommendations')->assertForbidden();
    }

    public function test_administrator_can_mark_recommendation_as_reviewed(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('markReviewed', $this->recommendation)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Reviewed', $this->recommendation->fresh()->status);
        $this->assertSame($administrator->id, $this->recommendation->fresh()->reviewed_by);
    }

    public function test_administrator_can_mark_recommendation_as_resolved(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('markResolved', $this->recommendation)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Resolved', $this->recommendation->fresh()->status);
        $this->assertSame($administrator->id, $this->recommendation->fresh()->resolved_by);
    }

    public function test_administrator_can_dismiss_recommendation(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->callTableAction('dismiss', $this->recommendation)
            ->assertHasNoTableActionErrors();

        $this->assertSame('Dismissed', $this->recommendation->fresh()->status);
        $this->assertSame($administrator->id, $this->recommendation->fresh()->resolved_by);
    }

    public function test_staff_and_technician_cannot_mark_recommendation_reviewed_by_default(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(ListMaintenanceRecommendations::class)
                ->assertTableActionHidden('markReviewed', $this->recommendation)
                ->assertTableActionHidden('markResolved', $this->recommendation)
                ->assertTableActionHidden('dismiss', $this->recommendation);
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
            'equipment_code' => 'EQ-FIL-'.uniqid(),
            'equipment_name' => 'Filament Equipment',
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
