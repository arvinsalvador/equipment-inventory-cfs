<?php

namespace Tests\Feature\Equipment;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use App\Support\OfficialEquipmentInventoryDocumentParser;
use Database\Seeders\OfficialEquipmentInventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialEquipmentInventoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_only_official_document_rows_and_reports_source_issues(): void
    {
        $parser = app(OfficialEquipmentInventoryDocumentParser::class);
        $result = $parser->parse();

        $this->assertCount(10, $result['summary']['account_code_sections_found']);
        $this->assertCount(46, $result['rows']);
        $this->assertSame(10, $result['summary']['rows_skipped']);
        $this->assertSame(1, $result['summary']['missing_property_numbers']);
        $this->assertCount(1, $result['summary']['malformed_unit_values']);
        $this->assertCount(0, $result['summary']['unparsed_dates']);
        $this->assertCount(0, $result['summary']['duplicate_conflicts']);

        $this->assertSame('Admin bldg.(cashier/Acctg/HR Offices)', $result['rows'][0]['equipment_name']);
        $this->assertSame('1981-06-03', $result['rows'][0]['acquisition_date']);
        $this->assertSame('3028973.05', $result['rows'][0]['acquisition_cost']);
        $this->assertSame('Acer Nitro 5 Laptop', collect($result['rows'])->firstWhere('property_number', '22-ICTE-001-002DC')['equipment_name']);
        $this->assertSame('61889.00', collect($result['rows'])->firstWhere('property_number', '20-OE-001-007DC')['acquisition_cost']);
    }

    public function test_parser_supports_required_date_and_unit_value_formats(): void
    {
        $parser = app(OfficialEquipmentInventoryDocumentParser::class);

        foreach ([
            '6/3/1981' => '1981-06-03',
            '13-Jan-19' => '2019-01-13',
            'Dec-8-2016' => '2016-12-08',
            'Sept-10-2016' => '2016-09-10',
            'March 27,2020' => '2020-03-27',
            'Nov 22,2018' => '2018-11-22',
            'Jan 28, 2022' => '2022-01-28',
            '04/19/2022' => '2022-04-19',
            'June 16, 2025' => '2025-06-16',
            'May 5, 2023' => '2023-05-05',
            '2/23/2024' => '2024-02-23',
            '02/19/25' => '2025-02-19',
            '1970' => '1970-01-01',
            '1981' => '1981-01-01',
            '1985' => '1985-01-01',
        ] as $source => $expected) {
            $this->assertSame($expected, $parser->parseDate($source));
        }

        $this->assertSame(['36574001.94', null], $parser->parseUnitValue('PHP 36,574,001.94'));
        $this->assertSame(['61889.00', '61,889,00'], $parser->parseUnitValue('61,889,00'));
    }

    public function test_official_inventory_seeder_imports_document_equipment_and_regenerates_workflows(): void
    {
        Storage::fake('public');

        $this->seed(OfficialEquipmentInventorySeeder::class);

        $this->assertSame(46, Equipment::count());
        $this->assertSame(46, Equipment::distinct('equipment_code')->count('equipment_code'));
        $this->assertSame(46, Equipment::whereNotNull('qr_identifier')->whereNotNull('qr_code_path')->count());
        $this->assertDatabaseMissing('equipment', ['equipment_name' => 'Garmin GPS with Echosounder']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Acer Nitro 5 Laptop', 'property_number' => '22-ICTE-001-002DC']);
        $this->assertDatabaseHas('equipment', ['equipment_name' => 'Koppel Floor-Mounted Air Conditioner', 'property_number' => '25-07OE-22DC-1']);
        $this->assertDatabaseHas('locations', ['name' => 'Science Lab']);
        $this->assertDatabaseHas('locations', ['name' => 'RIS 1']);

        foreach (OfficialEquipmentInventoryDocumentParser::APPROVED_CATEGORIES as $accountCode => $name) {
            $this->assertDatabaseHas('equipment_categories', ['account_code' => $accountCode, 'name' => $name]);
            $this->assertDatabaseMissing('equipment_categories', ['name' => $accountCode.' - '.$name]);
        }

        $this->assertGreaterThan(0, MaintenanceSchedule::count());
        $this->assertGreaterThan(0, MaintenanceRequest::count());
        $this->assertGreaterThan(0, WorkOrder::count());
        $this->assertGreaterThan(0, WorkOrderEvidence::count());
        $this->assertGreaterThan(0, MaintenanceRecommendation::count());
        $this->assertGreaterThan(0, BudgetPlan::count());
        $this->assertGreaterThan(0, AssetActionRequest::count());
        $this->assertFalse(WorkOrder::whereDoesntHave('equipment')->exists());
    }
}
