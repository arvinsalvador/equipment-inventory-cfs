<?php

namespace Tests\Feature\Seeders;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLifecycleProfile;
use App\Models\Location;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use App\Services\ExecutiveInsightService;
use Database\Seeders\ClimateFieldSchoolDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClimateFieldSchoolDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_climate_field_school_demo_seeder_builds_complete_demo_dataset(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing.admin@example.test',
        ]);

        $this->seed(ClimateFieldSchoolDemoSeeder::class);

        $this->assertDatabaseHas('users', ['email' => $existingUser->email]);
        $this->assertDatabaseHas('locations', ['name' => 'Climate Field School']);
        $this->assertStringContainsString(
            'SNSU Del Carmen Campus',
            Location::where('name', 'Climate Field School')->value('description'),
        );

        foreach ([
            'Office Equipment',
            'ICT Equipment',
            'Other Equipment',
            'Technical and Scientific Equipment',
            'Marine and Fisheries Equipment',
            'Agricultural and Forestry Equipment',
            'A.I. Equipment for Poultry',
            'A.I. Equipment for Small and Large Ruminants',
            'A.I. Equipment for Swine',
        ] as $category) {
            $this->assertDatabaseHas('equipment_categories', ['name' => $category]);
        }

        $this->assertGreaterThanOrEqual(180, Equipment::count());
        $this->assertSame(Equipment::count(), Equipment::distinct('equipment_code')->count('equipment_code'));
        $this->assertSame(Equipment::count(), Equipment::whereNotNull('qr_identifier')->count());
        $this->assertSame(Equipment::count(), Equipment::whereNotNull('acquisition_date')->whereNotNull('acquisition_cost')->count());

        $laptop = Equipment::where('equipment_name', 'Laptop')->firstOrFail();
        $this->assertSame('Laptop', $laptop->equipment_name);
        $this->assertStringContainsString('Apple MacBook Air Laptop', $laptop->description);
        $this->assertStringContainsString('Original equipment needed: Laptop for lectures and laboratories', $laptop->remarks);
        $this->assertGreaterThan(0, (float) $laptop->acquisition_cost);

        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Garmin GPS with Echosounder']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Hexcopter UAV']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'All-in-One Desktop Computer']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Printer with Scanner']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'DSLR Camera with Tripod']);

        $this->assertGreaterThan(0, MaintenanceSchedule::count());
        $this->assertGreaterThan(0, MaintenanceRequest::count());
        $this->assertGreaterThan(0, WorkOrder::count());
        $this->assertGreaterThan(0, WorkOrder::where('status', 'Completed')->count());
        $this->assertGreaterThan(0, WorkOrderEvidence::where('evidence_type', 'After maintenance')->count());
        $this->assertGreaterThan(0, MaintenanceRecommendation::count());
        $this->assertGreaterThan(0, EquipmentLifecycleProfile::whereIn('lifecycle_status', ['Replacement Candidate', 'Beyond Repair', 'High Maintenance'])->count());
        $this->assertGreaterThan(0, BudgetPlan::count());
        $this->assertGreaterThan(0, AssetActionRequest::count());
        $this->assertGreaterThan(0, SystemNotification::count());

        $technicalCategory = EquipmentCategory::where('name', 'Technical and Scientific Equipment')->firstOrFail();
        $this->assertTrue(Equipment::whereBelongsTo($technicalCategory, 'category')->exists());

        $executiveSummary = app(ExecutiveInsightService::class)->getAssetSummary();
        $this->assertGreaterThan(0, $executiveSummary['total_equipment']);
        $this->assertGreaterThan(0, $executiveSummary['total_acquisition_value']);

        $this->actingAs(User::role('Technician')->firstOrFail())
            ->get('/admin/mobile-technician-dashboard')
            ->assertOk()
            ->assertSee('Assigned Work Orders')
            ->assertSee('Quick Actions');
    }
}
