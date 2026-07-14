# Code Review: OrderController.php

**File:** `app/Http/Controllers/OrderController.php`  
**Score:** 6.0/10  
**Decision:** ⚠️ **ACCEPTABLE WITH FIXES**

---

## High Priority Issues

### 🟠 HIGH #1: Information Disclosure via Exception

**Location:** Lines 42-47, 60-65, 78-83  
**Severity:** HIGH

```php
catch (\Exception $e) {
    return $this->errorResponse('Failed: ' . $e->getMessage(), 500);
}
```

**Fix:**
```php
catch (\Throwable $e) {
    Log::error('Order operation failed', [
        'operation' => 'index|show|cancel',
        'user_id' => Auth::id(),
        'error' => $e->getMessage(),
    ]);
    return $this->errorResponse(__('orders.operation_failed'), 500);
}
```

---

### 🟠 HIGH #2: No FormRequest Validation

**Location:** Lines 34, 52, 70

No validation for order_id or pagination parameters.

**Fix:**
```php
// Create OrderCancelRequest.php
class OrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = Order::findOrFail($this->route('id'));
        return $this->user()->id === $order->user_id;
    }
    
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:orders,id'],
        ];
    }
}

// In controller
public function cancel(OrderCancelRequest $request, $id)
{
    $user = Auth::user();
    $order = $this->orderService->cancel((int) $id, $user);
    return $this->successResponse($order, __('orders.cancelled'));
}
```

---

### 🟡 MEDIUM #3: Minimal Audit Logging

**Location:** All methods

Only logs errors, not successful operations.

**Fix:**
```php
public function cancel(Request $request, $id)
{
    $user = Auth::user();
    
    Log::info('Order cancellation initiated', [
        'order_id' => $id,
        'user_id' => $user->id,
        'ip' => $request->ip(),
    ]);
    
    $order = $this->orderService->cancel((int) $id, $user);
    
    Log::info('Order cancelled successfully', [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'refund_amount' => $order->total_amount,
    ]);
    
    return $this->successResponse($order, __('orders.cancelled'));
}
```

---

### 🟡 MEDIUM #4: No Rate Limiting on Cancel

**Location:** Line 70

User could spam cancel requests.

**Fix:**
```php
// In routes
Route::middleware(['auth:sanctum', 'throttle:order-cancel'])->group(function () {
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
});

RateLimiter::for('order-cancel', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()->id);
});
```

---

## Positive Findings

✅ Good authorization checks (uses orderService which validates ownership)  
✅ Proper service layer usage  
✅ Clean controller methods  
✅ Consistent response format

---

## Summary

**Issues:** 4 (0 Critical, 2 High, 2 Medium)

**Must Fix:**
1. Stop exposing exception messages
2. Add FormRequest validation
3. Add comprehensive audit logging
4. Add rate limiting

**Status:** Acceptable for production after fixes

**Estimated Fix Time:** 3-4 hours
