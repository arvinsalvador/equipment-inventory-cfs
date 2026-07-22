<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\MaintenanceRecommendations\Pages\ListMaintenanceRecommendations;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use App\Services\MaintenanceRecommendationEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecommendationRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_refresh_counts_created_unchanged_updated_and_skipped_equipment(): void
    {
        $engine = app(MaintenanceRecommendationEngine::class);
        $overdue = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);
        $archived = $this->createEquipment(['is_archived' => true, 'next_maintenance_date' => today()->subDay()]);

        $first = $engine->generateForAllEquipment();
        $second = $engine->generateForAllEquipment();

        $recommendation = MaintenanceRecommendation::query()
            ->where('equipment_id', $overdue->id)
            ->where('rule_key', 'overdue_maintenance')
            ->firstOrFail();

        $recommendation->forceFill(['title' => 'Outdated title'])->save();

        $third = $engine->generateForAllEquipment();

        $this->assertSame(1, $first['equipment_checked']);
        $this->assertGreaterThanOrEqual(1, $first['created']);
        $this->assertSame(1, $first['skipped']);
        $this->assertGreaterThanOrEqual(1, $second['unchanged']);
        $this->assertSame(1, $third['updated']);
        $this->assertSame(1, MaintenanceRecommendation::query()->where('equipment_id', $overdue->id)->where('rule_key', 'overdue_maintenance')->whereIn('status', MaintenanceRecommendationEngine::ACTIVE_RECOMMENDATION_STATUSES)->count());
        $this->assertDatabaseMissing('maintenance_recommendations', [
            'equipment_id' => $archived->id,
            'rule_key' => 'overdue_maintenance',
        ]);
    }

    public function test_administrator_can_run_full_refresh_from_recommendations_page(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);

        $this->actingAs($administrator);

        Livewire::test(ListMaintenanceRecommendations::class)
            ->assertActionVisible('refreshRecommendations')
            ->callAction('refreshRecommendations')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('maintenance_recommendations', [
            'equipment_id' => $equipment->id,
            'rule_key' => 'overdue_maintenance',
            'status' => 'Open',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'recommendations_refreshed',
            'module' => 'AI Recommendation',
        ]);
    }

    public function test_staff_and_technician_cannot_run_full_refresh(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $this->actingAs($this->userWithRole($role));

            Livewire::test(ListMaintenanceRecommendations::class)
                ->assertActionHidden('refreshRecommendations');
        }
    }

    public function test_single_equipment_refresh_scans_only_selected_equipment(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $matching = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);
        $unrelated = $this->createEquipment(['next_maintenance_date' => today()->subDay()]);

        $this->actingAs($administrator);

        Livewire::test(ViewEquipment::class, ['record' => $matching->getRouteKey()])
            ->assertActionVisible('refreshRecommendations')
            ->callAction('refreshRecommendations')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('maintenance_recommendations', [
            'equipment_id' => $matching->id,
            'rule_key' => 'overdue_maintenance',
            'status' => 'Open',
        ]);
        $this->assertDatabaseMissing('maintenance_recommendations', [
            'equipment_id' => $unrelated->id,
            'rule_key' => 'overdue_maintenance',
        ]);
    }

    public function test_refresh_command_and_scheduler_are_available(): void
    {
        $equipment = $this->createEquipment(['warranty_expiration_date' => today()->addDays(10)]);

        $this->artisan('recommendations:refresh')
            ->expectsOutput('Recommendation refresh completed.')
            ->expectsOutput('Equipment scanned: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('maintenance_recommendations', [
            'equipment_id' => $equipment->id,
            'rule_key' => 'expiring_warranty',
            'status' => 'Open',
        ]);

        $this->artisan('schedule:list')
            ->expectsOutputToContain('recommendations:refresh')
            ->assertSuccessful();
    }

    public function test_refresh_permission_is_administrator_only(): void
    {
        $this->assertTrue($this->userWithRole('Administrator')->can('recommendations.refresh'));
        $this->assertFalse($this->userWithRole('Staff')->can('recommendations.refresh'));
        $this->assertFalse($this->userWithRole('Technician')->can('recommendations.refresh'));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-REF-'.uniqid(),
            'equipment_name' => 'Refresh Equipment',
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
