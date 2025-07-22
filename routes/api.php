<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
// use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;

use App\Models\Campus;
use App\Http\Controllers\CampusController;

use App\Models\Building;
use App\Http\Controllers\BuildingController;

use App\Models\Division;
use App\Http\Controllers\DivisionController;

use App\Models\Department;
use App\Http\Controllers\DepartmentController;

use App\Models\TocaPolicy;
use App\Http\Controllers\TocaController;

use App\Models\TocaAmount;
use App\Http\Controllers\TocaAmountController;

// Product Management
use App\Models\MainCategory;
use App\Http\Controllers\MainCategoryController;

use App\Models\SubCategory;
use App\Http\Controllers\SubCategoryController;

use App\Models\UnitOfMeasure;
use App\Http\Controllers\UnitController;

use App\Models\Product;
use App\Http\Controllers\ProductController;

use App\Models\VariantAttribute;
use App\Models\VariantValue;
use App\Http\Controllers\ProductVariantController;

// Inventory Management
use App\Models\Warehouse;
use App\Http\Controllers\WarehouseController;

use App\Models\StockBeginning;
use App\Models\MainStockBeginning;
use App\Http\Controllers\StockBeginningController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth')->group(function () {

    // Users Management
    Route::get('/users', [UserController::class, 'getUsers']);
    Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole']);
    // Roles
    Route::get('/roles', [RoleController::class, 'getRoles']);
    Route::get('/roles-name', [RoleController::class, 'getRoleNames']);
    Route::get('/role-permissions', [RoleController::class, 'getPermissions']);
    Route::get('/roles/{role}/permissions', [RoleController::class, 'getRolePermissions']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'getPermissions']);
    Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);

    // Campuses
    Route::get('/campuses', [CampusController::class, 'getCampuses'])->middleware('can:viewAny,' . Campus::class);
    Route::get('/campuses/{campus}/edit', [CampusController::class, 'edit'])->middleware('can:update,campus');
    Route::post('/campuses', [CampusController::class, 'store'])->middleware('can:create,' . Campus::class);
    Route::put('/campuses/{campus}', [CampusController::class, 'update'])->middleware('can:update,campus');
    Route::delete('/campuses/{campus}', [CampusController::class, 'destroy'])->middleware('can:delete,campus');

    // Buildings
    Route::get('/buildings', [BuildingController::class, 'getBuildings'])->middleware('can:viewAny,' . Building::class);
    Route::get('/buildings/{building}/edit', [BuildingController::class, 'edit'])->middleware('can:update,building');
    Route::post('/buildings', [BuildingController::class, 'store'])->middleware('can:create,' . Building::class);
    Route::put('/buildings/{building}', [BuildingController::class, 'update'])->middleware('can:update,building');
    Route::delete('/buildings/{building}', [BuildingController::class, 'destroy'])->middleware('can:delete,building');

    // Divisions
    Route::get('/divisions', [DivisionController::class, 'getDivisions'])->middleware('can:viewAny,' . Division::class);
    Route::get('/divisions/{division}/edit', [DivisionController::class, 'edit'])->middleware('can:update,division');
    Route::post('/divisions', [DivisionController::class, 'store'])->middleware('can:create,' . Division::class);
    Route::put('/divisions/{division}', [DivisionController::class, 'update'])->middleware('can:update,division');
    Route::delete('/divisions/{division}', [DivisionController::class, 'destroy'])->middleware('can:delete,division');

    // Departments
    Route::get('/departments', [DepartmentController::class, 'getDepartments'])->middleware('can:viewAny,' . Department::class);
    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->middleware('can:update,department');
    Route::post('/departments', [DepartmentController::class, 'store'])->middleware('can:create,' . Department::class);
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('can:update,department');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->middleware('can:delete,department');

    // Toca Policies
    Route::get('/toca-policies', [TocaController::class, 'getTocaPolicies'])->middleware('can:viewAny,' . TocaPolicy::class);
    Route::get('/toca-policies/{tocaPolicy}/edit', [TocaController::class, 'edit'])->middleware('can:update,tocaPolicy');
    Route::post('/toca-policies', [TocaController::class, 'store'])->middleware('can:create,' . TocaPolicy::class);
    Route::put('/toca-policies/{tocaPolicy}', [TocaController::class, 'update'])->middleware('can:update,tocaPolicy');
    Route::delete('/toca-policies/{tocaPolicy}', [TocaController::class, 'destroy'])->middleware('can:delete,tocaPolicy');

    // Toca Amounts
    Route::get('/toca-amounts', [TocaAmountController::class, 'getTocaAmounts'])->middleware('can:viewAny,' . TocaAmount::class);
    Route::get('/toca-amounts/{tocaAmount}/edit', [TocaAmountController::class, 'edit'])->middleware('can:update,tocaAmount');
    Route::post('/toca-amounts', [TocaAmountController::class, 'store'])->middleware('can:create,' . TocaAmount::class);
    Route::put('/toca-amounts/{tocaAmount}', [TocaAmountController::class, 'update'])->middleware('can:update,tocaAmount');
    Route::delete('/toca-amounts/{tocaAmount}', [TocaAmountController::class, 'destroy'])->middleware('can:delete,tocaAmount');

    // Product Management - Main Categories
    Route::get('/main-categories', [MainCategoryController::class, 'getMainCategories'])->middleware('can:viewAny,' . MainCategory::class);
    Route::get('/main-categories/{mainCategory}/edit', [MainCategoryController::class, 'edit'])->middleware('can:update,mainCategory');
    Route::post('/main-categories', [MainCategoryController::class, 'store'])->middleware('can:create,' . MainCategory::class);
    Route::put('/main-categories/{mainCategory}', [MainCategoryController::class, 'update'])->middleware('can:update,mainCategory');
    Route::delete('/main-categories/{mainCategory}', [MainCategoryController::class, 'destroy'])->middleware('can:delete,mainCategory');

    // Product Management - Sub Categories
    Route::get('/sub-categories', [SubCategoryController::class, 'getSubCategories'])->middleware('can:viewAny,' . SubCategory::class);
    Route::get('/sub-categories/{subCategory}/edit', [SubCategoryController::class, 'edit'])->middleware('can:update,subCategory');
    Route::post('/sub-categories', [SubCategoryController::class, 'store'])->middleware('can:create,' . SubCategory::class);
    Route::put('/sub-categories/{subCategory}', [SubCategoryController::class, 'update'])->middleware('can:update,subCategory');
    Route::delete('/sub-categories/{subCategory}', [SubCategoryController::class, 'destroy'])->middleware('can:delete,subCategory');

    // Product Management - Unit of Measure
    Route::get('/unit-of-measures', [UnitController::class, 'getUnitsOfMeasure'])->middleware('can:viewAny,' . UnitOfMeasure::class);
    Route::get('/unit-of-measures/{unitOfMeasure}/edit', [UnitController::class, 'edit'])->middleware('can:update,unitOfMeasure');
    Route::post('/unit-of-measures', [UnitController::class, 'store'])->middleware('can:create,' . UnitOfMeasure::class);
    Route::put('/unit-of-measures/{unitOfMeasure}', [UnitController::class, 'update'])->middleware('can:update,unitOfMeasure');
    Route::delete('/unit-of-measures/{unitOfMeasure}', [UnitController::class, 'destroy'])->middleware('can:delete,unitOfMeasure');

    // Product Management - Products
    Route::get('/products', [ProductController::class, 'getProducts'])->middleware('can:viewAny,' . Product::class);
    Route::middleware('auth:sanctum')->get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('can:update,product');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:create,' . Product::class);
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('can:update,product');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('can:delete,product');
    Route::get('/product-variants-stock', [ProductController::class, 'getStockManagedVariants'])->middleware('can:viewAny,' . Product::class);

    // Product Management - Trashed
    Route::get('/products/trashed', [ProductController::class, 'trashed'])->middleware('can:viewAny,' . Product::class);
    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->middleware('can:restore,product');
    Route::delete('/products/{product}/force', [ProductController::class, 'forceDelete'])->middleware('can:forceDelete,product');

    // Product Variant Attributes
    Route::get('/product-variant-attributes', [ProductVariantController::class, 'getProductVariantAttributes']);
    Route::post('/product-variant-attributes', [ProductVariantController::class, 'store']);
    Route::get('/product-variant-attributes/{productVariantAttribute}/edit', [ProductVariantController::class, 'edit']);
    Route::put('/product-variant-attributes/{productVariantAttribute}', [ProductVariantController::class, 'update']);
    Route::delete('/product-variant-attributes/{productVariantAttribute}', [ProductVariantController::class, 'destroy']);
    Route::post('/product-variant-attributes/{productVariantAttribute}/values', [ProductVariantController::class, 'addValues']);
    Route::get('/attributes-values', [ProductVariantController::class, 'getAttributesWithValues']);

    // Inventory Management - Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'getWarehouses'])->middleware('can:viewAny,' . Warehouse::class);
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->middleware('can:update,warehouse');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('can:create,' . Warehouse::class);
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('can:update,warehouse');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('can:delete,warehouse');
    Route::get('/warehouses/trashed', [WarehouseController::class, 'trashed'])->middleware('can:viewAny,' . Warehouse::class);
    Route::post('/warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])->middleware('can:restore,warehouse');
    Route::delete('/warehouses/{warehouse}/force', [WarehouseController::class, 'forceDelete'])->middleware('can:forceDelete,warehouse');

    // Inventory Items
    Route::get('/inventory/items', [ProductController::class, 'getStockManagedVariants'])->middleware('can:viewAny,' . Product::class);

    // Stock Beginning
    Route::get('/stock-beginnings', [StockBeginningController::class, 'getStockBeginnings'])->middleware('can:viewAny,' . MainStockBeginning::class);
    Route::post('/stock-beginnings', [StockBeginningController::class, 'store'])->middleware('can:create,' . MainStockBeginning::class);
    Route::get('/stock-beginnings/{mainStockBeginning}/edit', [StockBeginningController::class, 'edit'])->middleware('can:update,mainStockBeginning');
    Route::put('/stock-beginnings/{mainStockBeginning}', [StockBeginningController::class, 'update'])->middleware('can:update,mainStockBeginning');
    Route::delete('/stock-beginnings/{mainStockBeginning}', [StockBeginningController::class, 'destroy'])->middleware('can:delete,mainStockBeginning');
    Route::get('/stock-beginnings/trashed', [StockBeginningController::class, 'getTrashed'])->middleware('can:viewAny,' . MainStockBeginning::class);
    Route::post('/stock-beginnings/{mainStockBeginning}/restore', [StockBeginningController::class, 'restore'])->middleware('can:restore,mainStockBeginning');
    Route::delete('/stock-beginnings/{mainStockBeginning}/force', [StockBeginningController::class, 'forceDelete'])->middleware('can:forceDelete,mainStockBeginning');

});
