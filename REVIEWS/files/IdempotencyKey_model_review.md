# File Review: IdempotencyKey.php (Model)

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/IdempotencyKey.php  
**Lines:** 29  
**Type:** Eloquent Model - Idempotency Protection

---

## File Information

**Path:** `app/Models/IdempotencyKey.php`  
**Type:** Eloquent Model  
**Lines:** 29  
**Complexity:** Low  

**Purpose:**  
IdempotencyKey model for preventing duplicate operations:
- Stores operation keys and cached responses
- Prevents duplicate payment processing
- Returns cached response for duplicate requests
- Critical for payment safety

**Database Table:** `idempotency_keys`

---

## Overall Score

**Code Quality:** 5.5/10  
**Security:** 5.0/10 ⚠️  
**Performance:** 6.0/10  
**Maintainability:** 5.0/10  
**Laravel Best Practice:** 5.5/10  
**Architecture:** 4.5/10  

**Overall Score:** 5.3/10

**Decision:** ⚠️ **APPROVE WITH CRITICAL IMPROVEMENTS**

---

## Strengths

1. ✅ **Model Exists** - At least idempotency concept is recognized
2. ✅ **JSON Response Storage** - Proper casting for response data
3. ✅ **Expiration Support** - has expires_at field
4. ✅ **Basic Scopes** - valid() and byKey() scopes

---

## Critical Context

**Important:** In my review of PaymentService.php (File #1), I flagged "Missing idempotency protection" as a BLOCKING issue. This model EXISTS but isn't being USED properly in the payment flow, or is incomplete. The issues below explain the gaps.

---

## Issues Found

### Issue #1: No Unique Constraint on Key Field

**Severity:** 🟠 HIGH  
**Category:** Data Integrity & Race Conditions  
**Location:** Line 10

**Evidence:**
```php
protected $fillable = [
    'key',  // ← No unique constraint enforced at model level
    'response',
    'expires_at',
];
```

**Problem:**
The 'key' field must be unique, but this isn't enforced at the model level. Without a unique database constraint, race conditions can create duplicate keys:

```php
// Request 1 at 10:00:00.000
$existing = IdempotencyKey::where('key', $idempotencyKey)->first();
if (!$existing) {
    // Race condition window starts here!
    
    // Request 2 at 10:00:00.001 - also finds no existing key
    
    IdempotencyKey::create([
        'key' => $idempotencyKey,
        'response' => $response,
    ]);
    
    // Both requests create records - idempotency FAILS!
}
```

**Impact:**
- Duplicate payments can still occur
- Idempotency protection fails under load
- Race conditions guaranteed
- Defeats the entire purpose of this model

**Recommended Fix:**
```php
// In migration:
$table->string('key')->unique();  // ← Database-level uniqueness

// In model:
public static function boot()
{
    parent::boot();
    
    static::creating(function ($idempotencyKey) {
        $exists = self::where('key', $idempotencyKey->key)->exists();
        if ($exists) {
            throw new \DomainException('Idempotency key already exists');
        }
    });
}

// Better: use firstOrCreate() with unique constraint
public static function storeOrRetrieve(string $key, callable $operation): mixed
{
    try {
        return DB::transaction(function () use ($key, $operation) {
            // Try to insert - will fail if key exists
            $record = self::create([
                'key' => $key,
                'status' => 'processing',
                'expires_at' => now()->addHours(24),
            ]);
            
            // Execute operation
            $response = $operation();
            
            // Store response
            $record->update([
                'response' => $response,
                'status' => 'completed',
            ]);
            
            return $response;
        });
    } catch (\Illuminate\Database\QueryException $e) {
        // Unique constraint violation - key already exists
        $existing = self::where('key', $key)->firstOrFail();
        
        if ($existing->status === 'processing') {
            // Still processing - wait and retry
            throw new \Exception('Operation in progress');
        }
        
        return $existing->response;
    }
}
```

---

### Issue #2: No Status Field for In-Progress Operations

**Severity:** 🟠 HIGH  
**Category:** Race Conditions  
**Location:** Missing field

**Evidence:**
```php
protected $fillable = [
    'key',
    'response',
    'expires_at',
    // Missing: 'status'
];
```

**Problem:**
No way to track if operation is:
- Pending (key created but operation not started)
- Processing (operation in progress)
- Completed (operation finished successfully)
- Failed (operation failed)

Without status:
```php
// Request 1 creates key and starts long payment process
IdempotencyKey::create(['key' => 'abc123']);
processPayment();  // Takes 3 seconds

// Request 2 arrives 1 second later
$existing = IdempotencyKey::where('key', 'abc123')->first();
// $existing->response is NULL - operation still processing!
// What should we do? Retry? Wait? Return error?
```

**Impact:**
- Cannot tell if operation is in progress
- Duplicate requests during processing unclear
- May retry operations that are still running
- Race conditions

**Recommended Fix:**
```php
const STATUS_PENDING = 'pending';
const STATUS_PROCESSING = 'processing';
const STATUS_COMPLETED = 'completed';
const STATUS_FAILED = 'failed';

protected $fillable = [
    'key',
    'response',
    'status',
    'expires_at',
];

// Usage
public static function executeIdempotent(string $key, callable $operation): mixed
{
    $record = self::firstOrCreate(
        ['key' => $key],
        [
            'status' => self::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]
    );
    
    if ($record->status === self::STATUS_COMPLETED) {
        return $record->response;
    }
    
    if ($record->status === self::STATUS_PROCESSING) {
        // Wait for completion or timeout
        throw new \Exception('Operation already in progress');
    }
    
    if ($record->status === self::STATUS_FAILED) {
        // Retry allowed for failed operations
        $record->update(['status' => self::STATUS_PROCESSING]);
    }
    
    try {
        $record->update(['status' => self::STATUS_PROCESSING]);
        $response = $operation();
        $record->update([
            'status' => self::STATUS_COMPLETED,
            'response' => $response,
        ]);
        return $response;
    } catch (\Exception $e) {
        $record->update([
            'status' => self::STATUS_FAILED,
            'response' => ['error' => $e->getMessage()],
        ]);
        throw $e;
    }
}
```

---

### Issue #3: Missing Request Metadata

**Severity:** 🟡 MEDIUM  
**Category:** Debugging & Auditing  
**Location:** Missing fields

**Evidence:**
```php
protected $fillable = [
    'key',
    'response',
    'expires_at',
    // Missing: request_path, request_method, user_id, etc.
];
```

**Problem:**
Cannot track what operation the idempotency key protected:
- Which API endpoint?
- Which HTTP method?
- Which user?
- What was the original request payload?

This makes debugging impossible:
```php
// Found duplicate key, but what did it protect?
$key = IdempotencyKey::where('key', 'abc123')->first();
// No idea what operation this was!
```

**Recommended Fix:**
```php
protected $fillable = [
    'key',
    'request_path',      // e.g., '/api/payments'
    'request_method',    // e.g., 'POST'
    'request_payload',   // Original request data
    'user_id',          // Who made the request
    'response',
    'response_status',  // HTTP status code
    'status',
    'expires_at',
];

// Store with context
public static function createWithContext(
    string $key,
    \Illuminate\Http\Request $request
): self {
    return self::create([
        'key' => $key,
        'request_path' => $request->path(),
        'request_method' => $request->method(),
        'request_payload' => $request->except(['password', 'token']),
        'user_id' => auth()->id(),
        'status' => self::STATUS_PENDING,
        'expires_at' => now()->addHours(24),
    ]);
}
```

---

### Issue #4: No Integration with Payment/Order Models

**Severity:** 🟡 MEDIUM  
**Category:** Domain Modeling  
**Location:** Missing relationships

**Evidence:**
```php
// No relationships defined
```

**Problem:**
Should track which Payment or Order this key protected:
```php
// Currently cannot do:
$payment = $idempotencyKey->payment;

// Or inverse:
$payment->idempotencyKey;
```

**Impact:**
- Cannot trace idempotency to actual operations
- Difficult to audit
- Cannot cleanup old keys when payments are completed

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Relations\MorphTo;

public function idempotentable(): MorphTo
{
    return $this->morphTo();
}

// Or specific relationships:
public function payment(): BelongsTo
{
    return $this->belongsTo(Payment::class);
}

// Store with relationship
$idempotencyKey = IdempotencyKey::create([
    'key' => $key,
    'idempotentable_type' => Payment::class,
    'idempotentable_id' => $payment->id,
]);
```

---

### Issue #5: Response Structure Incomplete

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency  
**Location:** Line 12, 16

**Evidence:**
```php
protected $fillable = [
    'response',  // ← Just the body, what about status code? Headers?
];

protected $casts = [
    'response' => 'json',
];
```

**Problem:**
Only stores response body as JSON, but idempotent responses should include:
- HTTP status code (200, 201, etc.)
- Response headers
- Response body

Without this:
```php
// Original request returned 201 Created with Location header
// Idempotent retry returns... what status code?
```

**Recommended Fix:**
```php
protected $fillable = [
    'response_status',  // HTTP status code
    'response_headers', // Response headers
    'response_body',    // Response body
];

protected $casts = [
    'response_headers' => 'json',
    'response_body' => 'json',
    'response_status' => 'integer',
];

// Store complete response
public function storeResponse(\Illuminate\Http\JsonResponse $response): void
{
    $this->update([
        'response_status' => $response->status(),
        'response_headers' => $response->headers->all(),
        'response_body' => json_decode($response->getContent(), true),
        'status' => self::STATUS_COMPLETED,
    ]);
}

// Replay response
public function toResponse(): \Illuminate\Http\JsonResponse
{
    return response()->json(
        $this->response_body,
        $this->response_status,
        $this->response_headers
    );
}
```

---

### Issue #6: No Default TTL Logic

**Severity:** 🔵 LOW  
**Category:** Code Quality  
**Location:** Line 12

**Evidence:**
```php
'expires_at',  // ← Manual expiration, no helper
```

**Problem:**
No helper to set consistent TTL. Developers must manually calculate:
```php
IdempotencyKey::create([
    'expires_at' => now()->addHours(24),  // Manual
]);
```

**Recommended Fix:**
```php
const DEFAULT_TTL_HOURS = 24;

public static function generateKey(): string
{
    return Str::uuid()->toString();
}

public static function createNew(string $key, ?int $ttlHours = null): self
{
    return self::create([
        'key' => $key,
        'expires_at' => now()->addHours($ttlHours ?? self::DEFAULT_TTL_HOURS),
        'status' => self::STATUS_PENDING,
    ]);
}
```

---

### Issue #7: Scope Methods Missing Return Types

**Severity:** 🔵 LOW  
**Category:** Type Safety  
**Location:** Lines 20-28

**Evidence:**
```php
public function scopeValid($query)
{
    return $query->where('expires_at', '>', now());
}
```

**Problem:**
Missing parameter and return type declarations.

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeValid(Builder $query): Builder
{
    return $query->where('expires_at', '>', now());
}

public function scopeByKey(Builder $query, string $key): Builder
{
    return $query->where('key', $key);
}
```

---

### Issue #8: Key Generation Security Not Enforced

**Severity:** 🟡 MEDIUM  
**Category:** Security  
**Location:** Model-wide

**Evidence:**
```php
// Model doesn't enforce how keys are generated
```

**Problem:**
If keys are predictable, attackers can:
- Guess valid keys
- Replay operations
- Bypass idempotency protection

Keys should be:
- Cryptographically random (UUID v4 or similar)
- Long enough to prevent brute force
- Client-generated with server validation

**Recommended Fix:**
```php
use Illuminate\Support\Str;

public static function generateSecureKey(): string
{
    return Str::uuid()->toString();  // UUID v4
}

public static function validateKey(string $key): bool
{
    // UUID v4 format: 8-4-4-4-12 hex digits
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $key
    );
}

// In service/controller
public function store(Request $request)
{
    $idempotencyKey = $request->header('Idempotency-Key');
    
    if (!IdempotencyKey::validateKey($idempotencyKey)) {
        throw new \InvalidArgumentException('Invalid idempotency key format');
    }
    
    // Proceed with operation
}
```

---

## Recommendations

### IMMEDIATE (HIGH PRIORITY)

1. **Add Unique Constraint** - Database-level uniqueness on 'key'
2. **Add Status Field** - Track pending/processing/completed/failed
3. **Implement Atomic Check-and-Create** - Prevent race conditions
4. **Add Request Metadata** - path, method, user_id for debugging

### SHORT TERM

5. **Add Model Relationships** - Link to Payment/Order
6. **Store Complete Response** - Status code, headers, body
7. **Add Helper Methods** - executeIdempotent(), storeResponse()
8. **Enforce Key Format** - Validate UUID format

### LONG TERM

9. **Add Automatic Cleanup** - Scheduled command for expired keys
10. **Add Monitoring** - Track idempotency hit rate
11. **Add Return Types** - Better type safety
12. **Document Usage** - How to use this model correctly

---

## Integration Example

```php
// In PaymentService
use App\Models\IdempotencyKey;

public function createPayment(CreatePaymentRequest $request): JsonResponse
{
    $idempotencyKey = $request->header('Idempotency-Key');
    
    if (!$idempotencyKey) {
        throw new \InvalidArgumentException('Idempotency-Key header required');
    }
    
    return IdempotencyKey::executeIdempotent($idempotencyKey, function () use ($request) {
        // This code runs only once per idempotency key
        $payment = DB::transaction(function () use ($request) {
            $order = Order::create([...]);
            
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $request->amount,
                'status' => 'pending',
            ]);
            
            // Process payment with gateway
            $result = $this->gateway->processPayment($payment);
            
            $payment->update([
                'status' => 'success',
                'transaction_code' => $result['transaction_code'],
            ]);
            
            return $payment;
        });
        
        return response()->json([
            'status' => 'success',
            'payment' => $payment,
        ], 201);
    });
}
```

---

## Summary

IdempotencyKey model exists but is incomplete and not properly integrated with the payment flow.

**Critical Gaps:**
1. **No unique constraint** - allows duplicate keys (race conditions)
2. **No status tracking** - cannot handle in-progress operations
3. **No request metadata** - impossible to debug
4. **Not integrated** - exists but not used in PaymentService

This explains why my PaymentService review flagged "Missing idempotency protection" as BLOCKING - the model exists but isn't providing effective protection.

**Required Actions:**
- Add unique database constraint on key field
- Add status field for in-progress tracking
- Implement proper atomic check-and-create logic
- Integrate with PaymentService properly
- Add comprehensive request metadata

After implementing these fixes, this model will provide real idempotency protection instead of just being a placeholder.

**Status:** ⚠️ Critical improvements required (currently ineffective)

---

**🎉 PHASE 1 COMPLETE! This is File #16/16 in Phase 1.**

---

*Review completed: 2026-07-14 03:15 AM*
