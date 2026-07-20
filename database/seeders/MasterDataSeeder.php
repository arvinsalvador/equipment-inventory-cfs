<?php

namespace Database\Seeders;

use App\Models\EquipmentCategory;
use App\Models\Location;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public const EQUIPMENT_CATEGORIES = [
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

        foreach (self::EQUIPMENT_CATEGORIES as $accountCode => $category) {
            $record = EquipmentCategory::query()
                ->where('account_code', $accountCode)
                ->orWhere('name', $category)
                ->first();

            if ($record) {
                $record->update([
                    'account_code' => $accountCode,
                    'name' => $category,
                    'is_active' => true,
                ]);

                continue;
            }

            EquipmentCategory::create([
                'account_code' => $accountCode,
                'name' => $category,
                'is_active' => true,
            ]);
        }
    }
}
