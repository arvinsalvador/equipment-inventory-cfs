<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Filament\Widgets\HighestRiskEquipment;
use App\Filament\Widgets\MaintenanceRecommendationSummary;
use App\Filament\Widgets\RecommendationRuleDistribution;
use App\Filament\Widgets\RecommendationsByRisk;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRecommendationDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
    }

    public function test_dashboard_widgets_report_status_risk_and_rule_counts(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $this->createRecommendation(['status' => 'Open', 'risk_level' => 'Critical', 'rule_key' => 'overdue_maintenance']);
        $this->createRecommendation(['status' => 'Reviewed', 'risk_level' => 'High', 'rule_key' => 'due_soon']);
        $this->createRecommendation(['status' => 'Resolved', 'risk_level' => 'Moderate', 'rule_key' => 'repeated_repairs']);
        $this->createRecommendation(['status' => 'Dismissed', 'risk_level' => 'Low', 'rule_key' => 'expiring_warranty']);

        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Open']);
        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Reviewed']);
        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Resolved']);
        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Dismissed']);

        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['Critical']);
        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['High']);
        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['Moderate']);
        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['Low']);

        $this->assertSame(1, app(RecommendationRuleDistribution::class)->counts()['overdue_maintenance']);
        $this->assertSame(1, app(RecommendationRuleDistribution::class)->counts()['due_soon']);
    }

    public function test_highest_risk_equipment_widget_lists_top_open_risk_equipment(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));
        $otherEquipment = $this->createEquipment(['equipment_code' => 'EQ-HIGH-002', 'equipment_name' => 'Other High Risk']);

        $this->createRecommendation(['equipment_id' => $otherEquipment->id, 'risk_level' => 'High']);
        $this->createRecommendation(['equipment_id' => $this->equipment->id, 'risk_level' => 'Critical']);
        $this->createRecommendation(['equipment_id' => $this->equipment->id, 'risk_level' => 'Moderate']);

        $rows = app(HighestRiskEquipment::class)->rows();

        $this->assertSame($this->equipment->equipment_code, $rows->first()->equipment_code);
        $this->assertSame('Critical', $rows->first()->highest_risk);
        $this->assertSame(2, (int) $rows->first()->open_recommendation_count);
    }

    public function test_dashboard_widgets_follow_recommendation_view_authorization(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(MaintenanceRecommendationSummary::canView());
        $this->assertFalse(RecommendationsByRisk::canView());
        $this->assertFalse(RecommendationRuleDistribution::canView());
        $this->assertFalse(HighestRiskEquipment::canView());
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
            'equipment_code' => 'EQ-DASH-'.uniqid(),
            'equipment_name' => 'Dashboard Equipment',
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
