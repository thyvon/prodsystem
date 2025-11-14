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

use App\Models\Position;
use App\Http\Controllers\PositionController;

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

use App\Models\StockRequest;
use App\Http\Controllers\StockRequestController;

use App\Models\StockIssue;
use App\Http\Controllers\StockIssueController;

use App\Models\StockTransfer;
use App\Http\Controllers\StockTransferController;

use App\Http\Controllers\StockController;

// Approval Management
use App\Models\Approval;
use App\Http\Controllers\ApprovalController;

// Document Management
use App\Http\Controllers\DocumentTransferController;
use App\Models\DocumentTransfer;

// Digital Document Management
use App\Http\Controllers\DigitalDocsApprovalController;
use App\Models\DigitalDocsApproval;

// Purchase Request Management
use App\Http\Controllers\PurchaseRequestController;
use App\Models\PurchaseRequest;

// Telegram Bot
use App\Http\Controllers\TelegramController;

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

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Users Management
    Route::get('/users', [UserController::class, 'getUsers']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}/edit', [UserController::class, 'edit']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole']);
    Route::get('/users/campuses', [UserController::class, 'getCampuses']);
    Route::get('/users/buildings', [UserController::class, 'getBuildings']);
    Route::get('/users/departments', [UserController::class, 'getDepartments']);
    Route::get('/users/positions', [UserController::class, 'getPositions']);
    Route::get('/users/warehouses', [UserController::class, 'getWarehouses']);

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
    Route::get('/permissions-name', [PermissionController::class, 'getPermissionNames']);
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

    // Positions
    Route::get('/positions', [PositionController::class, 'getPositions'])->middleware('can:viewAny,' . Position::class);
    Route::get('/positions/{position}/edit', [PositionController::class, 'edit'])->middleware('can:update,position');
    Route::post('/positions', [PositionController::class, 'store'])->middleware('can:create,' . Position::class);
    Route::put('/positions/{position}', [PositionController::class, 'update'])->middleware('can:update,position');
    Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->middleware('can:delete,position');

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
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Product Management - Main Categories
    Route::get('/main-categories', [MainCategoryController::class, 'getMainCategories'])->middleware('can:viewAny,' . MainCategory::class)->name('api.main-categories.index');
    Route::get('/main-categories/{mainCategory}/edit', [MainCategoryController::class, 'edit'])->middleware('can:update,mainCategory')->name('api.main-categories.edit');
    Route::post('/main-categories', [MainCategoryController::class, 'store'])->middleware('can:create,' . MainCategory::class)->name('api.main-categories.store');
    Route::put('/main-categories/{mainCategory}', [MainCategoryController::class, 'update'])->middleware('can:update,mainCategory')->name('api.main-categories.update');
    Route::delete('/main-categories/{mainCategory}', [MainCategoryController::class, 'destroy'])->middleware('can:delete,mainCategory')->name('api.main-categories.destroy');

    // Product Management - Sub Categories
    Route::get('/sub-categories', [SubCategoryController::class, 'getSubCategories'])->middleware('can:viewAny,' . SubCategory::class)->name('api.sub-categories.index');
    Route::get('/sub-categories/{subCategory}/edit', [SubCategoryController::class, 'edit'])->middleware('can:update,subCategory')->name('api.sub-categories.edit');
    Route::post('/sub-categories', [SubCategoryController::class, 'store'])->middleware('can:create,' . SubCategory::class)->name('api.sub-categories.store');
    Route::put('/sub-categories/{subCategory}', [SubCategoryController::class, 'update'])->middleware('can:update,subCategory')->name('api.sub-categories.update');
    Route::delete('/sub-categories/{subCategory}', [SubCategoryController::class, 'destroy'])->middleware('can:delete,subCategory')->name('api.sub-categories.destroy');

    // Product Management - Unit of Measure
    Route::get('/unit-of-measures', [UnitController::class, 'getUnitsOfMeasure'])->middleware('can:viewAny,' . UnitOfMeasure::class)->name('api.unit-of-measures.index');
    Route::get('/unit-of-measures/{unitOfMeasure}/edit', [UnitController::class, 'edit'])->middleware('can:update,unitOfMeasure')->name('api.unit-of-measures.edit');
    Route::post('/unit-of-measures', [UnitController::class, 'store'])->middleware('can:create,' . UnitOfMeasure::class)->name('api.unit-of-measures.store');
    Route::put('/unit-of-measures/{unitOfMeasure}', [UnitController::class, 'update'])->middleware('can:update,unitOfMeasure')->name('api.unit-of-measures.update');
    Route::delete('/unit-of-measures/{unitOfMeasure}', [UnitController::class, 'destroy'])->middleware('can:delete,unitOfMeasure')->name('api.unit-of-measures.destroy');

    // Product Management - Products
    Route::get('/products', [ProductController::class, 'getProducts'])->middleware('can:viewAny,' . Product::class)->name('api.products.index');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('can:update,product')->name('api.products.edit');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:create,' . Product::class)->name('api.products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('can:update,product')->name('api.products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('can:delete,product')->name('api.products.destroy');
    Route::get('/product-variants-stock', [ProductController::class, 'getStockManagedVariants'])->middleware('can:viewAny,' . Product::class);
    Route::post('/products/import', [ProductController::class, 'import'])->middleware('can:create,' . Product::class);

    // Route::post('products/import', [ProductController::class, 'import'])->middleware('can:create,' . Product::class)->name('api.products.import');
    // Route::get('products/export', [ProductController::class, 'export'])->middleware('can:viewAny,' . Product::class)->name('api.products.export');


    // Product Management - Trashed
    Route::get('/products/trashed', [ProductController::class, 'trashed'])->middleware('can:viewAny,' . Product::class);
    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->middleware('can:restore,product');
    Route::delete('/products/{product}/force', [ProductController::class, 'forceDelete'])->middleware('can:forceDelete,product');

    // Product Variant Attributes
    Route::get('/product-variant-attributes', [ProductVariantController::class, 'getProductVariantAttributes'])->middleware('can:viewAny,' . VariantAttribute::class)->name('api.product-variant-attributes.index');
    Route::post('/product-variant-attributes', [ProductVariantController::class, 'store'])->middleware('can:create,' . VariantAttribute::class)->name('api.product-variant-attributes.store');
    Route::get('/product-variant-attributes/{productVariantAttribute}/edit', [ProductVariantController::class, 'edit'])->middleware('can:update,productVariantAttribute')->name('api.product-variant-attributes.edit');
    Route::put('/product-variant-attributes/{productVariantAttribute}', [ProductVariantController::class, 'update'])->middleware('can:update,productVariantAttribute')->name('api.product-variant-attributes.update');
    Route::delete('/product-variant-attributes/{productVariantAttribute}', [ProductVariantController::class, 'destroy'])->middleware('can:delete,productVariantAttribute')->name('api.product-variant-attributes.destroy');
    Route::post('/product-variant-attributes/{productVariantAttribute}/values', [ProductVariantController::class, 'addValues'])->middleware('can:create,productVariantAttribute')->name('api.product-variant-attributes.add-values');
    Route::get('/attributes-values', [ProductVariantController::class, 'getAttributesWithValues'])->middleware('can:viewAny,' . VariantAttribute::class)->name('api.attributes-values.index');

    // Inventory routes group
    Route::prefix('inventory')->group(function () {
        // Inventory Management - Warehouses
        Route::get('/warehouses', [WarehouseController::class, 'getWarehouses'])->middleware('can:viewAny,' . Warehouse::class);
        Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->middleware('can:update,warehouse');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('can:create,' . Warehouse::class);
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('can:update,warehouse');
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('can:delete,warehouse');
        Route::get('/warehouses/trashed', [WarehouseController::class, 'trashed'])->middleware('can:viewAny,' . Warehouse::class);
        Route::post('/warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])->middleware('can:restore,warehouse');
        Route::delete('/warehouses/{warehouse}/force', [WarehouseController::class, 'forceDelete'])->middleware('can:forceDelete,warehouse');
        Route::get('/warehouses/buildings', [WarehouseController::class, 'getBuildings'])->middleware('can:viewAny,' . Warehouse::class);

        // Inventory Items
        Route::get('/items', [StockController::class, 'getStockManagedVariants'])->middleware('can:viewAny,' . Product::class);

        // Stock Beginning
        Route::get('/stock-beginnings', [StockBeginningController::class, 'getStockBeginnings'])->middleware('can:viewAny,' . MainStockBeginning::class)->name('api.stock-beginnings.index');
        Route::post('/stock-beginnings', [StockBeginningController::class, 'store'])->middleware('can:create,' . MainStockBeginning::class)->name('api.stock-beginnings.store');
        Route::get('/stock-beginnings/{mainStockBeginning}/edit', [StockBeginningController::class, 'edit'])->middleware('can:update,mainStockBeginning')->name('api.stock-beginnings.edit');
        Route::put('/stock-beginnings/{mainStockBeginning}', [StockBeginningController::class, 'update'])->middleware('can:update,mainStockBeginning')->name('api.stock-beginnings.update');
        Route::delete('/stock-beginnings/{mainStockBeginning}', [StockBeginningController::class, 'destroy'])->middleware('can:delete,mainStockBeginning')->name('api.stock-beginnings.destroy');
        Route::get('/stock-beginnings/trashed', [StockBeginningController::class, 'getTrashed'])->middleware('can:viewAny,' . MainStockBeginning::class)->name('api.stock-beginnings.trashed');
        Route::post('/stock-beginnings/{mainStockBeginning}/restore', [StockBeginningController::class, 'restore'])->middleware('can:restore,mainStockBeginning')->name('api.stock-beginnings.restore');
        Route::delete('/stock-beginnings/{mainStockBeginning}/force', [StockBeginningController::class, 'forceDelete'])->middleware('can:forceDelete,mainStockBeginning')->name('api.stock-beginnings.forceDelete');
        Route::post('/stock-beginnings/import', [StockBeginningController::class, 'import'])->middleware('can:create,' . MainStockBeginning::class)->name('api.stock-beginnings.import');
        Route::get('/stock-beginnings/export', [StockBeginningController::class, 'export'])->middleware('can:viewAny,' . MainStockBeginning::class)->name('api.stock-beginnings.export');
        Route::get('/stock-beginnings/users', [StockBeginningController::class, 'getUsersForApproval'])->name('api.stock-beginnings.approval-users');
        Route::post('/stock-beginnings/{mainStockBeginning}/submit-approval', [StockBeginningController::class, 'submitApproval'])->name('api.stock-beginnings.submit-approval');
        Route::post('/stock-beginnings/{mainStockBeginning}/reassign-approval', [StockBeginningController::class, 'reassignResponder'])
            ->middleware('can:reassign,mainStockBeginning')
            ->name('api.stock-beginnings.reassign-approval');
        Route::get('/stock-beginnings/get-warehouses', [StockBeginningController::class, 'fetchWarehousesForStockBeginning'])
            ->middleware('can:viewAny,' . MainStockBeginning::class)
            ->name('api.stock-beginnings.get-warehouses');
        Route::get('/stock-beginnings/get-products', [StockBeginningController::class, 'fetProductsForStockBeginning'])
            ->middleware('can:viewAny,' . MainStockBeginning::class)
            ->name('api.stock-beginnings.get-products');

        // Stock Request
        Route::get('/stock-requests', [StockRequestController::class, 'getStockRequests'])->middleware('can:viewAny,' . StockRequest::class)->name('api.stock-requests.index');
        Route::post('/stock-requests', [StockRequestController::class, 'store'])->middleware('can:create,' . StockRequest::class)->name('api.stock-requests.store');
        Route::get('/stock-requests/{stockRequest}/edit', [StockRequestController::class, 'edit'])->middleware('can:update,stockRequest')->name('api.stock-requests.edit');
        Route::put('/stock-requests/{stockRequest}', [StockRequestController::class, 'update'])->middleware('can:update,stockRequest')->name('api.stock-requests.update');
        Route::delete('/stock-requests/{stockRequest}', [StockRequestController::class, 'destroy'])->middleware('can:delete,stockRequest')->name('api.stock-requests.destroy');
        Route::get('/stock-requests/trashed', [StockRequestController::class, 'getTrashed'])->middleware('can:viewAny,' . StockRequest::class)->name('api.stock-requests.trashed');
        Route::post('/stock-requests/{stockRequest}/restore', [StockRequestController::class, 'restore'])->middleware('can:restore,stockRequest')->name('api.stock-requests.restore');
        Route::delete('/stock-requests/{stockRequest}/force', [StockRequestController::class, 'forceDelete'])->middleware('can:forceDelete,stockRequest')->name('api.stock-requests.forceDelete');
        Route::get('/stock-requests/export', [StockRequestController::class, 'export'])->middleware('can:viewAny,' . StockRequest::class)->name('api.stock-requests.export');
        Route::get('/stock-requests/users-for-approval', [StockRequestController::class, 'getUsersForApproval'])->name('api.stock-requests.approval-users');
        Route::post('/stock-requests/{stockRequest}/submit-approval', [StockRequestController::class, 'submitApproval'])->name('api.stock-requests.submit-approval');
        Route::post('/stock-requests/{stockRequest}/reassign-approval', [StockRequestController::class, 'reassignResponder'])->middleware('can:reassign,stockRequest')->name('api.stock-requests.reassign-approval');
        Route::get('/stock-requests/get-warehouses', [StockRequestController::class, 'fetchWarehousesForStockRequest'])->middleware('can:viewAny,' . StockRequest::class)->name('api.stock-requests.get-warehouses');
        Route::get('/stock-requests/get-campuses', [StockRequestController::class, 'fetchCampusesForStockRequest'])->middleware('can:viewAny,' . StockRequest::class)->name('api.stock-requests.get-campuses');
        Route::get('/stock-requests/get-products', [StockRequestController::class, 'fetchProductsForStockRequest'])->middleware('can:viewAny,' . StockRequest::class)->name('api.stock-requests.get-products');

        // Stock Issue
        Route::get('/stock-issues', [StockIssueController::class, 'getStockIssues'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.index');
        Route::get('/stock-issue/items', [StockIssueController::class, 'getAllStockIssueItems'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issue.items');
        Route::post('/stock-issues', [StockIssueController::class, 'store'])->middleware('can:create,' . StockIssue::class)->name('api.stock-issues.store');
        Route::get('/stock-issues/{stockIssue}/edit', [StockIssueController::class, 'edit'])->middleware('can:update,stockIssue')->name('api.stock-issues.edit');
        Route::put('/stock-issues/{stockIssue}', [StockIssueController::class, 'update'])->middleware('can:update,stockIssue')->name('api.stock-issues.update');
        Route::delete('/stock-issues/{stockIssue}', [StockIssueController::class, 'destroy'])->middleware('can:delete,stockIssue')->name('api.stock-issues.destroy');
        Route::get('/stock-issues/get-stock-requests', [StockIssueController::class, 'getStockRequests'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.get-stock-requests');
        Route::get('/stock-issues/get-stock-request-items/{stockRequest}', [StockIssueController::class, 'getStockRequestItems'])->name('api.stock-issues.get-stock-request-items');
        Route::get('/stock-issues/get-campuses', [StockIssueController::class, 'getCampuses'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.get-campuses');
        Route::get('/stock-issues/get-departments', [StockIssueController::class, 'getDepartments'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.get-departments');
        Route::get('/stock-issues/get-warehouses', [StockIssueController::class, 'getWarehouses'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.get-warehouses');
        Route::get('/stock-issues/get-requesters', [StockIssueController::class, 'getRequesters'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.get-requesters');
        Route::get('/stock-issues/get-products', [StockIssueController::class, 'getProducts'])->middleware('can:viewAny,' . StockIssue::class)->name('api.stock-issues.get-products');
        Route::post('/stock-issues/import', [StockIssueController::class, 'import'])->name('api.stock-issues.import');

        // Stock Transfer
        Route::get('/stock-transfers', [StockTransferController::class, 'getStockTransfers'])->middleware('can:viewAny,' . StockTransfer::class)->name('api.stock-transfers.index');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])->middleware('can:create,' . StockTransfer::class)->name('api.stock-transfers.store');
        Route::get('/stock-transfers/{stockTransfer}/edit', [StockTransferController::class, 'getEditData'])->middleware('can:update,stockTransfer')->name('api.stock-transfers.edit');
        Route::put('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'update'])->middleware('can:update,stockTransfer')->name('api.stock-transfers.update');
        Route::get('/stock-transfers/get-warehouses-from', [StockTransferController::class, 'getWarehousesFrom'])->name('api.stock-transfers.get-warehouses-from');
        Route::get('/stock-transfers/get-warehouses-to', [StockTransferController::class, 'getWarehousesTo'])->name('api.stock-transfers.get-warehouses-to');
        Route::get('/stock-transfers/get-products', [StockTransferController::class, 'getProducts'])->name('api.stock-transfers.get-products-for-transfer');
        Route::get('/stock-transfers/get-users-for-approval', [StockTransferController::class, 'getUsersForApproval'])->name('api.stock-transfers.approval-users');
        Route::delete('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'destroy'])->middleware('can:delete,stockTransfer')->name('api.stock-transfers.destroy');
        Route::post('/stock-transfers/import', [StockTransferController::class, 'import'])->middleware('can:create,' . StockTransfer::class)->name('api.stock-transfers.import');
        Route::post('/stock-transfers/{stockTransfer}/submit-approval', [StockTransferController::class, 'submitApproval'])->name('api.stock-transfers.submit-approval');
        Route::post('/stock-transfers/{stockTransfer}/reassign-approval', [StockTransferController::class, 'reassignResponder'])->middleware('can:reassign,stockTransfer')->name('api.stock-transfers.reassign-approval');
        // // Stock Movement
        Route::get('/stock-movements', [StockController::class, 'getStockMovements'])->name('api.stock-movement.index');

        //Stock Transaction
        // Route::get('/stock-transactions', [StockTransactionController::class, 'getStockTransactions'])->name('api.stock-transactions.index');
    });
    

    // Approval Management
    Route::get('/approvals', [ApprovalController::class, 'getApprovals'])->name('api.approvals.index');

    // Document Transfers
    Route::post('/document-transfers', [DocumentTransferController::class, 'store'])->name('api.document-transfers.store');
    Route::get('/document-transfers/{documentTransfer}/edit', [DocumentTransferController::class, 'getEdit'])->name('api.document-transfers.edit');
    Route::delete('/document-transfers/{documentTransfer}', [DocumentTransferController::class, 'destroy'])->name('api.document-transfers.destroy');
    Route::put('/document-transfers/{documentTransfer}', [DocumentTransferController::class, 'update'])->name('api.document-transfers.update');
    Route::get('/document-transfers/get-receivers', [DocumentTransferController::class, 'getReceivers'])->name('api.document-transfers.get-receivers');
    Route::get('/document-transfers', [DocumentTransferController::class, 'getDocumentTransfers'])->name('api.document-transfers.index');
    Route::put('/document-transfers/{documentTransfer}/update-or-reassign', [DocumentTransferController::class, 'updateReceiversOrReceive'])->name('api.document-transfers.update-or-reassign');

    // Digital Document Approval
    Route::get('/digital-docs-approvals', [DigitalDocsApprovalController::class, 'getDigitalDocuments'])->name('api.digital-docs-approvals.index');
    Route::post('/digital-docs-approvals', [DigitalDocsApprovalController::class, 'store'])->name('api.digital-docs-approvals.store');
    Route::get('/digital-docs-approvals/{digitalDocsApproval}/edit', [DigitalDocsApprovalController::class, 'getEditData'])->name('api.digital-docs-approvals.edit');
    Route::put('/digital-docs-approvals/{digitalDocsApproval}', [DigitalDocsApprovalController::class, 'update'])->name('api.digital-docs-approvals.update');
    Route::delete('/digital-docs-approvals/{digitalDocsApproval}', [DigitalDocsApprovalController::class, 'destroy'])->name('api.digital-docs-approvals.destroy');
    Route::get('/digital-docs-approvals/get-users-for-approval', [DigitalDocsApprovalController::class, 'getApprovalUsers'])
    ->name('api.digital-docs-approvals.get-users-for-approval');
    Route::get('/digital-docs-approvals/get-users-for-reassign', [DigitalDocsApprovalController::class, 'getApprovalUsers'])->name('api.digital-docs-approvals.reassign-users');
    Route::post('/digital-docs-approvals/{digitalDocsApproval}/submit-approval', [DigitalDocsApprovalController::class, 'submitApproval'])
    ->name('api.digital-docs-approvals.submit-approval');
    Route::post('/digital-docs-approvals/{digitalDocsApproval}/reassign-approval', [DigitalDocsApprovalController::class, 'reassignResponder'])->middleware('can:reassign,digitalDocsApproval')->name('api.digital-docs-approvals.reassign-approval');


    // Purchase Request Management
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'getPurchaseRequests'])->middleware('can:viewAny,' . PurchaseRequest::class)->name('api.purchase-requests.index');
    Route::get('/purchase-requests/{purchaseRequest}/show', [PurchaseRequestController::class, 'showData'])->middleware('can:view,purchaseRequest')->name('api.purchase-requests.show');
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('api.purchase-requests.store')->middleware('can:create,' . PurchaseRequest::class);
    Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'getEditData'])->middleware('can:update,purchaseRequest')->name('api.purchase-requests.edit');
    Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->middleware('can:update,purchaseRequest')->name('api.purchase-requests.update');
    Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->middleware('can:delete,purchaseRequest')->name('api.purchase-requests.destroy');
    Route::get('/purchase-requests/get-approval-users', [PurchaseRequestController::class, 'getApprovalUsers'])
    ->name('api.purchase-requests.get-approval-users');
    // Route::post('/purchase-requests/{purchaseRequest}/submit-approval', [PurchaseRequestController::class, 'submitApproval'])
    // ->name('api.purchase-requests.submit-approval');
    Route::get('/purchase-requests/get-campuses', [PurchaseRequestController::class, 'getCampuses'])->name('api.purchase-requests.get-campuses');
    Route::get('/purchase-requests/get-departments', [PurchaseRequestController::class, 'getDepartments'])->name('api.purchase-requests.get-departments');
    Route::get('/purchase-requests/get-products', [PurchaseRequestController::class, 'getProducts'])->name('api.purchase-requests.get-products');
    Route::post('/purchase-requests/{purchaseRequest}/reassign-approval', [PurchaseRequestController::class, 'reassignResponder'])->middleware('can:reassign,purchaseRequest')->name('api.purchase-requests.reassign-approval');
    Route::post('/purchase-requests/import-items', [PurchaseRequestController::class, 'importItems'])->name('api.purchase-requests.import-items');
});

// Telegram Bot Document Transfer Webhook
Route::post('/telegram/webhook', [DocumentTransferController::class, 'webhook'])->withoutMiddleware(['auth']);
