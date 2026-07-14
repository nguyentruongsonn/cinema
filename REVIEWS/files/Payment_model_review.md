# File Review: Payment.php (Model)

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Payment.php  
**Lines:** 80  
**Type:** Eloquent Model - Payment Records

---

## File Information

**Path:** `app/Models/Payment.php`  
**Type:** Eloquent Model  
**Lines:** 80  
**Complexity:** Low  

**Purpose:**  
Core payment model representing payment transactions:
- Payment status tracking
- Gateway integration data
- Amount and audit timestamps
- Relationships to Order and User

**Database Table:** `payments`

---

## Overall Score

**Code Quality:** 6.0/10  
**Security:** 5.0/10 ⚠️  
**Performance:** 7.0/10  
**Maintainability:** 6.0/10  
**Laravel Best Practice:** 6.5/10  

**Overall Score:** 6.1/10

**Decision:** ⚠️ **APPROVE WITH CRITICAL FIXES REQUIRED**

---

## Strengths

1. ✅ **Status Constants** - Clean constant definitions (Lines 14-19)
2. ✅ **Proper Casts** - JSON payload, datetime fields properly cast (Lines 34-39)
3. ✅ **Decimal Precision** - Amount stored as decimal:2 for currency
4. ✅ **Query Scopes** - Helpful scopes for common queries (Lines 52-67)
5. ✅ **Helper Methods** - isSuccessful(), isPending() for readability

---

## Issues Found

### Issue #1: Mass Assignment Security Risk - Status Field

**Severity:** 🟠 HIGH  
**Category:** Security - Authorization Bypass  
**Location:** Lines 21-32

**Evidence:**
```php
protected $fillable = [
    'order_id',
    'user_id',
    'method',
    'transaction_code',
    'gateway_order_code',
    'amount',
    'status',  // ← CRITICAL: Status is mass-assignable!
    'payload',
    'paid_at',
    'failed_at',
];
```

**Problem:**
Status field is mass-assignable. If controllers don't properly validate, attackers could:
- Mark pending payments as 'success' without paying
- Change failed payments to success
- Bypass payment gateway entirely

**Attack Scenario:**
```php
// Attacker sends:
POST /api/payments
{
  "order_id": 123,
  "amount": 1000,
  "status": "success",  // ← Bypasses payment!
  "paid_at": "2026-07-14 03:00:00"
}

// If using Payment::create($request->all()):
// Payment marked as successful WITHOUT actual payment!
```

**Impact:**
- Free orders
- Payment bypass
- Financial loss
- Fraud

**Recommended Fix:**
```php
protected $fillable = [
    'order_id',
    'user_id',
    'method',
    'transaction_code',
    'gateway_order_code',
    'amount',
    'payload',
    // Remove: 'status', 'paid_at', 'failed_at'
];

// Add guarded for extra safety
protected $guarded = [
    'status',
    'paid_at',
    'failed_at',
];
```

**Better Approach - Controlled State Changes:**
```php
public function markAsPending(): void
{
    $this->update(['status' => self::STATUS_PENDING]);
}

public function markAsProcessing(): void
{
    if (!$this->isPending()) {
        throw new \DomainException('Can only process pending payments');
    }
    $this->update(['status' => self::STATUS_PROCESSING]);
}

public function markAsSuccess(string $transactionCode): void
{
    if (!in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING])) {
        throw new \DomainException('Invalid payment state transition');
    }
    
    $this->update([
        'status' => self::STATUS_SUCCESS,
        'transaction_code' => $transactionCode,
        'paid_at' => now(),
    ]);
}

public function markAsFailed(?string $reason = null): void
{
    if ($this->isSuccessful()) {
        throw new \DomainException('Cannot mark successful payment as failed');
    }
    
    $this->update([
        'status' => self::STATUS_FAILED,
        'failed_at' => now(),
        'payload' => array_merge($this->payload ?? [], ['failure_reason' => $reason]),
    ]);
}
```

---

### Issue #2: No State Transition Validation

**Severity:** 🟠 HIGH  
**Category:** Data Integrity  
**Location:** Model-wide

**Evidence:**
```php
// Currently possible:
$payment = Payment::find(1);
$payment->status = 'success';
$payment->save();

$payment->status = 'failed'; // ← Can change success to failed!
$payment->save();

$payment->status = 'invalid_status'; // ← Can set invalid status!
$payment->save();
```

**Problem:**
No validation of:
- Valid status values
- Valid state transitions
- Business rules for status changes

Valid transitions should be:
- pending → processing → success ✅
- pending → processing → failed ✅
- pending → cancelled ✅
- success → refunded ✅ (not failed!)
- success → failed ❌ (invalid!)

**Impact:**
- Data corruption
- Invalid payment states
- Business logic violations
- Audit trail inconsistencies

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function status(): Attribute
{
    return Attribute::make(
        set: function (string $value) {
            // Validate status value
            $validStatuses = [
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                self::STATUS_SUCCESS,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED,
                self::STATUS_REFUNDED,
            ];
            
            if (!in_array($value, $validStatuses)) {
                throw new \InvalidArgumentException("Invalid payment status: $value");
            }
            
            // Validate state transition
            if ($this->exists) {
                $this->validateStatusTransition($this->status, $value);
            }
            
            return $value;
        }
    );
}

private function validateStatusTransition(string $from, string $to): void
{
    $allowedTransitions = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SUCCESS, self::STATUS_FAILED],
        self::STATUS_SUCCESS => [self::STATUS_REFUNDED],
        self::STATUS_FAILED => [], // Terminal state
        self::STATUS_CANCELLED => [], // Terminal state
        self::STATUS_REFUNDED => [], // Terminal state
    ];
    
    if (!in_array($to, $allowedTransitions[$from] ?? [])) {
        throw new \DomainException("Invalid payment state transition: $from -> $to");
    }
}
```

---

### Issue #3: Missing Audit Trail Fields

**Severity:** 🟡 MEDIUM  
**Category:** Compliance & Audit  
**Location:** Model structure

**Evidence:**
```php
// No audit fields:
// - created_by
// - updated_by
// - ip_address
// - user_agent
```

**Problem:**
Payment records lack audit trail for compliance:
- Who created the payment record?
- Who updated it?
- From which IP address?
- What was the user agent?

Critical for:
- Fraud investigation
- Dispute resolution
- Regulatory compliance
- Security auditing

**Impact:**
- Compliance violations (PCI-DSS, GDPR)
- Cannot trace who made changes
- Difficult fraud investigation
- Weak audit trail

**Recommended Fix:**
```php
// In migration:
$table->unsignedBigInteger('created_by')->nullable();
$table->unsignedBigInteger('updated_by')->nullable();
$table->string('ip_address')->nullable();
$table->text('user_agent')->nullable();

// In model:
protected $fillable = [
    // ... existing fields ...
    'created_by',
    'updated_by',
    'ip_address',
    'user_agent',
];

// Use trait for auto-tracking
use App\Traits\AuditableModel;

// Or implement manually in PaymentService:
public function createPayment(array $data, Request $request): Payment
{
    return Payment::create([
        ...$data,
        'created_by' => auth()->id(),
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);
}
```

---

### Issue #4: No Unique Constraint Enforcement

**Severity:** 🟡 MEDIUM  
**Category:** Data Integrity  
**Location:** Lines 21-32

**Evidence:**
```php
protected $fillable = [
    'transaction_code',  // ← Should be unique
    'gateway_order_code',  // ← Should be unique
];
```

**Problem:**
No model-level uniqueness validation. While database may have constraints, model doesn't enforce them. Could lead to:
- Duplicate transaction codes
- Race conditions in concurrent requests
- Unclear error messages

**Impact:**
- Data integrity issues
- Duplicate payment records
- Poor error messages

**Recommended Fix:**
```php
// In model - add validation
public static function boot()
{
    parent::boot();
    
    static::creating(function ($payment) {
        if ($payment->transaction_code) {
            if (self::where('transaction_code', $payment->transaction_code)->exists()) {
                throw new \DomainException('Duplicate transaction code');
            }
        }
    });
}

// Or use validation rules in service/controller
public function rules(): array
{
    return [
        'transaction_code' => ['required', 'unique:payments,transaction_code'],
        'gateway_order_code' => ['nullable', 'unique:payments,gateway_order_code'],
    ];
}
```

---

### Issue #5: Scope Methods Missing Return Types

**Severity:** 🔵 LOW  
**Category:** Type Safety  
**Location:** Lines 52-67

**Evidence:**
```php
public function scopeSuccessful($query)
{
    return $query->where('status', self::STATUS_SUCCESS);
}
```

**Problem:**
Missing return type declarations on scope methods. Modern PHP (8.0+) supports mixed return types.

**Impact:**
- Reduced type safety
- Poor IDE support
- Harder to maintain

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeSuccessful(Builder $query): Builder
{
    return $query->where('status', self::STATUS_SUCCESS);
}

public function scopePending(Builder $query): Builder
{
    return $query->where('status', self::STATUS_PENDING);
}

public function scopeFailed(Builder $query): Builder
{
    return $query->where('status', self::STATUS_FAILED);
}
```

---

### Issue #6: Missing Soft Deletes

**Severity:** 🔵 LOW  
**Category:** Data Retention  
**Location:** Model structure

**Evidence:**
```php
class Payment extends Model
{
    // No SoftDeletes trait
```

**Problem:**
Payment records should never be hard-deleted for:
- Audit trail preservation
- Regulatory compliance
- Dispute resolution
- Financial reconciliation

**Impact:**
- Data loss risk
- Compliance violations
- Cannot recover deleted records

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;
    
    // Prevent accidental deletion
    public function delete()
    {
        throw new \RuntimeException('Payment records cannot be deleted. Use soft delete.');
    }
    
    // Or even prevent soft delete
    public static function boot()
    {
        parent::boot();
        
        static::deleting(function ($payment) {
            throw new \RuntimeException('Payment records are immutable');
        });
    }
}
```

---

## Recommendations

### Immediate (High Priority)

1. **Remove status from $fillable** - Prevent mass assignment attacks
2. **Add State Transition Validation** - Prevent invalid status changes
3. **Add Controlled State Methods** - markAsSuccess(), markAsFailed()
4. **Add Audit Trail Fields** - Track who/when/where for compliance

### Short Term

5. **Enforce Unique Constraints** - Model-level validation
6. **Add Return Types to Scopes** - Better type safety
7. **Implement Soft Deletes** - Protect financial records
8. **Add Model Events** - Log all status changes

### Long Term

9. **Create Payment State Machine** - Formal state pattern
10. **Add Payment History Table** - Track all transitions
11. **Implement Immutability** - Once success, record is frozen
12. **Add Fraud Detection Hooks** - Monitor suspicious patterns

---

## Test Requirements

```php
// Test 1: Mass assignment protection
public function test_status_cannot_be_mass_assigned()
{
    $payment = Payment::create([
        'order_id' => 1,
        'amount' => 100,
        'status' => 'success', // Try to bypass
    ]);
    
    $this->assertNotEquals('success', $payment->status);
    $this->assertEquals('pending', $payment->status); // Default
}

// Test 2: State transition validation
public function test_cannot_transition_success_to_failed()
{
    $payment = Payment::factory()->create(['status' => 'success']);
    
    $this->expectException(\DomainException::class);
    $payment->update(['status' => 'failed']);
}

// Test 3: Invalid status rejected
public function test_invalid_status_throws_exception()
{
    $payment = Payment::factory()->create();
    
    $this->expectException(\InvalidArgumentException::class);
    $payment->status = 'invalid_status';
    $payment->save();
}

// Test 4: Unique transaction code
public function test_duplicate_transaction_code_prevented()
{
    Payment::factory()->create(['transaction_code' => 'TXN123']);
    
    $this->expectException(\Exception::class);
    Payment::factory()->create(['transaction_code' => 'TXN123']);
}
```

---

## Summary

Payment model is a simple Eloquent model with basic functionality, but it lacks critical security and data integrity controls required for financial records.

**Critical Issues:**
1. **Status field is mass-assignable** - allows payment bypass
2. **No state transition validation** - data corruption risk
3. **Missing audit trail** - compliance violation
4. **No controlled state change methods** - business logic scattered

For a payment model handling real money, these gaps are unacceptable. The model needs:
- Strict access control (remove status from fillable)
- State machine validation
- Comprehensive audit trail
- Immutability guarantees

After implementing these fixes, this model will be production-ready for financial operations.

**Status:** ⚠️ Critical fixes required before production

---

*Review completed: 2026-07-14 03:05 AM*