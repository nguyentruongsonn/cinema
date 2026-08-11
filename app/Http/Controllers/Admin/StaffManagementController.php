<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * StaffManagementController
 *
 * For theater_manager to manage staff within their assigned theaters.
 */
class StaffManagementController extends Controller
{
    use ApiResponse;

    private const STAFF_ROLES = ['ticket_seller', 'ticket_checker', 'concession_staff'];

    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    /**
     * List theaters assigned to the current manager.
     */
    public function listMyTheaters(): JsonResponse
    {
        $actor = Auth::user();
        $theaters = $actor->theaters()->with('branch', 'screens')->get();

        return $this->successResponse($theaters, 'My theaters retrieved');
    }

    /**
     * List staff at the manager's theaters.
     */
    public function listStaff(Request $request): JsonResponse
    {
        $actor = Auth::user();
        $theaterIds = $actor->theaters()->pluck('theaters.id')->toArray();

        if (empty($theaterIds) && ! $actor->isAdmin()) {
            return $this->errorResponse('Bạn chưa được phân công rạp.', 403);
        }

        $staffRoleSlugs = self::STAFF_ROLES;

        $query = User::query()
            ->with(['role', 'theaters:id,name'])
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $staffRoleSlugs));

        // Scope to manager's theaters (admin sees all)
        if (! $actor->isAdmin()) {
            $query->whereHas('theaters', fn ($q) => $q->whereIn('theaters.id', $theaterIds));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) ($request->input('per_page', 15)), 1), 50);
        $staff = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => UserResource::collection($staff->getCollection()),
            'pagination' => [
                'total'        => $staff->total(),
                'per_page'     => $staff->perPage(),
                'current_page' => $staff->currentPage(),
                'last_page'    => $staff->lastPage(),
            ],
        ]);
    }

    /**
     * Create a staff member at the manager's theater.
     */
    public function createStaff(Request $request): JsonResponse
    {
        $actor = Auth::user();
        $actorTheaterIds = $actor->theaters()->pluck('theaters.id')->toArray();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
            'theater_ids' => ['required', 'array', 'min:1'],
            'theater_ids.*' => ['integer', 'exists:theaters,id'],
        ]);

        // Validate role is a staff role
        $role = Role::findOrFail($validated['role_id']);
        if (!in_array($role->slug, self::STAFF_ROLES)) {
            return $this->errorResponse('Bạn chỉ được tạo nhân viên với vai trò bán vé, soát vé, hoặc bắp nước.', 422);
        }

        // Validate theaters are within manager's scope
        if (! $actor->isAdmin()) {
            $requestedTheaterIds = array_map('intval', $validated['theater_ids']);
            $invalidTheaters = array_diff($requestedTheaterIds, $actorTheaterIds);
            if (!empty($invalidTheaters)) {
                return $this->errorResponse('Bạn không có quyền phân công nhân viên vào rạp ngoài phạm vi quản lý.', 403);
            }
        }

        return DB::transaction(function () use ($validated, $actor) {
            $user = $this->userService->createUser(
                array_diff_key($validated, array_flip(['role_id', 'theater_ids', 'status'])),
                (int) $validated['role_id'],
                true, // active
                array_map('intval', $validated['theater_ids'])
            );

            $user->load(['role', 'theaters:id,name']);

            Log::info('Theater manager created staff', [
                'manager_id' => $actor->id,
                'staff_id'   => $user->id,
                'role'       => $user->role?->slug,
                'theaters'   => $validated['theater_ids'],
            ]);

            return $this->successResponse(new UserResource($user), 'Nhân viên đã được tạo', 201);
        });
    }

    /**
     * Update staff member.
     */
    public function updateStaff(Request $request, User $user): JsonResponse
    {
        $actor = Auth::user();

        // Verify staff belongs to manager's theaters
        if (!$this->canManageUser($actor, $user)) {
            return $this->errorResponse('Bạn không có quyền quản lý nhân viên này.', 403);
        }

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255', "unique:users,email,{$user->id}"],
        ]);

        $user->update($validated);
        $user->load(['role', 'theaters:id,name']);

        return $this->successResponse(new UserResource($user), 'Thông tin nhân viên đã cập nhật');
    }

    /**
     * Toggle staff status.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        $actor = Auth::user();

        if (!$this->canManageUser($actor, $user)) {
            return $this->errorResponse('Bạn không có quyền thay đổi trạng thái nhân viên này.', 403);
        }

        $user = $this->userService->toggleStatus($user);
        $user->load(['role', 'theaters:id,name']);

        return $this->successResponse(new UserResource($user), 'Trạng thái nhân viên đã cập nhật');
    }

    /**
     * Check if the actor can manage the target user.
     */
    private function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        // Must be staff role
        if (!$target->hasAnyRole(self::STAFF_ROLES)) {
            return false;
        }

        // Staff must share at least one theater with manager
        $actorTheaterIds = $actor->theaters()->pluck('theaters.id')->toArray();
        $targetTheaterIds = $target->theaters()->pluck('theaters.id')->toArray();

        return !empty(array_intersect($actorTheaterIds, $targetTheaterIds));
    }
}
