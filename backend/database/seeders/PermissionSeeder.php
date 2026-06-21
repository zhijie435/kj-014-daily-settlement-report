<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'platform' => [
                'supplier.view', 'supplier.create', 'supplier.edit', 'supplier.delete',
                'distributor.view', 'distributor.create', 'distributor.edit', 'distributor.delete',
                'product.view', 'product.create', 'product.edit', 'product.delete',
                'order.view', 'order.create', 'order.edit', 'order.delete', 'order.approve',
                'payment.view', 'payment.create', 'payment.edit', 'payment.delete',
                'inventory.view', 'inventory.edit',
                'report.view', 'report.manage',
                'user.manage',
            ],
            'supplier' => [
                'product.view', 'product.create', 'product.edit',
                'order.view', 'order.ship',
                'inventory.view', 'inventory.edit',
            ],
            'distributor' => [
                'product.view',
                'order.view', 'order.create',
                'payment.view',
            ],
        ];

        $guardName = config('auth.defaults.guard');

        foreach ($permissions as $roleName => $perms) {
            foreach ($perms as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => $guardName]);
            }
        }

        $platformRole = Role::firstOrCreate(['name' => 'platform', 'guard_name' => $guardName]);
        $platformRole->syncPermissions($permissions['platform']);

        $supplierRole = Role::firstOrCreate(['name' => 'supplier', 'guard_name' => $guardName]);
        $supplierRole->syncPermissions($permissions['supplier']);

        $distributorRole = Role::firstOrCreate(['name' => 'distributor', 'guard_name' => $guardName]);
        $distributorRole->syncPermissions($permissions['distributor']);

        $agentPermissions = array_merge($permissions['distributor'], [
            'distributor.view.subordinate',
            'order.view.subordinate',
        ]);

        foreach ($agentPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => $guardName]);
        }

        $agentRole = Role::firstOrCreate(['name' => 'regional_agent', 'guard_name' => $guardName]);
        $agentRole->syncPermissions($agentPermissions);

        $admin = User::firstOrCreate(
            ['email' => 'admin@shearerline.com'],
            [
                'name' => 'System Admin',
                'phone' => '13800000000',
                'user_type' => 'platform',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ]
        );
        $admin->assignRole('platform');
    }
}
