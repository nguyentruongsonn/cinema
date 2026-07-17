<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('role');

        // Search by name or email - bounded and normalized
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            // Limit search length to prevent expensive queries
            if (mb_strlen($search) > 100) {
                throw new \InvalidArgumentException('Search query is too long (max 100 characters).');
            }

            // Use prefix search for indexed fields (email, username, phone) and LIKE for name
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "{$search}%")
                  ->orWhere('username', 'like', "{$search}%")
                  ->orWhere('phone', 'like', "{$search}%");
            });
        }

        // Filter by role - fail closed on invalid role
        if (!empty($filters['role'])) {
            $roleId = Role::query()->where('slug', '=', $filters['role'])->value('id');
            if (!$roleId) {
                // Invalid role returns no results instead of ignoring filter
                $query->whereRaw('1 = 0');
            } else {
                $query->where('role_id', $roleId);
            }
        }

        // Filter by status - use strict boolean validation
        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        // Filter by email verification - use strict boolean validation
        if (isset($filters['verified']) && $filters['verified'] !== '') {
            $verified = filter_var($filters['verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($verified !== null) {
                if ($verified) {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }
        }

        // Order by
        $allowedSorts = ['created_at', 'name', 'email', 'username', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true)
            ? $filters['sort_by']
            : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max($perPage, 1), 100);

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Create new user
     */
    public function createUser(array $data, ?int $roleId = null, bool $status = true): User
    {
        try {
            DB::beginTransaction();

            // Hash password if provided
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $allowedFields = [
                'name',
                'username',
                'email',
                'phone',
                'birthday',
                'gender',
                'avatar_url',
                'password',
            ];
            $userData = array_intersect_key($data, array_flip($allowedFields));
            $userData['status'] = $status;
            $userData['loyalty_points'] = 0;

            $user = User::create($userData);

            if ($roleId !== null) {
                $user->role_id = $roleId;
                $user->save();
            }

            DB::commit();

            Log::info('User created', ['user_id' => $user->id]);

            return $user->load('role');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user', [
                'user_id' => $user->id ?? null,
                'error' => 'User creation failed'
            ]);
            throw $e;
        }
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        $allowedFields = [
            'name',
            'username',
            'email',
            'phone',
            'birthday',
            'gender',
            'avatar_url',
            'address',
            'password',
        ];

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $profileData = array_intersect_key($data, array_flip($allowedFields));

        return DB::transaction(function () use ($user, $profileData) {
            $user->fill($profileData);
            $user->save();

            Log::info('User profile updated', ['user_id' => $user->id]);

            return $user->fresh('role');
        });
    }

    public function updateRole(User $user, int $roleId): User
    {
        return DB::transaction(function () use ($user, $roleId) {
            $role = Role::query()->findOrFail($roleId);
            $user->role_id = $role->id;
            $user->save();

            Log::info('User role updated', [
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            return $user->fresh('role');
        });
    }

    public function updateLoyaltyPoints(User $user, int $loyaltyPoints): User
    {
        if ($loyaltyPoints < 0) {
            throw new \InvalidArgumentException('Loyalty points cannot be negative.');
        }

        return DB::transaction(function () use ($user, $loyaltyPoints) {
            $user->loyalty_points = $loyaltyPoints;
            $user->save();

            Log::info('User loyalty points updated', [
                'user_id' => $user->id,
                'loyalty_points' => $loyaltyPoints,
            ]);

            return $user->fresh('role');
        });
    }

    public function updateStatus(User $user, bool $status): User
    {
        return DB::transaction(function () use ($user, $status) {
            $lockedUser = User::query()
                ->with('role')
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Prevent disabling the only active admin
            if (!$status && $lockedUser->role && $lockedUser->role->slug === 'admin') {
                $activeAdminCount = User::query()
                    ->whereHas('role', fn($q) => $q->where('slug', '=', 'admin'))
                    ->where('status', true)
                    ->where('id', '!=', $lockedUser->id)
                    ->count();

                if ($activeAdminCount === 0) {
                    throw new \DomainException('Cannot disable the last active admin.');
                }
            }

            $lockedUser->status = $status;
            $lockedUser->save();

            Log::info('User status updated', [
                'user_id' => $lockedUser->id,
                'status' => $status,
            ]);

            return $lockedUser->fresh('role');
        });
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Check if user has active orders
            if ($lockedUser->orders()->whereIn('status', ['pending', 'processing'])->exists()) {
                throw new \DomainException('Cannot delete user with active orders');
            }

            // Revoke all refresh tokens before deleting
            RefreshToken::revokeAllForUser($lockedUser->id);

            $userId = $lockedUser->id;
            $lockedUser->delete();

            Log::info('User deleted', ['user_id' => $userId]);

            return true;
        });
    }

    /**
     * Toggle user status with safeguards
     */
    public function toggleStatus(User $user): User
    {
        $lockedUser = User::query()
            ->whereKey($user->getKey())
            ->firstOrFail();

        return $this->updateStatus($lockedUser, !(bool) $lockedUser->status);
    }

    /**
     * Reset user password and revoke all sessions
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $user->password = Hash::make($newPassword);
            $user->save();

            // Revoke all refresh tokens to force re-login
            RefreshToken::revokeAllForUser($user->id);

            Log::info('User password reset', ['user_id' => $user->id]);

            return true;
        });
    }

    /**
     * Get all roles for dropdown
     */
    public function getAllRoles(): Collection
    {
        return Role::query()
            ->select(['id', 'name', 'slug', 'description'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get user statistics in a single optimized query
     */
    public function getUserStats(): array
    {
        $stats = DB::table('users')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) as unverified,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent
            ', [now()->subDays(7)])
            ->first();

        return [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'inactive' => (int) $stats->inactive,
            'verified' => (int) $stats->verified,
            'unverified' => (int) $stats->unverified,
            'recent' => (int) $stats->recent,
        ];
    }
}
