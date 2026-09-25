<?php

namespace Database\Seeders;

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Accounts\Authorization\StaffRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Synchronises the permission catalogue and staff role bundles.
 *
 * Idempotent: safe to run on every deploy. Permissions removed from the enum
 * are not deleted automatically (that is a reviewed, audited change).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (StaffRole::cases() as $staffRole) {
            $role = RoleModel::findOrCreate($staffRole->value, 'web');
            $role->syncPermissions(array_map(
                static fn (Permission $permission): string => $permission->value,
                $staffRole->permissions(),
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
