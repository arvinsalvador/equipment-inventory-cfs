<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_are_seeded(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(RolePermissionSeeder::ROLES, Role::pluck('name')->all());
        $this->assertEqualsCanonicalizing(RolePermissionSeeder::PERMISSIONS, Permission::pluck('name')->all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(3, Role::count());
        $this->assertSame(count(RolePermissionSeeder::PERMISSIONS), Permission::count());
    }

    public function test_administrator_receives_all_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $permissions = Role::findByName('Administrator')->permissions->pluck('name')->all();

        $this->assertEqualsCanonicalizing(RolePermissionSeeder::PERMISSIONS, $permissions);
    }

    public function test_staff_receives_only_intended_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $permissions = Role::findByName('Staff')->permissions->pluck('name')->all();

        $this->assertEqualsCanonicalizing(RolePermissionSeeder::STAFF_PERMISSIONS, $permissions);
    }

    public function test_technician_receives_only_intended_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $permissions = Role::findByName('Technician')->permissions->pluck('name')->all();

        $this->assertEqualsCanonicalizing(RolePermissionSeeder::TECHNICIAN_PERMISSIONS, $permissions);
    }

    public function test_oldest_existing_user_becomes_administrator_when_none_exists(): void
    {
        $oldestUser = User::factory()->create(['created_at' => now()->subDay()]);
        $newestUser = User::factory()->create();

        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue($oldestUser->fresh()->hasRole('Administrator'));
        $this->assertFalse($newestUser->fresh()->hasRole('Administrator'));
    }
}
