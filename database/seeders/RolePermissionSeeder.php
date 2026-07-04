<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const ROLES = [
        'Administrator',
        'Staff',
        'Technician',
    ];

    public const PERMISSIONS = [
        'users.viewAny',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.assignRoles',
        'access admin panel',
        'equipment.view',
        'equipment.create',
        'equipment.update',
        'equipment.archive',
        'master-data.manage',
        'maintenance-schedules.manage',
        'maintenance-requests.submit',
        'maintenance-requests.review',
        'work-orders.view',
        'work-orders.assign',
        'work-orders.accept',
        'work-orders.update-assigned',
        'work-orders.upload-evidence',
        'work-orders.verify',
        'beyond-repair.recommend',
        'beyond-repair.approve',
        'recommendations.view',
        'recommendations.review',
        'reports.view',
        'audit.view',
        'audit.export',
    ];

    public const STAFF_PERMISSIONS = [
        'access admin panel',
        'equipment.view',
        'equipment.create',
        'equipment.update',
        'maintenance-requests.submit',
        'work-orders.view',
        'work-orders.accept',
        'work-orders.update-assigned',
        'work-orders.upload-evidence',
        'recommendations.view',
        'reports.view',
    ];

    public const TECHNICIAN_PERMISSIONS = [
        'access admin panel',
        'equipment.view',
        'work-orders.view',
        'work-orders.accept',
        'work-orders.update-assigned',
        'work-orders.upload-evidence',
        'beyond-repair.recommend',
        'recommendations.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (self::ROLES as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        Role::findByName('Administrator', 'web')->syncPermissions(self::PERMISSIONS);
        Role::findByName('Staff', 'web')->syncPermissions(self::STAFF_PERMISSIONS);
        Role::findByName('Technician', 'web')->syncPermissions(self::TECHNICIAN_PERMISSIONS);

        $this->assignAdministratorToOldestUserWhenNeeded();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignAdministratorToOldestUserWhenNeeded(): void
    {
        if (User::role('Administrator')->exists()) {
            return;
        }

        $oldestUser = User::query()
            ->oldest('created_at')
            ->oldest('id')
            ->first();

        $oldestUser?->assignRole('Administrator');
    }
}
