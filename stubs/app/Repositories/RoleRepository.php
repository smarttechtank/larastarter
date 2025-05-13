<?php

namespace App\Repositories;

use App\Models\Role;
use App\Repositories\BaseRepository;

class RoleRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'description'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Role::class;
    }

    public function getFilter($filters)
    {
        return $this->model->filter($filters);
    }

    /**
     * Get all roles with their associated users count.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role>
     */
    public function getAll()
    {
        return $this->model->withCount('users')->get();
    }

    /**
     * Create a new role with the given inputs.
     *
     * @param array $inputs Array containing role data with keys:
     *                     - name: string Role's name
     *                     - description: string Role's description
     * @return Role The newly created role model
     */
    public function createNewRole(array $inputs): Role
    {
        // Create role
        $role = $this->model->create([
            'name' => $inputs['name'],
            'description' => $inputs['description'],
        ]);

        return $role;
    }

    /**
     * Delete a role with validation.
     *
     * @param int $id ID of the role to delete
     * @return array Associative array with result and message
     */
    public function destroyRole(int $id): array
    {
        // Get role to delete
        $roleToDelete = $this->find($id);

        // Check if role exists
        if (!$roleToDelete) {
            return [
                'success' => false,
                'message' => 'Role not found.',
                'status' => 404
            ];
        }

        // Check if role has associated users
        if ($roleToDelete->users()->count() > 0) {
            return [
                'success' => false,
                'message' => 'Role cannot be deleted because it has associated users.',
                'status' => 400
            ];
        }

        // Delete role
        $this->delete($id);

        return [
            'success' => true,
            'message' => 'Role deleted successfully',
            'status' => 200
        ];
    }

    /**
     * Delete multiple roles by IDs.
     *
     * @param array $ids Array of role IDs to delete
     * @return array Associative array with results
     */
    public function bulkDestroy(array $ids): array
    {
        $result = [
            'deleted' => 0,
            'failed' => 0,
            'attempted' => count($ids),
            'has_users' => []
        ];

        foreach ($ids as $id) {
            $role = $this->find($id);

            // Skip if role has associated users
            if ($role && $role->users()->count() > 0) {
                $result['failed']++;
                $result['has_users'][] = [
                    'id' => $role->id,
                    'name' => $role->name
                ];
                continue;
            }

            try {
                $this->delete($id);
                $result['deleted']++;
            } catch (\Exception $e) {
                $result['failed']++;
            }
        }

        return $result;
    }
}
