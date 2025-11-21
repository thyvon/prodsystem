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
            'purchaseRequest' => [
                'name' => 'Purchase Request',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete', 'initial', 'approve', 'reassign','verify', 'check'],
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
                'actions' => [
                    'create', 
                    'view', 
                    'update', 
                    'delete', 
                    'restore', 
                    'forceDelete',
                    'review', 
                    'check', 
                    'approve', 
                    'reassign',
                    'viewOwnRecord',
                    'viewByDefaultWarehouse',
                    'viewByWarehouseAccess',
                    'viewByCampusAccess',
                    'viewByDefaultCampus',
                    'viewAllRecord'
                ],
            ],
            'stockIssue' => [
                'name' => 'Stock Issue',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'stockIn' => [
                'name' => 'Stock In',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete'],
            ],
            'monthlyStockReport' => [
                'name' => 'Monthly Stock Report',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete', '', 'verify', 'check', 'acknowledge', 'reassign'],
            ],
            'stockTransfer' => [
                'name' => 'Stock Transfer',
                'actions' => [
                    'create', 
                    'view', 
                    'update', 
                    'delete', 
                    'restore', 
                    'forceDelete',
                    'initial',  
                    'approve', 
                    'reassign',
                    // 'viewOwnRecord',
                    // 'viewByDefaultWarehouse',
                    // 'viewByWarehouseAccess',
                    // 'viewByCampusAccess',
                    // 'viewByDefaultCampus',
                    // 'viewAllRecord'
                ],
            ],
            'digitalDocsApproval' => [
                'name' => 'Digital Docs Approval',
                'actions' => ['create', 'view', 'update', 'delete', 'restore', 'forceDelete','reassign','verify', 'check', 'approve','initial'],
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


        $permissions = [];

        foreach ($features as $feature => $data) {
            foreach ($data['actions'] as $action) {
                $permissions[] = [
                    'name' => "$feature.$action",
                    'feature_name' => $data['name'],
                ];
            }
        }

        // Create or update permissions
        foreach ($permissions as $perm) {
            $permission = Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['guard_name' => 'web']
            );
            $permission->update(['feature_name' => $perm['feature_name']]);
        }

        // Create only admin role and assign all permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Assign admin role to user ID 1
        $user = User::find(1);
        if ($user) {
            $user->assignRole('admin');
        }
    }
}