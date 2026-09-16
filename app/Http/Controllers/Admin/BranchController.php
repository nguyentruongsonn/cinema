<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class BranchController extends Controller
{
    /**
     * List branches with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Branch::class);

            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'string', Rule::in(['all', 'active', 'inactive'])],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
                'options' => ['nullable', 'boolean'],
            ]);

            if ($request->boolean('options')) {
                return response()->json([
                    'success' => true,
                    'data' => Branch::query()
                        ->select(['id', 'name'])
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get(),
                ]);
            }

            $query = Branch::query()->withCount([
                'theaters',
                'theaters as active_theaters_count' => fn ($theaters) => $theaters->where('status', true),
            ]);

            if (!empty($filters['search'])) {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('address', 'like', '%' . $search . '%');
                });
            }

            if (isset($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            $branches = $query->orderByDesc('created_at')
                ->paginate($filters['per_page'] ?? 15);

            return response()->json([
                'success' => true,
                'data' => BranchResource::collection($branches->getCollection()),
                'pagination' => [
                    'current_page' => $branches->currentPage(),
                    'last_page' => $branches->lastPage(),
                    'per_page' => $branches->perPage(),
                    'total' => $branches->total(),
                    'from' => $branches->firstItem(),
                    'to' => $branches->lastItem(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view branches'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid filter parameters', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to list branches', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to list branches'], 500);
        }
    }

    /**
     * Store new branch.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Branch::class);

            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', false);

            $branch = DB::transaction(function () use ($validated) {
                return Branch::create($validated);
            });

            Log::info('Branch created', [
                'actor_id' => Auth::id(),
                'branch_id' => $branch->id,
                'name' => $branch->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo chi nhánh thành công',
                'data' => new BranchResource($branch),
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to create branches'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid branch data', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to create branch', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to create branch'], 500);
        }
    }

    /**
     * Update branch.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        try {
            $this->authorize('update', $branch);

            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active');

            $updated = DB::transaction(function () use ($branch, $validated) {
                $changes = [];
                foreach ($validated as $field => $value) {
                    if ($branch->$field != $value) {
                        $changes[$field] = [
                            'old' => $branch->$field,
                            'new' => $value,
                        ];
                    }
                }

                $branch->update($validated);

                if (!empty($changes)) {
                    Log::info('Branch updated', [
                        'actor_id' => Auth::id(),
                        'branch_id' => $branch->id,
                        'changes' => $changes,
                    ]);
                }

                return $branch->fresh('theaters');
            });

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật chi nhánh thành công',
                'data' => new BranchResource($updated),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update this branch'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid branch data', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update branch', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to update branch'], 500);
        }
    }

    /**
     * Toggle branch active status.
     */
    public function toggleActive(Branch $branch): Response
    {
        try {
            $this->authorize('update', $branch);

            $updated = DB::transaction(function () use ($branch) {
                $locked = Branch::whereKey($branch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldStatus = (bool) $locked->is_active;

                $hasFutureShowtimes = $locked->theaters()
                    ->whereHas('screens.showtimes', function ($query) {
                        $query->where('scheduled_at', '>=', now());
                    })
                    ->exists();

                if ($oldStatus && $hasFutureShowtimes) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Cannot deactivate branch with future showtimes',
                    ], 409));
                }
                $locked->update(['is_active' => !$oldStatus]);

                Log::info('Branch status toggled', [
                    'actor_id' => Auth::id(),
                    'branch_id' => $branch->id,
                    'old_status' => $oldStatus,
                    'new_status' => !$oldStatus,
                ]);

                return $locked->fresh('theaters');
            });

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => new BranchResource($updated),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update branch status'], 403);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            Log::error('Failed to toggle branch status', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to update branch status'], 500);
        }
    }

    /**
     * Delete branch.
     */
    public function destroy(Branch $branch): Response
    {
        try {
            $this->authorize('delete', $branch);

            DB::transaction(function () use ($branch) {
                $locked = Branch::whereKey($branch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->theaters()->exists()) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Cannot delete branch with existing theaters',
                    ], 409));
                }

                $locked->delete();

                Log::info('Branch deleted', [
                    'actor_id' => Auth::id(),
                    'branch_id' => $locked->id,
                    'name' => $locked->name,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Xóa chi nhánh thành công',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to delete this branch'], 403);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            Log::error('Failed to delete branch', [
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to delete branch'], 500);
        }
    }
}
