<?php

namespace Tests\Feature\Reports;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrintableReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private EquipmentCategory $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->category = EquipmentCategory::create(['name' => 'Printable Category']);
        $this->location = Location::create(['name' => 'Printable Lab', 'type' => 'Room']);
    }

    public function test_report_center_lists_pdf_and_excel_actions(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('PDF')
            ->assertSee('Excel');
    }

    public function test_pdf_ready_report_template_renders_with_filters(): void
    {
        $included = $this->createEquipment('EQ-PDF-001', 'PDF Ready Equipment', 'Good');
        $this->createEquipment('EQ-PDF-002', 'Hidden PDF Equipment', 'Defective');

        $this->actingAs($this->administrator)
            ->get(route('reports.pdf', [
                'report' => 'equipment-inventory',
                'category' => $included->equipment_category_id,
                'condition' => 'Good',
            ]))
            ->assertOk()
            ->assertSee('Equipment Inventory Report')
            ->assertSee('Print / Save as PDF')
            ->assertSee('PDF Ready Equipment')
            ->assertSee('Condition: Good')
            ->assertDontSee('Hidden PDF Equipment');
    }

    public function test_excel_export_streams_excel_compatible_file_and_respects_filters(): void
    {
        $this->createEquipment('EQ-XLS-001', 'Excel Included Equipment', 'Good');
        $this->createEquipment('EQ-XLS-002', 'Excel Hidden Equipment', 'Defective');

        $content = $this->actingAs($this->administrator)
            ->get(route('reports.excel', [
                'report' => 'equipment-inventory',
                'condition' => 'Good',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Equipment Inventory Report', $content);
        $this->assertStringContainsString('Equipment code', $content);
        $this->assertStringContainsString('Excel Included Equipment', $content);
        $this->assertStringContainsString('Condition', $content);
        $this->assertStringNotContainsString('Excel Hidden Equipment', $content);
    }

    public function test_pdf_and_excel_exports_use_selected_columns_only(): void
    {
        $this->createEquipment('EQ-COL-001', 'Column Selected Equipment', 'Good');

        $this->actingAs($this->administrator)
            ->get(route('reports.pdf', [
                'report' => 'equipment-inventory',
                'columns' => ['equipment_code', 'equipment_name'],
            ]))
            ->assertOk()
            ->assertSee('Equipment code')
            ->assertSee('Equipment Name / Article')
            ->assertDontSee('Property number')
            ->assertDontSee('Current location');

        $content = $this->actingAs($this->administrator)
            ->get(route('reports.excel', [
                'report' => 'equipment-inventory',
                'columns' => ['equipment_code', 'equipment_name'],
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Equipment code', $content);
        $this->assertStringContainsString('Equipment Name / Article', $content);
        $this->assertStringNotContainsString('Property number', $content);
        $this->assertStringNotContainsString('Current location', $content);
    }

    public function test_ai_recommendation_pdf_and_excel_exports_render(): void
    {
        $equipment = $this->createEquipment('EQ-AI-001', 'AI Export Equipment', 'Good');
        MaintenanceRecommendation::create([
            'equipment_id' => $equipment->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Printable AI recommendation',
            'explanation' => 'Exportable recommendation.',
            'risk_level' => 'Critical',
            'recommended_action' => 'Schedule maintenance.',
            'generated_at' => now(),
            'status' => 'Open',
            'action_status' => 'Pending',
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.pdf', 'ai-recommendations'))
            ->assertOk()
            ->assertSee('AI Recommendation Report')
            ->assertSee('Printable AI recommendation');

        $content = $this->actingAs($this->administrator)
            ->get(route('reports.excel', 'ai-recommendations'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AI Recommendation Report', $content);
        $this->assertStringContainsString('Printable AI recommendation', $content);
    }

    public function test_unauthorized_user_cannot_access_pdf_or_excel_exports(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.pdf', 'equipment-inventory'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('reports.excel', 'equipment-inventory'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function createEquipment(string $code, string $name, string $condition): Equipment
    {
        return Equipment::create([
            'equipment_code' => $code,
            'equipment_name' => $name,
            'equipment_category_id' => $this->category->id,
            'current_location_id' => $this->location->id,
            'condition' => $condition,
            'operational_status' => 'Available',
        ]);
    }
}
