<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\BulkDestroyRolesRequest;

class RoleAPIController extends Controller
{
    private RoleRepository $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has permission to view all roles
        $this->authorize('viewAny', 'App\Models\Role');

        $filters = $request->only([
            'page',
            'per_page',
            'sort',
            'search',
        ]);

        if ($filters) {
            $roles = $this->roleRepository
                ->getFilter($filters)
                ->withCount('users')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString();
        } else {
            $roles = $this->roleRepository->getAll();
        }

        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        // Check if user has permission to create a new role
        $this->authorize('create', 'App\Models\Role');

        $role = $this->roleRepository->createNewRole($request->all());

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        // Get role
        $role = $this->roleRepository->find($id);

        // Check if role exists
        if (!$role) {
            return response()->json([
                'message' => 'Role not found.',
            ], 404);
        }

        // Check if user has permission to view the role
        $this->authorize('view', $role);

        // Load related data
        $role->loadCount('users');

        return response()->json($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $id): JsonResponse
    {
        // Get role
        $role = $this->roleRepository->find($id);

        // Check if role exists
        if (!$role) {
            return response()->json([
                'message' => 'Role not found.',
            ], 404);
        }

        // Check if user has permission to update the role
        $this->authorize('update', $role);

        // Prepare update data
        $data = $request->only(['name', 'description']);

        // Update role
        $this->roleRepository->update($data, $role->id);

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $this->roleRepository->find($role->id),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        // Get role to delete
        $roleToDelete = $this->roleRepository->find($id);

        if (!$roleToDelete) {
            return response()->json([
                'message' => 'Role not found.',
            ], 404);
        }

        // Check if user has permission to delete the role
        $this->authorize('delete', $roleToDelete);

        // Delete role
        $result = $this->roleRepository->destroyRole((int)$id);

        // Return appropriate response based on result
        return response()->json([
            'message' => $result['message']
        ], $result['status']);
    }

    /**
     * Remove multiple roles from storage.
     */
    public function bulkDestroy(BulkDestroyRolesRequest $request): JsonResponse
    {
        // Check if user has permission to bulk delete roles
        $this->authorize('bulkDelete', 'App\Models\Role');

        // Get validated data
        $ids = $request->validated('ids');

        // Delete multiple roles
        $result = $this->roleRepository->bulkDestroy($ids);

        return response()->json([
            'message' => $result['deleted'] . ' roles deleted successfully',
            'details' => [
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'total_attempted' => $result['attempted'],
                'roles_with_users' => $result['has_users'],
            ]
        ]);
    }
}
