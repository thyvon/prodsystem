<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;

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

use App\Http\Controllers\StockBeginningController;
use App\Models\MainStockBeginning;

use App\Http\Controllers\StockRequestController;
use App\Models\StockRequest;

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
})->middleware(['auth', 'verified'])->name('dashboard');

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

    // Inventory Management - Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index'])
        ->name('warehouses.index')->middleware('can:viewAny,' . Warehouse::class);

    // Inventory Items
    Route::get('/inventory/items', [ProductController::class, 'inventoryItemsIndex'])
        ->name('inventoryItems.index')->middleware('can:viewAny,' . Product::class);

    // Stock Beginnings
    Route::get('/stock-beginnings', [StockBeginningController::class, 'index'])
        ->name('stock-beginnings.index')->middleware('can:viewAny,' . MainStockBeginning::class);
    Route::get('/stock-beginnings/create', [StockBeginningController::class, 'create'])
        ->name('stock-beginnings.create')->middleware('can:create,' . MainStockBeginning::class);
    Route::get('/stock-beginnings/{mainStockBeginning}/edit', [StockBeginningController::class, 'edit'])
        ->name('stock-beginnings.edit')->middleware('can:update,' . MainStockBeginning::class);
    Route::get('/stock-beginnings/{mainStockBeginning}/show', [StockBeginningController::class, 'show'])
        ->name('stock-beginnings.pdf')->middleware('can:view,' . MainStockBeginning::class);
    Route::get('/stock-beginnings/{mainStockBeginning}/generate-pdf', [StockBeginningController::class, 'generatePdf'])
    ->name('stock-beginnings.pdf')->middleware('can:view,' . MainStockBeginning::class);

    // Stock Requests
    Route::get('/stock-requests', [StockRequestController::class, 'index'])
        ->name('stock-requests.index')->middleware('can:viewAny,' . StockRequest::class);
    Route::get('/stock-requests/create', [StockRequestController::class, 'create'])
        ->name('stock-requests.create')->middleware('can:create,' . StockRequest::class);
    Route::get('/stock-requests/{stockRequest}/edit', [StockRequestController::class, 'edit'])
        ->name('stock-requests.edit')->middleware('can:update,' . StockRequest::class);

});
require __DIR__.'/auth.php';
