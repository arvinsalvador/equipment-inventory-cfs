<?php

namespace Tests\Feature\MasterData;

use App\Models\EquipmentCategory;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_can_have_parent_and_children(): void
    {
        $campus = Location::create(['name' => 'Campus', 'type' => 'Campus']);
        $building = Location::create(['name' => 'Building', 'type' => 'Building', 'parent_id' => $campus->id]);

        $this->assertTrue($building->parent->is($campus));
        $this->assertTrue($campus->children->contains($building));
    }

    public function test_location_active_scope_returns_active_records_only(): void
    {
        $active = Location::create(['name' => 'Active location', 'type' => 'Room', 'is_active' => true]);
        $inactive = Location::create(['name' => 'Inactive location', 'type' => 'Room', 'is_active' => false]);

        $activeLocations = Location::active()->pluck('id');

        $this->assertTrue($activeLocations->contains($active->id));
        $this->assertFalse($activeLocations->contains($inactive->id));
    }

    public function test_equipment_category_active_scope_returns_active_records_only(): void
    {
        $active = EquipmentCategory::create(['name' => 'Active category', 'is_active' => true]);
        $inactive = EquipmentCategory::create(['name' => 'Inactive category', 'is_active' => false]);

        $activeCategories = EquipmentCategory::active()->pluck('id');

        $this->assertTrue($activeCategories->contains($active->id));
        $this->assertFalse($activeCategories->contains($inactive->id));
    }
}
