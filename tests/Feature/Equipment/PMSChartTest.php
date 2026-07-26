<?php

namespace Tests\Feature\Equipment;

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PMSChartTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->administrator = $this->userWithRole('Administrator');

        $category = EquipmentCategory::create(['name' => 'PMS Test Category']);
        $location = Location::create(['name' => 'PMS Test Office', 'type' => 'Office']);

        $this->equipment = Equipment::create([
            'equipment_code' => 'EQ-PMS-001',
            'equipment_name' => 'Laboratory Air Conditioner',
            'serial_number' => null,
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);
    }

    public function test_equipment_with_no_history_renders_an_empty_pms_chart(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.show', $this->equipment))
            ->assertOk()
            ->assertSee('Maintenance PMS Chart')
            ->assertSee('Laboratory Air Conditioner')
            ->assertSee('Not Recorded')
            ->assertSee('PMS Test Office')
            ->assertSee('No completed preventive maintenance has been recorded.')
            ->assertDontSee('Occupational Safety')
            ->assertDontSee('Airconditioning Units');
    }

    public function test_completed_schedules_are_sorted_and_show_the_actual_performer(): void
    {
        $technician = User::factory()->create(['name' => 'PMS Technician']);
        $this->schedule([
            'maintenance_type' => 'Newer inspection',
            'completion_remarks' => 'Cleaned filters and checked refrigerant.',
            'completed_at' => '2026-03-15 09:00:00',
            'completed_by' => $technician->id,
            'status' => 'Completed',
        ]);
        $this->schedule([
            'maintenance_type' => 'Older inspection',
            'checklist_instructions' => 'Inspect electrical terminals.',
            'completed_at' => '2026-01-10 09:00:00',
            'completed_by' => $technician->id,
            'status' => 'Completed',
        ]);
        $this->schedule([
            'maintenance_type' => 'Cancelled inspection',
            'completed_at' => null,
            'status' => 'Cancelled',
        ]);

        $response = $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.show', $this->equipment))
            ->assertOk()
            ->assertSee('Inspect electrical terminals.')
            ->assertSee('Cleaned filters and checked refrigerant.')
            ->assertSee('PMS Technician')
            ->assertDontSee('Cancelled inspection');

        $this->assertLessThan(
            strpos($response->getContent(), 'Cleaned filters and checked refrigerant.'),
            strpos($response->getContent(), 'Inspect electrical terminals.')
        );
    }

    public function test_only_verified_completed_preventive_work_orders_appear(): void
    {
        $technician = User::factory()->create(['name' => 'Verified Technician']);
        $this->linkedWorkOrder('overdue_maintenance', 'create_preventive_maintenance_schedule', [
            'status' => 'Completed',
            'action_performed' => 'Lubricated bearings and tested operation.',
            'assigned_to' => $technician->id,
            'completed_at' => '2026-02-01 08:00:00',
            'verified_at' => '2026-02-02 08:00:00',
        ]);
        $this->linkedWorkOrder('repeated_repairs', 'generate_inspection_work_order', [
            'status' => 'Completed',
            'action_performed' => 'Corrective motor replacement.',
            'assigned_to' => $technician->id,
            'completed_at' => '2026-02-03 08:00:00',
            'verified_at' => '2026-02-04 08:00:00',
        ]);
        $this->linkedWorkOrder('due_soon', 'schedule_preventive_maintenance', [
            'status' => 'Completed',
            'action_performed' => 'Unverified cleaning.',
            'assigned_to' => $technician->id,
            'completed_at' => '2026-02-05 08:00:00',
            'verified_at' => null,
        ]);

        $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.show', $this->equipment))
            ->assertOk()
            ->assertSee('Lubricated bearings and tested operation.')
            ->assertSee('Verified Technician')
            ->assertDontSee('Corrective motor replacement.')
            ->assertDontSee('Unverified cleaning.');
    }

    public function test_view_page_exposes_all_pms_chart_actions(): void
    {
        $this->actingAs($this->administrator);

        Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getRouteKey()])
            ->assertActionVisible('viewPmsChart')
            ->assertActionVisible('printPmsChart')
            ->assertActionVisible('downloadPmsChart')
            ->assertActionVisible('openPmsChart');
    }

    public function test_print_pdf_and_authorization_work(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.print', $this->equipment))
            ->assertOk()
            ->assertSee('window.print', false);

        $pdf = $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.pdf', $this->equipment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('pms-chart-EQ-PMS-001.pdf');

        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $this->actingAs(User::factory()->create())
            ->get(route('equipment.pms-chart.show', $this->equipment))
            ->assertForbidden();
    }

    public function test_long_history_is_not_truncated_and_pdf_spans_multiple_pages(): void
    {
        $technician = User::factory()->create(['name' => 'Long History Technician']);

        foreach (range(1, 70) as $index) {
            $this->schedule([
                'maintenance_type' => "PMS history item {$index}",
                'completed_at' => now()->subDays(71 - $index),
                'completed_by' => $technician->id,
                'status' => 'Completed',
            ]);
        }

        $html = $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.show', $this->equipment))
            ->assertOk()
            ->assertSee('PMS history item 1')
            ->assertSee('PMS history item 70');

        $this->assertSame(70, substr_count($html->getContent(), 'PMS history item '));

        $pdf = $this->actingAs($this->administrator)
            ->get(route('equipment.pms-chart.pdf', $this->equipment))
            ->assertOk();

        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()));
    }

    private function schedule(array $overrides): MaintenanceSchedule
    {
        return MaintenanceSchedule::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'maintenance_type' => 'Preventive Maintenance',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => '2026-01-01',
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ], $overrides));
    }

    private function linkedWorkOrder(string $ruleKey, string $actionType, array $overrides): WorkOrder
    {
        $request = MaintenanceRequest::create([
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $this->administrator->id,
            'problem_description' => 'Maintenance requested from recommendation.',
            'severity' => 'Moderate',
            'status' => 'Converted',
        ]);

        $workOrder = WorkOrder::create(array_merge([
            'maintenance_request_id' => $request->id,
            'equipment_id' => $this->equipment->id,
            'created_by' => $this->administrator->id,
            'title' => 'Recommendation work order',
            'problem_description' => 'Perform recommended maintenance.',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));

        MaintenanceRecommendation::create([
            'equipment_id' => $this->equipment->id,
            'rule_key' => $ruleKey,
            'title' => 'PMS recommendation',
            'explanation' => 'PMS recommendation explanation.',
            'recommended_action' => 'Perform preventive maintenance.',
            'priority' => 'Medium',
            'risk_level' => 'Medium',
            'status' => 'Resolved',
            'suggested_action_type' => $actionType,
            'linked_maintenance_request_id' => $request->id,
            'linked_work_order_id' => $workOrder->id,
            'generated_at' => now(),
        ]);

        return $workOrder;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
