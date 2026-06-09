# Controller Layer Review

**Date:** June 9, 2026, 2:15 AM ICT  
**Status:** CRITICAL ISSUES FOUND  
**Scope:** All 15 controllers + Admin/User subdirectories

---

## Executive Summary

**Overall Score: ⭐⭐½ (2.5/5)**

Controller layer shows **inconsistent architecture**:
- ✅ 3 controllers excellent (OrderController, SeatController, AuthController)
- ⚠️ 2 controllers acceptable (ProductController, ProfileController)
- ❌ 10+ controllers violate thin-controller principle

**Critical Pattern:** CRUD controllers bypass service layer entirely, contain business logic.

---

## Architecture Violations by Controller

### ⭐⭐⭐⭐⭐ EXCELLENT (3 controllers)

**OrderController** - Textbook thin controller
- ✅ All operations delegate to OrderService
- ✅ FormRequest validation
- ✅ Consistent error handling

**SeatController** - Proper service delegation
- ✅ Uses SeatService for all operations
- ✅ Clean, thin methods

**AuthController** - Service-based authentication
- ✅ Delegates to AuthService

---

### ❌ FAT CONTROLLERS (10+ violations)

#### 1. HomeController ⭐⭐ (2/5)

**Issues:**
```php
// Lines 24-28: Direct queries
$featuredMovie = Movie::query()->with('categories:id,name')->featured()->first();
$nowShowingMovies = Movie::query()->with('categories:id,name')->nowShowing()->get();
$upcomingMovies = Movie::query()->with('categories:id,name')->upcoming()->get();
$movieOptions = Movie::query()->active()->select('id', 'title')->get();
$cinemaOptions = Theater::query()->active()->select('id', 'name')->get();
```

**Why Bad:**
- 5 direct Model queries in controller
- Should use MovieService, TheaterService
- Business logic (filtering, eager loading) in controller

**Recommended:**
```php
public function __construct(
    private readonly MovieService $movieService,
    private readonly TheaterService $theaterService
) {}

public function index()
{
    return view('home', [
        'featuredMovie' => $this->movieService->getFeatured(),
        'nowShowingMovies' => $this->movieService->getNowShowing(),
        'upcomingMovies' => $this->movieService->getUpcoming(),
        'movieOptions' => $this->movieService->getActiveList(),
        'cinemaOptions' => $this->theaterService->getActiveList(),
    ]);
}
```

---

#### 2. ShowtimeController ⭐⭐ (2/5)

**Issues:**
```php
// Line 65: Direct create
$showtime = Showtime::create($validated);

// Line 95: Direct update
$showtime = Showtime::findOrFail($id);
$showtime->update($validated);

// Line 115: Direct delete
$showtime = Showtime::findOrFail($id);
$showtime->delete();
```

**Why Bad:**
- Full CRUD operations in controller
- No ShowtimeService usage
- No transaction management
- No validation of business rules (conflicts, capacity)

**Recommended:**
```php
public function __construct(
    private readonly ShowtimeService $showtimeService
) {}

public function store(StoreShowtimeRequest $request)
{
    $showtime = $this->showtimeService->create($request->validated());
    return $this->successResponse($showtime, 'Showtime created', 201);
}
```

---

#### 3. ScreenController ⭐⭐ (2/5)

**Same issues as ShowtimeController:**
```php
// Direct CRUD operations
Screen::create($validated);
Screen::findOrFail($id)->update($validated);
Screen::findOrFail($id)->delete();
```

**Missing:** ScreenService for business logic

---

#### 4. Admin/DashboardController ⭐ (1/5) - WORST OFFENDER

**Issues:**
```php
// Lines 20-115: 12+ raw DB::table() queries
$todayRevenue = (float) DB::table('orders')
    ->where('status', $confirmedStatus)
    ->whereDate('paid_at', today())
    ->sum('total_amount');

$revenueByDay = DB::table('orders')
    ->selectRaw('DATE(paid_at) as date, SUM(total_amount) as revenue')
    ->where('paid_at', '>=', now()->subDays(13))
    ->groupBy(DB::raw('DATE(paid_at)'))
    ->get();

$topMovies = DB::table('orders')
    ->selectRaw('showtimes.movie_id, movies.title, COUNT(orders.id) as orders_count')
    ->join('showtimes', 'orders.showtime_id', '=', 'showtimes.id')
    ->groupBy('showtimes.movie_id', 'movies.title')
    ->get();
```

**Why This is Terrible:**
- **12+ raw SQL queries in controller** (massive violation)
- Complex aggregations, joins, grouping in controller
- Should be in DashboardService or AnalyticsService
- Impossible to test, reuse, or maintain
- Performance queries not optimized

**Impact:** Production nightmare for:
- Query optimization
- Caching strategies
- Unit testing
- Code reuse

**Recommended:**
```php
// app/Services/DashboardService.php
class DashboardService
{
    public function getDashboardData(): array
    {
        return [
            'revenue' => $this->getRevenue(),
            'charts' => $this->getCharts(),
            'stats' => $this->getStats(),
        ];
    }

    private function getRevenue(): array
    {
        return Cache::remember('dashboard.revenue', 300, function() {
            return [
                'today' => Order::todayRevenue(),
                'month' => Order::monthlyRevenue(),
            ];
        });
    }
}

// Controller becomes thin
public function __construct(
    private readonly DashboardService $dashboardService
) {}

public function index()
{
    return view('admin.dashboard', $this->dashboardService->getDashboardData());
}
```

---

#### 5. PaymentController ⭐⭐ (2/5) - Already reviewed in Phase 5

**Issues:**
- Direct Order::query() in callback methods
- Code duplication (same query in 3 places)
- Unused PaymentService dependency

---

#### 6. BookingController ⭐⭐ (2/5) - Already reviewed in Phase 5

**Issues:**
- Direct Order::where() queries
- app() service locator pattern
- Authorization logic in controller

---

#### 7. User/PaymentController ⭐⭐ (2/5)

**Issues:**
```php
// Line 28: Direct query
$showtime = Showtime::query()->with('screen')->findOrFail($showtimeId);

// Line 95: Direct query with authorization
$order = Order::where('gateway_order_code', $orderCode)
    ->where('user_id', $user->id)
    ->firstOrFail();
```

**Why Bad:**
- Direct model access
- Authorization in controller
- Should use OrderService->findByGatewayCode()

---

#### 8. ProductController ⭐⭐⭐ (3/5) - Partially Acceptable

**Issues:**
```php
// Line 16: Direct query
$products = Product::query()->active()->paginate(15);
```

**Why Less Severe:**
- Simple query, no complex logic
- Could still benefit from ProductService
- At least uses scope (->active())

---

## Pattern Analysis

### Controllers WITH Service Layer (Good)

| Controller | Service Used | Score |
|------------|-------------|-------|
| OrderController | OrderService | ⭐⭐⭐⭐⭐ |
| SeatController | SeatService | ⭐⭐⭐⭐⭐ |
| AuthController | AuthService | ⭐⭐⭐⭐⭐ |

### Controllers WITHOUT Service Layer (Bad)

| Controller | Missing Service | Severity |
|------------|----------------|----------|
| Admin/DashboardController | DashboardService | 🔴 Critical |
| ShowtimeController | ShowtimeService | 🔴 Critical |
| ScreenController | ScreenService | 🔴 Critical |
| HomeController | MovieService | 🟠 High |
| PaymentController | Uses service but bypasses it | 🟠 High |
| BookingController | Uses service but bypasses it | 🟠 High |
| User/PaymentController | OrderService method | 🟡 Medium |
| ProductController | ProductService | 🟡 Medium |

---

## Code Duplication Issues

### Duplicate Pattern 1: Order Lookup by Gateway Code

**Found in 3 controllers:**
```php
// PaymentController line 44
$query = Order::query()->where('gateway_order_code', '=', $orderCode);
if (Auth::check()) {
    $query->where('user_id', Auth::id());
}

// BookingController line 35
$query = Order::where('gateway_order_code', $orderCode);
if (Auth::check()) {
    $query->where('user_id', Auth::id());
}

// User/PaymentController line 95
$order = Order::where('gateway_order_code', $orderCode)
    ->where('user_id', $user->id)
    ->firstOrFail();
```

**Solution:** Extract to OrderService
```php
public function findByGatewayCode(string|int $code, ?User $user = null): ?Order
{
    $query = Order::where('gateway_order_code', $code);
    if ($user) {
        $query->where('user_id', $user->id);
    }
    return $query->first();
}
```

---

## Validation Issues

### Issue: Missing FormRequest Validation

**Controllers using inline validation:**
```php
// ShowtimeController - no FormRequest
public function store(Request $request) { }

// ScreenController - no FormRequest
public function store(Request $request) { }
```

**Should use:**
```php
public function store(StoreShowtimeRequest $request) { }
public function store(StoreScreenRequest $request) { }
```

**Good Examples:**
- OrderController uses StoreOrderRequest ✅
- OrderController uses CancelOrderRequest ✅

---

## Authorization Issues

**Authorization logic in controllers:**
```php
// Multiple controllers check Auth::check() and filter by user_id
if (Auth::check()) {
    $query->where('user_id', Auth::id());
}
```

**Should use:**
- Laravel Policies for authorization
- Service layer for authorization logic
- Middleware for route-level auth

---

## Error Handling Issues

### Inconsistent Patterns

**Good:**
```php
// OrderController
catch (\RuntimeException $e) {
    $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
    return $this->errorResponse($e->getMessage(), $statusCode);
}
```

**Bad:**
```php
// PaymentController
catch (Throwable $e) {  // Too broad
    report($e);
    return response()->json(['success' => false], 500);
}
```

**Issues:**
- Catching Throwable (too broad)
- Inconsistent error response format
- Some controllers don't handle errors at all

---

## Missing Controllers

**UserController** - Empty stub (65 lines of empty methods)

```php
public function index() { // }
public function store(Request $request) { // }
// ... all empty
```

**Impact:** CRUD endpoints not implemented or routed elsewhere.

---

## Recommendations

### Priority 1: Refactor Admin Dashboard 🔴 CRITICAL

**Current:** 12+ raw queries in controller  
**Target:** DashboardService with caching

```php
class DashboardService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly RevenueAnalytics $analytics
    ) {}

    public function getDashboardData(): array
    {
        return Cache::remember('dashboard.data', 300, fn() => [
            'revenue' => $this->analytics->getRevenueSummary(),
            'charts' => $this->analytics->getRevenueChart(),
            'topMovies' => $this->analytics->getTopMovies(),
            'cards' => $this->getCardStats(),
        ]);
    }
}
```

**Impact:** Performance, testability, maintainability

---

### Priority 2: Add Missing Services 🔴 CRITICAL

Create service layer for CRUD controllers:

```bash
# Create services
app/Services/ShowtimeService.php
app/Services/ScreenService.php
app/Services/TheaterService.php
app/Services/MovieService.php
app/Services/DashboardService.php
```

**Pattern:**
```php
class ShowtimeService
{
    public function create(array $data): Showtime
    {
        return DB::transaction(function() use ($data) {
            // Validate business rules
            $this->validateNoConflicts($data);
            
            // Create showtime
            return Showtime::create($data);
        });
    }
    
    private function validateNoConflicts(array $data): void
    {
        // Business logic here
    }
}
```

---

### Priority 3: Extract Duplicate Query Logic 🟠 HIGH

**Add to OrderService:**
```php
public function findByGatewayCode(string|int $code, ?User $user = null): ?Order;
```

**Use in 3 controllers** (PaymentController, BookingController, User/PaymentController)

---

### Priority 4: Add FormRequest Validation 🟠 HIGH

Create missing FormRequests:
- StoreShowtimeRequest
- UpdateShowtimeRequest
- StoreScreenRequest
- UpdateScreenRequest
- StoreTheaterRequest
- UpdateTheaterRequest

---

### Priority 5: Standardize Error Handling 🟡 MEDIUM

Create base controller with standardized error handling:
```php
abstract class ApiController extends Controller
{
    use ApiResponse;
    
    protected function handleException(\Exception $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->errorResponse($e->getMessage(), 422);
        }
        
        if ($e instanceof AuthorizationException) {
            return $this->errorResponse('Unauthorized', 403);
        }
        
        report($e);
        return $this->errorResponse('Server error', 500);
    }
}
```

---

## Summary Scorecard

| Metric | Score | Notes |
|--------|-------|-------|
| **Thin Controller Compliance** | ⭐⭐ | Only 3/15 controllers are thin |
| **Service Layer Usage** | ⭐⭐ | CRUD controllers bypass services |
| **Code Duplication** | ⭐⭐ | Duplicate query logic in 3+ controllers |
| **Validation** | ⭐⭐⭐ | FormRequests used inconsistently |
| **Error Handling** | ⭐⭐⭐ | Inconsistent patterns |
| **Authorization** | ⭐⭐ | Logic in controllers, not policies |

**Overall Controller Layer: ⭐⭐½ (2.5/5)**

---

## Conclusion

Controller layer needs **major refactoring**:

1. **Admin/DashboardController** - Worst offender (12+ raw queries)
2. **CRUD Controllers** - Bypass service layer entirely
3. **Payment Controllers** - Duplicate logic, fat controllers
4. **HomeController** - Multiple direct queries

**Production Impact:**
- Hard to test (business logic in controllers)
- Hard to maintain (code duplication)
- Hard to optimize (queries scattered)
- Hard to extend (tight coupling)

**Next Steps:**
1. Create missing services (ShowtimeService, ScreenService, DashboardService)
2. Extract duplicate query logic to OrderService
3. Refactor Admin/DashboardController immediately
4. Add FormRequest validation to CRUD endpoints
5. Implement Laravel Policies for authorization

**Estimated Effort:** 2-3 weeks for full refactor

---

**Author:** Kiro AI Assistant  
**Review Type:** Controller Layer Audit  
**Confidence:** High (95%)
