<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\PublicFileStorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PublicFileStorageService $publicFiles
    ) {
    }

    /**
     * Display a listing of products with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Product::class);

            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'type' => ['nullable', 'string', Rule::in(['food', 'drink', 'all'])],
                'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'all'])],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = Product::query();

            if (!empty($filters['search'])) {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            if (!empty($filters['type']) && $filters['type'] !== 'all') {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('status', $filters['status'] === 'active');
            }

            $perPage = $filters['per_page'] ?? 10;
            $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->paginatedResponse(ProductResource::collection($products), 'Products retrieved successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to view products', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve products', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to retrieve products', 500);
        }
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $uploadedPath = null;

        try {
            $this->authorize('create', Product::class);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['food', 'drink'])],
                'price' => ['required', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                'stock' => ['required', 'integer', 'min:0', 'max:999999'],
                'description' => ['nullable', 'string', 'max:1000'],
                'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
                'status' => ['boolean'],
            ]);

            return DB::transaction(function () use ($request, $validated, &$uploadedPath) {
                // Handle image upload
                if ($request->hasFile('image_file') && $request->file('image_file')->isValid()) {
                    $uploadedPath = $this->publicFiles->store($request->file('image_file'), 'products');
                    $validated['image_url'] = $this->publicFiles->url($uploadedPath);
                }

                $product = Product::createManaged($validated);

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'product.created',
                    $product,
                    [],
                    $this->auditProductValues($product)
                );

                Log::info('Product created', [
                    'actor_id' => Auth::id(),
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'type' => $product->type,
                    'price' => $product->price,
                    'stock' => $product->stock,
                ]);

                return $this->successResponse(new ProductResource($product), 'Product created successfully', 201);
            });
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Clean up uploaded file on authorization failure
            if ($uploadedPath) {
                $this->publicFiles->deleteMany([$uploadedPath]);
            }
            return $this->errorResponse('Unauthorized to create products', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Clean up uploaded file on validation failure
            if ($uploadedPath) {
                $this->publicFiles->deleteMany([$uploadedPath]);
            }
            return $this->errorResponse('Invalid product data', 422, $e->errors());
        } catch (\Throwable $e) {
            // Clean up uploaded file on any failure
            if ($uploadedPath) {
                $this->publicFiles->deleteMany([$uploadedPath]);
            }
            Log::error('Failed to create product', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to create product', 500);
        }
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $oldImagePath = null;
        $newImagePath = null;

        try {
            $this->authorize('update', $product);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['food', 'drink'])],
                'price' => ['required', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                'stock' => ['required', 'integer', 'min:0', 'max:999999'],
                'description' => ['nullable', 'string', 'max:1000'],
                'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
                'status' => ['boolean'],
            ]);

            return DB::transaction(function () use ($request, $validated, $product, &$oldImagePath, &$newImagePath) {
                $changes = [];

                // Handle new image upload
                if ($request->hasFile('image_file') && $request->file('image_file')->isValid()) {
                    // Extract old path for cleanup after successful update
                    if ($product->image_url) {
                        $oldImagePath = str_replace('/storage/', '', $product->image_url);
                    }

                    // Upload new image
                    $newImagePath = $this->publicFiles->store($request->file('image_file'), 'products');
                    $validated['image_url'] = $this->publicFiles->url($newImagePath);
                    $changes['image_url'] = ['old' => $product->image_url, 'new' => $validated['image_url']];
                }

                // Track price and stock changes for audit
                if (isset($validated['price']) && $validated['price'] != $product->price) {
                    $changes['price'] = ['old' => $product->price, 'new' => $validated['price']];
                }
                if (isset($validated['stock']) && $validated['stock'] != $product->stock) {
                    $changes['stock'] = ['old' => $product->stock, 'new' => $validated['stock']];
                }

                $oldValues = $this->auditProductValues($product);

                $product->updateManaged($validated);
                $updatedProduct = $product->fresh();

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'product.updated',
                    $updatedProduct,
                    $oldValues,
                    $this->auditProductValues($updatedProduct)
                );

                // Delete old image AFTER successful database update
                if ($oldImagePath && $oldImagePath !== $newImagePath) {
                    $this->publicFiles->deleteMany([$oldImagePath]);
                }

                Log::info('Product updated', [
                    'actor_id' => Auth::id(),
                    'product_id' => $product->id,
                    'changes' => $changes,
                ]);

                return $this->successResponse(new ProductResource($updatedProduct), 'Product updated successfully');
            });
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Clean up newly uploaded file on authorization failure
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }
            return $this->errorResponse('Unauthorized to update this product', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Clean up newly uploaded file on validation failure
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }
            return $this->errorResponse('Invalid product data', 422, $e->errors());
        } catch (\Throwable $e) {
            // Clean up newly uploaded file on any failure
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }
            Log::error('Failed to update product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update product', 500);
        }
    }

    /**
     * Remove the specified product.
     * Blocks deletion if product is referenced by orders or combos.
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->authorize('delete', $product);

            // Check if product is referenced BEFORE transaction
            if ($product->orderItems()->exists()) {
                return $this->errorResponse('Cannot delete product that has been ordered', 409);
            }

            if ($product->comboItems()->exists()) {
                return $this->errorResponse('Cannot delete product that is used in combos', 409);
            }

            DB::transaction(function () use ($product) {
                $imagePath = $product->image_url ? str_replace('/storage/', '', $product->image_url) : null;

                $product->delete();

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'product.deleted',
                    $product,
                    $this->auditProductValues($product),
                    []
                );

                // Delete image AFTER successful database deletion
                if ($imagePath) {
                    $this->publicFiles->deleteMany([$imagePath]);
                }

                Log::info('Product deleted', [
                    'actor_id' => Auth::id(),
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                ]);
            });

            return $this->successResponse(null, 'Product deleted successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to delete this product', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to delete product', 500);
        }
    }

    /**
     * Toggle product active status.
     * Uses row locking to prevent concurrent toggle race conditions.
     */
    public function toggleActive(Product $product): JsonResponse
    {
        try {
            $this->authorize('toggleStatus', $product);

            DB::transaction(function () use ($product) {
                $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                $oldStatus = (bool) $locked->status;

                $locked->toggleActive();
                $locked->refresh();

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'product.status_toggled',
                    $locked,
                    ['status' => $oldStatus],
                    ['status' => (bool) $locked->status]
                );

                Log::info('Product status toggled', [
                    'actor_id' => Auth::id(),
                    'product_id' => $product->id,
                    'old_status' => $oldStatus,
                    'new_status' => ! $oldStatus,
                ]);
            });

            return $this->successResponse(new ProductResource($product->fresh()), 'Product status updated successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update product status', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle product status', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update product status', 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditProductValues(Product $product): array
    {
        return [
            'name' => $product->name,
            'type' => $product->type,
            'price' => $product->price,
            'stock' => $product->stock,
            'status' => (bool) $product->status,
            'image_url' => $product->image_url,
        ];
    }
}
