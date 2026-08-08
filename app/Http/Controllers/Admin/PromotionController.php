<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\AuditLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'category' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = Promotion::query();

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status'] === 'active');
        }

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        $promotions = $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 10);

        return $this->paginatedResponse($promotions, 'Promotions retrieved successfully');
    }

    public function getCategories(): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $categories = Promotion::whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    public function show(Promotion $promotion): JsonResponse
    {
        $this->authorize('view', $promotion);

        return $this->successResponse($promotion, 'Promotion retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        $this->normalizeDiscountTypeInput($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'unique:promotions,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => [
                'required',
                'numeric',
                Rule::when($request->input('discount_type') === 'percentage', ['min:0.01', 'max:100']),
                Rule::when($request->input('discount_type') === 'fixed_amount', ['min:1', 'max:10000000']),
            ],
            'min_order_value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'max_discount_amount' => [
                Rule::requiredIf($request->input('discount_type') === 'percentage'),
                'nullable',
                'numeric',
                'min:1',
                'max:10000000',
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'daily_usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['boolean'],
        ]);

        $validated['usage_count'] = 0;

        $promotion = $this->createPromotionFromValidatedData($validated);

        app(AuditLogService::class)->record(
            Auth::user(),
            'promotion.created',
            $promotion,
            [],
            $this->auditPromotionValues($promotion)
        );

        Log::info('Promotion created', [
            'actor_id' => Auth::id(),
            'promotion_id' => $promotion->getKey(),
            'code' => $promotion->code,
        ]);

        return $this->successResponse($promotion, 'Promotion created successfully', 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $this->authorize('update', $promotion);

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        $this->normalizeDiscountTypeInput($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promotions', 'code')->ignore($promotion->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discount_value' => [
                'required',
                'numeric',
                Rule::when($request->input('discount_type') === 'percentage', ['min:0.01', 'max:100']),
                Rule::when($request->input('discount_type') === 'fixed_amount', ['min:1', 'max:10000000']),
            ],
            'min_order_value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'max_discount_amount' => [
                Rule::requiredIf($request->input('discount_type') === 'percentage'),
                'nullable',
                'numeric',
                'min:1',
                'max:10000000',
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'daily_usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['boolean'],
        ]);

        $promotion = DB::transaction(function () use ($promotion, $validated) {
            $locked = Promotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->auditPromotionValues($locked);

            if ($locked->usage_count > 0) {
                unset(
                    $validated['code'],
                    $validated['discount_type'],
                    $validated['discount_value'],
                    $validated['min_order_value'],
                    $validated['max_discount_amount']
                );
            }

            $before = $locked->only(array_keys($validated));
            $this->updatePromotionFromValidatedData($locked, $validated);
            $locked->refresh();

            app(AuditLogService::class)->record(
                Auth::user(),
                'promotion.updated',
                $locked,
                $oldValues,
                $this->auditPromotionValues($locked)
            );

            Log::info('Promotion updated', [
                'actor_id' => Auth::id(),
                'promotion_id' => $locked->getKey(),
                'before' => $before,
                'changes' => $locked->getChanges(),
                'immutable_fields_frozen' => $locked->usage_count > 0,
            ]);

            return $locked;
        });

        return $this->successResponse($promotion, 'Promotion updated successfully');
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->authorize('delete', $promotion);

        return DB::transaction(function () use ($promotion) {
            $locked = Promotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();

            if ($locked->usage_count > 0) {
                return $this->errorResponse('Cannot delete promotion that has been used', 422);
            }

            $promotionId = $locked->id;
            $promotionCode = $locked->code;

            app(AuditLogService::class)->record(
                Auth::user(),
                'promotion.deleted',
                $locked,
                $this->auditPromotionValues($locked),
                []
            );

            $locked->delete();

            Log::info('Promotion deleted', [
                'actor_id' => Auth::id(),
                'promotion_id' => $promotionId,
                'code' => $promotionCode,
            ]);

            return $this->successResponse(null, 'Promotion deleted successfully');
        });
    }

    public function toggleActive(Promotion $promotion): JsonResponse
    {
        $this->authorize('toggleStatus', $promotion);

        $promotion = DB::transaction(function () use ($promotion) {
            $locked = Promotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $oldStatus = (bool) $locked->status;

            $locked->forceFill(['status' => ! $oldStatus])->save();
            $locked->refresh();

            app(AuditLogService::class)->record(
                Auth::user(),
                'promotion.status_toggled',
                $locked,
                ['status' => $oldStatus],
                ['status' => (bool) $locked->status]
            );

            Log::info('Promotion status toggled', [
                'actor_id' => Auth::id(),
                'promotion_id' => $locked->getKey(),
                'old_status' => $oldStatus,
                'new_status' => (bool) $locked->status,
            ]);

            return $locked;
        });

        return $this->successResponse($promotion, 'Promotion status updated successfully');
    }

    public function resetUsageCount(Promotion $promotion): JsonResponse
    {
        $this->authorize('resetUsageCount', $promotion);

        Log::warning('Unsafe promotion usage-count reset blocked', [
            'actor_id' => Auth::id(),
            'promotion_id' => $promotion->getKey(),
            'current_usage_count' => $promotion->usage_count,
        ]);

        return $this->errorResponse(
            'Usage count reset is disabled because it destroys promotion redemption history. Increase usage limits through an audited update instead.',
            422
        );
    }

    private function normalizeDiscountTypeInput(Request $request): void
    {
        if (! $request->filled('discount_type')) {
            return;
        }

        $request->merge([
            'discount_type' => match ($request->input('discount_type')) {
                'percent' => 'percentage',
                'fixed' => 'fixed_amount',
                default => $request->input('discount_type'),
            },
        ]);
    }

    private function createPromotionFromValidatedData(array $validated): Promotion
    {
        $promotion = new Promotion();
        $promotion->fill($validated);
        $promotion->forceFill([
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'usage_count' => $validated['usage_count'] ?? 0,
            'status' => $validated['status'] ?? true,
        ]);
        $promotion->save();

        return $promotion;
    }

    private function updatePromotionFromValidatedData(Promotion $promotion, array $validated): void
    {
        $promotion->fill($validated);
        $promotion->forceFill(array_intersect_key($validated, array_flip([
            'discount_type',
            'discount_value',
            'status',
        ])));
        $promotion->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPromotionValues(Promotion $promotion): array
    {
        return [
            'code' => $promotion->code,
            'name' => $promotion->name,
            'category' => $promotion->category,
            'discount_type' => $promotion->discount_type,
            'discount_value' => $promotion->discount_value,
            'min_order_value' => $promotion->min_order_value,
            'max_discount_amount' => $promotion->max_discount_amount,
            'usage_limit' => $promotion->usage_limit,
            'usage_count' => $promotion->usage_count,
            'daily_usage_limit' => $promotion->daily_usage_limit,
            'status' => (bool) $promotion->status,
        ];
    }
}
