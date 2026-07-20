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
use Database\Seeders\OfficialEquipmentInventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClimateFieldSchoolDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_equipment_inventory_seeder_builds_complete_demo_dataset(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing.admin@example.test',
        ]);

        $this->seed(OfficialEquipmentInventorySeeder::class);

        $this->assertDatabaseHas('users', ['email' => $existingUser->email]);
        $this->assertDatabaseHas('locations', ['name' => 'SNSU Del Carmen Campus']);
        $this->assertStringContainsString(
            'SNSU Del Carmen Campus',
            Location::where('name', 'SNSU Del Carmen Campus')->value('description'),
        );

        foreach ([
            '10604010' => 'Building',
            '10604020' => 'School Buildings',
            '10604990' => 'Other Structures',
            '10605020' => 'Office Equipment',
            '10607010' => 'Furniture & Fixtures',
            '10605030' => 'Information and Communication Technology Equipment',
            '10607020' => 'Books',
            '10605040' => 'Agricultural, Fishery & Forestry Equipment',
            '10605110' => 'Medical, Dental & Laboratory Equipment',
            '10605140' => 'Technical & Scientific Equipment',
        ] as $accountCode => $category) {
            $this->assertDatabaseHas('equipment_categories', ['account_code' => $accountCode, 'name' => $category]);
        }

        $this->assertSame(46, Equipment::count());
        $this->assertSame(Equipment::count(), Equipment::distinct('equipment_code')->count('equipment_code'));
        $this->assertSame(Equipment::count(), Equipment::whereNotNull('qr_identifier')->count());
        $this->assertSame(Equipment::count(), Equipment::whereNotNull('acquisition_date')->whereNotNull('acquisition_cost')->count());

        $laptop = Equipment::where('equipment_name', 'Acer Nitro 5 Laptop')->firstOrFail();
        $this->assertSame('22-ICTE-001-002DC', $laptop->property_number);
        $this->assertStringContainsString('AN515-57-5620', $laptop->description);
        $this->assertStringContainsString('Source document: Account-Code-10604010.docx', $laptop->remarks);
        $this->assertGreaterThan(0, (float) $laptop->acquisition_cost);

        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Koppel Floor-Mounted Air Conditioner']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Samsung 85-inch Smart TV']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Fish Breeding Tank']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Dental Chair']);
        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Garmin GPS with Echosounder']);

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

        $technicalCategory = EquipmentCategory::where('account_code', '10605140')->firstOrFail();
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
