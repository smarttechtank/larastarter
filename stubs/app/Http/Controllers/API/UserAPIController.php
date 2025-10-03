<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\AppBaseController;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\BulkDestroyUsersRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Http\Requests\ResendPasswordResetRequest;
use App\Http\Requests\RequestEmailChangeRequest;
use App\Http\Requests\VerifyEmailChangeRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;

class UserAPIController extends AppBaseController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has permission to view all users
        $this->authorize('viewAny', 'App\Models\User');

        $filters = $request->only([
            'page',
            'per_page',
            'sort',
            'search',
            'roles',
        ]);

        if ($filters) {
            $users = $this->userRepository
                ->getFilter($filters)
                ->with('role')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString();
        } else {
            $users = $this->userRepository->getAll();
        }

        return $this->sendResponse($users, 'Users retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        // Check if user has permission to create a new user
        $this->authorize('create', 'App\Models\User');

        $user = $this->userRepository->createNewUser($request->all());

        return $this->sendResponse($user, 'User created successfully. A password reset email has been sent. Please advice the user to reset their password.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        // Get user
        $user = $this->userRepository->find($id);

        // Check if user exists
        if (!$user) {
            return $this->sendError('User not found.', 404);
        }

        // Check if user has permission to view the user
        $this->authorize('view', $user);

        // Load related data
        $user->load('role');

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        // Get user
        $user = $this->userRepository->find($id);

        // Check if user exists
        if (!$user) {
            return $this->sendError('User not found.', 404);
        }

        // Check if user has permission to update the user
        $this->authorize('update', $user);

        // Prepare update data
        // Note: Admin updates can include email (bypasses verification for admin convenience)
        // Consider removing 'email' for stricter security in production environments
        $data = $request->only(['name', 'email', 'phone', 'role_id']);

        // Update user
        $this->userRepository->update($data, $user->id);

        return $this->sendResponse($this->userRepository->find($user->id), 'User updated successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProfile(UpdateUserRequest $request, ?int $id = null): JsonResponse
    {
        // Get user
        $user = $id ? $this->userRepository->find($id) : Auth::user();

        // Check if user exists
        if (!$user) {
            return $this->sendError('User not found.', 404);
        }

        // Check if user has permission to update the user
        $this->authorize('update', $user);

        // Prepare update data (email changes now require separate verification)
        $data = $request->only(['name', 'phone']);

        // Update user
        $this->userRepository->update($data, $user->id);

        return $this->sendResponse($this->userRepository->find($user->id), 'User updated successfully.');
    }

    /**
     * Update the password of the specified user.
     */
    public function updatePassword(UpdateUserPasswordRequest $request): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Check if user has permission to update the user
        $this->authorize('updatePassword', $user);

        // Update user password
        $this->userRepository->updateUserPassword($user->id, $request->password);

        return $this->sendSuccess('Password updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Get user to delete
        $userToDelete = $this->userRepository->find($id);

        if (!$userToDelete) {
            return $this->sendError('User not found.', 404);
        }

        // Check if user has permission to delete the user
        $this->authorize('delete', $userToDelete);

        // Delete user
        $result = $this->userRepository->destroyUser((int)$id, $user->id);

        // Return appropriate response based on result
        if ($result['success']) {
            return $this->sendSuccess($result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }

    /**
     * Remove multiple users from storage.
     */
    public function bulkDestroy(BulkDestroyUsersRequest $request): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user has permission to bulk delete users
        $this->authorize('bulkDelete', 'App\Models\User');

        // Get validated data
        $ids = $request->validated('ids');

        // Delete multiple users
        $result = $this->userRepository->bulkDestroy($ids, $user->id);

        $details = [
            'deleted' => $result['deleted'],
            'failed' => $result['failed'],
            'total_attempted' => $result['attempted'],
            'self_delete_attempt' => $result['self_delete_attempt']
                ? 'Self-deletion was attempted and skipped'
                : null,
        ];

        return $this->sendResponse($details, $result['deleted'] . ' users deleted successfully');
    }

    /**
     * Upload or update user avatar.
     */
    public function uploadAvatar(UpdateUserAvatarRequest $request): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Check if user has permission to update their avatar
        $this->authorize('update', $user);

        // Upload avatar
        /** @var UploadedFile $avatarFile */
        $avatarFile = $request->file('avatar');
        if (!$avatarFile || !($avatarFile instanceof UploadedFile)) {
            return $this->sendError('Avatar file is required.', 400);
        }

        $result = $this->userRepository->updateAvatar($user->id, $avatarFile);

        if ($result['success']) {
            $data = ['avatar_url' => $result['avatar_url'] ?? null];
            return $this->sendResponse($data, $result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }

    /**
     * Delete user avatar.
     */
    public function deleteAvatar(): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Check if user has permission to update their avatar
        $this->authorize('update', $user);

        // Delete avatar
        $result = $this->userRepository->deleteAvatar($user->id);

        if ($result['success']) {
            return $this->sendSuccess($result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }

    /**
     * Get the current authenticated user.
     */
    public function getCurrentUser(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Load related data
        $user->load('role');

        return $this->sendResponse($user, 'Current user retrieved successfully');
    }

    /**
     * Resend password reset link for a specific user (Admin only).
     */
    public function resendPasswordReset(ResendPasswordResetRequest $request): JsonResponse
    {
        // Get validated data
        $userId = $request->validated('user_id');

        // Get the user to resend password reset for
        $user = $this->userRepository->find($userId);

        // Check if user exists
        if (!$user) {
            return $this->sendError('User not found.', 404);
        }

        // Check if the authenticated user has permission to resend password reset
        $this->authorize('resendPasswordReset', $user);

        // Resend password reset link
        $result = $this->userRepository->resendPasswordResetLink($userId);

        if ($result['success']) {
            return $this->sendSuccess($result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }

    /**
     * Request an email change for the authenticated user.
     */
    public function requestEmailChange(RequestEmailChangeRequest $request): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Check if user has permission to update their email
        $this->authorize('update', $user);

        // Get validated data
        $newEmail = $request->validated('new_email');

        // Check if API request to determine route type
        $useApiRoute = $request->hasHeader('X-Request-Token');

        // Request email change
        $result = $this->userRepository->requestEmailChange($user->id, $newEmail, $useApiRoute);

        if ($result['success']) {
            return $this->sendSuccess($result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }

    /**
     * Verify and complete an email change.
     */
    public function verifyEmailChange(VerifyEmailChangeRequest $request): JsonResponse | RedirectResponse
    {
        // Get route parameters (already validated in request)
        $userId = $request->route('id');
        $token = $request->route('token');
        $newEmail = $request->route('email');

        // Verify email change
        $result = $this->userRepository->verifyEmailChange($userId, $token, $newEmail);

        if ($result['success']) {
            // For API requests, return JSON response
            if ($request->hasHeader('X-Request-Token')) {
                return $this->sendSuccess($result['message']);
            }

            // For web requests, redirect to frontend
            return redirect()->intended(
                config('app.frontend_url') . '/settings?tab=profile&email-change=1'
            );
        } else {
            // For API requests, return JSON error
            if ($request->hasHeader('X-Request-Token')) {
                return $this->sendError($result['message'], $result['status']);
            }

            // For web requests, redirect with error
            return redirect()->to(
                config('app.frontend_url') . '/settings?tab=profile&email-change=0'
            );
        }
    }

    /**
     * Cancel a pending email change for the authenticated user.
     */
    public function cancelEmailChange(): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Check if user has permission to update their email
        $this->authorize('update', $user);

        // Cancel email change
        $result = $this->userRepository->cancelEmailChange($user->id);

        if ($result['success']) {
            return $this->sendSuccess($result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }

    /**
     * Get the current user's pending email change status.
     */
    public function getEmailChangeStatus(): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        $data = [
            'has_pending_change' => !empty($user->pending_email),
            'pending_email' => $user->pending_email,
            'requested_at' => $user->email_change_requested_at,
        ];

        return $this->sendResponse($data, 'Email change status retrieved successfully');
    }

    /**
     * Resend email change verification to the authenticated user's pending email.
     */
    public function resendEmailChangeVerification(Request $request): JsonResponse
    {
        // Get authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return $this->sendError('User not authenticated.', 401);
        }

        // Check if user has permission to update their email
        $this->authorize('update', $user);

        // Check if API request to determine route type
        $useApiRoute = $request->hasHeader('X-Request-Token');

        // Resend email change verification
        $result = $this->userRepository->resendEmailChangeVerification($user->id, $useApiRoute);

        if ($result['success']) {
            return $this->sendSuccess($result['message']);
        } else {
            return $this->sendError($result['message'], $result['status']);
        }
    }
}
