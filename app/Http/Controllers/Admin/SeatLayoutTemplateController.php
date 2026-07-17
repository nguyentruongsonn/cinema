<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeatLayoutTemplateRequest;
use App\Http\Requests\Admin\UpdateSeatLayoutTemplateRequest;
use App\Http\Resources\SeatLayoutTemplateResource;
use App\Models\SeatLayoutTemplate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SeatLayoutTemplateController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of seat layout templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', SeatLayoutTemplate::class);

            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'string', Rule::in(['all', 'published', 'draft'])],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = SeatLayoutTemplate::query();

            // Search by template_name and description only
            // Avoid expensive JSON/text column searches
            if (!empty($filters['search'])) {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('template_name', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            if (!empty($filters['status'])) {
                if ($filters['status'] === 'published') {
                    $query->where('status', true);
                } elseif ($filters['status'] === 'draft') {
                    $query->where('status', false);
                }
            }

            $perPage = $filters['per_page'] ?? 10;
            $templates = $query->latest()->paginate($perPage);

            return $this->paginatedResponse($templates, 'Seat layout templates retrieved successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view seat layout templates', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve seat layout templates', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to retrieve seat layout templates', 500);
        }
    }

    /**
     * Store a newly created seat layout template.
     */
    public function store(StoreSeatLayoutTemplateRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', SeatLayoutTemplate::class);

            $validated = $request->validated();

            $template = SeatLayoutTemplate::create([
                'template_name' => $validated['template_name'],
                'description' => $validated['description'] ?? null,
                'seat_matrix' => $validated['seat_matrix'],
                'regular_seat_rows' => $validated['regular_seat_rows'] ?? null,
                'vip_seat_rows' => $validated['vip_seat_rows'] ?? null,
                'couple_seat_rows' => $validated['couple_seat_rows'] ?? null,
                'custom_matrix' => $validated['custom_matrix'] ?? null,
                'status' => $validated['status'] ?? false,
            ]);

            Log::info('Seat layout template created', [
                'actor_id' => Auth::id(),
                'template_id' => $template->id,
                'template_name' => $template->template_name,
            ]);

            return $this->successResponse(
                new SeatLayoutTemplateResource($template),
                'Tạo mẫu sơ đồ ghế thành công.',
                201
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to create seat layout templates', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid template data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to create seat layout template', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to create seat layout template', 500);
        }
    }

    /**
     * Update the specified seat layout template.
     * Blocks modification if template is used by screens.
     */
    public function update(UpdateSeatLayoutTemplateRequest $request, SeatLayoutTemplate $seatLayoutTemplate): JsonResponse
    {
        try {
            $this->authorize('update', $seatLayoutTemplate);

            // Block modification if template is used by screens
            if ($seatLayoutTemplate->screens()->exists()) {
                return $this->errorResponse('Cannot modify layout template currently used by screens. Create a new version instead.', 409);
            }

            $validated = $request->validated();

            $changes = [];
            foreach (['template_name', 'description', 'seat_matrix', 'regular_seat_rows', 'vip_seat_rows', 'couple_seat_rows', 'custom_matrix', 'status'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] != $seatLayoutTemplate->$field) {
                    $changes[$field] = [
                        'old' => $seatLayoutTemplate->$field,
                        'new' => $validated[$field],
                    ];
                }
            }

            $seatLayoutTemplate->update([
                'template_name' => $validated['template_name'],
                'description' => $validated['description'] ?? $seatLayoutTemplate->description,
                'seat_matrix' => $validated['seat_matrix'],
                'regular_seat_rows' => $validated['regular_seat_rows'] ?? null,
                'vip_seat_rows' => $validated['vip_seat_rows'] ?? null,
                'couple_seat_rows' => $validated['couple_seat_rows'] ?? null,
                'custom_matrix' => $validated['custom_matrix'] ?? $seatLayoutTemplate->custom_matrix,
                'status' => $validated['status'] ?? $seatLayoutTemplate->status,
            ]);

            Log::info('Seat layout template updated', [
                'actor_id' => Auth::id(),
                'template_id' => $seatLayoutTemplate->id,
                'changes' => $changes,
            ]);

            return $this->successResponse(
                new SeatLayoutTemplateResource($seatLayoutTemplate->fresh()),
                'Cập nhật mẫu sơ đồ ghế thành công.'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update this template', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid template data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to update seat layout template', [
                'template_id' => $seatLayoutTemplate->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update seat layout template', 500);
        }
    }

    /**
     * Toggle template active status.
     * Uses row locking to prevent concurrent toggle race conditions.
     */
    public function toggleActive(SeatLayoutTemplate $seatLayoutTemplate): JsonResponse
    {
        try {
            $this->authorize('update', $seatLayoutTemplate);

            DB::transaction(function () use ($seatLayoutTemplate) {
                $locked = SeatLayoutTemplate::where('id', $seatLayoutTemplate->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newStatus = !$locked->status;
                $locked->update(['status' => $newStatus]);

                Log::info('Seat layout template status toggled', [
                    'actor_id' => Auth::id(),
                    'template_id' => $seatLayoutTemplate->id,
                    'old_status' => !$newStatus,
                    'new_status' => $newStatus,
                ]);
            });

            return $this->successResponse([
                'status' => $seatLayoutTemplate->fresh()->status,
            ], 'Template status updated successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update template status', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle template status', [
                'template_id' => $seatLayoutTemplate->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update template status', 500);
        }
    }

    /**
     * Remove the specified seat layout template.
     * Blocks deletion if template is referenced by screens.
     */
    public function destroy(SeatLayoutTemplate $seatLayoutTemplate): JsonResponse
    {
        try {
            $this->authorize('delete', $seatLayoutTemplate);

            // Check if template is referenced by screens BEFORE deletion
            if ($seatLayoutTemplate->screens()->exists()) {
                return $this->errorResponse('Cannot delete layout template currently used by screens', 409);
            }

            DB::transaction(function () use ($seatLayoutTemplate) {
                $seatLayoutTemplate->delete();

                Log::info('Seat layout template deleted', [
                    'actor_id' => Auth::id(),
                    'template_id' => $seatLayoutTemplate->id,
                    'template_name' => $seatLayoutTemplate->template_name,
                ]);
            });

            return $this->successResponse(null, 'Xóa mẫu sơ đồ ghế thành công.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to delete this template', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete seat layout template', [
                'template_id' => $seatLayoutTemplate->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to delete seat layout template', 500);
        }
    }
}
