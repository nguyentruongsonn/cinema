# Code Review: OrderService.php

**File:** `app/Services/OrderService.php`  
**Score:** 7.0/10  
**Decision:** ⚠️ **ACCEPTABLE WITH FIXES**

---

## Critical Issues

### 🔴 CRITICAL #1: cancel() Not Wrapped in Transaction

**Location:** Lines 191-225  
**Severity:** CRITICAL - Data Corruption Risk

```php
public function cancel(int $orderId, $user): Order
{
    // NO TRANSACTION!
    $order = Order::lockForUpdate()->findOrFail($orderId);
    
    if ($order->user_id !== $user->id) {
        throw new \RuntimeException(__('orders.unauthorized'));
    }
    
    // Multiple database operations without transaction
    $order->update(['status' => Order::STATUS_CANCELLED]);
    $order->payment?->update(['status' => Payment::STATUS_CANCELLED]);
    
    // What if failure happens here?
    // Order cancelled but payment still "paid" in database
}
```

**Risk:**
- Order marked cancelled
- But payment update fails
- Inconsistent state: cancelled order with "paid" payment
- Stock not restored
- Promotion usage not decremented

**Fix:**
```php
public function cancel(int $orderId, $user): Order
{
    return DB::transaction(function () use ($orderId, $user) {
        $order = Order::lockForUpdate()->findOrFail($orderId);
        
        if ($order->user_id !== $user->id) {
            throw new \RuntimeException(__('orders.unauthorized'));
        }
        
        if ($order->status === Order::STATUS_CANCELLED) {
            return $order; // Already cancelled
        }
        
        if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])) {
            throw new \RuntimeException(__('orders.cannot_cancel'));
        }
        
        // Update order
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        
        // Update payment
        if ($order->payment) {
            $order->payment->update([
                'status' => Payment::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }
        
        // Restore product stock
        if ($order->order_products) {
            foreach ($order->order_products as $orderProduct) {
                $product = $orderProduct->product;
                if ($product) {
                    $product->increment('stock', $orderProduct->quantity);
                    
                    Log::info('Stock restored after cancellation', [
                        'product_id' => $product->id,
                        'quantity' => $orderProduct->quantity,
                    ]);
                }
            }
        }
        
        // Restore promotion usage
        if ($order->promotion_id) {
            DB::table('promotion_user')
                ->where('user_id', $order->user_id)
                ->where('promotion_id', $order->promotion_id)
                ->decrement('usage_count');
        }
        
        // Release seat hold if still exists
        SeatHold::where('order_id', $order->id)->delete();
        
        // Log cancellation
        Log::info('Order cancelled', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'total_amount' => $order->total_amount,
        ]);
        
        return $order->fresh();
    });
}
```

---

## High Priority Issues

### 🟠 HIGH #2: No Stock Restoration on Cancel

**Location:** Line 191-225

When order cancelled, product stock NOT restored.

**Impact:**
- Products permanently out of stock after cancellations
- Inventory count incorrect
- Business loss (cannot sell restored items)

---

### 🟠 HIGH #3: No Promotion Usage Decrement

**Location:** Line 191-225

When order cancelled, promotion usage_count NOT decremented.

**Impact:**
- User loses one promotion usage
- Unfair to customers
- Promotion appears "used" but order cancelled

---

## Positive Findings

✅ **Excellent** double-booking prevention in create()  
✅ Proper lockForUpdate() usage  
✅ Good validation logic  
✅ Authorization checks  
✅ Clean code structure

---

## Summary

**Issues:** 3 (1 Critical, 2 High)

**Must Fix:**
1. Wrap cancel() in DB transaction
2. Restore product stock on cancellation
3. Decrement promotion usage on cancellation

**Status:** Good service with critical transaction bug

**Estimated Fix Time:** 2-3 hours
