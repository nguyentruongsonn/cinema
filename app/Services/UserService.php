<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('roles');

        // Search by name or email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('slug', $filters['role']);
            });
        }

        // Filter by status
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }

        // Filter by email verification
        if (isset($filters['verified']) && $filters['verified'] !== '') {
            if ($filters['verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Order by
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create new user
     */
    public function createUser(array $data): User
    {
        try {
            DB::beginTransaction();

            // Hash password if provided
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Set default values
            $data['status'] = $data['status'] ?? true;
            $data['loyalty_points'] = $data['loyalty_points'] ?? 0;

            // Create user
            $user = User::create($data);

            // Assign roles if provided
            if (!empty($data['roles'])) {
                $roleIds = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
                $user->roles()->sync($roleIds);
            }

            DB::commit();

            Log::info('User created', ['user_id' => $user->id, 'email' => $user->email]);

            return $user->load('roles');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        try {
            DB::beginTransaction();

            // Hash password if provided and changed
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Update user
            $user->update($data);

            // Update roles if provided
            if (isset($data['roles'])) {
                $roleIds = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
                $user->roles()->sync($roleIds);
            }

            DB::commit();

            Log::info('User updated', ['user_id' => $user->id]);

            return $user->fresh('roles');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): bool
    {
        try {
            DB::beginTransaction();

            // Check if user has active orders
            if ($user->orders()->whereIn('status', ['pending', 'processing'])->exists()) {
                throw new \Exception('Cannot delete user with active orders');
            }

            $user->delete();

            DB::commit();

            Log::info('User deleted', ['user_id' => $user->id]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(User $user): User
    {
        $user->update(['status' => !$user->status]);
        
        Log::info('User status toggled', [
            'user_id' => $user->id,
            'new_status' => $user->status
        ]);

        return $user;
    }

    /**
     * Reset user password
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        try {
            $user->update([
                'password' => Hash::make($newPassword)
            ]);

            Log::info('User password reset', ['user_id' => $user->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset password', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all roles for dropdown
     */
    public function getAllRoles()
    {
        return Role::orderBy('name')->get();
    }

    /**
     * Get user statistics
     */
    public function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('status', true)->count(),
            'inactive' => User::where('status', false)->count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'recent' => User::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}