<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// For Permissions and Roles
use App\Http\Controllers\CampusController;
use App\Models\Campus;

use App\Http\Controllers\BuildingController;
use App\Models\Building;

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

});
require __DIR__.'/auth.php';
