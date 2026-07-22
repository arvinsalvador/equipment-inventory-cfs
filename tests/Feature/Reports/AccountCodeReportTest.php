<?php

namespace Tests\Feature\Reports;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountCodeReportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private EquipmentCategory $books;

    private EquipmentCategory $agriculture;

    private Location $library;

    private Location $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->books = EquipmentCategory::create([
            'account_code' => '10607020',
            'name' => 'Books',
            'is_active' => true,
        ]);
        $this->agriculture = EquipmentCategory::create([
            'account_code' => '10605040',
            'name' => 'Agricultural, Fishery & Forestry Equipment',
            'is_active' => true,
        ]);
        $this->library = Location::create(['name' => 'Library', 'type' => 'Room', 'is_active' => true]);
        $this->field = Location::create(['name' => 'Field Office', 'type' => 'Office', 'is_active' => true]);
    }

    public function test_report_center_lists_equipment_by_account_code_report(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Equipment by Account Code');
    }

    public function test_account_code_report_groups_equipment_and_renders_totals(): void
    {
        $this->createEquipment([
            'equipment_name' => 'Dewey Decimal Classification (4-Vol set)',
            'description' => 'Library reference books',
            'property_number' => '21-BO-001DC',
            'acquisition_date' => '2021-03-10',
            'acquisition_cost' => 61439.40,
            'equipment_category_id' => $this->books->id,
            'current_location_id' => $this->library->id,
        ]);
        $this->createEquipment([
            'equipment_name' => 'Fish Breeding Tank',
            'property_number' => '24-7AFFE-21DC-01',
            'acquisition_date' => '2025-06-16',
            'acquisition_cost' => 272000,
            'equipment_category_id' => $this->agriculture->id,
            'current_location_id' => $this->field->id,
        ]);
        $this->createEquipment([
            'equipment_name' => '4 Wheel Tractor',
            'property_number' => '25-10PAR-36DC-2',
            'acquisition_date' => '2025-03-11',
            'acquisition_cost' => 2000000,
            'equipment_category_id' => $this->agriculture->id,
            'current_location_id' => $this->field->id,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', 'equipment-by-account-code'))
            ->assertOk()
            ->assertSee('Account Code 10607020 - Books')
            ->assertSee('Account Code 10605040 - Agricultural, Fishery &amp; Forestry Equipment', false)
            ->assertSee('Dewey Decimal Classification (4-Vol set)')
            ->assertSee('Fish Breeding Tank')
            ->assertSee('4 Wheel Tractor')
            ->assertSee('Library')
            ->assertSee('Field Office')
            ->assertSee('PHP 61,439.40')
            ->assertSee('PHP 272,000.00')
            ->assertSee('PHP 2,000,000.00')
            ->assertSee('TOTAL VALUE')
            ->assertSee('PHP 2,272,000.00')
            ->assertSee('GRAND TOTAL')
            ->assertSee('PHP 2,333,439.40')
            ->assertDontSee('Balance Per Cards Quantity')
            ->assertDontSee('On Hand Per Counts Quantity');
    }

    public function test_account_code_report_filters_by_account_code_and_uses_actual_equipment_only(): void
    {
        $included = $this->createEquipment([
            'equipment_name' => 'Included Books Equipment',
            'equipment_category_id' => $this->books->id,
            'current_location_id' => $this->library->id,
            'acquisition_cost' => 100,
        ]);
        $this->createEquipment([
            'equipment_name' => 'Excluded Agriculture Equipment',
            'equipment_category_id' => $this->agriculture->id,
            'current_location_id' => $this->field->id,
            'acquisition_cost' => 200,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', [
                'report' => 'equipment-by-account-code',
                'account_code' => $this->books->account_code,
            ]))
            ->assertOk()
            ->assertSee($included->equipment_name)
            ->assertSee('Account Code 10607020 - Books')
            ->assertDontSee('Excluded Agriculture Equipment')
            ->assertDontSee('Old random equipment dataset');
    }

    public function test_account_code_report_print_pdf_csv_and_excel_render(): void
    {
        $this->createEquipment([
            'equipment_name' => 'Exportable Account Code Equipment',
            'equipment_category_id' => $this->books->id,
            'current_location_id' => $this->library->id,
            'acquisition_cost' => 1000,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.print', 'equipment-by-account-code'))
            ->assertOk()
            ->assertSee('Account Code 10607020 - Books')
            ->assertSee('GRAND TOTAL');

        $this->actingAs($this->administrator)
            ->get(route('reports.pdf', 'equipment-by-account-code'))
            ->assertOk()
            ->assertSee('Equipment by Account Code')
            ->assertSee('Print / Save as PDF')
            ->assertSee('Account Code 10607020 - Books');

        $csv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', 'equipment-by-account-code'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Article,Description,"Date Acquired","Property Number","Unit Value","Total Value",Remarks', $csv);
        $this->assertStringContainsString('Exportable Account Code Equipment', $csv);

        $excel = $this->actingAs($this->administrator)
            ->get(route('reports.excel', 'equipment-by-account-code'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Equipment by Account Code', $excel);
        $this->assertStringContainsString('Exportable Account Code Equipment', $excel);
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-ACCT-'.uniqid(),
            'equipment_name' => 'Account Code Equipment',
            'equipment_category_id' => $this->books->id,
            'current_location_id' => $this->library->id,
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
