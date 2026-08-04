<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Combo;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 50;
    private const MAX_SEARCH_LENGTH = 100;

    /**
     * Get active, in-stock products for booking flow.
     *
     * @param array<string, mixed> $filters
     */
    public function getBookingProducts(array $filters = []): LengthAwarePaginator
    {
        $productQuery = Product::query()
            ->active()
            ->inStock();

        $type = $this->normalizeString($filters['type'] ?? null);
        if ($type !== null && $type !== 'combo') {
            $productQuery->where('type', $type);
        }

        $search = $this->normalizeSearch($filters['q'] ?? null);
        if ($search !== null) {
            $like = '%' . $this->escapeLike($search) . '%';

            $productQuery->where(function ($subQuery) use ($like) {
                $subQuery->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        $products = $type === 'combo'
            ? collect()
            : $productQuery
            ->orderBy('type', 'asc')
            ->orderBy('name', 'asc')
            ->select([
                'id',
                'name',
                'type',
                'price',
                'stock',
                'image_url',
            ])
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'catalog_key' => 'product:' . $product->id,
                'catalog_type' => 'product',
                'name' => $product->name,
                'type' => $product->type,
                'price' => $product->price,
                'stock' => $product->stock,
                'image_url' => $product->image_url,
                'description' => $product->description,
                'available' => (int) $product->stock > 0,
                'max_quantity' => min((int) $product->stock, 10),
            ]);

        $comboQuery = Combo::query()
            ->active()
            ->inStock()
            ->with('comboItems.product');

        if ($search !== null) {
            $like = '%' . $this->escapeLike($search) . '%';

            $comboQuery->where(function ($subQuery) use ($like) {
                $subQuery->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $combos = $type !== null && ! in_array($type, ['combo', 'all'], true)
            ? collect()
            : $comboQuery
                ->orderBy('name', 'asc')
                ->get()
                ->filter(fn (Combo $combo): bool => $combo->available_stock > 0)
                ->map(fn (Combo $combo): array => [
                    'id' => $combo->id,
                    'catalog_key' => 'combo:' . $combo->id,
                    'catalog_type' => 'combo',
                    'name' => $combo->name,
                    'type' => 'combo',
                    'price' => $combo->price,
                    'stock' => $combo->available_stock,
                    'image_url' => $combo->image_url,
                    'description' => $combo->description,
                    'available' => $combo->available_stock > 0,
                    'max_quantity' => min($combo->available_stock, 10),
                ]);

        $items = $combos->concat($products)->values();

        return $this->paginateCollection($items, $perPage);
    }

    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageItems = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(mb_strtolower($value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeSearch(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim($value);

        if ($search === '') {
            return null;
        }

        return mb_substr($search, 0, self::MAX_SEARCH_LENGTH);
    }

    private function normalizePerPage(mixed $value): int
    {
        $perPage = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'default' => self::DEFAULT_PER_PAGE,
                'min_range' => 1,
            ],
        ]);

        return min((int) $perPage, self::MAX_PER_PAGE);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\%', '\_'],
            $value
        );
    }
}
