<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\MicrosoftAuthController;

use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// For Permissions and Roles
use App\Http\Controllers\CampusController;
use App\Models\Campus;

use App\Http\Controllers\BuildingController;
use App\Models\Building;

use App\Http\Controllers\DivisionController;
use App\Models\Division;

use App\Http\Controllers\DepartmentController;
use App\Models\Department;

use App\Http\Controllers\PositionController;
use App\Models\Position;

use App\Http\Controllers\TocaController;
use App\Models\TocaPolicy;

use App\Http\Controllers\TocaAmountController;
use App\Models\TocaAmount;

// Product Management
use App\Http\Controllers\MainCategoryController;
use App\Models\MainCategory;

use App\Http\Controllers\SubCategoryController;
use App\Models\SubCategory;

use App\Http\Controllers\UnitController;
use App\Models\UnitOfMeasure;

use App\Http\Controllers\ProductController;
use App\Models\Product;

use App\Models\VariantAttribute;
use App\Models\VariantValue;
use App\Http\Controllers\ProductVariantController;

// Inventory Management
use App\Http\Controllers\WarehouseController;
use App\Models\Warehouse;

use App\Http\Controllers\WarehouseProductController;
use App\Models\WarehouseProduct;

use App\Http\Controllers\StockBeginningController;
use App\Models\MainStockBeginning;

use App\Http\Controllers\StockRequestController;
use App\Models\StockRequest;

use App\Http\Controllers\StockIssueController;
use App\Models\StockIssue;

use App\Http\Controllers\StockTransferController;
use App\Models\StockTransfer;

use App\Http\Controllers\StockInController;
use App\Models\StockIn;

use App\Http\Controllers\StockController;
use App\Models\MonthlyStockReport;

use App\Http\Controllers\StockCountController;
use App\Models\StockCount;


// Approval Management
use App\Http\Controllers\ApprovalController;
use App\Models\Approval;

// Digital Document Management
use App\Http\Controllers\DigitalDocsApprovalController;
use App\Models\DigitalDocsApproval;

// Document Management
use App\Http\Controllers\DocumentTransferController;
use App\Models\DocumentTransfer;

// Purchase Request Management
use App\Http\Controllers\PurchaseRequestController;
use App\Models\PurchaseRequest;

use App\Http\Controllers\AttachementController;

// use App\Http\Controllers\StockRequestController;
// use App\Models\StockRequest;

/*
|----------------------------------------------------------------------
| Web Routes
|----------------------------------------------------------------------
| Here is where you can register web routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them
| will be assigned to the "web" middleware group. Make something great!
|
*/

// Home Route - Choose one (Dashboard or Welcome)
Route::get('/', function () {
    return view('dashboard'); // Show the dashboard view
})->middleware(['auth'])->name('dashboard');

// Profile Routes (Authenticated Users)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes - Only accessible to users with 'admin' role
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
});

Route::middleware(['auth'])->group(function () {
    // Campuses
    Route::get('/campuses', [CampusController::class, 'index'])
        ->name('campuses.index')->middleware('can:viewAny,' . Campus::class);

    // Buildings
    Route::get('/buildings', [BuildingController::class, 'index'])
        ->name('buildings.index')->middleware('can:viewAny,' . Building::class);

    // Divisions
    Route::get('/divisions', [DivisionController::class, 'index'])
        ->name('divisions.index')->middleware('can:viewAny,' . Division::class);

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index'])
        ->name('departments.index')->middleware('can:viewAny,' . Department::class);

    // Positions
    Route::get('/positions', [PositionController::class, 'index'])
        ->name('positions.index')->middleware('can:viewAny,' . Position::class);

    // Toca Policies
    Route::get('/toca-policies', [TocaController::class, 'index'])
        ->name('tocasPolicy.index')->middleware('can:viewAny,' . TocaPolicy::class);

    // Toca Amounts
    Route::get('/toca-amounts', [TocaAmountController::class, 'index'])
        ->name('tocasPolicy.amount')->middleware('can:viewAny,' . TocaAmount::class);

    // Main Categories
    Route::get('/main-categories', [MainCategoryController::class, 'index'])
        ->name('mainCategories.index')->middleware('can:viewAny,' . MainCategory::class);

    // Sub Categories
    Route::get('/sub-categories', [SubCategoryController::class, 'index'])
        ->name('subCategories.index')->middleware('can:viewAny,' . SubCategory::class);

    // Unit of Measure
    Route::get('/unit-of-measures', [UnitController::class, 'index'])
        ->name('unitsOfMeasure.index')->middleware('can:viewAny,' . UnitOfMeasure::class);
    
    // Product Management
    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index')->middleware('can:viewAny,' . Product::class);
    Route::get('/products/create', [ProductController::class, 'create'])
        ->name('products.create')->middleware('can:create,' . Product::class);
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->name('products.edit')->middleware('can:update,' . Product::class);

   Route::get('/product-variant-attributes', [ProductVariantController::class, 'index'])
        ->name('productVariantAttributes.index')->middleware('can:viewAny,' . VariantAttribute::class);

    // Inventory routes group
    Route::prefix('inventory')->group(function () {
        // Inventory Management - Warehouses
        Route::get('/warehouses', [WarehouseController::class, 'index'])
            ->name('warehouses.index')->middleware('can:viewAny,' . Warehouse::class);
        Route::get('/warehouses/products', [WarehouseProductController::class, 'index'])->name('warehouses.products');
        Route::post('/warehouses/products/import', [WarehouseProductController::class, 'import'])->name('warehouses.products.import');

        // Inventory Items
        Route::get('/items', [StockController::class, 'stockList'])
            ->name('inventoryItems.index')->middleware('can:viewAny,' . Product::class);

        // Stock Beginnings
        Route::get('/stock-beginnings', [StockBeginningController::class, 'index'])
            ->name('stock-beginnings.index')->middleware('can:viewAny,' . MainStockBeginning::class);
        Route::get('/stock-beginnings/create', [StockBeginningController::class, 'create'])
            ->name('stock-beginnings.create')->middleware('can:create,' . MainStockBeginning::class);
        Route::get('/stock-beginnings/{mainStockBeginning}/edit', [StockBeginningController::class, 'edit'])
            ->name('stock-beginnings.edit')->middleware('can:update,mainStockBeginning');
        Route::get('/stock-beginnings/{mainStockBeginning}/show', [StockBeginningController::class, 'show'])
            ->name('stock-beginnings.show')->middleware('can:view,mainStockBeginning');

        // Stock Requests

        Route::get('/stock-requests', [StockRequestController::class, 'index'])
            ->name('stock-requests.index')->middleware('can:viewAny,' . StockRequest::class);
        Route::get('/stock-requests/create', [StockRequestController::class, 'create'])
            ->name('stock-requests.create')->middleware('can:create,' . StockRequest::class);
        Route::get('/stock-requests/{stockRequest}/edit', [StockRequestController::class, 'edit'])
            ->name('stock-requests.edit')->middleware('can:update,stockRequest');
        Route::get('/stock-requests/{stockRequest}/show', [StockRequestController::class, 'show'])
            ->name('stock-requests.show')->middleware('can:view,stockRequest');

        // Stock Issue
        Route::get('/stock-issues', [StockIssueController::class, 'index'])
            ->name('stock-issues.index')->middleware('can:viewAny,' . StockIssue::class);
        Route::get('/stock-issues/create', [StockIssueController::class, 'create'])
            ->name('stock-issues.create')->middleware('can:create,' . StockIssue::class);
        Route::get('/stock-issues/{stockIssue}/edit', [StockIssueController::class, 'edit'])
            ->name('stock-issues.edit')->middleware('can:update,stockIssue');
        Route::get('/stock-issues/{stockIssue}/show', [StockIssueController::class, 'show'])
            ->name('stock-issues.show')->middleware('can:view,stockIssue');
        Route::get('/stock-issue/items', [StockIssueController::class, 'indexItem'])
            ->name('stock-issue.items')->middleware('can:viewAny,' . StockIssue::class);

        // Stock In
        Route::get('/stock-ins', [StockInController::class, 'index'])->middleware('can:viewAny,' . StockIn::class)->name('stock-ins.index');
        Route::get('/stock-ins/create', [StockInController::class, 'create'])->middleware('can:create,' . StockIn::class)->name('stock-ins.form');
        Route::get('/stock-ins/{stockIn}/edit', [StockInController::class, 'edit'])->middleware('can:update,stockIn')->name('stock-ins.edit');
        Route::get('/stock-ins/{stockIn}/show', [StockInController::class, 'show'])->middleware('can:view,stockIn')->name('stock-ins.show');
        Route::get('/stock-in/items', [StockInController::class, 'indexItem'])
            ->name('stock-in.items')->middleware('can:viewAny,' . StockIn::class);

        // Stock Transfer
        Route::get('/stock-transfers', [StockTransferController::class, 'index'])
             ->middleware('can:viewAny,' . StockTransfer::class)->name('stock-transfers.index');
        Route::get('/stock-transfers/create', [StockTransferController::class, 'form'])->name('stock-transfers.create')
            ->middleware('can:create,' . StockTransfer::class);
        Route::get('/stock-transfers/{stockTransfer}/edit', [StockTransferController::class, 'form'])->name('stock-transfers.edit')
            ->middleware('can:update,stockTransfer');
        Route::get('/stock-transfers/{stockTransfer}/show', [StockTransferController::class, 'show'])
            ->name('stock-transfers.show')->middleware('can:view,stockTransfer');

        // Stock Count
        Route::get('/stock-counts', [StockCountController::class, 'index'])
            ->name('stock-counts.index')->middleware('can:viewAny,' . StockCount::class);
        Route::get('/stock-counts/create', [StockCountController::class, 'create'])
            ->name('stock-counts.create')->middleware('can:create,' . StockCount::class);
        Route::get('/stock-counts/{stockCount}/edit', [StockCountController::class, 'edit'])
            ->name('stock-counts.edit')->middleware('can:update,stockCount');
        Route::get('/stock-counts/{stockCount}/show', [StockCountController::class, 'show'])
            ->name('stock-counts.show')->middleware('can:view,stockCount');

        // Stock Movements
        Route::get('/stock-movements', [StockController::class, 'stockMovement'])
            ->name('stock-movements.index');

        // Stock Report
        Route::get('/stock-reports/track-report', [StockController::class, 'index'])->middleware('can:viewAny,' . MonthlyStockReport::class)
            ->name('stock-reports.index');
        Route::post('/stock-reports/print-report', [StockController::class, 'generateStockReportHtml'])->middleware('can:viewAny,' . MonthlyStockReport::class)
            ->name('stock-reports.print-report');
        Route::get('/stock-reports/monthly-report/create', [StockController::class, 'create'])->middleware('can:create,' . MonthlyStockReport::class)
            ->name('stock-reports.monthly-report.create');
        Route::get('/stock-reports/monthly-report/{monthlyStockReport}/edit', [StockController::class, 'edit'])->middleware('can:update,monthlyStockReport')
            ->name('stock-reports.monthly-report.edit');
        Route::get('/stock-reports/monthly-report', [StockController::class, 'monthlyReport'])->middleware('can:viewAny,' . MonthlyStockReport::class)
            ->name('stock-reports.monthly-report');
        Route::post('/stock-reports/monthly-report/{monthlyStockReport}/showpdf', [StockController::class, 'showpdf'])->middleware('can:view,monthlyStockReport')
            ->name('stock-reports.monthly-report.showpdf');
        // Route::post('/stock-reports/monthly-report/{monthlyStockReport}/print-pdf', [StockController::class, 'pdfReport'])->middleware('can:view,monthlyStockReport')
        //     ->name('stock-reports.monthly-report.pdfReport');
        Route::get('/stock-reports/monthly-report/{monthlyStockReport}/print-report', [StockController::class, 'htmlReport'])->middleware('can:view,monthlyStockReport')
            ->name('stock-reports.monthly-report.htmlReport');
        Route::get('/stock-reports/monthly-report/{monthlyStockReport}/show', [StockController::class, 'showDetails'])->middleware('can:view,monthlyStockReport')
            ->name('stock-reports.monthly-report.show');

        Route::get('/stock-reports/warehouse-products/report', [WarehouseProductController::class, 'getStockReportByProduct'])
            ->name('warehouses.products.report');
        // Reports Attach PR
        Route::get('/stock-reports/reports-list', [WarehouseProductController::class, 'reportIndex'])->name('warehouses.reports-list');
        Route::get('/stock-reports/reports/create-report', [WarehouseProductController::class, 'createReport'])->name('warehouses.reports.create');
        Route::get('/stock-reports/reports/{warehouseProductReport}/edit-report', [WarehouseProductController::class, 'editReport'])->name('warehouses.reports.edit-report');
        Route::get('/stock-reports/reports/{warehouseProductReport}/show-report', [WarehouseProductController::class, 'showReport'])->name('warehouses.reports.show-report');
        Route::get('/stock-reports/reports/{warehouseProductReport}/print-report', [WarehouseProductController::class, 'showPdf'])->name('warehouses.reports.print-report');
        Route::get('/stock-reports/stock-onhand-by-warehouse', [WarehouseProductController::class, 'stockOnhandByWarehouseIndex'])
            ->name('warehouses.stock-onhand-by-warehouse-index');
    });

    //Approval View Route
        //Stock Beginning View
        Route::get('/approvals/stock-beginnings/{mainStockBeginning}/show', [StockBeginningController::class, 'show'])
        ->name('approvals-stock-beginnings.show');
        //Stock Request View
        Route::get('/approvals/stock-requests/{stockRequest}/show', [StockRequestController::class, 'show'])
        ->name('approvals-stock-requests.show');
        //Stock Transfer View
        Route::get('/approvals/stock-transfers/{stockTransfer}/show', [StockTransferController::class, 'show'])
        ->name('approvals-stock-transfers.show');
        //Digital Document View
        Route::get('/approvals/digital-docs-approvals/{digitalDocsApproval}/show', [DigitalDocsApprovalController::class, 'show'])
        ->name('approvals-digital-docs-approvals.show');
        //Purchase Request View
        Route::get('/approvals/purchase-requests/{purchaseRequest}/show', [PurchaseRequestController::class, 'show'])
        ->name('approvals-purchase-requests.show');
        //Monthly Stock Report View
        Route::get('/approvals/monthly-stock-reports/{monthlyStockReport}/show', [StockController::class, 'showDetails'])
        ->name('approvals-monthly-stock-reports.show');
        //Stock Count View
        Route::get('/approvals/stock-counts/{stockCount}/show', [StockCountController::class, 'show'])
        ->name('approvals-stock-counts.show');
        //Stock Report View
        Route::get('/approvals/stock-reports/{warehouseProductReport}/show', [WarehouseProductController::class, 'showReport'])
        ->name('approvals-stock-reports.show');

    // Approval Management
    Route::get('/approvals', [ApprovalController::class, 'index'])
        ->name('approvals.index');


    // Document Transfers
    Route::get('/document-transfers', [DocumentTransferController::class, 'index'])
        ->name('document-transfers.index');
    Route::get('/document-transfers/create', [DocumentTransferController::class, 'form'])
        ->name('document-transfers.create');
    Route::get('/document-transfers/{documentTransfer}/edit', [DocumentTransferController::class, 'form'])
        ->name('document-transfers.edit');
    // Route::get('/document-transfers/{documentTransfer}/show', [DocumentTransferController::class, 'show'])
    //     ->name('document-transfers.show');

    // Digital Document Approval
    Route::get('/digital-docs-approvals', [DigitalDocsApprovalController::class, 'index'])
        ->name('digital-docs-approvals.index');
    Route::get('/digital-docs-approvals/create', [DigitalDocsApprovalController::class, 'form'])
        ->name('digital-docs-approvals.create');
    Route::get('/digital-docs-approvals/{digitalDocsApproval}/edit', [DigitalDocsApprovalController::class, 'form'])
        ->name('digital-docs-approvals.edit');
    Route::get('/digital-docs-approvals/{digitalDocsApproval}/show', [DigitalDocsApprovalController::class, 'show'])
        ->name('digital-docs-approvals.show');
    Route::get('/digital-docs-approvals/{digitalDocsApproval}/view', [DigitalDocsApprovalController::class, 'viewFile'])
    ->name('digital-approval.view-file');


    // Purchase Requests
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])
         ->middleware('can:viewAny,' . PurchaseRequest::class)
         ->name('purchase-requests.index');
    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'form'])
        ->middleware('can:create,' . PurchaseRequest::class)
        ->name('purchase-requests.create');
    Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'form'])
        ->middleware('can:update,purchaseRequest')
         ->name('purchase-requests.edit');
    Route::get('/purchase-requests/{purchaseRequest}/show', [PurchaseRequestController::class, 'show'])
        ->middleware('can:view,purchaseRequest')
         ->name('purchase-requests.show');
    // web.php
    Route::get('purchase-requests/{purchaseRequest}/pdf', [PurchaseRequestController::class, 'viewPdf'])->name('purchase-requests.pdf');
    // Document Attachment View Route
    Route::get('/documents/{file}', [AttachementController::class, 'viewFile'])
        ->name('documents.view-file');
});

// Microsoft OAuth Login Route
Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect'])->name('microsoft.login');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);
require __DIR__.'/auth.php';
