<?php

namespace Tests\Feature\BudgetPlanning;

use App\Filament\Resources\BudgetPlans\BudgetPlanResource;
use App\Filament\Resources\BudgetPlans\Pages\CreateBudgetPlan;
use App\Filament\Resources\BudgetPlans\Pages\ListBudgetPlans;
use App\Filament\Resources\BudgetPlans\Pages\ViewBudgetPlan;
use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\User;
use App\Services\BudgetForecastingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BudgetPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->equipment = $this->createEquipment([
            'equipment_code' => 'EQ-BP-001',
            'equipment_name' => 'Budget Plan Equipment',
            'acquisition_cost' => 18000,
        ]);
    }

    public function test_budget_plan_and_items_model_relationships_number_total_and_transitions(): void
    {
        $plan = $this->createBudgetPlan(['fiscal_year' => 2027]);

        $item = BudgetPlanItem::create([
            'budget_plan_id' => $plan->id,
            'equipment_id' => $this->equipment->id,
            'item_type' => 'Replacement',
            'description' => 'Replace failing unit.',
            'priority' => 'Critical',
            'estimated_cost' => 15000,
            'status' => 'Proposed',
        ]);

        $this->assertStringStartsWith('BP-2027-', $plan->plan_number);
        $this->assertTrue($plan->items->first()->is($item));
        $this->assertTrue($item->budgetPlan->is($plan));
        $this->assertTrue($item->equipment->is($this->equipment));
        $this->assertTrue($item->isCritical());
        $this->assertFalse($item->isApproved());

        $plan->recalculateTotal();
        $this->assertSame('15000.00', $plan->total_estimated_budget);

        $plan->submitForReview($this->administrator);
        $this->assertSame('Under Review', $plan->status);
        $this->assertTrue($plan->reviewedBy->is($this->administrator));

        $plan->approve($this->administrator);
        $this->assertSame('Approved', $plan->status);
        $this->assertTrue($plan->approvedBy->is($this->administrator));
        $this->assertNotNull($plan->approved_at);
    }

    public function test_budget_plan_reject_and_cancel_transitions_work(): void
    {
        $rejected = $this->createBudgetPlan(['status' => 'Under Review']);
        $rejected->reject($this->administrator, 'Budget moved to later cycle.');

        $this->assertSame('Rejected', $rejected->status);
        $this->assertSame('Budget moved to later cycle.', $rejected->remarks);

        $cancelled = $this->createBudgetPlan();
        $cancelled->cancel('Duplicate budget plan.');

        $this->assertSame('Cancelled', $cancelled->status);
        $this->assertSame('Duplicate budget plan.', $cancelled->remarks);
    }

    public function test_forecasting_service_creates_replacement_beyond_repair_high_maintenance_and_asset_action_items(): void
    {
        $replacement = $this->createEquipment(['equipment_code' => 'EQ-BP-002', 'acquisition_cost' => 22000]);
        EquipmentLifecycleProfile::create([
            'equipment_id' => $replacement->id,
            'health_score' => 35,
            'health_grade' => 'Critical',
            'lifecycle_status' => 'Replacement Candidate',
            'replacement_recommendation' => 'Replace Equipment',
            'replacement_reason' => 'Critical lifecycle health.',
        ]);

        $beyondRepair = $this->createEquipment(['equipment_code' => 'EQ-BP-003', 'condition' => 'Beyond repair', 'acquisition_cost' => 9000]);
        $highMaintenance = $this->createEquipment(['equipment_code' => 'EQ-BP-004', 'acquisition_cost' => 7000]);
        EquipmentLifecycleProfile::create([
            'equipment_id' => $highMaintenance->id,
            'health_score' => 55,
            'health_grade' => 'Poor',
            'lifecycle_status' => 'High Maintenance',
            'replacement_recommendation' => 'Schedule Major Inspection',
        ]);

        $request = AssetActionRequest::create([
            'equipment_id' => $this->equipment->id,
            'requested_by' => $this->administrator->id,
            'approved_by' => $this->administrator->id,
            'request_type' => 'Replacement',
            'reason' => 'Approved replacement.',
            'estimated_cost' => 33000,
            'priority' => 'Critical',
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        $items = app(BudgetForecastingService::class)->generateForecastItemsForYear(2027);

        $this->assertTrue($items->contains(fn (array $item): bool => $item['equipment_id'] === $replacement->id && $item['item_type'] === 'Replacement'));
        $this->assertTrue($items->contains(fn (array $item): bool => $item['equipment_id'] === $beyondRepair->id && $item['item_type'] === 'Disposal Support'));
        $this->assertTrue($items->contains(fn (array $item): bool => $item['equipment_id'] === $highMaintenance->id && $item['item_type'] === 'Inspection'));
        $this->assertTrue($items->contains(fn (array $item): bool => $item['asset_action_request_id'] === $request->id && (float) $item['estimated_cost'] === 33000.0));
    }

    public function test_forecast_cost_precedence_fallback_and_duplicate_prevention(): void
    {
        $noCost = $this->createEquipment(['equipment_code' => 'EQ-BP-005', 'acquisition_cost' => null]);
        EquipmentLifecycleProfile::create([
            'equipment_id' => $noCost->id,
            'health_score' => 30,
            'health_grade' => 'Critical',
            'lifecycle_status' => 'Replacement Candidate',
            'replacement_recommendation' => 'Replace Equipment',
        ]);

        $request = AssetActionRequest::create([
            'equipment_id' => $this->equipment->id,
            'requested_by' => $this->administrator->id,
            'approved_by' => $this->administrator->id,
            'request_type' => 'Replacement',
            'reason' => 'Approved replacement.',
            'estimated_cost' => 44000,
            'priority' => 'High',
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        $plan = $this->createBudgetPlan(['fiscal_year' => 2028]);
        $service = app(BudgetForecastingService::class);
        $first = $service->addForecastItemsToPlan($plan);
        $second = $service->addForecastItemsToPlan($plan);

        $this->assertGreaterThan(0, $first['added']);
        $this->assertSame(0, $second['added']);
        $this->assertGreaterThan(0, $second['skipped']);
        $this->assertDatabaseHas('budget_plan_items', [
            'asset_action_request_id' => $request->id,
            'estimated_cost' => 44000,
        ]);
        $this->assertDatabaseHas('budget_plan_items', [
            'equipment_id' => $noCost->id,
            'estimated_cost' => 0,
        ]);
        $this->assertStringContainsString('estimate set to 0', BudgetPlanItem::where('equipment_id', $noCost->id)->first()->forecast_reason);
    }

    public function test_budget_plan_policy_rules(): void
    {
        $staff = $this->userWithRole('Staff');
        $technician = $this->userWithRole('Technician');
        $plan = $this->createBudgetPlan(['status' => 'Under Review']);

        $this->assertTrue($this->administrator->can('viewAny', BudgetPlan::class));
        $this->assertTrue($this->administrator->can('create', BudgetPlan::class));
        $this->assertTrue($this->administrator->can('update', $plan));
        $this->assertTrue($this->administrator->can('approve', $plan));
        $this->assertFalse($staff->can('viewAny', BudgetPlan::class));
        $this->assertFalse($technician->can('viewAny', BudgetPlan::class));
        $this->assertFalse($this->administrator->can('delete', $plan));
        $this->assertFalse($this->administrator->can('forceDelete', $plan));
    }

    public function test_filament_budget_plan_pages_create_generate_forecast_and_workflow_actions(): void
    {
        $this->actingAs($this->administrator);
        EquipmentLifecycleProfile::create([
            'equipment_id' => $this->equipment->id,
            'health_score' => 25,
            'health_grade' => 'Critical',
            'lifecycle_status' => 'Replacement Candidate',
            'replacement_recommendation' => 'Replace Equipment',
        ]);

        $this->get(BudgetPlanResource::getUrl('index'))->assertOk();
        $this->get(BudgetPlanResource::getUrl('create'))->assertOk();

        Livewire::test(CreateBudgetPlan::class)
            ->fillForm([
                'title' => 'FY 2029 Replacement Plan',
                'fiscal_year' => 2029,
                'description' => 'Phase 13B test plan.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = BudgetPlan::latest('id')->first();

        Livewire::test(ListBudgetPlans::class)
            ->callTableAction('generateForecastItems', $plan)
            ->assertHasNoTableActionErrors()
            ->callTableAction('submitForReview', $plan)
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $plan->items()->count());
        $this->assertSame('Under Review', $plan->fresh()->status);

        Livewire::test(ListBudgetPlans::class)
            ->callTableAction('approve', $plan->fresh())
            ->assertHasNoTableActionErrors();

        $this->assertSame('Approved', $plan->fresh()->status);

        Livewire::test(ViewBudgetPlan::class, ['record' => $plan->getRouteKey()])
            ->assertSee($plan->plan_number)
            ->assertSee('FY 2029 Replacement Plan');

        $this->assertDatabaseHas('budget_plan_items', [
            'budget_plan_id' => $plan->id,
            'item_type' => 'Replacement',
        ]);
    }

    public function test_filament_reject_action_and_unauthorized_access(): void
    {
        $plan = $this->createBudgetPlan(['status' => 'Under Review']);

        $this->actingAs($this->administrator);

        Livewire::test(ListBudgetPlans::class)
            ->callTableAction('reject', $plan, data: [
                'remarks' => 'Rejected from table.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Rejected', $plan->fresh()->status);

        $this->actingAs($this->userWithRole('Staff'))
            ->get(BudgetPlanResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('Technician'))
            ->get(BudgetPlanResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_budget_reports_render_csv_and_print(): void
    {
        $plan = $this->createBudgetPlan(['fiscal_year' => 2030, 'status' => 'Approved', 'total_estimated_budget' => 51000]);
        BudgetPlanItem::create([
            'budget_plan_id' => $plan->id,
            'equipment_id' => $this->equipment->id,
            'item_type' => 'Replacement',
            'description' => 'Replace equipment.',
            'priority' => 'Critical',
            'estimated_cost' => 51000,
            'status' => 'Proposed',
            'forecast_reason' => 'Critical lifecycle health.',
        ]);

        $this->actingAs($this->administrator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Budget Plan Report')
            ->assertSee('Budget Plan Item Report');

        $csv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', ['report' => 'budget-plans', 'fiscal_year' => 2030]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Plan number', $csv);
        $this->assertStringContainsString($plan->plan_number, $csv);

        $this->actingAs($this->administrator)
            ->get(route('reports.print', 'budget-plan-items'))
            ->assertOk()
            ->assertSee('Budget Plan Item Report')
            ->assertSee('Critical lifecycle health.');
    }

    public function test_audit_logs_are_created_for_approval_forecast_generation_and_recalculation(): void
    {
        $this->actingAs($this->administrator);
        EquipmentLifecycleProfile::create([
            'equipment_id' => $this->equipment->id,
            'health_score' => 30,
            'health_grade' => 'Critical',
            'lifecycle_status' => 'Replacement Candidate',
            'replacement_recommendation' => 'Replace Equipment',
        ]);

        $plan = $this->createBudgetPlan(['status' => 'Under Review']);
        app(BudgetForecastingService::class)->addForecastItemsToPlan($plan);
        $plan->approve($this->administrator);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'forecast_generated',
            'module' => 'Budget Plan',
            'entity_id' => $plan->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'total_recalculated',
            'module' => 'Budget Plan',
            'entity_id' => $plan->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approved',
            'module' => 'Budget Plan',
            'entity_id' => $plan->id,
        ]);
    }

    private function createBudgetPlan(array $overrides = []): BudgetPlan
    {
        return BudgetPlan::create(array_merge([
            'title' => 'Annual Asset Replacement Plan',
            'fiscal_year' => 2027,
            'description' => 'Focused Phase 13B plan.',
            'status' => 'Draft',
            'prepared_by' => $this->administrator->id,
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::firstOrCreate(['name' => 'Budget Planning Category']);
        $location = Location::firstOrCreate(['name' => 'Budget Planning Room'], ['type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-BP-'.uniqid(),
            'equipment_name' => 'Budget Planning Equipment',
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
