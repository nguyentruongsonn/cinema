<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserService;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display users management page
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);
        return view('admin.users.index');
    }

    /**
     * Get paginated users (API)
     */
    public function list(ListUsersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search' => $request->input('search'),
            'role' => $request->input('role'),
            'status' => $request->input('status'),
            'verified' => $request->input('verified'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        $perPage = $request->input('per_page', 15);
        $users = $this->userService->getPaginatedUsers($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users->getCollection()),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    /**
     * Store a new user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        return DB::transaction(function () use ($request) {
            $user = $this->userService->createUser($request->userData(), $request->roleId(), $request->status());
            $user->load('role');

            app(AuditLogService::class)->record(
                $request->user(),
                'user.created',
                $user,
                [],
                $this->auditUserValues($user)
            );

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => new UserResource($user)
            ], 201);
        });
    }

    /**
     * Get single user details
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['role', 'orders' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Update user
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $oldValues = $this->auditUserValues($user);

        return DB::transaction(function () use ($request, $user, $oldValues) {
            // Update basic profile fields
            if (array_intersect_key($request->userData(), array_flip(['name', 'username', 'email', 'phone', 'birthday', 'gender', 'avatar_url', 'address', 'password']))) {
                $user = $this->userService->updateUser($user, $request->userData());
            }

            // Handle role update separately (requires specific permission)
            if ($request->roleId() !== null) {
                $this->authorize('updateRole', $user);
                $user = $this->userService->updateRole($user, $request->roleId());
            }

            // Handle loyalty points update separately (requires specific permission)
            if ($request->loyaltyPoints() !== null) {
                $this->authorize('updateLoyaltyPoints', $user);
                $user = $this->userService->updateLoyaltyPoints($user, $request->loyaltyPoints());
            }

            // Handle status update separately (requires specific permission)
            if ($request->status() !== null) {
                $this->authorize('updateStatus', $user);
                $user = $this->userService->updateStatus($user, $request->status());
            }

            $user->load('role');

            app(AuditLogService::class)->record(
                $request->user(),
                'user.updated',
                $user,
                $oldValues,
                $this->auditUserValues($user)
            );

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => new UserResource($user)
            ]);
        });
    }

    /**
     * Delete user
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            $oldValues = $this->auditUserValues($user);

            DB::transaction(function () use ($user, $oldValues): void {
                $this->userService->deleteUser($user);

                app(AuditLogService::class)->record(
                    auth()->user(),
                    'user.deleted',
                    $user,
                    $oldValues,
                    []
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user', ['user_id' => $user->id, 'error' => $e->getMessage(), 'admin' => auth()->id()]);
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user. User may have existing orders or bookings.'
            ], 422);
        }
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(User $user): JsonResponse
    {
        $this->authorize('updateStatus', $user);
        $oldValues = $this->auditUserValues($user);

        $updatedUser = DB::transaction(function () use ($user, $oldValues) {
            $updatedUser = $this->userService->toggleStatus($user);

            $updatedUser->load('role');

            app(AuditLogService::class)->record(
                auth()->user(),
                'user.status_toggled',
                $updatedUser,
                $oldValues,
                $this->auditUserValues($updatedUser)
            );

            return $updatedUser;
        });

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => new UserResource($updatedUser)
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        DB::transaction(function () use ($request, $user): void {
            $this->userService->resetPassword($user, $request->validated('password'));

            app(AuditLogService::class)->record(
                $request->user(),
                'user.password_reset',
                $user,
                [],
                ['credential_reset' => true]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Get all roles
     */
    public function getRoles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = $this->userService->getAllRoles();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get user statistics
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $stats = $this->userService->getUserStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    private function auditUserValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'role_id' => $user->role_id,
            'status' => (bool) $user->status,
            'loyalty_points' => (int) $user->loyalty_points,
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
        ];
    }
}
