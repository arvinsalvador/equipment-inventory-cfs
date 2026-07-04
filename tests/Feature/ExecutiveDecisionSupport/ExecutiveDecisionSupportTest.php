<?php

namespace Tests\Feature\ExecutiveDecisionSupport;

use App\Filament\Pages\ExecutiveDecisionSupport;
use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ExecutiveInsightService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExecutiveDecisionSupportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $technician;

    private Location $location;

    private Equipment $weatherStation;

    private Equipment $pump;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->technician = $this->userWithRole('Technician');
        $this->location = Location::create(['name' => 'Executive Lab', 'type' => 'Room']);
        $category = EquipmentCategory::create(['name' => 'Executive Category']);

        $this->weatherStation = $this->createEquipment($category, 'EQ-EX-001', 'Executive Weather Station', 10000, 'Good');
        $this->pump = $this->createEquipment($category, 'EQ-EX-002', 'Executive Pump', 25000, 'Beyond repair');

        $this->seedExecutiveData();
    }

    public function test_service_asset_maintenance_lifecycle_budget_and_recommendation_summaries(): void
    {
        $service = app(ExecutiveInsightService::class);

        $this->assertSame(2, $service->getAssetSummary()['total_equipment']);
        $this->assertSame(35000.0, $service->getAssetSummary()['total_acquisition_value']);
        $this->assertSame(2, $service->getMaintenanceSummary()['open_work_orders']);
        $this->assertSame(1, $service->getMaintenanceSummary()['overdue_maintenance']);
        $this->assertSame(1, $service->getLifecycleSummary()['replacement_candidates']);
        $this->assertSame(40000.0, $service->getBudgetSummary()['estimated_replacement_budget']);
        $this->assertSame(1, $service->getBudgetSummary()['pending_budget_plans']);
        $this->assertSame(1, $service->getRecommendationSummary()['critical_recommendations']);
        $this->assertSame(1, $service->getRecommendationSummary()['pending_actions']);
    }

    public function test_service_returns_risk_matrix_replacement_forecast_maintenance_burden_and_readable_insights(): void
    {
        $service = app(ExecutiveInsightService::class);
        $matrix = collect($service->getRiskPriorityMatrix())->keyBy('risk_level');
        $forecast = $service->getReplacementForecastSummary();
        $burden = $service->getMaintenanceBurdenSummary();
        $insights = $service->getStrategicInsights();
        $actions = $service->getExecutiveActionItems();

        $this->assertSame(2, $matrix['Critical']['equipment_count']);
        $this->assertSame(1, $matrix['Critical']['open_recommendations']);
        $this->assertSame(40000.0, $matrix['Critical']['estimated_budget_impact']);
        $this->assertSame(1, $forecast['replacement_candidates_this_year']);
        $this->assertSame(40000.0, $forecast['estimated_replacement_cost']);
        $this->assertSame(1, $forecast['beyond_repair_equipment']);
        $this->assertSame(33.3, $burden['work_order_completion_rate']);
        $this->assertStringContainsString('equipment items are replacement candidates', implode(' ', $insights));
        $this->assertContains('Review replacement candidates.', $actions);
        $this->assertContains('Resolve critical AI recommendations.', $actions);
    }

    public function test_administrator_can_access_executive_dashboard_and_sections_render(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/executive-decision-support')
            ->assertOk()
            ->assertSee('Executive Decision Support')
            ->assertSee('Total Equipment')
            ->assertSee('Total Asset Acquisition Value')
            ->assertSee('Estimated Replacement Budget')
            ->assertSee('Risk Priority Matrix')
            ->assertSee('Replacement Forecast Summary')
            ->assertSee('Maintenance Burden Summary')
            ->assertSee('Budget Decision Summary')
            ->assertSee('AI Recommendation Decision Summary')
            ->assertSee('Recommended Management Actions')
            ->assertSee('Executive Weather Station')
            ->assertSee('Review replacement candidates.');
    }

    public function test_staff_technician_and_user_without_permission_cannot_access_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Staff'))
            ->get('/admin/executive-decision-support')
            ->assertForbidden();

        $this->actingAs($this->technician)
            ->get('/admin/executive-decision-support')
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get('/admin/executive-decision-support')
            ->assertForbidden();
    }

    public function test_permission_required_and_navigation_visibility_follows_authorization(): void
    {
        $this->actingAs($this->administrator);
        $this->assertTrue(ExecutiveDecisionSupport::canAccess());
        $this->assertTrue(ExecutiveDecisionSupport::shouldRegisterNavigation());

        $this->actingAs($this->userWithRole('Staff'));
        $this->assertFalse(ExecutiveDecisionSupport::canAccess());
        $this->assertFalse(ExecutiveDecisionSupport::shouldRegisterNavigation());

        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel', 'executive-dashboard.view');

        $this->actingAs($user);
        $this->assertTrue(ExecutiveDecisionSupport::canAccess());
        $this->assertTrue(ExecutiveDecisionSupport::shouldRegisterNavigation());
    }

    private function seedExecutiveData(): void
    {
        EquipmentLifecycleProfile::create([
            'equipment_id' => $this->weatherStation->id,
            'estimated_end_of_life_date' => now()->toDateString(),
            'health_score' => 30,
            'health_grade' => 'Critical',
            'lifecycle_status' => 'Replacement Candidate',
            'replacement_recommendation' => 'Replace Equipment',
            'replacement_reason' => 'Critical executive lifecycle risk.',
        ]);

        EquipmentLifecycleProfile::create([
            'equipment_id' => $this->pump->id,
            'estimated_end_of_life_date' => now()->addYear()->toDateString(),
            'health_score' => 45,
            'health_grade' => 'Poor',
            'lifecycle_status' => 'Beyond Repair',
            'replacement_recommendation' => 'Dispose Equipment',
        ]);

        $this->createWorkOrder($this->weatherStation, 'Completed', 'Normal', now()->subDays(3), now()->subDay());
        $this->createWorkOrder($this->weatherStation, 'In progress', 'High', now()->subDay());
        $this->createWorkOrder($this->pump, 'For verification', 'Critical', now()->subDays(2));

        MaintenanceSchedule::create([
            'equipment_id' => $this->weatherStation->id,
            'maintenance_type' => 'Executive overdue check',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => now()->subDay()->toDateString(),
            'assigned_user_id' => $this->technician->id,
            'priority' => 'High',
            'status' => 'Upcoming',
        ]);

        MaintenanceRecommendation::create([
            'equipment_id' => $this->weatherStation->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Executive critical recommendation',
            'explanation' => 'Critical overdue maintenance.',
            'risk_level' => 'Critical',
            'recommended_action' => 'Schedule overdue maintenance.',
            'generated_at' => now(),
            'status' => 'Open',
            'action_status' => 'Pending',
        ]);

        MaintenanceRecommendation::create([
            'equipment_id' => $this->pump->id,
            'rule_key' => 'beyond_repair_evidence_incomplete',
            'title' => 'Executive high recommendation',
            'explanation' => 'Beyond repair follow-up.',
            'risk_level' => 'High',
            'recommended_action' => 'Review disposal support.',
            'generated_at' => now(),
            'status' => 'Reviewed',
            'action_status' => 'Approved',
        ]);

        $approvedPlan = BudgetPlan::create([
            'title' => 'Executive Approved Plan',
            'fiscal_year' => now()->year,
            'status' => 'Approved',
            'prepared_by' => $this->administrator->id,
            'approved_by' => $this->administrator->id,
            'approved_at' => now(),
            'total_estimated_budget' => 40000,
        ]);

        BudgetPlanItem::create([
            'budget_plan_id' => $approvedPlan->id,
            'equipment_id' => $this->weatherStation->id,
            'item_type' => 'Replacement',
            'description' => 'Replace executive weather station.',
            'priority' => 'Critical',
            'estimated_cost' => 40000,
            'status' => 'Proposed',
            'forecast_reason' => 'Critical lifecycle equipment.',
        ]);

        BudgetPlan::create([
            'title' => 'Executive Pending Plan',
            'fiscal_year' => now()->year,
            'status' => 'Under Review',
            'prepared_by' => $this->administrator->id,
            'total_estimated_budget' => 12000,
        ]);

        AssetActionRequest::create([
            'equipment_id' => $this->weatherStation->id,
            'requested_by' => $this->administrator->id,
            'request_type' => 'Replacement',
            'reason' => 'Executive pending asset request.',
            'priority' => 'Critical',
            'status' => 'Submitted',
        ]);
    }

    private function createEquipment(EquipmentCategory $category, string $code, string $name, float $cost, string $condition): Equipment
    {
        return Equipment::create([
            'equipment_code' => $code,
            'equipment_name' => $name,
            'equipment_category_id' => $category->id,
            'current_location_id' => $this->location->id,
            'condition' => $condition,
            'operational_status' => $condition === 'Beyond repair' ? 'Unavailable' : 'Available',
            'acquisition_cost' => $cost,
        ]);
    }

    private function createWorkOrder(Equipment $equipment, string $status, string $priority, mixed $createdAt, mixed $completedAt = null): WorkOrder
    {
        return WorkOrder::create([
            'equipment_id' => $equipment->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->technician->id,
            'title' => "{$status} executive work order",
            'problem_description' => 'Executive dashboard test work order.',
            'priority' => $priority,
            'status' => $status,
            'created_at' => $createdAt,
            'completed_at' => $completedAt,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
