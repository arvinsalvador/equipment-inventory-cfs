<?php

namespace Tests\Feature\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_view_create_update_and_archive_equipment(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $equipment = $this->createEquipment();

        $this->assertTrue($administrator->can('view', $equipment));
        $this->assertTrue($administrator->can('create', Equipment::class));
        $this->assertTrue($administrator->can('update', $equipment));
        $this->assertTrue($administrator->can('archive', $equipment));
    }

    public function test_staff_can_view_create_and_update_equipment(): void
    {
        $staff = $this->userWithRole('Staff');
        $equipment = $this->createEquipment();

        $this->assertTrue($staff->can('view', $equipment));
        $this->assertTrue($staff->can('create', Equipment::class));
        $this->assertTrue($staff->can('update', $equipment));
        $this->assertFalse($staff->can('archive', $equipment));
    }

    public function test_technician_can_view_equipment(): void
    {
        $technician = $this->userWithRole('Technician');
        $equipment = $this->createEquipment();

        $this->assertTrue($technician->can('view', $equipment));
    }

    public function test_technician_cannot_create_equipment(): void
    {
        $technician = $this->userWithRole('Technician');

        $this->assertFalse($technician->can('create', Equipment::class));
    }

    public function test_technician_cannot_update_equipment(): void
    {
        $technician = $this->userWithRole('Technician');
        $equipment = $this->createEquipment();

        $this->assertFalse($technician->can('update', $equipment));
    }

    public function test_staff_and_technician_cannot_permanently_delete_equipment(): void
    {
        $equipment = $this->createEquipment();

        $this->assertFalse($this->userWithRole('Staff')->can('forceDelete', $equipment));
        $this->assertFalse($this->userWithRole('Technician')->can('forceDelete', $equipment));
    }

    public function test_unauthorized_user_cannot_view_equipment(): void
    {
        $user = User::factory()->create();
        $equipment = $this->createEquipment();

        $this->assertFalse($user->can('view', $equipment));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function createEquipment(): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create([
            'equipment_code' => 'EQ-TEST-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);
    }
}
