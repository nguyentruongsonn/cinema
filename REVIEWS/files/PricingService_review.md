# File Review: PricingService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/PricingService.php  
**Lines:** 229  
**Type:** Service Layer - Order Pricing Snapshot

---

## File Information

**Path:** `app/Services/PricingService.php`  
**Type:** Laravel Service Class  
**Lines:** 229  
**Complexity:** Medium  

**Purpose:**  
Builds a pricing snapshot for checkout/order creation:
- Calculates dynamic ticket pricing per selected seat
- Calculates selected product totals
- Applies voucher promotion discount
- Applies loyalty point discount
- Returns subtotal, discounts, final amount, seat items, and product items

**Business Impact:** 🔴 CRITICAL - Directly affects payment amount, revenue, discounts, loyalty points, voucher usage, and booking correctness.

---

## Overall Score

**Code Quality:** 6.0/10  
**Security:** 5.4/10  
**Performance:** 6.0/10  
**Maintainability:** 5.8/10  
**Laravel Best Practice:** 5.7/10  

**Overall Score:** 5.8/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Dedicated Pricing Service Exists** - Pricing logic is separated from controllers
2. ✅ **TicketPricingService Is Injected** - Dynamic ticket pricing is delegated to another service instead of hardcoded fully here
3. ✅ **Promotion Ownership Check Exists** - Voucher lookup requires the voucher to belong to the user through `whereHas('users')`
4. ✅ **Promotion Validity Scopes Are Used** - `active()`, `valid()`, and `byCode()` are applied
5. ✅ **Max Discount Cap Is Applied** - Percentage discounts respect `max_discount_amount`
6. ✅ **Final Amount Is Clamped at Zero** - Negative final amounts are prevented

---

## Issues Found

### Issue #1: Pricing Snapshot Does Not Verify Requested Seats Belong to the Showtime Screen

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Revenue Integrity / Authorization  
**Location:** Lines 29-30

**Evidence:**
```php
$seatIds = array_map(fn($item) => (int) ($item['id'] ?? $item), $seatRequests);
$seats = Seat::with('seatType')->whereIn('id', $seatIds)->get();
```

**Problem:**
The service loads seats by ID only. It does not verify that the seats belong to `$showtime->screen_id`.

**Why this matters:**
A client can submit seat IDs from another screen. This can produce an incorrect price snapshot and can be used to manipulate checkout data if downstream booking logic trusts this snapshot. In a cinema booking system, seat selection must be scoped to the showtime's actual screen.

**How to fix:**
Scope seats to the showtime screen.

**Example:**
```php
$seats = Seat::query()
    ->with('seatType')
    ->where('screen_id', $showtime->screen_id)
    ->whereIn('id', $seatIds)
    ->get();
```

Also reject the request if the count does not match the requested seat count.

---

### Issue #2: Missing Seat Count Validation Allows Silent Dropping of Invalid Seat IDs

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Payment Correctness  
**Location:** Lines 29-40

**Evidence:**
```php
$seatIds = array_map(fn($item) => (int) ($item['id'] ?? $item), $seatRequests);
$seats = Seat::with('seatType')->whereIn('id', $seatIds)->get();

foreach ($seats as $seat) {
```

**Problem:**
If the request contains invalid, duplicate, inactive, or unrelated seat IDs, the query silently returns fewer rows. The service then calculates price only for returned seats.

**Why this matters:**
The customer may submit 5 seats, but if only 3 are found, the snapshot charges for 3 without clearly rejecting the invalid request. This creates mismatch risk between UI, order, holds, and payment.

**How to fix:**
Normalize unique requested seat IDs and fail if the loaded seat count differs.

```php
$seatIds = collect($seatRequests)
    ->map(fn ($item) => (int) ($item['id'] ?? $item))
    ->filter()
    ->unique()
    ->values();

if ($seatIds->count() !== count($seatRequests)) {
    throw new InvalidArgumentException('Invalid or duplicate seats requested.');
}

if ($seats->count() !== $seatIds->count()) {
    throw new InvalidArgumentException('One or more seats are invalid for this showtime.');
}
```

---

### Issue #3: No Seat Availability / Hold / Booking Validation

**Severity:** 🔴 CRITICAL  
**Category:** Concurrency / Booking Correctness  
**Location:** Lines 29-40

**Evidence:**
```php
$seats = Seat::with('seatType')->whereIn('id', $seatIds)->get();
```

**Problem:**
The pricing snapshot calculates prices for seats without checking whether those seats are available for the requested showtime.

**Why this matters:**
A customer can receive a valid-looking price for already booked or held seats. If order creation later uses this snapshot asynchronously or inconsistently, this can contribute to duplicate booking, failed payments after payment attempt, or poor user experience.

**How to fix:**
Pricing should be tied to validated seat availability or a seat hold. At minimum, require a valid hold/session ID and price only seats from that hold.

---

### Issue #4: Pricing Calculation Is Not Transactional and Does Not Lock Voucher/User State

**Severity:** 🔴 CRITICAL  
**Category:** Concurrency / Discount Integrity  
**Location:** Lines 116-124 and 152-165

**Evidence:**
```php
[$voucherDiscount, $voucherPayload] = $this->applyPromotion($voucherCode, $subtotal, $user);
```

```php
if ($pointsUsed > 0 && $user && $user->loyalty_points >= $pointsUsed) {
    $pointDiscount = $pointsUsed * 1000;
}
```

```php
$promotion = Promotion::query()
    ->active()
    ->valid()
    ->byCode($promotionCode)
    ->whereHas('users', function ($query) use ($user) {
```

**Problem:**
The service reads user loyalty points and voucher pivot state without locking rows and without a transaction.

**Why this matters:**
A user can start multiple concurrent checkouts using the same loyalty points or voucher. Without row locks and final atomic consumption, the system can issue duplicate discounts and lose money.

**How to fix:**
The final pricing verification and discount consumption must run inside the same transaction as order creation/payment intent creation. Lock the user row and voucher pivot row before applying discounts.

---

### Issue #5: Loyalty Points Are Not Capped to Subtotal or Final Payable Amount Before Voucher Interaction

**Severity:** 🟠 HIGH  
**Category:** Revenue Logic / Business Rules  
**Location:** Lines 118-127

**Evidence:**
```php
$pointDiscount = 0;
if ($pointsUsed > 0 && $user && $user->loyalty_points >= $pointsUsed) {
    $pointDiscount = $pointsUsed * 1000;
}

$totalDiscount = $voucherDiscount + $pointDiscount;
$finalAmount = max(0, $subtotal - $totalDiscount);
```

**Problem:**
The service allows loyalty point discount to exceed the remaining amount after voucher discount. The final amount is clamped to zero, but the snapshot still reports the full point discount and `points_used`.

**Why this matters:**
This can over-consume loyalty points for no additional benefit or create inconsistent accounting where discount amount exceeds subtotal. It also complicates refunds and loyalty reconciliation.

**How to fix:**
Cap points to the payable amount after voucher discount.

```php
$remainingAfterVoucher = max(0, $subtotal - $voucherDiscount);
$maxPointsUsable = intdiv((int) $remainingAfterVoucher, 1000);
$pointsUsed = min($pointsUsed, $user->loyalty_points, $maxPointsUsable);
$pointDiscount = $pointsUsed * 1000;
```

---

### Issue #6: Product Requests Silently Drop Invalid or Inactive Products

**Severity:** 🟠 HIGH  
**Category:** Business Logic / API Correctness  
**Location:** Lines 84-94

**Evidence:**
```php
$requestedProducts = collect($productRequests)
    ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
    ->filter(fn (int $quantity) => $quantity > 0);

if ($requestedProducts->isNotEmpty()) {
    $products = Product::whereIn('id', $requestedProducts->keys()->all())
        ->where('status', 1)
        ->get();
```

**Problem:**
If a requested product does not exist or is inactive, it is silently omitted from the snapshot.

**Why this matters:**
The client may think products are included while the backend charges for fewer items. This creates checkout inconsistency and support issues. It can also hide client-side manipulation instead of rejecting it.

**How to fix:**
Reject the request when not all requested products are found and active.

```php
if ($products->count() !== $requestedProducts->count()) {
    throw new InvalidArgumentException('One or more products are unavailable.');
}
```

---

### Issue #7: Duplicate Product IDs Are Collapsed with Last Quantity Wins

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / API Correctness  
**Location:** Lines 85-87

**Evidence:**
```php
$requestedProducts = collect($productRequests)
    ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
    ->filter(fn (int $quantity) => $quantity > 0);
```

**Problem:**
`mapWithKeys()` overwrites duplicate product IDs. If the same product appears multiple times, only the last quantity is retained.

**Why this matters:**
This can create inconsistent totals compared with the client cart.

**How to fix:**
Either reject duplicate product IDs or group and sum quantities explicitly.

```php
$requestedProducts = collect($productRequests)
    ->groupBy(fn ($product) => (int) $product['id'])
    ->map(fn ($items) => $items->sum(fn ($product) => (int) $product['quantity']));
```

---

### Issue #8: Product Quantities Are Not Capped

**Severity:** 🟠 HIGH  
**Category:** Abuse Control / Business Logic  
**Location:** Lines 85-98

**Evidence:**
```php
->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
->filter(fn (int $quantity) => $quantity > 0);
```

```php
$lineTotal = $unitPrice * $quantity;
```

**Problem:**
There is no maximum quantity per product or per order.

**Why this matters:**
A malicious client can submit extremely large quantities, causing unrealistic totals, integer/float precision problems, payment gateway limits, and inventory/reservation issues.

**How to fix:**
Validate product quantities in a FormRequest and enforce server-side limits.

```php
if ($quantity > 50) {
    throw new InvalidArgumentException('Product quantity exceeds allowed limit.');
}
```

---

### Issue #9: Money Uses Floats

**Severity:** 🟠 HIGH  
**Category:** Financial Correctness  
**Location:** Lines 82, 96-98, 112, 144, 191-205

**Evidence:**
```php
$productTotal = 0;
$unitPrice = (float) $product->price;
$lineTotal = $unitPrice * $quantity;
```

```php
private function applyPromotion(?string $promotionCode, float $subtotal, User $user): array
```

```php
private function calculatePromotionDiscount(Promotion $promotion, float $subtotal): float
```

**Problem:**
The service uses floats for money calculations.

**Why this matters:**
Floats are not exact and can create rounding errors in totals, discounts, and payment amounts. Cinema checkout/payment should calculate monetary values in integer minor units, especially for gateway integration.

**How to fix:**
Use integer VND amounts consistently.

```php
$unitPrice = (int) $product->price;
$lineTotal = $unitPrice * $quantity;
```

Return integer totals from discount calculations.

---

### Issue #10: Voucher Error Uses Generic `RuntimeException`

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Exception Handling  
**Location:** Lines 167-174

**Evidence:**
```php
if (!$promotion) {
    throw new \RuntimeException('Mã khuyến mãi chưa được đăng ký trong Kho Voucher, không hợp lệ hoặc đã hết hạn.');
}
```

```php
if ($subtotal < $minOrderValue) {
    throw new \RuntimeException('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã khuyến mãi.');
}
```

**Problem:**
Domain validation failures are thrown as generic runtime exceptions.

**Why this matters:**
API consumers need consistent validation error responses. Generic exceptions can become 500 responses depending on controller handling and global exception mapping.

**How to fix:**
Throw `ValidationException` or a typed domain exception mapped to HTTP 422.

---

### Issue #11: Promotion Code Is Not Normalized Consistently

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Validation  
**Location:** Lines 144-155

**Evidence:**
```php
$promotionCode = trim((string) $promotionCode);
...
->byCode($promotionCode)
```

**Problem:**
The code is trimmed but not normalized for case, Unicode whitespace, or maximum length.

**Why this matters:**
Voucher codes are often expected to be case-insensitive and bounded. Long or malformed codes can cause inconsistent matching and unnecessary query work.

**How to fix:**
Normalize and validate promotion code before query.

```php
$promotionCode = mb_strtoupper(trim($promotionCode));
if (mb_strlen($promotionCode) > 64) {
    throw ValidationException::withMessages(['voucher_code' => 'Invalid voucher code.']);
}
```

---

### Issue #12: Seat Pricing Ignores Requested Seat Quantity Semantics

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Pricing Correctness  
**Location:** Lines 29-67

**Evidence:**
```php
$seatIds = array_map(fn($item) => (int) ($item['id'] ?? $item), $seatRequests);
...
'quantity' => 1,
```

**Problem:**
Each seat item is always priced as quantity `1`. The service ignores any requested seat quantity or passenger/customer type metadata.

**Why this matters:**
Double/couple seats are detected, but the output quantity remains `1`. Depending on business rules, double seats may represent two persons or a special seat type. The service also hardcodes customer type to adult.

**How to fix:**
Define explicit seat pricing semantics. If all seats are individual inventory units, keep quantity 1 but ensure double-seat pricing rules are documented and tested. If customer type is supported, pass validated customer type from request.

---

### Issue #13: Customer Type Is Hardcoded to `adult`

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Extensibility  
**Location:** Lines 46-52

**Evidence:**
```php
// Default customer type is 'adult' (can be extended later to accept user preferences)
$pricingResult = $this->ticketPricingService->calculate(
    format: $formatName,
    scheduledAt: $scheduledAt,
    customerType: 'adult',
```

**Problem:**
Customer type is hardcoded to `adult`.

**Why this matters:**
If the business supports child, student, senior, or member pricing, the current implementation cannot price those correctly. The comment acknowledges the limitation but leaves it in production logic.

**How to fix:**
Accept validated ticket/customer type per seat request and pass it to `TicketPricingService`.

---

### Issue #14: No Validation That Showtime Has Required Relationships for Pricing

**Severity:** 🟡 MEDIUM  
**Category:** Data Correctness / Exception Safety  
**Location:** Lines 25-58

**Evidence:**
```php
$showtime->load(['format', 'movie', 'screen.theater']);
$formatName = $showtime->format?->name ?? '2D';
$movieSurcharge = (int) ($showtime->movie?->surcharge ?? 0);
...
theaterPricing: $showtime->screen?->theater?->pricing_profile
```

**Problem:**
Missing relationships are silently defaulted instead of treated as invalid pricing configuration.

**Why this matters:**
If a showtime is missing format, movie, screen, or theater data, pricing should fail fast. Defaulting to `2D` and zero surcharge can undercharge customers and hide data corruption.

**How to fix:**
Validate required relationships before pricing.

```php
if (!$showtime->format || !$showtime->movie || !$showtime->screen || !$showtime->screen->theater) {
    throw new DomainException('Showtime pricing configuration is incomplete.');
}
```

---

### Issue #15: Product Pricing Does Not Check Inventory or Availability Window

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Inventory Correctness  
**Location:** Lines 90-98

**Evidence:**
```php
$products = Product::whereIn('id', $requestedProducts->keys()->all())
    ->where('status', 1)
    ->get();
```

**Problem:**
Product pricing only checks `status = 1`. It does not check stock, branch/theater availability, sale window, or product availability for the selected showtime/theater.

**Why this matters:**
Customers may buy concessions unavailable at the theater or out of stock.

**How to fix:**
Scope products to theater/branch availability and inventory if the domain supports it.

---

### Issue #16: Voucher Discount Is Calculated Against Product + Seat Subtotal Without Category Eligibility Check

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Promotion Correctness  
**Location:** Lines 112-117

**Evidence:**
```php
$subtotal = $seatTotal + $productTotal;
[$voucherDiscount, $voucherPayload] = $this->applyPromotion($voucherCode, $subtotal, $user);
```

**Problem:**
Promotions are applied to the full subtotal without checking whether the voucher is eligible for tickets, products, specific movies, theaters, formats, or order types.

**Why this matters:**
This can over-discount if some promotions are intended for ticket-only, combo-only, or specific campaign rules.

**How to fix:**
Pass pricing context into promotion calculation and enforce promotion eligibility rules.

---

### Issue #17: No Audit Logging for Discount Application

**Severity:** 🟡 MEDIUM  
**Category:** Logging / Fraud Monitoring  
**Location:** Lines 116-127 and 177-187

**Evidence:**
```php
[$voucherDiscount, $voucherPayload] = $this->applyPromotion($voucherCode, $subtotal, $user);
```

```php
$pointDiscount = $pointsUsed * 1000;
```

**Problem:**
The service applies voucher and point discounts without emitting any audit/security log.

**Why this matters:**
Discount abuse and duplicate voucher/point usage are high-risk revenue events. Production systems need traceability for applied promotions and loyalty redemptions.

**How to fix:**
Emit structured domain events or audit logs when an order actually reserves/consumes discounts.

---

### Issue #18: `isDoubleSeat()` Uses Magic Keyword Matching

**Severity:** 🔵 LOW  
**Category:** Maintainability / Business Logic  
**Location:** Lines 214-227

**Evidence:**
```php
$doubleKeywords = ['double', 'couple', 'đôi', 'sweetbox', 'sweet-box'];
```

**Problem:**
Double-seat detection depends on hardcoded keywords in seat type name/slug.

**Why this matters:**
Renaming a seat type can change pricing behavior. Pricing rules should not depend on free-text labels.

**How to fix:**
Add an explicit boolean/configuration field to seat type, such as `is_double_seat`, or use a stable enum.

---

### Issue #19: Method `buildSnapshot()` Has Too Many Responsibilities

**Severity:** 🟡 MEDIUM  
**Category:** Clean Code / Maintainability  
**Location:** Lines 17-142

**Evidence:**
```php
public function buildSnapshot(
    User $user,
    Showtime $showtime,
    array $seatRequests,
    array $productRequests,
    ?string $voucherCode,
    int $pointsUsed
): array {
```

**Problem:**
`buildSnapshot()` handles seat loading, ticket pricing, product pricing, promotion validation, point discounting, and response construction.

**Why this matters:**
This method is difficult to test in isolation and risky to change. Money logic needs small, deterministic units with explicit inputs and outputs.

**How to fix:**
Split into smaller methods:
- `priceSeats()`
- `priceProducts()`
- `applyPromotion()`
- `applyPoints()`
- `buildSnapshotResponse()`

---

### Issue #20: Return Value Is an Untyped Array for Critical Money Data

**Severity:** 🟡 MEDIUM  
**Category:** Type Safety / Maintainability  
**Location:** Lines 129-141

**Evidence:**
```php
return [
    'subtotal' => $subtotal,
    'seat_total' => $seatTotal,
    'product_total' => $productTotal,
```

**Problem:**
The pricing snapshot is returned as a loosely typed array.

**Why this matters:**
Critical money data should have a stable contract. Arrays make it easy to miss fields, change types, or introduce inconsistent keys.

**How to fix:**
Use a DTO/value object for pricing snapshots and line items.

---

## Recommendations

### IMMEDIATE

1. **Scope Seats to Showtime Screen** - Never price seats by ID alone
2. **Reject Missing/Duplicate/Invalid Seats** - Do not silently drop seats
3. **Tie Pricing to Seat Holds / Availability Checks** - Prevent checkout from pricing unavailable seats
4. **Move Final Discount Verification Into Transaction** - Lock user/voucher state when consuming points or vouchers
5. **Use Integer Money Values** - Remove floats from financial calculations
6. **Cap Loyalty Points to Remaining Payable Amount**
7. **Reject Invalid or Inactive Products Instead of Dropping Them**

### SHORT TERM

8. **Replace RuntimeException With Validation/Domain Exceptions**
9. **Normalize Voucher Codes**
10. **Cap Product Quantities**
11. **Validate Required Showtime Pricing Relationships**
12. **Add Audit Logging for Applied Discounts**
13. **Remove Magic Keyword-Based Double Seat Detection**

### LONG TERM

14. **Introduce PricingSnapshot DTO**
15. **Split `buildSnapshot()` Into Smaller Testable Methods**
16. **Add Promotion Eligibility Rules by Product/Ticket/Theater/Movie**
17. **Support Validated Customer Types Per Seat**
18. **Add High-Coverage Tests for Pricing, Discounts, Concurrency, and Edge Cases**

---

## Improved Version Snippet

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

public function buildSnapshot(
    User $user,
    Showtime $showtime,
    array $seatRequests,
    array $productRequests,
    ?string $voucherCode,
    int $pointsUsed
): array {
    $showtime->loadMissing(['format', 'movie', 'screen.theater']);

    if (!$showtime->format || !$showtime->movie || !$showtime->screen || !$showtime->screen->theater) {
        throw ValidationException::withMessages([
            'showtime' => 'Showtime pricing configuration is incomplete.',
        ]);
    }

    $seatIds = collect($seatRequests)
        ->map(fn ($item) => (int) ($item['id'] ?? $item))
        ->filter()
        ->unique()
        ->values();

    if ($seatIds->count() !== count($seatRequests)) {
        throw ValidationException::withMessages([
            'seats' => 'Invalid or duplicate seats requested.',
        ]);
    }

    $seats = Seat::query()
        ->with('seatType')
        ->where('screen_id', $showtime->screen_id)
        ->whereIn('id', $seatIds->all())
        ->get();

    if ($seats->count() !== $seatIds->count()) {
        throw ValidationException::withMessages([
            'seats' => 'One or more seats are invalid for this showtime.',
        ]);
    }

    // Final voucher/point consumption should happen in the order creation transaction.
}
```

---

## Summary

PricingService.php centralizes important checkout pricing logic, but it is not production-ready for a money-sensitive cinema booking flow. The biggest risks are incorrect seat scoping, silent dropping of invalid seats/products, non-transactional voucher/point checks, and float-based money calculations.

**Strengths:**
- Dedicated service exists
- Dynamic ticket pricing is delegated
- Voucher ownership check exists
- Valid promotion scopes are used
- Discount caps are applied for percentage promotions

**Main Gaps:**
1. Seats are priced by ID without showtime screen scoping
2. Invalid seats/products are silently dropped
3. No seat availability/hold validation
4. Voucher and loyalty point checks are not transactional
5. Loyalty points can exceed remaining payable amount
6. Money uses floats
7. Generic exceptions may produce inconsistent API responses
8. Magic keyword logic determines double seats
9. Critical pricing snapshot uses an untyped array

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 12:42 PM*  
*File #33/137 - Phase 3: Business Logic (5/20 complete)*
