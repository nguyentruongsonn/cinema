<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComboResource;
use App\Models\Combo;
use App\Models\Product;
use App\Services\PublicFileStorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ComboController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PublicFileStorageService $publicFiles
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Combo::class);

            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'string', Rule::in(['all', 'active', 'inactive'])],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = Combo::with('comboItems.product');

            if (!empty($filters['search'])) {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('status', $filters['status'] === 'active');
            }

            $perPage = $filters['per_page'] ?? 10;
            $combos = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $combos->setCollection(ComboResource::collection($combos->getCollection())->collection);

            return $this->paginatedResponse($combos, 'Combos retrieved successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view combos', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve combos', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to retrieve combos', 500);
        }
    }

    public function show(Combo $combo): JsonResponse
    {
        try {
            $this->authorize('view', $combo);

            $combo->load('comboItems.product');

            return $this->successResponse(new ComboResource($combo), 'Combo retrieved successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view this combo', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve combo', [
                'combo_id' => $combo->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to retrieve combo', 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $uploadedPath = null;

        try {
            $this->authorize('create', Combo::class);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:combos,name'],
                'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
                'description' => ['nullable', 'string', 'max:1000'],
                'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
                'status' => ['boolean'],
                'items' => ['required', 'array', 'min:1', 'max:10'],
                'items.*.product_id' => [
                    'required',
                    'distinct',
                    Rule::exists('products', 'id')->where(fn ($q) => $q
                        ->where('status', 1)
                        ->whereIn('type', ['food', 'drink'])
                    ),
                ],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            ]);

            return DB::transaction(function () use ($request, $validated, &$uploadedPath) {
                if ($request->hasFile('image_file') && $request->file('image_file')->isValid()) {
                    $uploadedPath = $this->publicFiles->store($request->file('image_file'), 'combos');
                    $validated['image_url'] = $this->publicFiles->url($uploadedPath);
                }

                $combo = Combo::createManaged([
                    'name' => $validated['name'],
                    'price' => $validated['price'],
                    'description' => $validated['description'] ?? null,
                    'image_url' => $validated['image_url'] ?? null,
                    'status' => $validated['status'] ?? true,
                ]);

                foreach ($validated['items'] as $item) {
                    $combo->comboItems()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                $this->updateOriginalPrice($combo);

                $originalPrice = $combo->fresh()->original_price;
                if ($validated['price'] < $originalPrice * 0.3) {
                    throw new \Exception('Combo discount exceeds 70% threshold');
                }

                Log::info('Combo created', [
                    'actor_id' => Auth::id(),
                    'combo_id' => $combo->id,
                    'name' => $combo->name,
                    'price' => $combo->price,
                    'original_price' => $originalPrice,
                ]);

                $combo->load('comboItems.product');

                return $this->successResponse(new ComboResource($combo), 'Combo created successfully', 201);
            });
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            if ($uploadedPath) {
                $this->publicFiles->deleteMany([$uploadedPath]);
            }
            return $this->errorResponse('Unauthorized to create combos', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($uploadedPath) {
                $this->publicFiles->deleteMany([$uploadedPath]);
            }
            return $this->errorResponse('Invalid combo data', 422, $e->errors());
        } catch (\Throwable $e) {
            if ($uploadedPath) {
                $this->publicFiles->deleteMany([$uploadedPath]);
            }
            Log::error('Failed to create combo', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to create combo', 500);
        }
    }

    public function update(Request $request, Combo $combo): JsonResponse
    {
        $oldImagePath = null;
        $newImagePath = null;

        try {
            $this->authorize('update', $combo);

            if ($combo->orderItems()->exists()) {
                return $this->errorResponse('Cannot modify combo that is used in orders. Consider creating a new combo version.', 409);
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:combos,name,' . $combo->id],
                'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
                'description' => ['nullable', 'string', 'max:1000'],
                'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
                'status' => ['boolean'],
                'items' => ['required', 'array', 'min:1', 'max:10'],
                'items.*.product_id' => [
                    'required',
                    'distinct',
                    Rule::exists('products', 'id')->where(fn ($q) => $q
                        ->where('status', 1)
                        ->whereIn('type', ['food', 'drink'])
                    ),
                ],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            ]);

            return DB::transaction(function () use ($request, $validated, $combo, &$oldImagePath, &$newImagePath) {
                $changes = [];

                if ($request->hasFile('image_file') && $request->file('image_file')->isValid()) {
                    if ($combo->image_url) {
                        $oldImagePath = str_replace('/storage/', '', $combo->image_url);
                    }

                    $newImagePath = $this->publicFiles->store($request->file('image_file'), 'combos');
                    $validated['image_url'] = $this->publicFiles->url($newImagePath);
                    $changes['image_url'] = ['old' => $combo->image_url, 'new' => $validated['image_url']];
                }

                if (isset($validated['price']) && $validated['price'] != $combo->price) {
                    $changes['price'] = ['old' => $combo->price, 'new' => $validated['price']];
                }

                $combo->updateManaged([
                    'name' => $validated['name'],
                    'price' => $validated['price'],
                    'description' => $validated['description'] ?? null,
                    'image_url' => $validated['image_url'] ?? $combo->image_url,
                    'status' => $validated['status'] ?? $combo->status,
                ]);

                $combo->comboItems()->delete();

                foreach ($validated['items'] as $item) {
                    $combo->comboItems()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                $this->updateOriginalPrice($combo);

                $originalPrice = $combo->fresh()->original_price;
                if ($validated['price'] < $originalPrice * 0.3) {
                    throw new \Exception('Combo discount exceeds 70% threshold');
                }

                if ($oldImagePath && $oldImagePath !== $newImagePath) {
                    $this->publicFiles->deleteMany([$oldImagePath]);
                }

                Log::info('Combo updated', [
                    'actor_id' => Auth::id(),
                    'combo_id' => $combo->id,
                    'changes' => $changes,
                ]);

                $combo->load('comboItems.product');

                return $this->successResponse(new ComboResource($combo), 'Combo updated successfully');
            });
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }
            return $this->errorResponse('Unauthorized to update this combo', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }
            return $this->errorResponse('Invalid combo data', 422, $e->errors());
        } catch (\Throwable $e) {
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }
            Log::error('Failed to update combo', [
                'combo_id' => $combo->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update combo', 500);
        }
    }

    public function destroy(Combo $combo): JsonResponse
    {
        try {
            $this->authorize('delete', $combo);

            if ($combo->orderItems()->exists()) {
                return $this->errorResponse('Cannot delete combo that is used in orders', 409);
            }

            DB::transaction(function () use ($combo) {
                $imagePath = $combo->image_url ? str_replace('/storage/', '', $combo->image_url) : null;

                $combo->delete();

                if ($imagePath) {
                    $this->publicFiles->deleteMany([$imagePath]);
                }

                Log::info('Combo deleted', [
                    'actor_id' => Auth::id(),
                    'combo_id' => $combo->id,
                    'combo_name' => $combo->name,
                ]);
            });

            return $this->successResponse(null, 'Combo deleted successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to delete this combo', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete combo', [
                'combo_id' => $combo->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to delete combo', 500);
        }
    }

    public function toggleActive(Combo $combo): JsonResponse
    {
        try {
            $this->authorize('toggleStatus', $combo);

            DB::transaction(function () use ($combo) {
                $locked = Combo::where('id', $combo->id)->lockForUpdate()->first();
                $oldStatus = (bool) $locked->status;

                $locked->toggleActive();

                Log::info('Combo status toggled', [
                    'actor_id' => Auth::id(),
                    'combo_id' => $combo->id,
                    'old_status' => $oldStatus,
                    'new_status' => ! $oldStatus,
                ]);
            });

            $combo = $combo->fresh()->load('comboItems.product');

            return $this->successResponse(new ComboResource($combo), 'Combo status updated successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update combo status', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle combo status', [
                'combo_id' => $combo->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update combo status', 500);
        }
    }

    public function getAvailableProducts(): JsonResponse
    {
        try {
            $this->authorize('create', Combo::class);

            $products = Product::where('status', 1)
                ->whereIn('type', ['food', 'drink'])
                ->where('stock', '>', 0)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'price', 'stock']);

            return $this->successResponse($products, 'Available products retrieved successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view combo products', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve available products', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to retrieve available products', 500);
        }
    }

    private function updateOriginalPrice(Combo $combo): void
    {
        $originalPrice = DB::table('combo_items')
            ->join('products', 'combo_items.product_id', '=', 'products.id')
            ->where('combo_items.combo_id', $combo->id)
            ->selectRaw('SUM(products.price * combo_items.quantity) as total')
            ->value('total');

        $combo->forceFill(['original_price' => $originalPrice ?? 0])->save();
    }
}
