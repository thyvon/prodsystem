<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $features = [
            'purchase_request' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            'purchase_order'   => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            'invoice_entry'    => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            'evaluation'       => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            'campus'          => ['create', 'view', 'update', 'delete'],
        ];

        $permissions = [];

        // Generate permissions dynamically
        foreach ($features as $feature => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "$feature.$action";
            }
        }

        // Create permissions
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $purchaser = Role::firstOrCreate(['name' => 'purchaser']);
        $requester = Role::firstOrCreate(['name' => 'requester']);
        $approver = Role::firstOrCreate(['name' => 'approver']);

        // Assign all permissions to admin
        $admin->syncPermissions(Permission::all());

        // Example: Assign specific permissions to roles (customize as needed)
        $purchaser->syncPermissions([
            'purchase_request.create',
            'purchase_request.view',
            'purchase_request.update',
            'purchase_order.view',
            'invoice_entry.view',
            'evaluation.view',
        ]);

        $requester->syncPermissions([
            'purchase_request.create',
            'purchase_request.view',
            'purchase_request.update',
        ]);

        $approver->syncPermissions([
            'purchase_request.view',
            'purchase_request.update',
            'purchase_order.view',
            'purchase_order.update',
        ]);

        // Assign admin role to user ID 1
        $user = User::find(1);
        if ($user) {
            $user->assignRole('admin');
        }
    }
}
