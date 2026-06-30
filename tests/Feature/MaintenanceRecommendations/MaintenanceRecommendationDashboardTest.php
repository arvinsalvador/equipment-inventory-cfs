<?php

namespace Tests\Feature\MaintenanceRecommendations;

use App\Filament\Widgets\HighestRiskEquipment;
use App\Filament\Widgets\MaintenanceRecommendationSummary;
use App\Filament\Widgets\OperationsCommandHeader;
use App\Filament\Widgets\OperationsKpiOverview;
use App\Filament\Widgets\PriorityAttention;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\RecentAiRecommendations;
use App\Filament\Widgets\RecommendationActionStatus;
use App\Filament\Widgets\RecommendationRuleDistribution;
use App\Filament\Widgets\RecommendationsByRisk;
use App\Filament\Widgets\RecommendationTimeline;
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

        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Open Recommendations']);
        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Reviewed Recommendations']);
        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Resolved Recommendations']);
        $this->assertSame(1, app(MaintenanceRecommendationSummary::class)->counts()['Dismissed Recommendations']);

        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['Critical']);
        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['High']);
        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['Moderate']);
        $this->assertSame(1, app(RecommendationsByRisk::class)->counts()['Low']);

        $this->assertSame(1, app(RecommendationRuleDistribution::class)->counts()['Overdue Maintenance']);
        $this->assertSame(1, app(RecommendationRuleDistribution::class)->counts()['Due Soon']);
    }

    public function test_dashboard_page_renders_operations_command_center(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $this->createRecommendation([
            'title' => 'Critical equipment intervention',
            'risk_level' => 'Critical',
            'rule_key' => 'defective_without_work_order',
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Smart Maintenance Command Center')
            ->assertSee('Equipment')
            ->assertSee('Requires Immediate Attention')
            ->assertSee('Critical equipment intervention')
            ->assertSee('Quick Actions')
            ->assertSee('Run Recommendation Scan');
    }

    public function test_operations_dashboard_kpis_and_header_use_existing_data(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $recommendation = $this->createRecommendation([
            'risk_level' => 'Critical',
            'action_status' => 'Pending',
            'generated_at' => now(),
        ]);

        $stats = collect(app(OperationsKpiOverview::class)->stats())->keyBy('label');

        $this->assertGreaterThanOrEqual(1, $stats['Equipment']['value']);
        $this->assertSame(1, $stats['Critical Recommendations']['value']);
        $this->assertSame(1, $stats['Pending Recommendation Actions']['value']);
        $this->assertNotNull(app(OperationsCommandHeader::class)->lastRecommendationScan());
        $this->assertSame($recommendation->fresh()->generated_at->toDayDateTimeString(), app(OperationsCommandHeader::class)->lastRecommendationScan());
    }

    public function test_dashboard_action_status_counts_are_correct(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        foreach (['Pending', 'Approved', 'Executed', 'Rejected', 'Cancelled'] as $status) {
            $this->createRecommendation(['action_status' => $status]);
        }

        $counts = app(RecommendationActionStatus::class)->counts();

        $this->assertSame(1, $counts['Pending']);
        $this->assertSame(1, $counts['Approved']);
        $this->assertSame(1, $counts['Executed']);
        $this->assertSame(1, $counts['Rejected']);
        $this->assertSame(1, $counts['Cancelled']);
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
        $this->assertNotEmpty($rows->first()->suggested_action);
    }

    public function test_priority_attention_and_recent_recommendation_cards_use_critical_records(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $critical = $this->createRecommendation([
            'title' => 'Critical equipment intervention',
            'risk_level' => 'Critical',
            'rule_key' => 'defective_without_work_order',
        ]);
        $this->createRecommendation([
            'title' => 'Moderate maintenance reminder',
            'risk_level' => 'Moderate',
            'rule_key' => 'due_soon',
        ]);

        $attentionRows = app(PriorityAttention::class)->records();
        $recentRows = app(RecentAiRecommendations::class)->records();

        $this->assertTrue($attentionRows->contains($critical));
        $this->assertSame('Critical', $attentionRows->first()->risk_level);
        $this->assertTrue($recentRows->contains($critical));
        $this->assertSame('Generate Corrective Work Order', $critical->fresh()->getSuggestedActionLabel());
    }

    public function test_quick_actions_and_timeline_are_available(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));

        $this->createRecommendation(['status' => 'Reviewed', 'reviewed_at' => now()]);

        $actions = collect(app(QuickActions::class)->actions())->pluck('label')->all();

        $this->assertContains('Run Recommendation Scan', $actions);
        $this->assertContains('Create Work Order', $actions);
        $this->assertContains('Create Maintenance Schedule', $actions);
        $this->assertContains('View Critical Recommendations', $actions);
        $this->assertNotEmpty(app(RecommendationTimeline::class)->records());
    }

    public function test_dashboard_widgets_follow_recommendation_view_authorization(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(MaintenanceRecommendationSummary::canView());
        $this->assertFalse(RecommendationsByRisk::canView());
        $this->assertFalse(RecommendationRuleDistribution::canView());
        $this->assertFalse(HighestRiskEquipment::canView());
        $this->assertFalse(RecommendationActionStatus::canView());
        $this->assertFalse(OperationsCommandHeader::canView());
        $this->assertFalse(OperationsKpiOverview::canView());
        $this->assertFalse(PriorityAttention::canView());
        $this->assertFalse(RecentAiRecommendations::canView());
        $this->assertFalse(RecommendationTimeline::canView());
        $this->assertFalse(QuickActions::canView());
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
