<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use App\Services\PositionService;
use App\Services\DepartmentService;
use App\Services\CampusService;
use App\Services\WarehouseService;
use App\Services\BuildingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $userService;
    protected $positionService;
    protected $departmentService;
    protected $campusService;
    protected $warehouseService;
    protected $buildingService;

    public function __construct(
        UserService $userService,
        PositionService $positionService,
        DepartmentService $departmentService,
        CampusService $campusService,
        WarehouseService $warehouseService,
        BuildingService $buildingService
    ) {
        $this->userService = $userService;
        $this->positionService = $positionService;
        $this->departmentService = $departmentService;
        $this->campusService = $campusService;
        $this->warehouseService = $warehouseService;
        $this->buildingService = $buildingService;
    }

    public function index()
    {
        return view('users.index');
    }

    public function create()
    {
        return view('users.form');
    }

    public function getUsers(Request $request)
    {
        return $this->userService->getUsers($request);
    }

    public function getPositions(Request $request)
    {
        return $this->positionService->getPositions($request);
    }

    public function getDepartments(Request $request)
    {
        return $this->departmentService->getDepartments($request);
    }

    public function getCampuses(Request $request)
    {
        return $this->campusService->getCampuses($request);
    }

    public function getWarehouses(Request $request)
    {
        return $this->warehouseService->getWarehouses($request);
    }

    public function getBuildings(Request $request)
    {
        return $this->buildingService->getBuildings($request);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'card_number' => ['nullable', 'string', 'max:255'],
                'profile_url' => ['nullable', 'file', 'image', 'max:2048'],
                'signature_url' => ['nullable', 'file', 'image', 'max:2048'],
                'telegram_id' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'is_active' => ['required', 'integer'],
                'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
                'departments' => ['sometimes', 'array'],
                'departments.*.id' => ['required_with:departments', 'integer', 'exists:departments,id'],
                'departments.*.is_default' => ['required_with:departments', 'boolean'],
                'campus' => ['sometimes', 'array'],
                'campus.*.id' => ['required_with:campus', 'integer', 'exists:campus,id'],
                'campus.*.is_default' => ['required_with:campus', 'boolean'],
                'warehouses' => ['sometimes', 'array'],
                'warehouses.*.id' => ['required_with:warehouses', 'integer', 'exists:warehouses,id'],
                'warehouses.*.is_default' => ['required_with:warehouses', 'boolean'],
                'positions' => ['sometimes', 'array'],
                'positions.*.id' => ['required_with:positions', 'integer', 'exists:positions,id'],
                'positions.*.is_default' => ['required_with:positions', 'boolean'],
                'roles' => ['sometimes', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()->all(),
                ], 422);
            }

            DB::beginTransaction();

            // Handle file uploads
            $profilePath = $request->hasFile('profile_url') ? $request->file('profile_url')->store('profiles', 'public') : null;
            $signaturePath = $request->hasFile('signature_url') ? $request->file('signature_url')->store('signatures', 'public') : null;

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'card_number' => $request->card_number,
                'profile_url' => $profilePath,
                'signature_url' => $signaturePath,
                'telegram_id' => $request->telegram_id,
                'phone' => $request->phone,
                'is_active' => (int) $request->is_active,
                'building_id' => $request->building_id,
                'email_verified_at' => $request->email_verified_at,
            ]);

            // Sync relations
            if ($request->has('departments')) {
                $departments = collect($request->departments)->mapWithKeys(fn($d) => [$d['id'] => ['is_default' => $d['is_default']]]);
                $user->departments()->sync($departments);
            }

            if ($request->has('campus')) {
                $campus = collect($request->campus)->mapWithKeys(fn($c) => [$c['id'] => ['is_default' => $c['is_default']]]);
                $user->campus()->sync($campus);
            }

            if ($request->has('warehouses')) {
                $warehouses = collect($request->warehouses)->mapWithKeys(fn($w) => [$w['id'] => ['is_default' => $w['is_default']]]);
                $user->warehouses()->sync($warehouses);
            }

            if ($request->has('positions')) {
                $positions = collect($request->positions)->mapWithKeys(fn($p) => [$p['id'] => ['is_default' => $p['is_default']]]);
                $user->positions()->sync($positions);
            }

            if ($request->has('roles')) $user->syncRoles($request->roles);
            if ($request->has('permissions')) $user->syncPermissions($request->permissions);

            DB::commit();

            return response()->json([
                'message' => 'User created successfully.',
                'user' => $user->load(['roles', 'permissions', 'departments', 'campus', 'warehouses', 'positions']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create user.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'password' => ['nullable', 'string', 'min:8'],
                'card_number' => ['nullable', 'string', 'max:255'],
                'profile_url' => ['nullable', 'file', 'image', 'max:2048'],
                'signature_url' => ['nullable', 'file', 'image', 'max:2048'],
                'telegram_id' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'is_active' => ['required', 'integer'],
                'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
                'departments' => ['sometimes', 'array'],
                'departments.*.id' => ['required_with:departments', 'integer', 'exists:departments,id'],
                'departments.*.is_default' => ['required_with:departments', 'boolean'],
                'campus' => ['sometimes', 'array'],
                'campus.*.id' => ['required_with:campus', 'integer', 'exists:campus,id'],
                'campus.*.is_default' => ['required_with:campus', 'boolean'],
                'warehouses' => ['sometimes', 'array'],
                'warehouses.*.id' => ['required_with:warehouses', 'integer', 'exists:warehouses,id'],
                'warehouses.*.is_default' => ['required_with:warehouses', 'boolean'],
                'positions' => ['sometimes', 'array'],
                'positions.*.id' => ['required_with:positions', 'integer', 'exists:positions,id'],
                'positions.*.is_default' => ['required_with:positions', 'boolean'],
                'roles' => ['sometimes', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()->all(),
                ], 422);
            }

            DB::beginTransaction();

            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password ? Hash::make($request->password) : $user->password,
                'card_number' => $request->card_number,
                'telegram_id' => $request->telegram_id,
                'phone' => $request->phone,
                'is_active' => (int) $request->is_active,
                'building_id' => $request->building_id,
                'email_verified_at' => $request->email_verified_at ?? $user->email_verified_at,
            ];

            // Handle uploaded files
            if ($request->hasFile('profile_url')) {
                $userData['profile_url'] = $request->file('profile_url')->store('profiles', 'public');
            }

            if ($request->hasFile('signature_url')) {
                $userData['signature_url'] = $request->file('signature_url')->store('signatures', 'public');
            }

            $user->update($userData);

            // Sync relations
            if ($request->has('departments')) {
                $departments = collect($request->departments)->mapWithKeys(fn($d) => [$d['id'] => ['is_default' => $d['is_default']]]);
                $user->departments()->sync($departments);
            }

            if ($request->has('campus')) {
                $campus = collect($request->campus)->mapWithKeys(fn($c) => [$c['id'] => ['is_default' => $c['is_default']]]);
                $user->campus()->sync($campus);
            }

            if ($request->has('warehouses')) {
                $warehouses = collect($request->warehouses)->mapWithKeys(fn($w) => [$w['id'] => ['is_default' => $w['is_default']]]);
                $user->warehouses()->sync($warehouses);
            }

            if ($request->has('positions')) {
                $positions = collect($request->positions)->mapWithKeys(fn($p) => [$p['id'] => ['is_default' => $p['is_default']]]);
                $user->positions()->sync($positions);
            }

            if ($request->has('roles')) $user->syncRoles($request->roles);
            if ($request->has('permissions')) $user->syncPermissions($request->permissions);

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully.',
                'user' => $user->load(['roles', 'permissions', 'departments', 'campus', 'warehouses', 'positions']),
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'User not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update user.', 'errors' => [$e->getMessage()]], 500);
        }
    }



    public function edit($id)
    {
        try {
            $user = User::with(['roles', 'permissions', 'departments', 'campus', 'warehouses', 'positions'])->findOrFail($id);

            // Prepare data for the Vue form
            $userData = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => (int) $user->is_active,
                    'building_id' => $user->building_id,
                    'card_number' => $user->card_number,
                    'phone' => $user->phone,
                    'telegram_id' => $user->telegram_id,
                    'profile_url' => $user->profile_url,
                    'signature_url' => $user->signature_url,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toDateTimeString() : null,
                    'departments' => $user->departments->map(function ($department) {
                        return [
                            'id' => $department->id,
                            'name' => $department->name,
                            'is_default' => (bool) $department->pivot->is_default,
                        ];
                    })->toArray(),
                    'campus' => $user->campus->map(function ($campus) {
                        return [
                            'id' => $campus->id,
                            'name' => $campus->name,
                            'is_default' => (bool) $campus->pivot->is_default,
                        ];
                    })->toArray(),
                    'warehouses' => $user->warehouses->map(function ($warehouse) {
                        return [
                            'id' => $warehouse->id,
                            'name' => $warehouse->name,
                            'is_default' => (bool) $warehouse->pivot->is_default,
                        ];
                    })->toArray(),
                    'positions' => $user->positions->map(function ($position) {
                        return [
                            'id' => $position->id,
                            'title' => $position->title,
                            'is_default' => (bool) $position->pivot->is_default,
                        ];
                    })->toArray(),
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'permissions' => $user->permissions->pluck('name')->toArray(),
                ],
            ];

            \Log::debug('Fetched user for editing', [
                'user_id' => $user->id,
                'user_data' => $userData,
            ]);

            return view('users.form', [
                'userData' => $userData,
            ]);
        } catch (ModelNotFoundException $e) {
            \Log::error('User not found', ['user_id' => $id]);
            return response()->view('errors.404', [
                'message' => 'User not found.',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error fetching user for editing', [
                'user_id' => $id,
                'error_message' => $e->getMessage(),
            ]);
            return response()->view('errors.500', [
                'message' => 'Failed to fetch user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    public function getRoles()
    {
        try {
            $roles = Role::pluck('name')->toArray();
            return response()->json($roles, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch roles.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    public function getPermissions()
    {
        try {
            $permissions = Permission::pluck('name')->toArray();
            return response()->json($permissions, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch permissions.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    public function assignRole(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'roles' => ['required', 'array'],
                'roles.*' => ['string', 'exists:roles,name'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()->all(),
                ], 422);
            }

            $user = User::findOrFail($id);
            $user->syncRoles($request->roles);

            return response()->json([
                'message' => 'Roles assigned successfully.',
                'user' => $user->load('roles'),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign roles.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    public function assignPermission(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'permissions' => ['required', 'array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()->all(),
                ], 422);
            }

            $user = User::findOrFail($id);
            $user->syncPermissions($request->permissions);

            return response()->json([
                'message' => 'Permissions assigned successfully.',
                'user' => $user->load('permissions'),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign permissions.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}
?>