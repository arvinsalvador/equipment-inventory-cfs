<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\MaintenanceAnalytics;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MaintenanceAnalyticsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $technician;

    private EquipmentCategory $category;

    private Location $location;

    private Equipment $weatherStation;

    private Equipment $defectivePrinter;

    private Equipment $beyondRepairPump;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrator = $this->userWithRole('Administrator');
        $this->technician = $this->userWithRole('Technician');
        $this->category = EquipmentCategory::create(['name' => 'Analytics Category']);
        $this->location = Location::create(['name' => 'Analytics Lab', 'type' => 'Room']);

        $this->weatherStation = $this->createEquipment('EQ-AN-001', 'Analytics Weather Station', 'Good');
        $this->defectivePrinter = $this->createEquipment('EQ-AN-002', 'Defective Analytics Printer', 'Defective');
        $this->beyondRepairPump = $this->createEquipment('EQ-AN-003', 'Beyond Repair Pump', 'Beyond repair');

        $this->seedAnalyticsData();
    }

    public function test_authorized_user_can_access_maintenance_analytics_dashboard(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/maintenance-analytics')
            ->assertOk()
            ->assertSee('Maintenance Analytics Dashboard')
            ->assertSee('System Insights');
    }

    public function test_unauthorized_user_cannot_access_maintenance_analytics_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/maintenance-analytics')
            ->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->assertFalse(MaintenanceAnalytics::canAccess());
        $this->assertFalse(MaintenanceAnalytics::shouldRegisterNavigation());
    }

    public function test_executive_kpis_and_equipment_health_are_calculated(): void
    {
        $analytics = app(MaintenanceAnalyticsService::class)->dashboard('this_month');

        $this->assertSame(3, $analytics['executive_kpis']['total_equipment']['value']);
        $this->assertSame(1, $analytics['executive_kpis']['open_work_orders']['value']);
        $this->assertSame(1, $analytics['executive_kpis']['overdue_maintenance']['value']);
        $this->assertSame(1, $analytics['executive_kpis']['critical_ai_recommendations']['value']);
        $this->assertSame(2, $analytics['executive_kpis']['pending_recommendation_actions']['value']);

        $health = collect($analytics['equipment_health'])->keyBy('label');
        $this->assertSame(1, $health['Healthy / Good']['count']);
        $this->assertSame(1, $health['Defective']['count']);
        $this->assertSame(1, $health['Beyond repair']['count']);
    }

    public function test_work_order_schedule_request_and_ai_analytics_are_calculated(): void
    {
        $analytics = app(MaintenanceAnalyticsService::class)->dashboard('this_month');
        $workOrderStatuses = collect($analytics['work_orders']['statuses'])->keyBy('label');
        $scheduleStatuses = collect($analytics['maintenance_schedules']['statuses'])->keyBy('label');
        $requestStatuses = collect($analytics['maintenance_requests']['statuses'])->keyBy('label');
        $riskCounts = collect($analytics['ai_recommendations']['risks'])->keyBy('label');
        $actionCounts = collect($analytics['ai_recommendations']['actions'])->keyBy('label');
        $ruleCounts = collect($analytics['ai_recommendations']['rules'])->keyBy('label');

        $this->assertSame(1, $workOrderStatuses['Completed']['count']);
        $this->assertSame(1, $workOrderStatuses['In progress']['count']);
        $this->assertSame(1, $workOrderStatuses['Cancelled']['count']);
        $this->assertSame(33.3, $analytics['work_orders']['completion_rate']);

        $this->assertSame(1, $scheduleStatuses['Overdue']['count']);
        $this->assertSame(1, $scheduleStatuses['Due today']['count']);
        $this->assertSame(1, $scheduleStatuses['Completed']['count']);

        $this->assertSame(1, $requestStatuses['Submitted']['count']);
        $this->assertSame(1, $requestStatuses['Converted']['count']);

        $this->assertSame(1, $riskCounts['Critical']['count']);
        $this->assertSame(2, $actionCounts['Pending']['count']);
        $this->assertSame(1, $ruleCounts['Overdue Maintenance']['count']);
    }

    public function test_top_tables_and_trends_are_available(): void
    {
        $analytics = app(MaintenanceAnalyticsService::class)->dashboard('this_month');

        $technician = collect($analytics['technician_performance'])->firstWhere('name', $this->technician->name);
        $this->assertSame(3, $technician['assigned']);
        $this->assertSame(1, $technician['completed']);
        $this->assertSame(1, $technician['pending']);

        $equipment = collect($analytics['equipment_reliability'])->firstWhere('equipment_code', 'EQ-AN-001');
        $this->assertSame(1, $equipment['completed_work_orders']);
        $this->assertSame(1, $equipment['open_work_orders']);

        $location = collect($analytics['location_analytics'])->firstWhere('location', 'Analytics Lab');
        $this->assertSame(3, $location['equipment_count']);
        $this->assertSame(1, $location['open_work_orders']);

        $this->assertCount(6, $analytics['recommendation_trend']);
        $this->assertCount(6, $analytics['workload_trend']);
    }

    public function test_dashboard_renders_analytics_sections_and_insights(): void
    {
        $this->actingAs($this->administrator)
            ->get('/admin/maintenance-analytics?period=this_month')
            ->assertOk()
            ->assertSee('Equipment Health Analytics')
            ->assertSee('Work Order Analytics')
            ->assertSee('Maintenance Schedule Analytics')
            ->assertSee('Maintenance Request Analytics')
            ->assertSee('AI Recommendation Analytics')
            ->assertSee('Technician Performance Analytics')
            ->assertSee('Equipment Reliability Analytics')
            ->assertSee('Location-Based Analytics')
            ->assertSee('AI Recommendation Trend')
            ->assertSee('Maintenance Workload Trend')
            ->assertSee('There are 1 critical recommendations requiring attention.')
            ->assertSee('1 preventive maintenance schedules are overdue.')
            ->assertSee('Analytics Weather Station')
            ->assertSee('Analytics Lab');
    }

    private function seedAnalyticsData(): void
    {
        $this->createWorkOrder([
            'work_order_number' => 'WO-AN-0001',
            'equipment_id' => $this->weatherStation->id,
            'status' => 'Completed',
            'created_at' => now()->subDays(4),
            'completed_at' => now()->subDays(2),
            'verified_at' => now()->subDay(),
        ]);

        $this->createWorkOrder([
            'work_order_number' => 'WO-AN-0002',
            'equipment_id' => $this->weatherStation->id,
            'status' => 'In progress',
            'created_at' => now()->subDay(),
        ]);

        $this->createWorkOrder([
            'work_order_number' => 'WO-AN-0003',
            'equipment_id' => $this->defectivePrinter->id,
            'status' => 'Cancelled',
            'created_at' => now()->subDay(),
        ]);

        MaintenanceSchedule::create([
            'equipment_id' => $this->weatherStation->id,
            'maintenance_type' => 'Overdue preventive check',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => now()->subDay()->toDateString(),
            'assigned_user_id' => $this->technician->id,
            'priority' => 'High',
            'status' => 'Upcoming',
        ]);

        MaintenanceSchedule::create([
            'equipment_id' => $this->weatherStation->id,
            'maintenance_type' => 'Today preventive check',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => now()->toDateString(),
            'assigned_user_id' => $this->technician->id,
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ]);

        MaintenanceSchedule::create([
            'equipment_id' => $this->weatherStation->id,
            'maintenance_type' => 'Completed preventive check',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => now()->subDays(2)->toDateString(),
            'assigned_user_id' => $this->technician->id,
            'priority' => 'Normal',
            'status' => 'Completed',
            'completed_at' => now()->subDay(),
            'completed_by' => $this->technician->id,
        ]);

        MaintenanceRequest::create([
            'equipment_id' => $this->weatherStation->id,
            'submitted_by' => $this->administrator->id,
            'problem_description' => 'Analytics submitted request',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ]);

        MaintenanceRequest::create([
            'equipment_id' => $this->defectivePrinter->id,
            'submitted_by' => $this->administrator->id,
            'problem_description' => 'Analytics converted request',
            'severity' => 'High',
            'status' => 'Converted',
            'converted_at' => now(),
            'converted_by' => $this->administrator->id,
        ]);

        $this->createRecommendation([
            'equipment_id' => $this->weatherStation->id,
            'title' => 'Critical analytics recommendation',
            'rule_key' => 'overdue_maintenance',
            'risk_level' => 'Critical',
            'status' => 'Open',
            'action_status' => 'Pending',
        ]);

        $this->createRecommendation([
            'equipment_id' => $this->defectivePrinter->id,
            'title' => 'High analytics recommendation',
            'rule_key' => 'defective_without_work_order',
            'risk_level' => 'High',
            'status' => 'Reviewed',
            'action_status' => 'Pending',
        ]);

        $this->createRecommendation([
            'equipment_id' => $this->beyondRepairPump->id,
            'title' => 'Executed analytics recommendation',
            'rule_key' => 'repeated_repairs',
            'risk_level' => 'Moderate',
            'status' => 'Resolved',
            'action_status' => 'Executed',
            'resolved_at' => now(),
            'actioned_at' => now(),
        ]);
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
            'operational_status' => $condition === 'Defective' ? 'Under maintenance' : 'Available',
        ]);
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'equipment_id' => $this->weatherStation->id,
            'created_by' => $this->administrator->id,
            'assigned_to' => $this->technician->id,
            'accepted_by' => $this->technician->id,
            'title' => 'Analytics work order',
            'problem_description' => 'Analytics work order description',
            'priority' => 'Normal',
            'status' => 'Available',
        ], $overrides));
    }

    private function createRecommendation(array $overrides = []): MaintenanceRecommendation
    {
        return MaintenanceRecommendation::create(array_merge([
            'equipment_id' => $this->weatherStation->id,
            'rule_key' => 'overdue_maintenance',
            'title' => 'Analytics recommendation',
            'explanation' => 'Analytics recommendation explanation',
            'risk_level' => 'High',
            'recommended_action' => 'Schedule maintenance',
            'generated_at' => now(),
            'status' => 'Open',
            'action_status' => 'Pending',
        ], $overrides));
    }
}
