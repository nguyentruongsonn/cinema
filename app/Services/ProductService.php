<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $query = Product::query()
            ->active()
            ->inStock();

        $type = $this->normalizeString($filters['type'] ?? null);
        if ($type !== null) {
            $query->where('type', $type);
        }

        $search = $this->normalizeSearch($filters['q'] ?? null);
        if ($search !== null) {
            $like = '%' . $this->escapeLike($search) . '%';

            $query->where(function ($subQuery) use ($like) {
                $subQuery->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        return $query
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
            ->paginate($perPage);
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
