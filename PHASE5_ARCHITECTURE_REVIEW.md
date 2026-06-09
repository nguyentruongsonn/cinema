# Phase 5: Architecture Review

**Date:** June 9, 2026, 2:08 AM ICT  
**Status:** ✅ COMPLETED  
**Focus:** SOLID principles, design patterns, service layer structure, thin controllers

---

## Executive Summary

Architecture review reveals **mixed quality**: Service layer is well-designed with proper orchestration patterns, but payment-related controllers systematically violate thin-controller principles. Core domain logic (orders, bookings) follows good practices, while payment callback handlers contain fat controller anti-patterns.

### Architecture Health: ⭐⭐⭐½ (3.5/5)

**Strengths:**
- ✅ Well-structured service layer with orchestration pattern
- ✅ Proper dependency injection in core controllers
- ✅ Clean separation of concerns in OrderService
- ✅ FormRequest validation consistently used

**Critical Issues:**
- ❌ Payment controllers bypass service layer
- ❌ Code duplication across PaymentController & BookingController
- ❌ Missing repository pattern and interfaces
- ⚠️ Inconsistent dependency injection (constructor vs service locator)

---

## 1. Application Structure Overview

```
app/
├── Events/              (2 events)
│   ├── OrderPaid
│   └── SeatStatusUpdated
├── Exceptions/          (1 custom exception)
│   └── PaymentGatewayException
├── Http/
│   ├── Controllers/     (15 controllers + subdirectories)
│   ├── Middleware/
│   ├── Requests/        (FormRequest validation)
│   └── Resources/       (API transformers)
├── Jobs/                (1 job)
│   └── ProcessPayOSWebhook
├── Models/              (26 Eloquent models)
├── Providers/           (1 provider)
│   └── AppServiceProvider
├── Services/            (10 services)
│   ├── AuthService
│   ├── MovieService
│   ├── OrderService
│   ├── PaymentService
│   ├── PayOSGateway
│   ├── PricingService
│   ├── OrderExpirationService
│   ├── OrderFulfillmentService
│   ├── SeatService
│   ├── ShowtimeService
│   └── TheaterService
└── Traits/              (1 trait)
    └── ApiResponse
```

**Layering:**
- **Controllers:** HTTP boundary, should be thin
- **Services:** Business logic orchestration
- **Models:** Eloquent ORM, data access
- **Jobs:** Asynchronous processing
- **Events:** Domain events (underutilized)

---

## 2. Controller Layer Analysis

### 2.1 Excellent: OrderController ⭐⭐⭐⭐⭐

**File:** `app/Http/Controllers/OrderController.php`

**Architecture:**
```php
class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService  // ✅ Constructor DI
    ) {}

    public function store(StoreOrderRequest $request)  // ✅ FormRequest validation
    {
        try {
            $user = Auth::user();
            $order = $this->orderService->create($request->validated(), $user);  // ✅ Delegates to service
            
            return $this->successResponse(
                $this->orderService->format($order),
                'Order created successfully',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $statusCode);
        }
    }
}
```

**Why This is Excellent:**

1. **Thin Controller** ✅
   - Only 107 lines total
   - No business logic in controller
   - All operations delegated to OrderService

2. **Dependency Injection** ✅
   - Constructor injection of OrderService
   - PHP 8.1+ readonly properties
   - Proper type hinting

3. **FormRequest Validation** ✅
   - `StoreOrderRequest` - validation separated
   - `CancelOrderRequest` - specific validation rules
   - Follows Single Responsibility Principle

4. **Consistent Error Handling** ✅
   - Try-catch blocks with appropriate status codes
   - Uses ApiResponse trait for standardized responses

5. **SOLID Compliance** ✅
   - **S**ingle Responsibility: Only handles HTTP concerns
   - **O**pen/Closed: Extensible via service injection
   - **L**iskov Substitution: N/A (not using inheritance)
   - **I**nterface Segregation: N/A (no interfaces used, but could benefit)
   - **D**ependency Inversion: Depends on OrderService (concrete, but injected)

**Verdict:** This is **textbook thin controller architecture**.

---

### 2.2 Poor: PaymentController ⭐⭐ (2/5)

**File:** `app/Http/Controllers/PaymentController.php`

**Problems:**

#### Issue 1: Direct Eloquent Queries (Fat Controller)

```php
// Line 44-54 in payosCallback()
$query = Order::query()->where('gateway_order_code', '=', $orderCode);  // ❌

if (Auth::check()) {
    $query->where('user_id', Auth::id());  // ❌ Authorization in controller
}

$order = $query->first();
$showtimeId = $order?->showtime_id;
```

**Why This is Bad:**
- Business logic (querying orders) in controller
- Violates Single Responsibility Principle
- Should be delegated to OrderService or PaymentService

#### Issue 2: Code Duplication

Same query pattern repeated in:
- `payosCallback()` (lines 44-54)
- `payosCancel()` (lines 77-87)

**Impact:**
- Maintenance nightmare
- Changes must be made in multiple places
- Violates DRY (Don't Repeat Yourself)

#### Issue 3: Unused Dependency

```php
public function __construct(
    private readonly PaymentService $paymentService  // ⚠️ Injected but NEVER used
) {}
```

**Why This is Bad:**
- PaymentService exists but controller doesn't use it
- Controllers query models directly instead
- Service layer bypassed entirely

#### Issue 4: Security Concern in Webhook

```php
// Line 108 in payosWebhook()
ProcessPayOSWebhook::dispatch($request->all());  // ⚠️ Passes raw request
```

**Concern:**
- Dispatches entire raw request array to job
- No validation before queueing
- Could process malicious payloads

**Recommended Refactor:**

```php
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService
    ) {}

    public function payosCallback(Request $request): RedirectResponse
    {
        $orderCode = $request->query('orderCode');
        $status = $request->query('status');

        // Delegate to service
        $order = $this->orderService->findByGatewayCode($orderCode, Auth::user());

        if ($order && $status === 'PAID') {
            return redirect()->route('booking.show', [
                'showtimeId' => $order->showtime_id,
                'paymentStatus' => 'success',
                'orderCode' => $orderCode,
            ]);
        }

        return redirect()->route('home');
    }

    public function payosWebhook(Request $request): JsonResponse
    {
        // Validate webhook signature first (already done in middleware)
        $validated = $request->validate([
            'orderCode' => 'required|integer',
            'amount' => 'required|integer',
            'status' => 'required|string',
        ]);

        ProcessPayOSWebhook::dispatch($validated);  // Only dispatch validated data

        return response()->json(['success' => true]);
    }
}
```

**SOLID Violations:**
- ❌ **Single Responsibility:** Handles HTTP + data access + authorization
- ❌ **Dependency Inversion:** Depends on concrete Order model, not abstraction

---

### 2.3 Poor: BookingController ⭐⭐ (2/5)

**File:** `app/Http/Controllers/BookingController.php`

**Same Issues as PaymentController:**

```php
// Line 35-42: Direct query in controller
$query = Order::where('gateway_order_code', $orderCode);  // ❌

if (Auth::check()) {
    $query->where('user_id', Auth::id());  // ❌ Duplicate authorization logic
}

$order = $query->first();

// Line 46: Service Locator anti-pattern
app(PaymentService::class)->syncFromGateway($order);  // ❌ Should use constructor DI
```

**Additional Issues:**

1. **Service Locator Pattern** ❌
   - Uses `app()` helper instead of constructor injection
   - Violates Dependency Inversion Principle
   - Hides dependencies (not explicit in constructor)

2. **Code Duplication** ❌
   - Exact same query logic as PaymentController
   - Should extract to OrderService method like:
     ```php
     $order = $this->orderService->findByGatewayCode($orderCode, Auth::user());
     ```

**Why Service Locator is Bad:**
```php
// BAD: Hidden dependency
public function show(Request $request, int $showtimeId)
{
    app(PaymentService::class)->syncFromGateway($order);  // Where did this come from?
}

// GOOD: Explicit dependency
public function __construct(
    private readonly PaymentService $paymentService
) {}

public function show(Request $request, int $showtimeId)
{
    $this->paymentService->syncFromGateway($order);  // Clear dependency
}
```

---

## 3. Service Layer Analysis

### 3.1 Excellent: PaymentService ⭐⭐⭐⭐⭐

**File:** `app/Services/PaymentService.php`

**Architecture:**
```php
class PaymentService
{
    public function __construct(
        private readonly PayOSGateway            $gateway,      // ✅
        private readonly PricingService          $pricing,      // ✅
        private readonly OrderFulfillmentService $fulfillment,  // ✅
    ) {}

    public function initiate(User $user, Showtime $showtime, array $validated, string $baseUrl): array
    {
        // Delegate pricing calculation to specialized service
        $pricing = $this->pricing->buildSnapshot(
            $user, $showtime, $seatRequests, $productRequests, 
            $validated['voucher_code'] ?? null, 
            (int) ($validated['points_used'] ?? 0)
        );

        // Create order in transaction
        $order = DB::transaction(function () use ($user, $showtime, $pricing) {
            return Order::create([/* ... */]);
        });

        // Delegate payment link creation to gateway
        $response = $this->gateway->createPaymentLink([/* ... */]);

        return ['order' => $order, 'checkoutUrl' => $response['checkoutUrl']];
    }
}
```

**Why This is Excellent:**

1. **Service Orchestration Pattern** ✅
   - PaymentService **orchestrates** but doesn't do everything
   - Delegates to specialized services:
     - PricingService: Handles complex pricing logic
     - PayOSGateway: Handles payment gateway integration
     - OrderFulfillmentService: Handles order completion

2. **Single Responsibility** ✅
   - Only responsible for coordinating payment flow
   - Doesn't contain pricing logic, gateway logic, or fulfillment logic
   - Each concern handled by appropriate service

3. **Proper Dependency Injection** ✅
   - All dependencies injected via constructor
   - Clear, explicit dependencies
   - Testable (can mock dependencies)

4. **Transaction Boundaries** ✅
   - Wraps order creation in DB transaction
   - Ensures atomicity of database operations

**This is the CORRECT service layer pattern.**

---

### 3.2 Good: OrderService ⭐⭐⭐⭐

**File:** `app/Services/OrderService.php` (reviewed in Phase 4)

**Strengths:**
- Proper transaction usage
- Pessimistic locking for race conditions
- Delegates to OrderExpirationService
- Clean method signatures

**Minor Issues:**
- Line 413: Large file (could be split into OrderCreationService, OrderQueryService)
- Some methods do multiple things (create + format)

---

## 4. Missing Architectural Patterns

### 4.1 Repository Pattern ❌

**Current:** Services talk directly to Eloquent models

```php
// In PaymentController (bad)
$order = Order::where('gateway_order_code', $orderCode)->first();
```

**Recommended:** Repository abstraction layer

```php
// app/Repositories/OrderRepository.php
interface OrderRepositoryInterface
{
    public function findByGatewayCode(int $code, ?User $user = null): ?Order;
    public function findPendingOrders(int $showtimeId): Collection;
}

class OrderRepository implements OrderRepositoryInterface
{
    public function findByGatewayCode(int $code, ?User $user = null): ?Order
    {
        $query = Order::where('gateway_order_code', $code);
        
        if ($user) {
            $query->where('user_id', $user->id);
        }
        
        return $query->first();
    }
}

// Bind in AppServiceProvider
$this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);

// Use in controllers/services
public function __construct(
    private readonly OrderRepositoryInterface $orderRepo
) {}
```

**Benefits:**
- Centralized query logic
- Easier to test (mock interface)
- Can swap implementations (SQL → NoSQL)
- Clearer separation of concerns

---

### 4.2 Interface Segregation ⚠️

**Current:** Services are concrete classes, not interfaces

```php
// Current
public function __construct(
    private readonly OrderService $orderService  // Concrete class
) {}
```

**Recommended:** Program to interfaces

```php
// app/Contracts/OrderServiceInterface.php
interface OrderServiceInterface
{
    public function create(array $data, User $user): Order;
    public function findForUser(int $id, User $user): Order;
    public function cancel(int $id, User $user): Order;
}

// Implement interface
class OrderService implements OrderServiceInterface
{
    // Implementation
}

// Bind in AppServiceProvider
$this->app->bind(OrderServiceInterface::class, OrderService::class);

// Use interface
public function __construct(
    private readonly OrderServiceInterface $orderService
) {}
```

**Benefits:**
- Easier testing (mock interfaces)
- Flexibility to swap implementations
- Better SOLID compliance (Dependency Inversion)
- Clear contracts

---

### 4.3 Event-Driven Architecture (Underutilized) ⚠️

**Current:** Only 2 events defined

```
app/Events/
├── OrderPaid.php
└── SeatStatusUpdated.php
```

**Opportunities:**

```php
// More events to decouple logic
OrderCreated::class
OrderCancelled::class
PaymentProcessed::class
PaymentFailed::class
SeatReserved::class
SeatReleased::class
```

**Example Refactor:**

```php
// Instead of direct calls in OrderService
public function cancel(int $id, User $user): Order
{
    $order = $this->find($id, $user);
    $order->update(['status' => Order::STATUS_CANCELLED]);
    
    // Direct coupling
    $this->seatService->releaseSeats($order);  // ❌
    $this->notificationService->sendCancellationEmail($order);  // ❌
    
    return $order;
}

// Use events for decoupling
public function cancel(int $id, User $user): Order
{
    $order = $this->find($id, $user);
    $order->update(['status' => Order::STATUS_CANCELLED]);
    
    // Loosely coupled via events
    event(new OrderCancelled($order));  // ✅
    
    return $order;
}

// Register listeners
Event::listen(OrderCancelled::class, ReleaseSeatListener::class);
Event::listen(OrderCancelled::class, SendCancellationEmailListener::class);
```

**Benefits:**
- Decouples services
- Easier to add new behaviors
- Asynchronous processing
- Better testability

---

## 5. Dependency Injection Issues

### 5.1 AppServiceProvider - Missing Bindings

**File:** `app/Providers/AppServiceProvider.php`

**Current State:**
```php
public function register(): void
{
    // Empty - no service bindings
}
```

**Issues:**
- No explicit service bindings
- Relies entirely on Laravel's auto-wiring
- No interface bindings
- Tight coupling to concrete implementations

**Recommended:**
```php
public function register(): void
{
    // Service bindings
    $this->app->bind(OrderServiceInterface::class, OrderService::class);
    $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
    
    // Repository bindings
    $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    
    // Gateway bindings (could swap implementations)
    $this->app->bind(PaymentGatewayInterface::class, PayOSGateway::class);
    
    // Singletons for shared state
    $this->app->singleton(SeatLockManager::class);
}
```

---

### 5.2 Inconsistent DI Patterns

**Three patterns found:**

1. **Constructor Injection (Good)** ✅
```php
public function __construct(
    private readonly OrderService $orderService
) {}
```

2. **Service Locator (Bad)** ❌
```php
app(PaymentService::class)->syncFromGateway($order);
```

3. **Facade (Acceptable for framework services)** ✅
```php
Auth::user()
DB::transaction()
```

**Recommendation:** Standardize on constructor injection.

---

## 6. SOLID Principles Assessment

### Single Responsibility Principle

**✅ GOOD:**
- OrderService: Order management only
- PricingService: Pricing calculations only
- PayOSGateway: Payment gateway integration only

**❌ BAD:**
- PaymentController: HTTP + data access + authorization
- BookingController: HTTP + data access + service location

**Score:** 3/5

---

### Open/Closed Principle

**Current:** Hard to extend without modification

**Example:** Adding new payment gateway requires modifying PaymentService

**Recommended:**
```php
interface PaymentGatewayInterface
{
    public function createPaymentLink(array $data): array;
    public function verifyWebhook(Request $request): bool;
}

class PayOSGateway implements PaymentGatewayInterface { }
class StripeGateway implements PaymentGatewayInterface { }

// Service works with any gateway
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway
    ) {}
}
```

**Score:** 2/5

---

### Liskov Substitution Principle

**N/A** - Minimal inheritance used (good!)

**Score:** 5/5 (by not using inheritance incorrectly)

---

### Interface Segregation Principle

**Current:** No interfaces → Can't violate principle

**Recommended:** Add interfaces for better contract definition

**Score:** 2/5 (not using interfaces at all)

---

### Dependency Inversion Principle

**Mixed:**
- ✅ Services injected (not instantiated)
- ❌ Depend on concrete classes, not abstractions
- ❌ Service locator pattern in BookingController

**Score:** 3/5

---

**Overall SOLID Score:** 15/25 (60%) - **Room for improvement**

---

## 7. Code Quality Metrics

### Coupling

**Tight Coupling:**
- Controllers → Eloquent models directly
- Services → Concrete service classes
- No abstraction layer

**Loose Coupling:**
- Controllers → Services (when done right)
- Service orchestration pattern

**Score:** ⭐⭐⭐ (3/5)

---

### Cohesion

**High Cohesion (Good):**
- PricingService: Only pricing logic
- OrderService: Only order operations
- PayOSGateway: Only PayOS integration

**Low Cohesion (Bad):**
- PaymentController: HTTP + queries + authorization
- BookingController: Multiple concerns

**Score:** ⭐⭐⭐⭐ (4/5)

---

### Code Reuse

**Issues:**
- Query logic duplicated (PaymentController, BookingController)
- Only 1 trait (ApiResponse)
- Limited use of events/listeners

**Score:** ⭐⭐ (2/5)

---

## 8. Actionable Recommendations

### Priority 1: Fix Fat Controllers (CRITICAL) 🔴

**Refactor PaymentController and BookingController:**

```php
// Extract duplicate query logic to OrderService
class OrderService
{
    public function findByGatewayCode(
        string|int $code, 
        ?User $user = null
    ): ?Order {
        $query = Order::where('gateway_order_code', $code);
        
        if ($user) {
            $query->where('user_id', $user->id);
        }
        
        return $query->first();
    }
}

// Use in controllers
class PaymentController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function payosCallback(Request $request): RedirectResponse
    {
        $order = $this->orderService->findByGatewayCode(
            $request->query('orderCode'),
            Auth::user()
        );
        
        // Rest of logic...
    }
}
```

**Impact:** Eliminates code duplication, improves maintainability

---

### Priority 2: Add Repository Layer (HIGH) 🟠

**Create repository interfaces and implementations:**

```bash
php artisan make:interface Repositories/OrderRepositoryInterface
php artisan make:class Repositories/OrderRepository
```

**Bind in AppServiceProvider:**
```php
$this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
```

**Impact:** Better separation of concerns, easier testing

---

### Priority 3: Add Service Interfaces (HIGH) 🟠

**Create interface for each service:**

```php
interface OrderServiceInterface
{
    public function create(array $data, User $user): Order;
    public function findForUser(int $id, User $user): Order;
    // ... other methods
}
```

**Impact:** Better testability, flexibility, SOLID compliance

---

### Priority 4: Expand Event System (MEDIUM) 🟡

**Add more domain events:**
- OrderCreated, OrderCancelled
- PaymentProcessed, PaymentFailed
- SeatReserved, SeatReleased

**Create listeners for side effects:**
- SendOrderConfirmationEmail
- ReleaseSeatHolds
- UpdateInventory

**Impact:** Decoupled architecture, easier to extend

---

### Priority 5: Standardize DI (MEDIUM) 🟡

**Remove all `app()` calls, use constructor injection:**

```php
// BAD
app(PaymentService::class)->syncFromGateway($order);

// GOOD
public function __construct(
    private readonly PaymentService $paymentService
) {}

$this->paymentService->syncFromGateway($order);
```

**Impact:** Explicit dependencies, better testability

---

## 9. Architecture Score Card

| Aspect | Score | Notes |
|--------|-------|-------|
| **Controller Layer** | ⭐⭐⭐ | Mixed: OrderController excellent, Payment controllers poor |
| **Service Layer** | ⭐⭐⭐⭐ | Well-designed orchestration, good separation |
| **Data Layer** | ⭐⭐⭐ | Eloquent used well, but no repository pattern |
| **SOLID Compliance** | ⭐⭐⭐ | 60% - Room for improvement |
| **Design Patterns** | ⭐⭐ | Service layer good, missing repositories/events |
| **Dependency Injection** | ⭐⭐⭐ | Mostly good, some service locator anti-patterns |
| **Code Reuse** | ⭐⭐ | Significant duplication in controllers |
| **Testability** | ⭐⭐⭐ | Services testable, controllers harder due to tight coupling |

**Overall Architecture:** ⭐⭐⭐½ (3.5/5)

---

## 10. Conclusion

The application demonstrates **solid service layer architecture** with proper orchestration patterns, but **inconsistent controller layer** quality. Core domain logic (orders, pricing) is well-structured, while payment callback handlers contain architectural anti-patterns.

**Key Strengths:**
- Well-designed service orchestration
- Proper transaction management
- Good eager loading (Phase 4)
- FormRequest validation throughout

**Critical Improvements Needed:**
1. Refactor fat controllers (PaymentController, BookingController)
2. Add repository pattern for data access
3. Create service interfaces for DIP compliance
4. Expand event-driven architecture
5. Standardize dependency injection

**Production Readiness:**
- ✅ Safe for MVP deployment
- ⚠️ Refactor payment controllers before scaling
- 🎯 Implement repositories and interfaces for long-term maintainability

---

**Author:** Kiro AI Assistant  
**Phase:** 5 - Architecture Review Complete  
**Confidence:** High (90%)  
**Next Steps:** Implement Priority 1 & 2 recommendations before production scaling
