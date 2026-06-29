<?php

namespace Tests\Feature\Maintenance;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceSchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->schedule = $this->createSchedule();
    }

    public function test_administrator_can_manage_maintenance_schedules(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->assertTrue($administrator->can('viewAny', MaintenanceSchedule::class));
        $this->assertTrue($administrator->can('view', $this->schedule));
        $this->assertTrue($administrator->can('create', MaintenanceSchedule::class));
        $this->assertTrue($administrator->can('update', $this->schedule));
        $this->assertTrue($administrator->can('complete', $this->schedule));
        $this->assertTrue($administrator->can('reschedule', $this->schedule));
        $this->assertTrue($administrator->can('cancel', $this->schedule));
        $this->assertTrue($administrator->can('delete', $this->schedule));
        $this->assertFalse($administrator->can('forceDelete', $this->schedule));
    }

    public function test_staff_and_technician_can_view_but_cannot_manage_schedules_by_default(): void
    {
        foreach (['Staff', 'Technician'] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('view', $this->schedule));
            $this->assertFalse($user->can('create', MaintenanceSchedule::class));
            $this->assertFalse($user->can('update', $this->schedule));
            $this->assertFalse($user->can('complete', $this->schedule));
            $this->assertFalse($user->can('reschedule', $this->schedule));
            $this->assertFalse($user->can('cancel', $this->schedule));
            $this->assertFalse($user->can('delete', $this->schedule));
            $this->assertFalse($user->can('forceDelete', $this->schedule));
        }
    }

    public function test_user_with_equipment_view_permission_can_view_schedule(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('equipment.view');

        $this->assertTrue($user->can('view', $this->schedule));
        $this->assertFalse($user->can('update', $this->schedule));
    }

    public function test_unauthorized_user_cannot_view_or_manage_schedules(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('viewAny', MaintenanceSchedule::class));
        $this->assertFalse($user->can('view', $this->schedule));
        $this->assertFalse($user->can('create', MaintenanceSchedule::class));
        $this->assertFalse($user->can('update', $this->schedule));
        $this->assertFalse($user->can('delete', $this->schedule));
    }

    private function createSchedule(): MaintenanceSchedule
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);
        $equipment = Equipment::create([
            'equipment_code' => 'EQ-TEST-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ]);

        return MaintenanceSchedule::create([
            'equipment_id' => $equipment->id,
            'maintenance_type' => 'Preventive check',
            'maintenance_frequency' => 'Monthly',
            'scheduled_date' => today()->addDay(),
            'priority' => 'Normal',
            'status' => 'Upcoming',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
