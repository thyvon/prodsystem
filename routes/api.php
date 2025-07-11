<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;

use App\Models\Campus;
use App\Http\Controllers\CampusController;


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
});
