# Backend Refactor Plan - Controllers & Services

## Current Status Analysis

### ✅ Already Following Standards
| Controller | Service | Status |
|------------|---------|--------|
| MovieController | MovieService | ✅ Thin controller, DI via constructor |
| TheaterController | TheaterService | ✅ Thin controller, DI via constructor |
| ShowtimeController | ShowtimeService | ✅ Thin controller, method injection |

### ⚠️ Needs Refactoring

#### High Priority (Complex Business Logic)

**1. OrderController**
- **Issues:**
  - `formatOrder()` private method - formatting logic in controller
  - `isAdmin()` private method - authorization logic in controller
  - Complex query logic in `store()` and `userOrders()`
- **Action Required:**
  - Create `OrderService` with business logic
  - Move formatting to service or transformer
  - Move authorization to policy or gate
  - Extract queries to service layer
- **Impact:** High - core booking functionality

**2. PaymentController**
- **Issues:**
  - `formatPayment()` private method - formatting logic in controller
  - `isAdmin()` private method - authorization logic in controller
  - Payment verification logic in controller
  - Transaction handling in controller
- **Action Required:**
  - Create `PaymentService` with payment processing logic
  - Move formatting to service or transformer
  - Move authorization to policy
  - Extract payment gateway logic to service
- **Impact:** High - payment processing is critical

**3. SeatController**
- **Issues:**
  - `cleanupExpiredReservations()` - business logic in controller
  - `getBookedSeatIds()` - query logic in controller
  - Complex seat locking mechanism
  - Uses `OrderExpirationService` but still has logic
- **Action Required:**
  - Create `SeatService` or expand existing logic
  - Move cleanup logic to service
  - Move query logic to repository or service
  - Simplify controller to thin layer
- **Impact:** High - seat booking core functionality

#### Medium Priority (Moderate Logic)

**4. HomeController**
- **Issues:**
  - `transformMovie()` private method - data transformation in controller
  - Query logic directly in controller
- **Action Required:**
  - Create `HomeService` or use existing MovieService
  - Move transformation to transformer class or service
  - Extract queries to service
- **Impact:** Medium - affects homepage performance

**5. ScreenController**
- **Issues:**
  - CRUD logic directly in controller
  - No validation requests
  - Direct model queries
- **Action Required:**
  - Create `ScreenService`
  - Add `StoreScreenRequest` and `UpdateScreenRequest`
  - Move queries to service
- **Impact:** Medium - admin functionality

#### Low Priority (Simple CRUD)

**6. UserController**
- **Issues:**
  - Basic CRUD, but no service layer
  - No validation requests
- **Action Required:**
  - Create `UserService` (optional, can use repository pattern)
  - Add validation requests if needed
- **Impact:** Low - admin functionality only

**7. AuthController**
- **Status:** Already well-structured
  - Uses dependency injection
  - Has validation requests
  - Logic is auth-specific (acceptable in auth controller)
- **Action:** No changes needed for now

## Refactor Priority Order

### Phase 1: Critical Business Logic
1. **OrderService** - Extract order creation, cancellation logic
2. **PaymentService** - Extract payment processing, verification
3. **SeatService** - Extract seat locking, cleanup logic

### Phase 2: Data Layer
4. **HomeService** - Extract homepage data aggregation
5. **ScreenService** - Extract screen management

### Phase 3: Optional
6. **UserService** - Admin user management (low priority)

## Implementation Standards

### Service Layer Pattern
```php
<?php

namespace App\Services;

class OrderService
{
    public function createOrder(array $data): Order
    {
        // Business logic here
        // Validation
        // Database transactions
        // Event dispatching
        return $order;
    }
}
```

### Controller Pattern
```php
<?php

namespace App\Http\Controllers;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function store(StoreOrderRequest $request)
    {
        $order = $this->orderService->createOrder(
            $request->validated()
        );

        return $this->success($order, 'Order created');
    }
}
```

### Request Validation Pattern
```php
<?php

namespace App\Http\Requests;

class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'showtime_id' => 'required|exists:showtimes,id',
            'seats' => 'required|array|min:1',
            // ... more rules
        ];
    }
}
```

## Required New Files

### Services
- `app/Services/OrderService.php`
- `app/Services/PaymentService.php`
- `app/Services/SeatService.php`
- `app/Services/HomeService.php`
- `app/Services/ScreenService.php`

### Requests
- `app/Http/Requests/StoreOrderRequest.php`
- `app/Http/Requests/CancelOrderRequest.php`
- `app/Http/Requests/StorePaymentRequest.php`
- `app/Http/Requests/VerifyPaymentRequest.php`
- `app/Http/Requests/LockSeatRequest.php`
- `app/Http/Requests/StoreScreenRequest.php`
- `app/Http/Requests/UpdateScreenRequest.php`

### Policies (Optional)
- `app/Policies/OrderPolicy.php`
- `app/Policies/PaymentPolicy.php`

## Benefits

1. **Testability**: Services can be unit tested independently
2. **Reusability**: Business logic can be reused across controllers
3. **Maintainability**: Clear separation of concerns
4. **Scalability**: Easy to add new features without bloating controllers
5. **Type Safety**: Better IDE support and type hinting
6. **Debugging**: Easier to trace issues in isolated services

## Next Steps

1. Review this plan with team
2. Start with Phase 1 (OrderService, PaymentService, SeatService)
3. Write tests for new services
4. Refactor controllers one by one
5. Update documentation

## Notes

- Keep backward compatibility during refactor
- Add comprehensive tests before refactoring
- Use database transactions in services
- Implement proper error handling
- Follow existing naming conventions
- Use dependency injection consistently
