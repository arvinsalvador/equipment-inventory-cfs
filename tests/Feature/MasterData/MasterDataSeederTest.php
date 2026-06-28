<?php

namespace Tests\Feature\MasterData;

use App\Models\EquipmentCategory;
use App\Models\Location;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locations_are_created(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertDatabaseHas('locations', ['name' => 'SNSU Del Carmen Campus', 'type' => 'Campus']);
        $this->assertDatabaseHas('locations', ['name' => 'Climate Field School Building', 'type' => 'Building']);
        $this->assertDatabaseHas('locations', ['name' => 'Other Campus Location', 'type' => 'Other']);
    }

    public function test_default_equipment_categories_are_created(): void
    {
        $this->seed(MasterDataSeeder::class);

        foreach (MasterDataSeeder::EQUIPMENT_CATEGORIES as $category) {
            $this->assertDatabaseHas('equipment_categories', ['name' => $category]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(5, Location::count());
        $this->assertSame(count(MasterDataSeeder::EQUIPMENT_CATEGORIES), EquipmentCategory::count());
    }

    public function test_climate_field_school_building_exists_under_snsu_del_carmen_campus(): void
    {
        $this->seed(MasterDataSeeder::class);

        $campus = Location::where('name', 'SNSU Del Carmen Campus')->firstOrFail();
        $building = Location::where('name', 'Climate Field School Building')->firstOrFail();

        $this->assertTrue($building->parent->is($campus));
    }

    public function test_climate_field_school_rooms_and_areas_exist_under_building(): void
    {
        $this->seed(MasterDataSeeder::class);

        $building = Location::where('name', 'Climate Field School Building')->firstOrFail();

        $this->assertTrue($building->children()->where('name', 'Climate Field School Main Room')->exists());
        $this->assertTrue($building->children()->where('name', 'Climate Field School Storage Area')->exists());
    }
}
