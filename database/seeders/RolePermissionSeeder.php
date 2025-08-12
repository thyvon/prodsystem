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
        // Define features with human-readable names
        $features = [
            'purchase_request' => [
                'name' => 'Purchase Request',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'purchase_order' => [
                'name' => 'Purchase Order',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'invoice_entry' => [
                'name' => 'Invoice Entry',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'evaluation' => [
                'name' => 'Evaluation',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'campus' => [
                'name' => 'Campus',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'building' => [
                'name' => 'Building',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'department' => [
                'name' => 'Department',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'position' => [
                'name' => 'Position',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'division' => [
                'name' => 'Division',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'product' => [
                'name' => 'Product',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'mainCategory' => [
                'name' => 'Main Category',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'subCategory' => [
                'name' => 'Sub Category',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'unitOfMeasure' => [
                'name' => 'Unit of Measure',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'productVariantAttribute' => [
                'name' => 'Product Attribute',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'mainStockBeginning' => [
                'name' => 'Main Stock Beginning',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete', 'review', 'check', 'approve','reassign'],
            ],
            'stockRequest' => [
                'name' => 'Stock Request',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete', 'review', 'check', 'approve','reassign'],
            ],
            'warehouse' => [
                'name' => 'Warehouse',
                'actions' => ['create', 'view', 'update', 'delete'],
            ],
            'toca' => [
                'name' => 'TOCA',
                'actions' => ['create', 'view', 'update', 'delete'],
            ]
        ];

        // Update existing permissions with feature_name
        foreach ($features as $feature => $data) {
            Permission::where('name', 'like', "$feature.%")->update(['feature_name' => $data['name']]);
        }

        $permissions = [];

        // Generate permissions dynamically
        foreach ($features as $feature => $data) {
            foreach ($data['actions'] as $action) {
                $permissions[] = [
                    'name' => "$feature.$action",
                    'feature_name' => $data['name'],
                ];
            }
        }

        // Create or update permissions with feature_name
        foreach ($permissions as $perm) {
            $permission = Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['guard_name' => 'web'] // Default guard
            );
            // Ensure feature_name is set
            $permission->update(['feature_name' => $perm['feature_name']]);
        }

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $purchaser = Role::firstOrCreate(['name' => 'purchaser']);
        $requester = Role::firstOrCreate(['name' => 'requester']);
        $approver = Role::firstOrCreate(['name' => 'approver']);

        // Assign all permissions to admin
        $admin->syncPermissions(Permission::all());

        // Assign specific permissions to roles
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