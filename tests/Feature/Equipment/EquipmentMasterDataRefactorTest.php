<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\BudgetPlan;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use App\Services\Reports\ReportRegistry;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentMasterDataRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_government_equipment_categories_keep_account_codes_separate_from_names(): void
    {
        $this->seed(MasterDataSeeder::class);

        foreach (MasterDataSeeder::EQUIPMENT_CATEGORIES as $accountCode => $name) {
            $this->assertDatabaseHas('equipment_categories', [
                'account_code' => $accountCode,
                'name' => $name,
            ]);
        }

        $category = EquipmentCategory::where('account_code', '10605030')->firstOrFail();

        $this->assertSame('Information and Communication Technology Equipment', $category->name);
        $this->assertSame('10605030 - Information and Communication Technology Equipment', $category->display_name);
    }

    public function test_master_equipment_pages_and_lookup_show_account_code_category_and_article_label(): void
    {
        $administrator = $this->administrator();
        $category = EquipmentCategory::create([
            'account_code' => '10605030',
            'name' => 'Information and Communication Technology Equipment',
            'is_active' => true,
        ]);
        $location = Location::create(['name' => 'Climate Field School', 'type' => 'Building', 'is_active' => true]);
        $equipment = Equipment::create([
            'equipment_code' => 'CFS-ICT-0001',
            'property_number' => '10605030-CFS-0001',
            'equipment_name' => 'Laptop',
            'equipment_category_id' => $category->id,
            'description' => 'Apple MacBook Air Laptop for lectures and laboratories.',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 85000,
            'current_location_id' => $location->id,
            'custodian' => 'Climate Field School',
            'condition' => 'Good',
            'operational_status' => 'Available',
            'qr_identifier' => 'lookup-master-data-test',
            'remarks' => 'Imported government inventory test record.',
        ]);

        $this->actingAs($administrator)
            ->get(EquipmentResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Master List of Equipments')
            ->assertSee('Equipment Name / Article');

        $this->actingAs($administrator)
            ->get(route('equipment.lookup', ['qr_identifier' => $equipment->qr_identifier]))
            ->assertOk()
            ->assertSee('Equipment Name / Article')
            ->assertSee('Account Code')
            ->assertSee('10605030')
            ->assertSee('Information and Communication Technology Equipment')
            ->assertSee('Acquisition Cost')
            ->assertSee('Assigned Office')
            ->assertSee('AI Recommendation Summary');
    }

    public function test_reports_include_account_code_article_label_acquisition_and_budget_procurement_fields(): void
    {
        $category = EquipmentCategory::create([
            'account_code' => '10605140',
            'name' => 'Technical & Scientific Equipment',
            'is_active' => true,
        ]);
        $location = Location::create(['name' => 'Science Lab', 'type' => 'Room', 'is_active' => true]);
        Equipment::create([
            'equipment_code' => 'CFS-TSE-0001',
            'property_number' => '10605140-CFS-0001',
            'equipment_name' => 'Water Quality Meter',
            'equipment_category_id' => $category->id,
            'acquisition_date' => '2025-01-01',
            'acquisition_cost' => 45000,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);

        $plan = BudgetPlan::create([
            'title' => 'FY 2027 CFS Replacement Plan',
            'fiscal_year' => 2027,
            'budget_date' => '2026-07-20',
            'funds' => 'GAA',
            'purchase_order_number' => 'PO-CFS-2027-0001',
            'status' => 'Prepared',
            'prepared_by' => $this->administrator()->id,
        ]);

        $registry = app(ReportRegistry::class);
        $inventoryColumns = collect($registry->columns('equipment-inventory', false))->pluck('label')->all();
        $inventory = $registry->rows('equipment-inventory')->first();
        $budget = $registry->rows('budget-plans')->firstWhere('plan_number', $plan->plan_number);

        $this->assertContains('Equipment Name / Article', $inventoryColumns);
        $this->assertContains('Account Code', $inventoryColumns);
        $this->assertContains('Acquisition cost', $inventoryColumns);
        $this->assertSame('10605140', $inventory['account_code']);
        $this->assertSame('Technical & Scientific Equipment', $inventory['category']);
        $this->assertSame('45000.00', (string) $inventory['acquisition_cost']);
        $this->assertSame('GAA', $budget['funds']);
        $this->assertSame('PO-CFS-2027-0001', $budget['purchase_order_number']);
    }

    public function test_budget_plan_supports_fund_dropdown_values_budget_date_and_purchase_order_number(): void
    {
        $plan = BudgetPlan::create([
            'title' => 'Procurement Budget',
            'fiscal_year' => 2028,
            'budget_date' => '2026-07-20',
            'funds' => 'Trust Fund',
            'purchase_order_number' => 'PO-TRUST-0001',
            'prepared_by' => $this->administrator()->id,
        ]);

        $this->assertSame(['GAA', 'IGF', 'Trust Fund', 'Income'], array_values(BudgetPlan::fundOptions()));
        $this->assertSame('2026-07-20', $plan->budget_date->toDateString());
        $this->assertSame('Trust Fund', $plan->funds);
        $this->assertSame('PO-TRUST-0001', $plan->purchase_order_number);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Administrator'));

        return $user;
    }
}
