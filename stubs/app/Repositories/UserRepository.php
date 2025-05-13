<?php

namespace App\Repositories;

use Str;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class UserRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'email',
        'email_verified_at',
        'password'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return User::class;
    }

    public function getFilter($filters)
    {
        return $this->model->filter($filters);
    }

    /**
     * Get all users with their associated roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>
     */
    public function getAll()
    {
        return $this->model->with('role')->get();
    }

    /**
     * Create a new user with the given inputs.
     *
     * @param array $inputs Array containing user data with keys:
     *                     - name: string User's name
     *                     - email: string User's email
     *                     - role_id: int Role ID to assign
     * @return User The newly created user model
     */
    public function createNewUser(array $inputs): User
    {
        // Create user with role
        $user = $this->model->create([
            'name' => $inputs['name'],
            'email' => $inputs['email'],
            'password' => Hash::make(Str::random(8)),
            'role_id' => $inputs['role_id'],
        ]);

        // Trigger Laravel's forgot password system
        Password::broker()->sendResetLink(['email' => $user->email]);

        return $user;
    }

    /**
     * Update a user's password.
     *
     * @param int $id The ID of the user to update
     * @param string $password The new password to set
     * @return void
     */
    public function updateUserPassword(int $id, string $password): void
    {
        $user = $this->model->find($id);

        $user->password = Hash::make($password);

        $user->save();
    }

    /**
     * Delete a user with validation.
     *
     * @param int $idToDelete ID of the user to delete
     * @param int $currentUserId ID of the authenticated user
     * @return array Associative array with result and message
     */
    public function destroyUser(int $idToDelete, int $currentUserId): array
    {
        // Get user to delete
        $userToDelete = $this->find($idToDelete);

        // Check if user exists
        if (!$userToDelete) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        // Check if user is trying to delete themselves
        if ($currentUserId == $userToDelete->id) {
            return [
                'success' => false,
                'message' => 'You cannot delete yourself.',
                'status' => 400
            ];
        }

        // Delete user
        $this->delete($idToDelete);

        return [
            'success' => true,
            'message' => 'User deleted successfully',
            'status' => 200
        ];
    }

    /**
     * Delete multiple users by IDs.
     *
     * @param array $ids Array of user IDs to delete
     * @param int $currentUserId ID of the authenticated user
     * @return array Associative array with results
     */
    public function bulkDestroy(array $ids, int $currentUserId): array
    {
        $result = [
            'deleted' => 0,
            'failed' => 0,
            'attempted' => count($ids),
            'self_delete_attempt' => false
        ];

        foreach ($ids as $id) {
            // Skip if user is trying to delete themselves
            if ($id == $currentUserId) {
                $result['self_delete_attempt'] = true;
                $result['failed']++;
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
