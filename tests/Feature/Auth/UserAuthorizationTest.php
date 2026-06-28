<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_without_access_admin_panel_permission_cannot_access_filament(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
    }

    public function test_administrator_can_access_user_management(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->actingAs($administrator);

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::shouldRegisterNavigation());
    }

    public function test_staff_cannot_access_user_management(): void
    {
        $staff = $this->userWithRole('Staff');

        $this->actingAs($staff);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
    }

    public function test_technician_cannot_access_user_management(): void
    {
        $technician = $this->userWithRole('Technician');

        $this->actingAs($technician);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $this->assertFalse($administrator->can('delete', $administrator));
    }

    public function test_last_administrator_cannot_be_deleted_or_demoted(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $policy = app(UserPolicy::class);

        $this->assertFalse($administrator->can('delete', $administrator));
        $this->assertFalse($policy->canRemoveAdministratorRole($administrator, 'Staff'));

        $secondAdministrator = $this->userWithRole('Administrator');

        $this->assertTrue($administrator->can('delete', $secondAdministrator));
        $this->assertTrue($policy->canRemoveAdministratorRole($secondAdministrator, 'Staff'));
    }

    public function test_staff_and_technician_do_not_have_permanent_delete_permissions(): void
    {
        $target = User::factory()->create();

        $this->assertFalse($this->userWithRole('Staff')->can('forceDelete', $target));
        $this->assertFalse($this->userWithRole('Technician')->can('forceDelete', $target));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
