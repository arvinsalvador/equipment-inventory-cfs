<?php

namespace Database\Seeders;

use App\Models\EquipmentCategory;
use App\Models\Location;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public const EQUIPMENT_CATEGORIES = [
        'Weather monitoring equipment',
        'Agricultural equipment',
        'Laboratory equipment',
        'Computers',
        'Air-conditioning units',
        'Office equipment',
        'Water systems',
        'Other equipment',
    ];

    public function run(): void
    {
        $campus = Location::updateOrCreate(
            ['name' => 'SNSU Del Carmen Campus', 'parent_id' => null],
            ['type' => 'Campus', 'is_active' => true]
        );

        $building = Location::updateOrCreate(
            ['name' => 'Climate Field School Building', 'parent_id' => $campus->id],
            ['type' => 'Building', 'is_active' => true]
        );

        Location::updateOrCreate(
            ['name' => 'Climate Field School Main Room', 'parent_id' => $building->id],
            ['type' => 'Room', 'is_active' => true]
        );

        Location::updateOrCreate(
            ['name' => 'Climate Field School Storage Area', 'parent_id' => $building->id],
            ['type' => 'Area', 'is_active' => true]
        );

        Location::updateOrCreate(
            ['name' => 'Other Campus Location', 'parent_id' => $campus->id],
            ['type' => 'Other', 'is_active' => true]
        );

        foreach (self::EQUIPMENT_CATEGORIES as $category) {
            EquipmentCategory::updateOrCreate(
                ['name' => $category],
                ['is_active' => true]
            );
        }
    }
}
