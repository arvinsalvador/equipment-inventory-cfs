<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use Illuminate\Database\Seeder;

class SampleEquipmentSeeder extends Seeder
{
    public const SAMPLE_CODES = [
        'EQ-CFS-0001',
        'EQ-CFS-0002',
        'EQ-CFS-0003',
    ];

    public function run(): void
    {
        $building = Location::where('name', 'Climate Field School Building')->first();
        $mainRoom = Location::where('name', 'Climate Field School Main Room')->first();
        $weather = EquipmentCategory::where('account_code', '10605140')->first();
        $computers = EquipmentCategory::where('account_code', '10605030')->first();
        $office = EquipmentCategory::where('account_code', '10605020')->first();

        if (! $building || ! $mainRoom || ! $weather || ! $computers || ! $office) {
            return;
        }

        $samples = [
            [
                'equipment_code' => 'EQ-CFS-0001',
                'equipment_name' => 'Automatic Weather Station Console',
                'equipment_category_id' => $weather->id,
                'current_location_id' => $building->id,
                'condition' => 'Good',
                'operational_status' => 'Available',
                'custodian' => 'Climate Field School',
            ],
            [
                'equipment_code' => 'EQ-CFS-0002',
                'equipment_name' => 'Training Laptop',
                'equipment_category_id' => $computers->id,
                'current_location_id' => $mainRoom->id,
                'condition' => 'Good',
                'operational_status' => 'In use',
                'custodian' => 'Climate Field School',
            ],
            [
                'equipment_code' => 'EQ-CFS-0003',
                'equipment_name' => 'Office Printer',
                'equipment_category_id' => $office->id,
                'current_location_id' => $mainRoom->id,
                'condition' => 'Fair',
                'operational_status' => 'Available',
                'custodian' => 'Climate Field School',
            ],
        ];

        foreach ($samples as $sample) {
            Equipment::updateOrCreate(
                ['equipment_code' => $sample['equipment_code']],
                $sample
            );
        }
    }
}
