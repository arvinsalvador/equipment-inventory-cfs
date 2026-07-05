<?php

namespace Tests\Feature\Reports;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocationHistory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportFrameworkTest extends TestCase
{
    use RefreshDatabase;

    private EquipmentCategory $category;

    private Location $location;

    private Equipment $equipment;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->category = EquipmentCategory::create(['name' => 'Weather Equipment']);
        $this->location = Location::create(['name' => 'Main Room', 'type' => 'Room']);
        $this->equipment = $this->createEquipment([
            'equipment_code' => 'EQ-RPT-001',
            'equipment_name' => 'Report Weather Station',
            'condition' => 'Good',
        ]);
    }

    public function test_authorized_user_can_access_report_center(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Equipment Inventory Report');
    }

    public function test_unauthorized_user_cannot_access_report_center(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/reports')
            ->assertForbidden();
    }

    public function test_report_center_lists_all_available_reports(): void
    {
        $response = $this->actingAs($this->administrator)->get('/admin/reports');

        foreach ([
            'Equipment Inventory Report',
            'Equipment by Category',
            'Equipment by Location',
            'Equipment by Condition',
            'Maintenance Schedule Report',
            'Overdue Maintenance Report',
            'Maintenance Request Report',
            'Work Order Report',
            'Completed Work Orders Report',
            'Beyond-Repair Equipment Report',
            'AI Recommendation Report',
            'Equipment Transfer History Report',
            'Equipment Maintenance History Report',
        ] as $report) {
            $response->assertSee($report);
        }
    }

    public function test_report_center_renders_professional_builder_sections(): void
    {
        $response = $this->actingAs($this->administrator)
            ->get('/admin/reports?report=equipment-inventory');

        $response
            ->assertOk()
            ->assertSee('Report Center')
            ->assertSee('Professional Report Builder')
            ->assertSee('Report Type')
            ->assertSee('Filters')
            ->assertSee('Columns to Include')
            ->assertSee('Sorting & Grouping', false)
            ->assertSee('Output Options')
            ->assertSee('Generate Report')
            ->assertSee('Report Summary')
            ->assertSee('Preview')
            ->assertSee('PDF Preview')
            ->assertSee('Open PDF')
            ->assertSee('Download PDF')
            ->assertSee('Print PDF')
            ->assertSee('md:grid-cols-2')
            ->assertSee('xl:grid-cols-4')
            ->assertSee('<iframe', false);
    }

    public function test_report_center_column_picker_controls_preview_and_pdf_links(): void
    {
        $response = $this->actingAs($this->administrator)
            ->get('/admin/reports?report=equipment-inventory&columns%5B0%5D=equipment_code&columns%5B1%5D=equipment_name');

        $response
            ->assertOk()
            ->assertSee('Equipment code')
            ->assertSee('Equipment name')
            ->assertSee('columns%5B0%5D=equipment_code', false)
            ->assertSee('columns%5B1%5D=equipment_name', false);
    }

    public function test_authorized_user_can_access_core_report_pages(): void
    {
        $this->createMaintenanceSchedule();
        $this->createWorkOrder();
        $this->createRecommendation();

        foreach ([
            'equipment-inventory' => 'Equipment Inventory Report',
            'maintenance-schedules' => 'Maintenance Schedule Report',
            'work-orders' => 'Work Order Report',
            'ai-recommendations' => 'AI Recommendation Report',
        ] as $slug => $title) {
            $this->actingAs($this->administrator)
                ->get(route('reports.show', $slug))
                ->assertOk()
                ->assertSee($title);
        }
    }

    public function test_unauthorized_user_cannot_access_report_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.show', 'equipment-inventory'))
            ->assertForbidden();
    }

    public function test_equipment_inventory_report_filters_by_category_location_and_condition(): void
    {
        $otherCategory = EquipmentCategory::create(['name' => 'Office Equipment']);
        $otherLocation = Location::create(['name' => 'Storage', 'type' => 'Room']);
        $this->createEquipment([
            'equipment_code' => 'EQ-RPT-002',
            'equipment_name' => 'Filtered Printer',
            'equipment_category_id' => $otherCategory->id,
            'current_location_id' => $otherLocation->id,
            'condition' => 'Defective',
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', [
                'report' => 'equipment-inventory',
                'category' => $this->category->id,
                'location' => $this->location->id,
                'condition' => 'Good',
            ]))
            ->assertOk()
            ->assertSee('Report Weather Station')
            ->assertDontSee('Filtered Printer');
    }

    public function test_work_order_report_filters_by_status(): void
    {
        $this->createWorkOrder(['work_order_number' => 'WO-FILTER-0001', 'status' => 'Completed']);
        $this->createWorkOrder(['work_order_number' => 'WO-FILTER-0002', 'status' => 'Available']);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', ['report' => 'work-orders', 'status' => 'Completed']))
            ->assertOk()
            ->assertSee('WO-FILTER-0001')
            ->assertDontSee('WO-FILTER-0002');
    }

    public function test_maintenance_schedule_report_filters_by_date_range(): void
    {
        $this->createMaintenanceSchedule(['maintenance_type' => 'Included calibration', 'scheduled_date' => now()->toDateString()]);
        $this->createMaintenanceSchedule(['maintenance_type' => 'Excluded calibration', 'scheduled_date' => now()->addMonth()->toDateString()]);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', [
                'report' => 'maintenance-schedules',
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Included calibration')
            ->assertDontSee('Excluded calibration');
    }

    public function test_ai_recommendation_report_filters_by_risk_level(): void
    {
        $this->createRecommendation(['title' => 'Critical risk recommendation', 'risk_level' => 'Critical']);
        $this->createRecommendation(['title' => 'Low risk recommendation', 'risk_level' => 'Low']);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', ['report' => 'ai-recommendations', 'risk_level' => 'Critical']))
            ->assertOk()
            ->assertSee('Critical risk recommendation')
            ->assertDontSee('Low risk recommendation');
    }

    public function test_transfer_history_report_filters_by_equipment(): void
    {
        $otherEquipment = $this->createEquipment(['equipment_code' => 'EQ-RPT-009', 'equipment_name' => 'Other Equipment']);
        $from = Location::create(['name' => 'Old Room', 'type' => 'Room']);

        EquipmentLocationHistory::create([
            'equipment_id' => $this->equipment->id,
            'from_location_id' => $from->id,
            'to_location_id' => $this->location->id,
            'transferred_by' => $this->administrator->id,
            'transferred_at' => now(),
            'remarks' => 'Included transfer',
        ]);
        EquipmentLocationHistory::create([
            'equipment_id' => $otherEquipment->id,
            'from_location_id' => $from->id,
            'to_location_id' => $this->location->id,
            'transferred_by' => $this->administrator->id,
            'transferred_at' => now(),
            'remarks' => 'Excluded transfer',
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.show', ['report' => 'equipment-transfer-history', 'equipment' => $this->equipment->id]))
            ->assertOk()
            ->assertSee('Included transfer')
            ->assertDontSee('Excluded transfer');
    }

    public function test_csv_exports_include_requested_headers_and_respect_filters(): void
    {
        $this->createWorkOrder(['work_order_number' => 'WO-CSV-0001', 'status' => 'Completed']);
        $this->createWorkOrder(['work_order_number' => 'WO-CSV-0002', 'status' => 'Available']);
        $this->createRecommendation(['title' => 'CSV recommendation']);

        $inventoryCsv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', 'equipment-inventory'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Equipment code","Property number","Equipment name"', $inventoryCsv);

        $workOrderCsv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', ['report' => 'work-orders', 'status' => 'Completed']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Work order number",Equipment,"Created by"', $workOrderCsv);
        $this->assertStringContainsString('WO-CSV-0001', $workOrderCsv);
        $this->assertStringNotContainsString('WO-CSV-0002', $workOrderCsv);

        $recommendationCsv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', 'ai-recommendations'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Equipment,Rule,Title,"Risk level"', $recommendationCsv);
    }

    public function test_csv_export_uses_selected_columns_only(): void
    {
        $csv = $this->actingAs($this->administrator)
            ->get(route('reports.csv', [
                'report' => 'equipment-inventory',
                'columns' => ['equipment_code', 'equipment_name'],
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Equipment code","Equipment name"', $csv);
        $this->assertStringNotContainsString('Property number', $csv);
        $this->assertStringNotContainsString('Category', $csv);
    }

    public function test_unauthorized_user_cannot_export_csv(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.csv', 'equipment-inventory'))
            ->assertForbidden();
    }

    public function test_print_views_render_and_require_authorization(): void
    {
        $this->createWorkOrder();
        $this->createRecommendation();

        foreach ([
            'equipment-inventory' => 'Equipment Inventory Report',
            'work-orders' => 'Work Order Report',
            'ai-recommendations' => 'AI Recommendation Report',
        ] as $slug => $title) {
            $this->actingAs($this->administrator)
                ->get(route('reports.print', $slug))
                ->assertOk()
                ->assertSee('CFS Equipment Inventory and Maintenance')
                ->assertSee($title);
        }

        $this->actingAs(User::factory()->create())
            ->get(route('reports.print', 'equipment-inventory'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-RPT-'.uniqid(),
            'equipment_name' => 'Report Equipment',
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }

    private function createMaintenanceSchedule(array $overrides = []): MaintenanceSchedule
    {
        return MaintenanceSchedule::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'maintenance_type' => 'Preventive maintenance',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => now()->toDateString(),
            'assigned_user_id' => $this->administrator->id,
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ], $overrides));
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->administrator->id,
            'title' => 'Report work order',
            'problem_description' => 'Reportable work order',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createRecommendation(array $overrides = []): MaintenanceRecommendation
    {
        return MaintenanceRecommendation::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Report recommendation',
            'explanation' => 'Reportable recommendation',
            'risk_level' => 'High',
            'recommended_action' => 'Schedule maintenance',
            'generated_at' => now(),
            'status' => 'Open',
            'action_status' => 'Pending',
        ], $overrides));
    }
}
