<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'workspace.view', 'workspace.manage', 'workspace.invite',
            'imports.upload', 'imports.view',
            'orders.view', 'settlements.view', 'bank.view', 'gst.view',
            'reconciliation.run', 'reconciliation.view',
            'reports.export',
            'products.view', 'products.manage',
            'competitors.manage',
            'ai.chat',
            'admin.users', 'admin.platform',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roleMap = [
            'platform_admin' => $permissions,
            'workspace_admin' => array_diff($permissions, ['admin.users', 'admin.platform']),
            'seller' => [
                'workspace.view', 'imports.upload', 'imports.view',
                'orders.view', 'settlements.view', 'bank.view', 'gst.view',
                'reconciliation.run', 'reconciliation.view', 'reports.export',
                'products.view', 'products.manage', 'competitors.manage', 'ai.chat',
            ],
            'accountant' => [
                'workspace.view', 'imports.upload', 'imports.view',
                'orders.view', 'settlements.view', 'bank.view', 'gst.view',
                'reconciliation.run', 'reconciliation.view', 'reports.export', 'ai.chat',
            ],
            'agency' => [
                'workspace.view', 'imports.upload', 'imports.view',
                'products.view', 'products.manage', 'competitors.manage',
                'reports.export', 'ai.chat',
            ],
        ];

        foreach ($roleMap as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
