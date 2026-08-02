<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TheaterResource;
use App\Models\Theater;
use App\Traits\ApiResponse;
use App\Http\Requests\Admin\StoreTheaterRequest;
use App\Http\Requests\Admin\UpdateTheaterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TheaterController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of theaters with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Theater::class);

            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
                'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
                'status' => ['nullable', 'string', 'in:all,active,inactive'],
                'options' => ['nullable', 'boolean'],
            ]);

            $query = Theater::with('branch')
                ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $filters['status'] === 'active'));

            if (!empty($filters['search'])) {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('address', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            if ($request->boolean('options')) {
                $theaters = $query->select(['id', 'branch_id', 'name'])
                    ->orderBy('name')
                    ->get();

                return $this->successResponse($theaters, 'Theater options retrieved successfully');
            }

            $perPage = $filters['per_page'] ?? 10;
            $theaters = $query->latest()->paginate($perPage);
            $theaters->setCollection(
                $theaters->getCollection()->map(fn (Theater $theater) => new TheaterResource($theater))
            );

            return $this->paginatedResponse($theaters, 'Theaters retrieved successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view theaters', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve theaters', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to retrieve theaters', 500);
        }
    }

    /**
     * Store a newly created theater.
     */
    public function store(StoreTheaterRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Theater::class);

            $validated = $request->validated();

            $pricingProfile = [
                'base_price' => (int) ($request->input('base_price') ?? 70000),
                'weekend_surcharge' => (int) ($request->input('weekend_surcharge') ?? 10000),
                'holiday_surcharge' => (int) ($request->input('holiday_surcharge') ?? 20000),
                'happy_day_price' => (int) ($request->input('happy_day_price') ?? 50000),
                'student_discount' => (int) ($request->input('student_discount') ?? 10000),
                'beta_ten_discount' => -10000,
            ];

            $theater = Theater::create([
                'branch_id' => $validated['branch_id'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'pricing_profile' => $pricingProfile,
                'status' => $validated['status'] ?? true,
            ]);

            Log::info('Theater created', [
                'actor_id' => Auth::id(),
                'theater_id' => $theater->id,
                'theater_name' => $theater->name,
                'branch_id' => $theater->branch_id,
            ]);

            $theater->load('branch');
            return $this->successResponse(new TheaterResource($theater), 'Tạo rạp chiếu thành công.', 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to create theaters', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid theater data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to create theater', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to create theater', 500);
        }
    }

    /**
     * Update the specified theater.
     */
    public function update(UpdateTheaterRequest $request, Theater $theater): JsonResponse
    {
        try {
            $this->authorize('update', $theater);

            $validated = $request->validated();

            $changes = [];
            foreach (['branch_id', 'name', 'address', 'phone', 'email', 'pricing_profile', 'status'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] != $theater->$field) {
                    $changes[$field] = [
                        'old' => $theater->$field,
                        'new' => $validated[$field],
                    ];
                }
            }

            $pricingProfile = [
                'base_price' => (int) ($request->input('base_price') ?? 70000),
                'weekend_surcharge' => (int) ($request->input('weekend_surcharge') ?? 10000),
                'holiday_surcharge' => (int) ($request->input('holiday_surcharge') ?? 20000),
                'happy_day_price' => (int) ($request->input('happy_day_price') ?? 50000),
                'student_discount' => (int) ($request->input('student_discount') ?? 10000),
                'beta_ten_discount' => -10000,
            ];

            $theater->update([
                'branch_id' => $validated['branch_id'],
                'name' => $validated['name'],
                'address' => $validated['address'],
                'phone' => $validated['phone'] ?? $theater->phone,
                'email' => $validated['email'] ?? $theater->email,
                'pricing_profile' => $pricingProfile,
                'status' => $validated['status'] ?? $theater->status,
            ]);

            Log::info('Theater updated', [
                'actor_id' => Auth::id(),
                'theater_id' => $theater->id,
                'changes' => $changes,
            ]);

            $theater->load('branch');
            $theater->refresh();
            return $this->successResponse(new TheaterResource($theater), 'Cập nhật rạp chiếu thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update this theater', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid theater data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to update theater', [
                'theater_id' => $theater->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update theater', 500);
        }
    }

    /**
     * Toggle theater active status.
     * Uses row locking to prevent concurrent toggle race conditions.
     */
    public function toggleActive(Theater $theater): JsonResponse
    {
        try {
            $this->authorize('update', $theater);

            $newStatus = DB::transaction(function () use ($theater): bool {
                $locked = Theater::query()
                    ->whereKey($theater->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $newStatus = !$locked->status;

                if ($newStatus === false && $locked->screens()
                    ->whereHas('showtimes', fn ($query) => $query->where('scheduled_at', '>=', now()))
                    ->exists()) {
                    throw new \DomainException('Cannot deactivate theater with future showtimes.');
                }

                $locked->update(['status' => $newStatus]);

                Log::info('Theater status toggled', [
                    'actor_id' => Auth::id(),
                    'theater_id' => $theater->id,
                    'old_status' => !$newStatus,
                    'new_status' => $newStatus,
                ]);

                return $newStatus;
            });

            return $this->successResponse([
                'status' => $newStatus,
            ], 'Theater status updated successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update theater status', 403);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle theater status', [
                'theater_id' => $theater->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update theater status', 500);
        }
    }

    /**
     * Remove the specified theater.
     * Blocks deletion if theater has screens or historical usage.
     */
    public function destroy(Theater $theater): JsonResponse
    {
        try {
            $this->authorize('delete', $theater);

            // Check if theater has screens BEFORE deletion
            if ($theater->screens()->exists()) {
                return $this->errorResponse('Cannot delete theater with screens. Deactivate the theater instead.', 409);
            }

            DB::transaction(function () use ($theater): void {
                Theater::query()
                    ->whereKey($theater->getKey())
                    ->delete();

                Log::info('Theater deleted', [
                    'actor_id' => Auth::id(),
                    'theater_id' => $theater->id,
                    'theater_name' => $theater->name,
                ]);
            });

            return $this->successResponse(null, 'Xóa rạp chiếu thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to delete this theater', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete theater', [
                'theater_id' => $theater->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to delete theater', 500);
        }
    }
}
