<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Api\AuthController;
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

Route::post('/login', [AuthController::class, 'login']);

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
});
