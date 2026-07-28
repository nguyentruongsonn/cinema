<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\AuditLogService;
use App\Services\PublicFileStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    public function __construct(
        private readonly PublicFileStorageService $publicFiles
    ) {
    }

    /**
     * Display banners management page.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Banner::class);

        return view('admin.banners.index');
    }

    /**
     * Get banners list (API).
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Banner::class);

            $filters = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'string', Rule::in(['all', '1', '0'])],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = Banner::query()->with('images');

            if (!empty($filters['search'])) {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            if (isset($filters['status']) && $filters['status'] !== 'all') {
                $query->where('is_active', $filters['status'] === '1');
            }

            $banners = $query->orderBy('created_at')
                ->orderBy('id')
                ->paginate($filters['per_page'] ?? 15);

            return response()->json([
                'success' => true,
                'data' => BannerResource::collection($banners->getCollection())->resolve(),
                'pagination' => [
                    'current_page' => $banners->currentPage(),
                    'last_page' => $banners->lastPage(),
                    'per_page' => $banners->perPage(),
                    'total' => $banners->total(),
                    'from' => $banners->firstItem(),
                    'to' => $banners->lastItem(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view banners'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Invalid filter parameters', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to list banners', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to list banners'], 500);
        }
    }

    /**
     * Store new banners.
     */
    public function store(Request $request): JsonResponse
    {
        $storedPaths = [];

        try {
            $this->authorize('create', Banner::class);

            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
                'image_paths' => ['required', 'array', 'min:1', 'max:5'],
                'image_paths.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'link_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\//i'],
                'is_active' => ['sometimes', 'boolean'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after:start_date'],
            ]);

            $banner = DB::transaction(function () use ($request, $validated, &$storedPaths) {
                $baseData = $this->sanitizeBannerData($validated);
                $baseData['is_active'] = $baseData['is_active'] ?? false;
                unset($baseData['image_paths']);

                $banner = Banner::create($baseData);

                foreach ($request->file('image_paths') as $file) {
                    $path = $this->storeBannerImage($file);
                    $storedPaths[] = $path;
                    $banner->images()->create(['image_path' => $path]);
                }

                $banner->load('images');
                app(AuditLogService::class)->record(
                    Auth::user(),
                    'banner.created',
                    $banner,
                    [],
                    $this->auditBannerValues($banner)
                );

                return $banner;
            });

            Log::info('Banner created', [
                'actor_id' => Auth::id(),
                'banner_id' => $banner->id,
                'image_count' => $banner->images->count(),
            ]);
            HomeController::flushCachedData();

            return response()->json([
                'success' => true,
                'message' => 'Tạo banner thành công',
                'data' => (new BannerResource($banner))->resolve(),
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->deleteStoredFiles($storedPaths);

            return response()->json(['success' => false, 'message' => 'Unauthorized to create banners'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->deleteStoredFiles($storedPaths);

            return response()->json(['success' => false, 'message' => 'Invalid banner data', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            $this->deleteStoredFiles($storedPaths);

            Log::error('Failed to create banners', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to create banners'], 500);
        }
    }

    /**
     * Update banner.
     */
    public function update(Request $request, Banner $banner): JsonResponse
    {
        $newImagePaths = [];
        $oldImagePaths = [];
        $replacedImages = false;

        try {
            $this->authorize('update', $banner);

            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1000'],
                'image_paths' => ['nullable', 'array', 'min:1', 'max:5'],
                'image_paths.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'link_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\//i'],
                'is_active' => ['sometimes', 'boolean'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after:start_date'],
            ]);

            $updatedBanner = DB::transaction(function () use ($request, $banner, $validated, &$newImagePaths, &$oldImagePaths, &$replacedImages) {
                $data = $this->sanitizeBannerData($validated);
                unset($data['image_paths']);
                $banner->load('images');
                $oldValues = $this->auditBannerValues($banner);

                if ($request->hasFile('image_paths')) {
                    $oldImagePaths = $banner->images->pluck('image_path')->all();
                    foreach ($request->file('image_paths') as $file) {
                        $newImagePaths[] = $this->storeBannerImage($file);
                    }
                    $banner->images()->delete();
                    foreach ($newImagePaths as $path) {
                        $banner->images()->create(['image_path' => $path]);
                    }
                    $replacedImages = true;
                }

                $changes = [];
                foreach ($data as $field => $value) {
                    if ($banner->$field != $value) {
                        $changes[$field] = [
                            'old' => $banner->$field,
                            'new' => $value,
                        ];
                    }
                }

                $banner->update($data);
                $banner->refresh()->load('images');

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'banner.updated',
                    $banner,
                    $oldValues,
                    $this->auditBannerValues($banner)
                );

                Log::info('Banner updated', [
                    'actor_id' => Auth::id(),
                    'banner_id' => $banner->id,
                    'changes' => $changes,
                ]);

                return $banner->fresh();
            });

            if ($replacedImages) {
                $this->publicFiles->deleteMany($oldImagePaths);
            }
            HomeController::flushCachedData();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật banner thành công',
                'data' => (new BannerResource($updatedBanner))->resolve(),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->deleteStoredFiles($newImagePaths);

            return response()->json(['success' => false, 'message' => 'Unauthorized to update this banner'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->deleteStoredFiles($newImagePaths);

            return response()->json(['success' => false, 'message' => 'Invalid banner data', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            $this->deleteStoredFiles($newImagePaths);

            Log::error('Failed to update banner', [
                'banner_id' => $banner->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to update banner'], 500);
        }
    }

    /**
     * Delete banner.
     */
    public function destroy(Banner $banner): JsonResponse
    {
        try {
            $this->authorize('delete', $banner);

            $banner->load('images');
            $imagePaths = $banner->images->pluck('image_path')->all();

            DB::transaction(function () use ($banner) {
                app(AuditLogService::class)->record(
                    Auth::user(),
                    'banner.deleted',
                    $banner,
                    $this->auditBannerValues($banner),
                    []
                );

                $banner->delete();

                Log::info('Banner deleted', [
                    'actor_id' => Auth::id(),
                    'banner_id' => $banner->id,
                    'title' => $banner->title,
                ]);
            });

            $this->publicFiles->deleteMany($imagePaths);
            HomeController::flushCachedData();

            return response()->json([
                'success' => true,
                'message' => 'Xóa banner thành công',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to delete this banner'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to delete banner', [
                'banner_id' => $banner->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to delete banner'], 500);
        }
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Banner $banner): JsonResponse
    {
        try {
            $this->authorize('update', $banner);

            $updated = DB::transaction(function () use ($banner) {
                $locked = Banner::whereKey($banner->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldStatus = (bool) $locked->is_active;
                $locked->update(['is_active' => !$oldStatus]);
                $locked->refresh();

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'banner.status_toggled',
                    $locked,
                    ['is_active' => $oldStatus],
                    ['is_active' => (bool) $locked->is_active]
                );

                Log::info('Banner status toggled', [
                    'actor_id' => Auth::id(),
                    'banner_id' => $banner->id,
                    'old_status' => $oldStatus,
                    'new_status' => !$oldStatus,
                ]);

                return $locked->fresh();
            });
            HomeController::flushCachedData();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $updated,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update banner status'], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle banner status', [
                'banner_id' => $banner->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to update banner status'], 500);
        }
    }

    private function sanitizeBannerData(array $data): array
    {
        foreach (['title', 'description'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = trim(strip_tags($data[$field]));
            }
        }

        return $data;
    }

    private function storeBannerImage(\Illuminate\Http\UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . '.' . $extension;

        return $this->publicFiles->storeAs($file, 'banners', $filename);
    }

    private function deleteStoredFiles(array $paths): void
    {
        $this->publicFiles->deleteMany($paths);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditBannerValues(Banner $banner): array
    {
        return [
            'title' => $banner->title,
            'is_active' => (bool) $banner->is_active,
            'image_count' => $banner->images->count(),
            'link_url' => $banner->link_url,
        ];
    }
}
