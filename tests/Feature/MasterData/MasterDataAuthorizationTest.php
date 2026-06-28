<?php

namespace Tests\Feature\MasterData;

use App\Filament\Resources\EquipmentCategories\EquipmentCategoryResource;
use App\Filament\Resources\Locations\LocationResource;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_manage_locations(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $location = Location::create(['name' => 'Campus', 'type' => 'Campus']);

        $this->assertTrue($administrator->can('create', Location::class));
        $this->assertTrue($administrator->can('update', $location));
        $this->assertTrue($administrator->can('delete', $location));
    }

    public function test_administrator_can_manage_equipment_categories(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $category = EquipmentCategory::create(['name' => 'Category']);

        $this->assertTrue($administrator->can('create', EquipmentCategory::class));
        $this->assertTrue($administrator->can('update', $category));
        $this->assertTrue($administrator->can('delete', $category));
    }

    public function test_staff_cannot_manage_locations(): void
    {
        $staff = $this->userWithRole('Staff');
        $location = Location::create(['name' => 'Campus', 'type' => 'Campus']);

        $this->assertFalse($staff->can('create', Location::class));
        $this->assertFalse($staff->can('update', $location));
        $this->assertFalse($staff->can('delete', $location));
    }

    public function test_staff_cannot_manage_equipment_categories(): void
    {
        $staff = $this->userWithRole('Staff');
        $category = EquipmentCategory::create(['name' => 'Category']);

        $this->assertFalse($staff->can('create', EquipmentCategory::class));
        $this->assertFalse($staff->can('update', $category));
        $this->assertFalse($staff->can('delete', $category));
    }

    public function test_technician_cannot_manage_locations(): void
    {
        $technician = $this->userWithRole('Technician');
        $location = Location::create(['name' => 'Campus', 'type' => 'Campus']);

        $this->assertFalse($technician->can('create', Location::class));
        $this->assertFalse($technician->can('update', $location));
        $this->assertFalse($technician->can('delete', $location));
    }

    public function test_technician_cannot_manage_equipment_categories(): void
    {
        $technician = $this->userWithRole('Technician');
        $category = EquipmentCategory::create(['name' => 'Category']);

        $this->assertFalse($technician->can('create', EquipmentCategory::class));
        $this->assertFalse($technician->can('update', $category));
        $this->assertFalse($technician->can('delete', $category));
    }

    public function test_users_with_equipment_view_can_view_active_master_data(): void
    {
        $staff = $this->userWithRole('Staff');
        $activeLocation = Location::create(['name' => 'Active location', 'type' => 'Room', 'is_active' => true]);
        $inactiveLocation = Location::create(['name' => 'Inactive location', 'type' => 'Room', 'is_active' => false]);
        $activeCategory = EquipmentCategory::create(['name' => 'Active category', 'is_active' => true]);
        $inactiveCategory = EquipmentCategory::create(['name' => 'Inactive category', 'is_active' => false]);

        $this->assertTrue($staff->can('view', $activeLocation));
        $this->assertFalse($staff->can('view', $inactiveLocation));
        $this->assertTrue($staff->can('view', $activeCategory));
        $this->assertFalse($staff->can('view', $inactiveCategory));
    }

    public function test_administrator_can_access_filament_master_data_resources(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->actingAs($administrator);

        $this->assertTrue(LocationResource::canViewAny());
        $this->assertTrue(EquipmentCategoryResource::canViewAny());
        $this->get('/admin/locations')->assertSuccessful();
        $this->get('/admin/equipment-categories')->assertSuccessful();
    }

    public function test_staff_cannot_access_filament_master_data_create_or_edit_pages(): void
    {
        $staff = $this->userWithRole('Staff');
        $location = Location::create(['name' => 'Campus', 'type' => 'Campus']);
        $category = EquipmentCategory::create(['name' => 'Category']);

        $this->actingAs($staff);

        $this->assertFalse(LocationResource::canCreate());
        $this->assertFalse(EquipmentCategoryResource::canCreate());
        $this->get('/admin/locations/create')->assertForbidden();
        $this->get("/admin/locations/{$location->id}/edit")->assertForbidden();
        $this->get('/admin/equipment-categories/create')->assertForbidden();
        $this->get("/admin/equipment-categories/{$category->id}/edit")->assertForbidden();
    }

    public function test_technician_cannot_access_filament_master_data_create_or_edit_pages(): void
    {
        $technician = $this->userWithRole('Technician');
        $location = Location::create(['name' => 'Campus', 'type' => 'Campus']);
        $category = EquipmentCategory::create(['name' => 'Category']);

        $this->actingAs($technician);

        $this->assertFalse(LocationResource::canCreate());
        $this->assertFalse(EquipmentCategoryResource::canCreate());
        $this->get('/admin/locations/create')->assertForbidden();
        $this->get("/admin/locations/{$location->id}/edit")->assertForbidden();
        $this->get('/admin/equipment-categories/create')->assertForbidden();
        $this->get("/admin/equipment-categories/{$category->id}/edit")->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
