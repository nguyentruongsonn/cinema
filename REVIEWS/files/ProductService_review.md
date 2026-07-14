# File Review: ProductService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/ProductService.php  
**Lines:** 46  
**Type:** Service Layer - Booking Product Catalog Query

---

## File Information

**Path:** `app/Services/ProductService.php`  
**Type:** Laravel Service Class  
**Lines:** 46  
**Complexity:** Low  

**Purpose:**  
Returns active, in-stock products for the booking flow with optional type filtering and text search.

**Business Impact:** 🟠 HIGH - Product listing affects checkout options, concession sales, inventory exposure, and API performance.

---

## Overall Score

**Code Quality:** 6.7/10  
**Security:** 6.4/10  
**Performance:** 5.8/10  
**Maintainability:** 6.4/10  
**Laravel Best Practice:** 6.2/10  

**Overall Score:** 6.3/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Small Focused Method** - The service has a narrow purpose.
2. ✅ **Uses Query Scopes** - `active()` and `inStock()` improve readability.
3. ✅ **Selected Columns Are Explicit** - Avoids returning the entire product row.
4. ✅ **Search Is Parameter Bound by Eloquent** - The `LIKE` values are passed through query builder bindings.
5. ✅ **Stable Sorting** - Products are consistently ordered by type and name.
6. ✅ **Simple to Unit/Feature Test** - Query behavior is straightforward.

---

## Issues Found

### Issue #1: Unbounded `get()` Can Return Entire Product Catalog

**Severity:** 🟠 HIGH  
**Category:** Performance / API Scalability  
**Location:** Lines 33-44

**Evidence:**
```php
return $query
    ->orderBy('type')
    ->orderBy('name')
    ->get([
        'id',
        'name',
        'type',
        'price',
        'stock',
        'image_url',
        'description',
    ]);
```

**Problem:**
The method returns all matching products without pagination or a hard limit.

**Why this matters:**
If the catalog grows or the endpoint is abused, this can create large responses, high memory usage, and slow database queries. Booking flow APIs should remain predictable under load.

**How to fix:**
Use pagination or enforce a maximum limit.

**Example:**
```php
$perPage = min((int) $request->input('per_page', 20), 50);

return $query
    ->orderBy('type')
    ->orderBy('name')
    ->paginate($perPage);
```

---

### Issue #2: Service Depends Directly on `Illuminate\Http\Request`

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Testability / Clean Code  
**Location:** Lines 7 and 14

**Evidence:**
```php
use Illuminate\Http\Request;
```

```php
public function getBookingProducts(Request $request): Collection
```

**Problem:**
The service layer accepts the HTTP request object directly.

**Why this matters:**
This couples business/query logic to the transport layer. It makes the service less reusable, harder to test outside HTTP, and pushes request parsing into the service instead of the controller/FormRequest layer.

**How to fix:**
Pass validated filter data into the service.

**Example:**
```php
public function getBookingProducts(array $filters): LengthAwarePaginator
{
    $type = $filters['type'] ?? null;
    $search = $filters['q'] ?? null;
}
```

---

### Issue #3: `type` Filter Is Not Validated or Whitelisted in This Service Contract

**Severity:** 🟡 MEDIUM  
**Category:** Validation / API Correctness  
**Location:** Lines 20-22

**Evidence:**
```php
if ($request->filled('type')) {
    $query->where('type', $request->input('type'));
}
```

**Problem:**
The method accepts any `type` value and applies it directly as a filter. There is no visible whitelist or normalization in this file.

**Why this matters:**
Invalid product types should be rejected consistently. Without validation, clients can send arbitrary values and receive empty results instead of a useful validation error. It also makes API behavior dependent on database contents rather than a stable contract.

**How to fix:**
Validate `type` in a FormRequest or enum before calling the service.

```php
'type' => ['nullable', Rule::in(['food', 'drink', 'combo'])]
```

---

### Issue #4: Search Input Is Not Length-Limited or Normalized

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Abuse Control / Validation  
**Location:** Lines 24-30

**Evidence:**
```php
if ($request->filled('q')) {
    $search = $request->input('q');

    $query->where(function ($subQuery) use ($search) {
        $subQuery->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    });
}
```

**Problem:**
The search term is used without trimming, length limits, minimum length rules, or wildcard escaping.

**Why this matters:**
Very long search strings can produce expensive queries. User-supplied `%` and `_` wildcards can unintentionally broaden searches. This is not SQL injection because Laravel binds values, but it is still an API abuse/performance problem.

**How to fix:**
Normalize and validate the search term before querying.

```php
$search = trim((string) ($filters['q'] ?? ''));

if (mb_strlen($search) > 100) {
    throw ValidationException::withMessages(['q' => 'Search query is too long.']);
}
```

For literal search behavior, escape `%`, `_`, and backslash.

---

### Issue #5: Leading-Wildcard `LIKE` Search Is Not Index-Friendly

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Database  
**Location:** Lines 27-30

**Evidence:**
```php
$subQuery->where('name', 'like', "%{$search}%")
    ->orWhere('description', 'like', "%{$search}%");
```

**Problem:**
`LIKE "%term%"` generally prevents normal B-tree index usage.

**Why this matters:**
As the product table grows, catalog search can become slow and cause unnecessary database load. Searching `description` with a leading wildcard is especially expensive.

**How to fix:**
Use full-text indexes/search, prefix search, or dedicated search infrastructure.

```php
$query->whereFullText(['name', 'description'], $search);
```

If MySQL full-text is unavailable, consider prefix search on normalized indexed columns.

---

### Issue #6: Description Is Returned in Booking Catalog Listing

**Severity:** 🔵 LOW  
**Category:** API Design / Performance  
**Location:** Lines 36-44

**Evidence:**
```php
->get([
    'id',
    'name',
    'type',
    'price',
    'stock',
    'image_url',
    'description',
]);
```

**Problem:**
The listing endpoint returns `description` for every product.

**Why this matters:**
Descriptions can increase payload size. Booking flow catalog lists often only need summary fields. Large payloads slow down mobile clients and checkout UX.

**How to fix:**
Return a lightweight product summary resource and expose full description through a product detail endpoint if needed.

---

### Issue #7: Raw `stock` Is Exposed to Booking Clients

**Severity:** 🟡 MEDIUM  
**Category:** Information Disclosure / Business Logic  
**Location:** Lines 36-44

**Evidence:**
```php
'stock',
```

**Problem:**
The method exposes exact stock values to clients.

**Why this matters:**
Exact inventory may be sensitive business information and can be scraped. Most booking flows only need availability or capped display values such as `available`, `low_stock`, or `max_quantity`.

**How to fix:**
Return an API Resource that transforms stock into a controlled field.

```php
'available' => $product->stock > 0,
'max_quantity' => min($product->stock, 10),
```

---

### Issue #8: No Theater/Branch/Showtime Scoping for Products

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Inventory Correctness  
**Location:** Lines 16-18

**Evidence:**
```php
$query = Product::query()
    ->active()
    ->inStock();
```

**Problem:**
The product catalog is global. It is not scoped to theater, branch, screen, or showtime.

**Why this matters:**
Concessions are often location-specific. A product may be in stock globally but unavailable at the selected cinema. Showing unavailable products creates failed checkout or fulfillment issues.

**How to fix:**
Pass theater/showtime context and scope products accordingly.

```php
public function getBookingProducts(array $filters, int $theaterId): LengthAwarePaginator
{
    return Product::query()
        ->active()
        ->availableAtTheater($theaterId)
        ->inStockForTheater($theaterId)
        ->paginate(...);
}
```

---

### Issue #9: No API Resource/DTO Boundary

**Severity:** 🟡 MEDIUM  
**Category:** Laravel Best Practice / API Consistency  
**Location:** Lines 33-44

**Evidence:**
```php
return $query
    ->orderBy('type')
    ->orderBy('name')
    ->get([
        'id',
        'name',
        'type',
        'price',
        'stock',
        'image_url',
        'description',
    ]);
```

**Problem:**
The service returns an Eloquent collection directly.

**Why this matters:**
API output shape should be controlled by API Resources, not raw model collections. Returning models directly increases coupling between database columns and public API response.

**How to fix:**
Return query results to a controller that wraps them in a `ProductResource` or return DTOs.

---

### Issue #10: Missing Explicit Return Type for Paginated/API Use Case

**Severity:** 🔵 LOW  
**Category:** Maintainability / API Design  
**Location:** Lines 6 and 14

**Evidence:**
```php
use Illuminate\Database\Eloquent\Collection;
```

```php
public function getBookingProducts(Request $request): Collection
```

**Problem:**
The method is locked to returning an Eloquent `Collection`.

**Why this matters:**
The correct production API behavior should likely be pagination. The current return type makes future pagination a breaking service contract change.

**How to fix:**
Change the contract to return `LengthAwarePaginator` or a dedicated result object after introducing pagination.

---

### Issue #11: No Error Handling or Validation Feedback Path

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Validation  
**Location:** Lines 20-30

**Evidence:**
```php
if ($request->filled('type')) {
    $query->where('type', $request->input('type'));
}

if ($request->filled('q')) {
```

**Problem:**
Invalid filters do not produce validation errors. They simply affect query results.

**Why this matters:**
API clients need deterministic validation responses. Silently returning empty lists for invalid filters makes bugs hard to diagnose and produces inconsistent API behavior.

**How to fix:**
Use a FormRequest such as `ListBookingProductsRequest` and call the service with `$request->validated()`.

---

## Recommendations

### IMMEDIATE

1. **Add Pagination or Hard Limit** - Do not use unbounded `get()` for API catalog listing.
2. **Remove `Request` From Service Contract** - Pass validated filter arrays.
3. **Validate `type` Against Allowed Values**.
4. **Limit and Normalize Search Input**.
5. **Scope Products to Theater/Showtime Availability** if products are not global.

### SHORT TERM

6. **Use API Resources** - Avoid exposing raw model collections.
7. **Stop Exposing Exact Stock** - Return availability/max purchasable quantity instead.
8. **Optimize Search** - Use full-text indexes or indexed normalized search fields.
9. **Separate Listing and Detail Payloads** - Avoid returning description in every listing if not required.

### LONG TERM

10. **Introduce Theater-Level Inventory** if concessions differ by location.
11. **Add Catalog Query Tests** for type filtering, search behavior, pagination, and stock visibility.
12. **Add Rate Limiting at Route Level** for search/catalog endpoint.

---

## Improved Version Snippet

```php
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

public function getBookingProducts(array $filters, ?int $theaterId = null): LengthAwarePaginator
{
    $query = Product::query()
        ->active()
        ->inStock();

    if ($theaterId !== null) {
        $query->availableAtTheater($theaterId);
    }

    if (!empty($filters['type'])) {
        $query->where('type', $filters['type']);
    }

    if (!empty($filters['q'])) {
        $search = trim((string) $filters['q']);

        if (mb_strlen($search) > 100) {
            throw ValidationException::withMessages([
                'q' => 'Search query is too long.',
            ]);
        }

        $query->whereFullText(['name', 'description'], $search);
    }

    $perPage = min((int) ($filters['per_page'] ?? 20), 50);

    return $query
        ->orderBy('type')
        ->orderBy('name')
        ->paginate($perPage);
}
```

---

## Summary

ProductService.php is small and readable, but it is too permissive for a production API. The main production risks are unbounded catalog responses, HTTP request coupling inside the service layer, unvalidated filters, inefficient wildcard search, exact stock exposure, and missing theater/showtime product scoping.

**Strengths:**
- Simple and focused
- Uses model scopes
- Explicit selected columns
- Easy to test

**Main Gaps:**
1. Unbounded `get()` can return the whole catalog
2. Service depends directly on `Request`
3. Filters are not validated in this service contract
4. Search is unbounded and uses index-unfriendly wildcard matching
5. Exact stock is exposed to clients
6. Products are not scoped to theater/showtime availability
7. Raw Eloquent collection is returned instead of a resource/paginated contract

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 01:00 PM*  
*File #35/137 - Phase 3: Business Logic (7/20 complete)*
