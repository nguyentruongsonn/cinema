# File Review: PromotionService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/PromotionService.php  
**Lines:** 211  
**Type:** Service Layer - Promotion / Voucher Logic

---

## File Information

**Path:** `app/Services/PromotionService.php`  
**Type:** Laravel Service Class  
**Lines:** 211  
**Complexity:** Medium  

**Purpose:**  
Handles promotion/voucher behavior:
- Listing active registered promotions for a user
- Registering a promotion code into `user_promotion`
- Validating a promotion against an order total
- Calculating discount amount

**Business Impact:** 🔴 CRITICAL - This file directly affects discounts, voucher redemption, order totals, and revenue. Incorrect concurrency, validation, or discount calculation can lose money or allow promotion abuse.

---

## Overall Score

**Code Quality:** 5.9/10  
**Security:** 5.6/10  
**Performance:** 6.2/10  
**Maintainability:** 5.7/10  
**Laravel Best Practice:** 5.8/10  

**Overall Score:** 5.8/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses Query Builder Bindings for Promotion Code** - `whereRaw(..., [value])` avoids direct SQL value interpolation.
2. ✅ **Checks Promotion Status and Validity Dates** - Active/date windows are consistently checked in multiple methods.
3. ✅ **Checks Global Usage Limit** - `usage_count < usage_limit` is considered.
4. ✅ **Checks Per-User Pivot Usage State** - `used_at` and pivot `usage_count` are checked for user-registered vouchers.
5. ✅ **Caps Fixed Discount to Order Total** - Prevents fixed discount from exceeding total.
6. ✅ **Supports Max Discount Cap for Percentage Promotions**.
7. ✅ **Keeps Discount Calculation in One Method**.

---

## Issues Found

### Issue #1: Promotion Registration Is Not Transactional

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Database Correctness / Business Logic  
**Location:** Lines 49-102

**Evidence:**
```php
$promotion = Promotion::where('promotions.status', 1)
    ...
    ->first();
```

```php
$existing = $user->promotions()
    ->where('promotions.id', $promotion->id)
    ->first();
```

```php
$user->promotions()->attach($promotion->id, [
    'status' => 1,
    'usage_count' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

**Problem:**
Promotion lookup, duplicate check, and pivot insert are performed without a database transaction.

**Why this matters:**
Two concurrent requests can both pass the `$existing` check and both attempt to attach the same promotion. Depending on database constraints, this can create duplicate user vouchers or throw an unhandled duplicate-key exception.

**How to fix:**
Wrap registration in a transaction and enforce a unique database constraint on `(user_id, promotion_id)`.

```php
return DB::transaction(function () use ($user, $code) {
    $promotion = Promotion::where(...)
        ->lockForUpdate()
        ->first();

    if (!$promotion) {
        return [...];
    }

    $existing = $user->promotions()
        ->where('promotions.id', $promotion->id)
        ->lockForUpdate()
        ->first();

    ...
});
```

---

### Issue #2: Global Usage Limit Check Is Race-Prone

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Revenue Protection  
**Location:** Lines 58-61 and 129-132

**Evidence:**
```php
->where(function ($query) {
    $query->whereNull('promotions.usage_limit')
        ->orWhereColumn('promotions.usage_count', '<', 'promotions.usage_limit');
})
```

**Problem:**
The service checks `usage_count < usage_limit`, but it does not lock the promotion row or atomically increment usage count in this file.

**Why this matters:**
Multiple concurrent users can validate/register the same promotion when only one usage remains. This can oversubscribe limited promotions and lose revenue.

**How to fix:**
Use atomic conditional updates when consuming a promotion.

```php
$updated = Promotion::whereKey($promotion->id)
    ->where(function ($query) {
        $query->whereNull('usage_limit')
            ->orWhereColumn('usage_count', '<', 'usage_limit');
    })
    ->increment('usage_count');

if ($updated === 0) {
    throw new DomainException('Promotion usage limit reached.');
}
```

Registration and redemption need clearly separated semantics.

---

### Issue #3: `validatePromotion()` Does Not Reserve or Consume the Voucher

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Concurrency / Payment Correctness  
**Location:** Lines 118-179

**Evidence:**
```php
$promotion = $promotionQuery->first();
```

```php
return [
    'valid' => true,
    'promotion' => $promotion,
    'discount_amount' => $discountAmount,
];
```

**Problem:**
Validation only checks eligibility and calculates a discount. It does not reserve, consume, or lock the promotion.

**Why this matters:**
If callers treat validation as sufficient before payment/order creation, the same voucher can be validated multiple times in parallel before one is marked as used elsewhere. Promotion redemption must be atomic with order creation or payment confirmation.

**How to fix:**
Create a dedicated `redeemPromotion()` method that runs inside the order transaction and atomically:
1. Locks the user-promotion pivot row.
2. Locks the promotion row.
3. Revalidates eligibility.
4. Applies discount.
5. Marks pivot row used with `order_id`.
6. Increments global usage count.

---

### Issue #4: User Promotion Pivot Is Not Locked Before Validation

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Duplicate Redemption  
**Location:** Lines 135-144

**Evidence:**
```php
$promotionQuery->whereHas('users', function ($query) use ($user) {
    $query->where('users.id', $user->id)
        ->where('user_promotion.status', 1)
        ->whereNull('user_promotion.used_at')
        ->where(function ($pivotQuery) {
            $pivotQuery->whereNull('user_promotion.usage_count')
                ->orWhere('user_promotion.usage_count', 0);
        });
});
```

**Problem:**
The pivot row is checked through `whereHas()` but not locked.

**Why this matters:**
Two simultaneous order submissions can both see the voucher as unused. Without locking during redemption, duplicate discount application is possible.

**How to fix:**
Use explicit pivot model/table query with `lockForUpdate()` inside the order transaction.

```php
$pivot = DB::table('user_promotion')
    ->where('user_id', $user->id)
    ->where('promotion_id', $promotion->id)
    ->whereNull('used_at')
    ->lockForUpdate()
    ->first();
```

---

### Issue #5: No Unique Guarantee for User Promotion Registration in Service

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / Duplicate Data  
**Location:** Lines 73-102

**Evidence:**
```php
$existing = $user->promotions()
    ->where('promotions.id', $promotion->id)
    ->first();
```

```php
$user->promotions()->attach($promotion->id, [
    'status' => 1,
    'usage_count' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

**Problem:**
The code relies on an application-level existence check before insert.

**Why this matters:**
Application-level checks are not enough under concurrency. Duplicate rows can cause repeated voucher availability, duplicate discount application, or inconsistent voucher wallet UI.

**How to fix:**
Add a unique index on the pivot table and handle duplicate-key exceptions idempotently.

```php
$table->unique(['user_id', 'promotion_id']);
```

Use `syncWithoutDetaching()` or `firstOrCreate()` on a pivot model if appropriate.

---

### Issue #6: Promotion Code Lookup Uses `LOWER()` and Prevents Normal Index Use

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Database  
**Location:** Line 62 and Line 133

**Evidence:**
```php
->whereRaw('LOWER(promotions.code) = ?', [mb_strtolower(trim($code))])
```

```php
->whereRaw('LOWER(promotions.code) = ?', [mb_strtolower(trim($code))]);
```

**Problem:**
Applying `LOWER()` to the column usually prevents normal index use.

**Why this matters:**
Promotion code lookup should be fast and index-backed. During campaigns, voucher-code validation can be high traffic.

**How to fix:**
Store normalized codes in uppercase/lowercase and query directly.

```php
$normalizedCode = mb_strtoupper(trim($code));

Promotion::where('code', $normalizedCode)->...
```

Enforce a unique index on normalized `code`.

---

### Issue #7: Promotion Code Input Is Not Length-Limited

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Performance  
**Location:** Lines 47, 62, 118, and 133

**Evidence:**
```php
public function registerPromotionForUser(User $user, string $code): array
```

```php
->whereRaw('LOWER(promotions.code) = ?', [mb_strtolower(trim($code))])
```

```php
public function validatePromotion(string $code, float $orderTotal, ?User $user = null): array
```

**Problem:**
The service accepts arbitrary-length promotion codes.

**Why this matters:**
Very long input can increase memory usage, slow string operations, create large query bindings, and clutter logs/upstream validation if reused.

**How to fix:**
Normalize and validate code format before querying.

```php
$code = mb_strtoupper(trim($code));

if ($code === '' || mb_strlen($code) > 64) {
    return ['valid' => false, ...];
}
```

---

### Issue #8: Empty Promotion Code Can Query the Database

**Severity:** 🟡 MEDIUM  
**Category:** Validation / API Correctness  
**Location:** Lines 62 and 133

**Evidence:**
```php
mb_strtolower(trim($code))
```

**Problem:**
If `$code` is empty or whitespace, the service still performs a database query for an empty code.

**Why this matters:**
Invalid input should fail before database access. This wastes resources and makes behavior dependent on whether an empty code exists in the database.

**How to fix:**
Reject empty codes before building the query.

```php
$code = trim($code);

if ($code === '') {
    return [
        'success' => false,
        'promotion' => null,
        'message' => 'Promotion code is required.',
    ];
}
```

---

### Issue #9: Money Uses `float`

**Severity:** 🟠 HIGH  
**Category:** Money Correctness / Financial Logic  
**Location:** Lines 115, 118, 159, 172, 188, 193, 201, and 209

**Evidence:**
```php
@param float $orderTotal
```

```php
public function validatePromotion(string $code, float $orderTotal, ?User $user = null): array
```

```php
public function calculateDiscount(Promotion $promotion, float $orderTotal): float
```

```php
return round($discount, 0);
```

**Problem:**
Order totals and discounts are represented as floating-point numbers.

**Why this matters:**
Floating-point arithmetic can introduce rounding errors. Financial calculations should use integer minor units or decimal value objects.

**How to fix:**
Use integer VND amounts or a Money value object.

```php
public function calculateDiscountInMinorUnits(Promotion $promotion, int $orderTotal): int
{
    ...
}
```

---

### Issue #10: Negative Order Totals Are Not Rejected

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Business Logic  
**Location:** Lines 118-172 and 188-209

**Evidence:**
```php
public function validatePromotion(string $code, float $orderTotal, ?User $user = null): array
```

```php
$discountAmount = $this->calculateDiscount($promotion, $orderTotal);
```

**Problem:**
The service does not reject negative order totals.

**Why this matters:**
A negative order total can produce unexpected discount behavior and indicates invalid caller state. Financial services should fail closed on invalid money values.

**How to fix:**
```php
if ($orderTotal < 0) {
    throw new InvalidArgumentException('Order total cannot be negative.');
}
```

---

### Issue #11: Percentage Discount Value Is Not Validated

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Revenue Protection  
**Location:** Lines 192-199

**Evidence:**
```php
if (in_array($promotion->discount_type, ['percentage', 'percent'])) {
    $discount = $orderTotal * ((float) $promotion->discount_value / 100);
```

**Problem:**
The method does not enforce that percentage values are within a safe range, such as `0 <= value <= 100`.

**Why this matters:**
A bad admin value like `500` can create a 500% discount unless capped by `max_discount_amount`. If max cap is not set, discount can exceed the order total.

**How to fix:**
Validate promotion data at creation/update and defensively cap calculation.

```php
$percentage = max(0, min((float) $promotion->discount_value, 100));
$discount = $orderTotal * ($percentage / 100);
$discount = min($discount, $orderTotal);
```

---

### Issue #12: Percentage Discount Is Not Capped to Order Total

**Severity:** 🟠 HIGH  
**Category:** Financial Logic / Revenue Protection  
**Location:** Lines 192-199

**Evidence:**
```php
$discount = $orderTotal * ((float) $promotion->discount_value / 100);

$maxDiscount = (float) ($promotion->max_discount_amount ?? 0);
if ($maxDiscount > 0 && $discount > $maxDiscount) {
    $discount = $maxDiscount;
}
```

**Problem:**
Fixed-amount discounts are capped to order total, but percentage discounts are not.

**Why this matters:**
If `discount_value` is above 100 or bad data exists, discount can exceed order total. The function should never return a discount greater than the payable total.

**How to fix:**
Always cap final discount.

```php
$discount = min($discount, $orderTotal);
```

---

### Issue #13: Unknown Discount Type Silently Returns Zero

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Error Handling  
**Location:** Lines 190-209

**Evidence:**
```php
$discount = 0;

if (in_array($promotion->discount_type, ['percentage', 'percent'])) {
    ...
} elseif (in_array($promotion->discount_type, ['fixed_amount', 'amount'])) {
    ...
}

return round($discount, 0);
```

**Problem:**
Unsupported `discount_type` silently returns a zero discount.

**Why this matters:**
Bad promotion data should be detected. Silent zero-discount behavior creates confusing customer experience and hides admin/configuration errors.

**How to fix:**
Throw a domain exception or return validation failure for unsupported types.

```php
throw new DomainException("Unsupported promotion discount type.");
```

---

### Issue #14: Mixed API Response Shape

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Maintainability  
**Location:** Lines 47-108 and 118-179

**Evidence:**
```php
return [
    'success' => false,
    'promotion' => null,
    'message' => 'Mã khuyến mãi không tồn tại hoặc đã hết hạn.',
];
```

```php
return [
    'valid' => false,
    'promotion' => null,
    'discount_amount' => 0,
    'error' => $user ? 'Mã khuyến mãi chưa được đăng ký trong Kho Voucher.' : 'Promotion not found',
];
```

**Problem:**
`registerPromotionForUser()` returns `success/message`, while `validatePromotion()` returns `valid/error/discount_amount`.

**Why this matters:**
Inconsistent result objects make controllers more complex and increase the chance of wrong response handling.

**How to fix:**
Use a DTO/result object with consistent fields.

```php
PromotionResult::failure(code: 'PROMOTION_NOT_FOUND', message: '...');
PromotionResult::success(promotion: $promotion, discountAmount: $amount);
```

---

### Issue #15: Service Contains User-Facing Localized Messages

**Severity:** 🔵 LOW  
**Category:** Maintainability / Localization  
**Location:** Lines 69, 86, 93, 107, 154, and 166

**Evidence:**
```php
'message' => 'Mã khuyến mãi không tồn tại hoặc đã hết hạn.',
```

```php
'error' => 'Đơn hàng tối thiểu ' . number_format($minOrderValue, 0, ',', '.') . 'đ để áp dụng mã này.',
```

**Problem:**
The service hardcodes Vietnamese user-facing messages.

**Why this matters:**
Services should return domain result codes or throw domain exceptions. Presentation messages belong in translation files or API response layer.

**How to fix:**
Return stable error codes and translate in controller/resource layer.

```php
return PromotionResult::failure('PROMOTION_EXPIRED');
```

---

### Issue #16: `now()` Is Called Multiple Times Inside One Logical Query

**Severity:** 🔵 LOW  
**Category:** Correctness / Testability  
**Location:** Lines 20-25, 51-56, and 122-127

**Evidence:**
```php
$query->whereNull('promotions.start_date')
    ->orWhere('promotions.start_date', '<=', now());
```

```php
$query->whereNull('promotions.end_date')
    ->orWhere('promotions.end_date', '>=', now());
```

**Problem:**
`now()` is called multiple times in the same logical operation.

**Why this matters:**
Although usually harmless, boundary-time behavior can become inconsistent around exact start/end timestamps. It also makes testing less deterministic.

**How to fix:**
Capture time once.

```php
$now = now();
...
->where('promotions.start_date', '<=', $now)
->where('promotions.end_date', '>=', $now)
```

---

### Issue #17: Repeated Promotion Eligibility Query Logic

**Severity:** 🟡 MEDIUM  
**Category:** Duplicate Code / Maintainability  
**Location:** Lines 17-30, 49-62, and 120-133

**Evidence:**
```php
->where('promotions.status', 1)
->where(function ($query) {
    $query->whereNull('promotions.start_date')
        ->orWhere('promotions.start_date', '<=', now());
})
->where(function ($query) {
    $query->whereNull('promotions.end_date')
        ->orWhere('promotions.end_date', '>=', now());
})
->where(function ($query) {
    $query->whereNull('promotions.usage_limit')
        ->orWhereColumn('promotions.usage_count', '<', 'promotions.usage_limit');
})
```

**Problem:**
The same active/date/usage-limit logic is duplicated across methods.

**Why this matters:**
Duplicate eligibility logic is easy to change in one place but forget in another, creating inconsistent promotion behavior.

**How to fix:**
Move reusable constraints to an Eloquent scope on `Promotion`.

```php
public function scopeCurrentlyUsable($query, CarbonInterface $now)
{
    return $query->where('status', 1)
        ->where(...)
        ->where(...);
}
```

---

### Issue #18: `in_array()` Uses Loose Comparison

**Severity:** 🔵 LOW  
**Category:** Clean Code / Correctness  
**Location:** Lines 192 and 200

**Evidence:**
```php
if (in_array($promotion->discount_type, ['percentage', 'percent'])) {
```

```php
} elseif (in_array($promotion->discount_type, ['fixed_amount', 'amount'])) {
```

**Problem:**
`in_array()` is called without strict mode.

**Why this matters:**
Loose comparison is avoidable and can cause edge-case bugs when values are not exactly strings.

**How to fix:**
```php
in_array($promotion->discount_type, ['percentage', 'percent'], true)
```

---

### Issue #19: Missing Return Types

**Severity:** 🔵 LOW  
**Category:** Maintainability / Type Safety  
**Location:** Lines 15 and 47

**Evidence:**
```php
public function getUserRegisteredPromotions(User $user)
```

```php
public function registerPromotionForUser(User $user, string $code): array
```

**Problem:**
`getUserRegisteredPromotions()` does not declare a return type. `registerPromotionForUser()` returns raw array instead of a typed DTO.

**Why this matters:**
Typed contracts improve static analysis and reduce controller integration bugs.

**How to fix:**
```php
public function getUserRegisteredPromotions(User $user): Collection
```

For operation results, use DTOs.

---

### Issue #20: No Audit Logging for Promotion Registration or Redemption Validation

**Severity:** 🟡 MEDIUM  
**Category:** Logging / Auditability / Fraud Investigation  
**Location:** Lines 47-108 and 118-179

**Evidence:**
No `Log::...` calls exist in this file.

**Problem:**
Promotion registration and validation actions are not logged.

**Why this matters:**
Promotion abuse and revenue-impacting discount behavior require auditability. Without logs, it is difficult to investigate fraud, repeated failed attempts, or oversubscription incidents.

**How to fix:**
Log meaningful events without exposing unnecessary PII.

```php
Log::info('Promotion registered', [
    'user_id' => $user->id,
    'promotion_id' => $promotion->id,
]);
```

For failed attempts, consider rate-limited security logging.

---

### Issue #21: Promotion Validation Allows Anonymous Code Validation

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Abuse Prevention  
**Location:** Lines 118 and 135-145

**Evidence:**
```php
public function validatePromotion(string $code, float $orderTotal, ?User $user = null): array
```

```php
if ($user) {
    $promotionQuery->whereHas('users', function ($query) use ($user) {
        ...
    });
}
```

**Problem:**
When `$user` is null, the method validates any active promotion code without checking user registration in `user_promotion`.

**Why this matters:**
If the business rule requires vouchers to be registered in a user's voucher wallet, anonymous validation can bypass that rule depending on caller behavior.

**How to fix:**
Split public promotion validation from user-wallet voucher validation into separate explicit methods, or require `User $user` if all checkout promotions must be user-bound.

---

### Issue #22: `promotion->fresh()` Is Unnecessary and Adds Extra Query

**Severity:** 🔵 LOW  
**Category:** Performance / Clean Code  
**Location:** Line 92

**Evidence:**
```php
'promotion' => $promotion->fresh(),
```

**Problem:**
The service refreshes the promotion from the database after it was already loaded.

**Why this matters:**
This adds an unnecessary query in the already-registered path. There is no mutation before this call that requires a refresh.

**How to fix:**
```php
'promotion' => $promotion,
```

---

## Recommendations

### IMMEDIATE

1. **Make Promotion Registration Transactional**.
2. **Add Unique Constraint on `user_promotion(user_id, promotion_id)`**.
3. **Implement Atomic Promotion Redemption** inside the order/payment transaction.
4. **Lock Promotion and Pivot Rows During Redemption**.
5. **Use Integer Money Values Instead of Float**.
6. **Always Cap Discount to Order Total**.
7. **Validate Percentage Values and Unknown Discount Types**.
8. **Normalize and Length-Limit Promotion Codes**.

### SHORT TERM

9. **Move Repeated Eligibility Logic to a Promotion Scope**.
10. **Replace Raw Arrays with DTO/Result Objects**.
11. **Remove User-Facing Translated Messages from Service Layer**.
12. **Add Audit Logging for Registration, Validation Failure, and Redemption**.
13. **Capture `now()` Once Per Operation**.
14. **Use Strict `in_array()`**.
15. **Add Return Types**.

### LONG TERM

16. **Separate Registration, Validation, and Redemption Responsibilities**.
17. **Create a PromotionRedemptionService Tied to Order Transactions**.
18. **Add Fraud/Rate-Limit Strategy for Promotion Code Attempts**.
19. **Add Tests for Concurrent Registration and Redemption**.
20. **Add Database-Level Constraints for Code Uniqueness and Pivot Uniqueness**.

---

## Improved Direction

Promotion handling should separate three different operations:

```php
// 1. Register code to user wallet.
// Does not apply discount.
registerPromotionForUser(User $user, string $code): PromotionRegistrationResult

// 2. Preview discount.
// Does not reserve or consume.
previewPromotion(User $user, Promotion $promotion, int $orderTotal): PromotionPreviewResult

// 3. Redeem promotion.
// Must run inside the same DB transaction as order creation.
redeemPromotionForOrder(User $user, Order $order, Promotion $promotion): PromotionRedemptionResult
```

A redemption method should lock rows:

```php
return DB::transaction(function () use ($user, $order, $promotionId) {
    $promotion = Promotion::whereKey($promotionId)
        ->lockForUpdate()
        ->firstOrFail();

    $pivot = DB::table('user_promotion')
        ->where('user_id', $user->id)
        ->where('promotion_id', $promotion->id)
        ->whereNull('used_at')
        ->lockForUpdate()
        ->first();

    if (!$pivot) {
        throw new DomainException('Promotion already used or unavailable.');
    }

    // Revalidate dates, limits, min order, discount type.
    // Apply discount.
    // Mark pivot used.
    // Increment global usage count atomically.
});
```

---

## Summary

PromotionService.php contains the core promotion/voucher logic, but it is not production-ready for a revenue-impacting discount system. The most serious risks are concurrency-related: registration is not transactional, usage limits are not enforced atomically, pivot rows are not locked, and validation does not reserve or consume vouchers.

**Strengths:**
- Uses bound SQL values for code lookup
- Checks active status/date ranges
- Checks global and per-user usage state
- Has centralized discount calculation
- Caps fixed discount to order total

**Main Gaps:**
1. Non-transactional promotion registration
2. Race-prone global usage-limit checks
3. No atomic voucher redemption
4. No pivot locking for user voucher usage
5. Possible duplicate user-promotion rows
6. Uses float for money
7. Percentage discounts can exceed order total
8. Unknown discount types silently return zero
9. Duplicate eligibility query logic
10. No audit logging for promotion activity

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 01:37 PM*  
*File #37/137 - Phase 3: Business Logic (9/20 complete)*