# File Review: Order.php (Model)

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Order.php  
**Lines:** 84  
**Type:** Eloquent Model - Order Records

---

## File Information

**Path:** `app/Models/Order.php`  
**Type:** Eloquent Model  
**Lines:** 84  
**Complexity:** Low  

**Purpose:**  
Core order model representing customer orders:
- Order status tracking
- Payment integration
- Showtime booking records
- Order items relationship
- User and promotion linking

**Database Table:** `orders`

---

## Overall Score

**Code Quality:** 6.0/10  
**Security:** 5.5/10 ⚠️  
**Performance:** 7.0/10  
**Maintainability:** 5.5/10  
**Laravel Best Practice:** 6.5/10  

**Overall Score:** 6.1/10

**Decision:** ⚠️ **APPROVE WITH CRITICAL FIXES REQUIRED**

---

## Strengths

1. ✅ **Proper Relationships** - Clear relationships to User, Showtime, Payment, OrderItems
2. ✅ **Proper Casts** - JSON payload, datetime fields, decimal amount properly cast
3. ✅ **Query Scopes** - Helpful scopes for common queries (Lines 70-83)
4. ✅ **Factory Support** - HasFactory trait for testing

---

## Issues Found

### Issue #1: Mass Assignment Security Risk - Status Field

**Severity:** 🟠 HIGH  
**Category:** Security - Authorization Bypass  
**Location:** Lines 19-33

**Evidence:**
```php
protected $fillable = [
    'code',
    'gateway_order_code',
    'user_id',
    'showtime_id',
    'total_amount',
    'payload',
    'status',  // ← CRITICAL: Status is mass-assignable!
    'payment_provider',
    'payment_status',
    'checkout_url',
    'paid_at',  // ← Also mass-assignable!
    'cancelled_at',
    'expired_at',
];
```

**Problem:**
Critical fields are mass-assignable. Same issue as Payment model. Attackers could:
- Mark pending orders as paid/confirmed without payment
- Change paid orders to cancelled (fraud)
- Manipulate timestamps (paid_at, cancelled_at)
- Bypass business logic entirely

**Attack Scenario:**
```php
// Attacker sends:
PATCH /api/orders/123
{
  "status": 2,  // ← Mark as PAID/CONFIRMED without payment!
  "paid_at": "2026-07-14 03:00:00"
}

// If using $order->update($request->all()):
// Order marked as paid WITHOUT actual payment!
```

**Impact:**
- Free orders (financial loss)
- Order fraud
- Payment bypass
- Data corruption

**Recommended Fix:**
```php
protected $fillable = [
    'code',
    'gateway_order_code',
    'user_id',
    'showtime_id',
    'total_amount',
    'payload',
    'payment_provider',
    'checkout_url',
    // Remove: 'status', 'payment_status', 'paid_at', 'cancelled_at', 'expired_at'
];

protected $guarded = [
    'status',
    'payment_status',
    'paid_at',
    'cancelled_at',
    'expired_at',
];
```

**Better Approach - Controlled State Changes:**
```php
public function markAsPending(): void
{
    $this->update(['status' => self::STATUS_PENDING]);
}

public function markAsConfirmed(): void
{
    if ($this->status !== self::STATUS_PENDING) {
        throw new \DomainException('Can only confirm pending orders');
    }
    
    $this->update([
        'status' => self::STATUS_CONFIRMED,
        'paid_at' => now(),
    ]);
}

public function markAsCancelled(?string $reason = null): void
{
    if ($this->status === self::STATUS_CONFIRMED) {
        throw new \DomainException('Cannot cancel confirmed orders. Use refund instead.');
    }
    
    $this->update([
        'status' => self::STATUS_CANCELLED,
        'cancelled_at' => now(),
        'payload' => array_merge($this->payload ?? [], ['cancellation_reason' => $reason]),
    ]);
}
```

---

### Issue #2: Confusing Status Alias - PAID vs CONFIRMED

**Severity:** 🟡 MEDIUM  
**Category:** Code Clarity & Maintainability  
**Location:** Lines 15-18

**Evidence:**
```php
public const STATUS_CANCELLED = 0;
public const STATUS_PENDING = 1;
public const STATUS_CONFIRMED = 2;
public const STATUS_PAID = 2; // Alias for confirmed in this context
```

**Problem:**
Two constants (STATUS_CONFIRMED and STATUS_PAID) have the same value (2). This causes:
- Confusion about which to use
- Inconsistent code (`if ($order->status === self::STATUS_PAID)` vs `self::STATUS_CONFIRMED`)
- Debugging difficulties
- Maintenance issues

**Why This Matters:**
```php
// Which is correct?
if ($order->status === self::STATUS_PAID) { ... }
if ($order->status === self::STATUS_CONFIRMED) { ... }

// Both work, but which represents the business logic?
// Are "paid" and "confirmed" really the same state?
```

**Recommended Fix - Remove Alias:**
```php
public const STATUS_CANCELLED = 0;
public const STATUS_PENDING = 1;
public const STATUS_CONFIRMED = 2; // Order is confirmed AND paid

// Remove: STATUS_PAID alias
```

**Better Approach - Separate States:**
```php
public const STATUS_CANCELLED = 0;
public const STATUS_PENDING = 1;
public const STATUS_PROCESSING = 2; // Payment being processed
public const STATUS_CONFIRMED = 3; // Payment confirmed
public const STATUS_FULFILLED = 4; // Order fulfilled (seats assigned)

// Clear state progression:
// pending → processing → confirmed → fulfilled
//     ↓
// cancelled
```

---

### Issue #3: Payment Status Duplication Risk

**Severity:** 🟡 MEDIUM  
**Category:** Data Consistency  
**Location:** Line 28 + Line 65

**Evidence:**
```php
protected $fillable = [
    // ...
    'payment_status',  // ← Order has payment_status field
];

public function payment(): HasOne
{
    return $this->hasOne(Payment::class);  // ← Also has Payment relationship
}
```

**Problem:**
Order has BOTH:
1. `payment_status` field (denormalized)
2. `payment()` relationship to Payment model

This can lead to inconsistency:
- Order.payment_status = 'success'
- But Payment.status = 'failed'
- Which is the source of truth?

**Impact:**
- Data inconsistency
- Sync issues
- Confusing logic
- Difficult debugging

**Recommended Fix - Choose ONE Source of Truth:**

**Option 1: Remove denormalized field (preferred)**
```php
// Remove payment_status from order
// Always check: $order->payment->status

public function isPaid(): bool
{
    return $this->payment && $this->payment->isSuccessful();
}
```

**Option 2: Keep denormalized but sync automatically**
```php
// In Payment model - Observer or Event
public static function boot()
{
    parent::boot();
    
    static::saved(function ($payment) {
        $payment->order->update([
            'payment_status' => $payment->status
        ]);
    });
}

// Add accessor to Order
public function isPaid(): bool
{
    // Use denormalized for performance
    return $this->payment_status === 'success';
}
```

---

### Issue #4: No State Transition Validation

**Severity:** 🟠 HIGH  
**Category:** Data Integrity  
**Location:** Model-wide

**Evidence:**
```php
// Currently possible:
$order = Order::find(1);
$order->status = self::STATUS_CONFIRMED;
$order->save();

$order->status = self::STATUS_PENDING; // ← Can downgrade confirmed to pending!
$order->save();

$order->status = 999; // ← Can set invalid status!
$order->save();
```

**Problem:**
No validation of:
- Valid status values
- Valid state transitions
- Business rules

Valid transitions should be:
- pending → confirmed ✅
- pending → cancelled ✅
- confirmed → cancelled ❌ (should require refund)
- confirmed → pending ❌ (impossible in real world)

**Impact:**
- Data corruption
- Invalid order states
- Business logic violations

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function status(): Attribute
{
    return Attribute::make(
        set: function (int $value) {
            // Validate status value
            $validStatuses = [
                self::STATUS_CANCELLED,
                self::STATUS_PENDING,
                self::STATUS_CONFIRMED,
            ];
            
            if (!in_array($value, $validStatuses, true)) {
                throw new \InvalidArgumentException("Invalid order status: $value");
            }
            
            // Validate state transition
            if ($this->exists) {
                $this->validateStatusTransition($this->status, $value);
            }
            
            return $value;
        }
    );
}

private function validateStatusTransition(int $from, int $to): void
{
    $allowedTransitions = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [], // Terminal state - requires special refund process
        self::STATUS_CANCELLED => [], // Terminal state
    ];
    
    if (!in_array($to, $allowedTransitions[$from] ?? [])) {
        throw new \DomainException("Invalid order state transition: $from -> $to");
    }
}
```

---

### Issue #5: Missing Audit Trail Fields

**Severity:** 🟡 MEDIUM  
**Category:** Compliance & Audit  
**Location:** Model structure

**Evidence:**
```php
// No audit fields:
// - created_by
// - updated_by
// - cancelled_by
// - ip_address
```

**Problem:**
Order records lack audit trail:
- Who created the order?
- Who cancelled it?
- From which IP?

Critical for:
- Fraud investigation
- Dispute resolution
- Customer service
- Security auditing

**Recommended Fix:**
```php
// In migration:
$table->unsignedBigInteger('created_by')->nullable();
$table->unsignedBigInteger('updated_by')->nullable();
$table->unsignedBigInteger('cancelled_by')->nullable();
$table->string('ip_address')->nullable();

// In model:
protected $fillable = [
    // ... existing fields ...
    'created_by',
    'cancelled_by',
    'ip_address',
];

protected $guarded = [
    'updated_by', // Auto-tracked
];
```

---

### Issue #6: Scope Methods Missing Return Types

**Severity:** 🔵 LOW  
**Category:** Type Safety  
**Location:** Lines 70-83

**Evidence:**
```php
public function scopeByStatus($query, $status)
{
    return $query->where('status', $status);
}
```

**Problem:**
Missing parameter and return type declarations.

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeByStatus(Builder $query, int $status): Builder
{
    return $query->where('status', $status);
}

public function scopeByOrderCode(Builder $query, string $code): Builder
{
    return $query->where('code', $code);
}

public function scopeByUser(Builder $query, int $userId): Builder
{
    return $query->where('user_id', $userId);
}
```

---

### Issue #7: No Expired Order Handling

**Severity:** 🔵 LOW  
**Category:** Business Logic  
**Location:** Missing functionality

**Evidence:**
```php
'expired_at' => 'datetime',  // ← Field exists but no logic
```

**Problem:**
Has expired_at field but no:
- Scope to find expired orders
- Automatic cleanup
- Business logic to handle expiration

**Recommended Fix:**
```php
// Add scopes
public function scopeExpired(Builder $query): Builder
{
    return $query->where('expired_at', '<=', now())
                 ->where('status', self::STATUS_PENDING);
}

public function scopeNotExpired(Builder $query): Builder
{
    return $query->where(function ($q) {
        $q->whereNull('expired_at')
          ->orWhere('expired_at', '>', now());
    });
}

// Add helper
public function isExpired(): bool
{
    return $this->expired_at && $this->expired_at->isPast();
}

// Add scheduled job to cancel expired orders
// In App\Console\Kernel or separate job
Order::expired()->chunk(100, function ($orders) {
    foreach ($orders as $order) {
        $order->markAsCancelled('Expired');
    }
});
```

---

## Recommendations

### Immediate (High Priority)

1. **Remove status from $fillable** - Prevent mass assignment attacks
2. **Add State Transition Validation** - Prevent invalid status changes
3. **Remove STATUS_PAID Alias** - Use STATUS_CONFIRMED consistently
4. **Resolve Payment Status Duplication** - Choose single source of truth

### Short Term

5. **Add Audit Trail Fields** - Track who/when/where
6. **Add Return Types to Scopes** - Better type safety
7. **Implement Expired Order Logic** - Auto-cancel expired orders
8. **Add Soft Deletes** - Protect order records

### Long Term

9. **Create Order State Machine** - Formal state pattern
10. **Add Order History Table** - Track all status changes
11. **Implement Immutability** - Once confirmed, minimal changes allowed
12. **Add Business Rule Validation** - Enforce complex constraints

---

## Test Requirements

```php
// Test 1: Mass assignment protection
public function test_status_cannot_be_mass_assigned()
{
    $order = Order::factory()->create(['status' => Order::STATUS_PENDING]);
    
    $order->update(['status' => Order::STATUS_CONFIRMED]);
    
    $this->assertEquals(Order::STATUS_PENDING, $order->fresh()->status);
}

// Test 2: State transition validation
public function test_cannot_downgrade_confirmed_to_pending()
{
    $order = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
    
    $this->expectException(\DomainException::class);
    $order->status = Order::STATUS_PENDING;
    $order->save();
}

// Test 3: Expired orders detected
public function test_expired_orders_are_detected()
{
    $order = Order::factory()->create([
        'expired_at' => now()->subHour(),
        'status' => Order::STATUS_PENDING
    ]);
    
    $this->assertTrue($order->isExpired());
    $this->assertEquals(1, Order::expired()->count());
}

// Test 4: Payment status consistency
public function test_order_payment_status_syncs_with_payment_model()
{
    $order = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'success'
    ]);
    
    $this->assertTrue($order->isPaid());
}
```

---

## Summary

Order model is a simple Eloquent model with basic functionality, but shares critical issues with Payment model:

**Critical Issues:**
1. **Status field is mass-assignable** - allows order manipulation
2. **No state transition validation** - data corruption risk
3. **Confusing status alias (PAID = CONFIRMED)** - maintainability issue
4. **Payment status duplication** - consistency risk

The model needs the same fixes as Payment:
- Remove status from fillable
- Add state machine validation
- Controlled state change methods
- Comprehensive audit trail

Additionally, the confusing STATUS_PAID alias should be removed, and payment_status duplication resolved.

**Status:** ⚠️ Critical fixes required before production

---

*Review completed: 2026-07-14 03:07 AM*
