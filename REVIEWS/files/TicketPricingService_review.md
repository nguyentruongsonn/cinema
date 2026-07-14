# File Review: TicketPricingService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/TicketPricingService.php  
**Lines:** 234  
**Type:** Service Layer - Ticket Pricing Rule Engine

---

## File Information

**Path:** `app/Services/TicketPricingService.php`  
**Type:** Laravel Service Class  
**Lines:** 234  
**Complexity:** Medium  

**Purpose:**  
Calculates ticket prices based on:
- Show format
- Showtime date/time
- Customer type
- Double-seat flag
- Movie surcharge
- Format surcharge
- Seat type surcharge
- Optional theater pricing profile

**Business Impact:** 🔴 CRITICAL - This service directly determines ticket revenue and must be deterministic, validated, auditable, and consistent with published pricing rules.

---

## Overall Score

**Code Quality:** 6.2/10  
**Security:** 6.0/10  
**Performance:** 8.0/10  
**Maintainability:** 5.8/10  
**Laravel Best Practice:** 6.4/10  

**Overall Score:** 6.3/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Dedicated Pricing Service** - Ticket pricing logic is isolated from controllers.
2. ✅ **Pure Calculation Logic** - No database calls, making the service relatively easy to unit test.
3. ✅ **Integer Money Values Mostly Used** - Base prices and surcharges are treated as integers.
4. ✅ **Structured Return Payload** - Response includes base price, surcharge breakdown, total, day type, time slot, customer type, price group, and format.
5. ✅ **Supports Theater-Level Pricing Profile** - Pricing can be overridden through `$theaterPricing`.
6. ✅ **Reduced Customer Types Are Supported** - `student`, `child`, and `senior` receive discount logic.
7. ✅ **Holiday and Weekend Logic Is Centralized** - Day classification is delegated to `resolveDayType()`.

---

## Issues Found

### Issue #1: Invalid Format Is Silently Repriced as `2D`

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Revenue Integrity  
**Location:** Lines 59-60

**Evidence:**
```php
// Normalise format key (2D, 3D; others fall back to 2D)
$formatKey = in_array($format, ['2D', '3D'], true) ? $format : '2D';
```

**Problem:**
Any format other than `2D` or `3D` is silently changed to `2D`.

**Why this matters:**
The docblock explicitly mentions formats like `4DX` and `IMAX`, but the implementation treats them as `2D`. This can undercharge premium formats in production. Silent fallback is dangerous in pricing code because invalid data becomes a lower price instead of a hard failure.

**How to fix:**
Reject unsupported formats or load supported formats from a controlled enum/config/table.

**Example:**
```php
$supportedFormats = ['2D', '3D', '4DX', 'IMAX'];

if (!in_array($format, $supportedFormats, true)) {
    throw new InvalidArgumentException('Unsupported ticket format.');
}
```

---

### Issue #2: `$formatKey` Does Not Affect Price

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Pricing Correctness  
**Location:** Lines 59-63 and 154-163

**Evidence:**
```php
$formatKey = in_array($format, ['2D', '3D'], true) ? $format : '2D';
```

```php
return [
    ...
    'format'        => $formatKey,
];
```

**Problem:**
The normalized format is only returned in the payload. It does not change base price or surcharge unless `$formatSurcharge` is separately passed by caller.

**Why this matters:**
Format pricing is partly externalized to caller state, creating hidden coupling between `PricingService` and `TicketPricingService`. If the caller passes format name but forgets or misloads `formatSurcharge`, the result is underpriced while still returning a valid-looking format.

**How to fix:**
Either:
- Make this service fully responsible for format pricing; or
- Rename the argument/contract to make clear that `$format` is informational and surcharge must be precomputed.

A pricing service should not accept a business-critical input that has no direct effect on price.

---

### Issue #3: Theater Pricing Profile Is Unvalidated and Can Produce Invalid Prices

**Severity:** 🟠 HIGH  
**Category:** Data Validation / Revenue Integrity  
**Location:** Lines 62-70 and 90-110

**Evidence:**
```php
$defaultPricing = [
    'base_price' => 70000,
    'weekend_surcharge' => 10000,
    'holiday_surcharge' => 20000,
    'happy_day_price' => 50000,
    'student_discount' => 10000,
    'beta_ten_discount' => -10000,
];
$tp = $theaterPricing ?? $defaultPricing;
```

```php
$basePrice = (int) ($tp['happy_day_price'] ?? 50000);
```

```php
$basePrice = (int) ($tp['base_price'] ?? 70000);
```

**Problem:**
`$theaterPricing` is accepted as an arbitrary array and values are cast to integers without validation. Negative prices, non-numeric values, extremely large values, missing keys, or malformed profiles are not rejected.

**Why this matters:**
Pricing profiles are business-critical configuration. Invalid profile data can undercharge, overcharge, or create impossible ticket prices.

**How to fix:**
Validate pricing profile before using it.

**Example:**
```php
private function normalizePricingProfile(?array $theaterPricing): array
{
    $profile = array_merge($this->defaultPricing(), $theaterPricing ?? []);

    foreach (['base_price', 'weekend_surcharge', 'holiday_surcharge', 'happy_day_price', 'student_discount'] as $key) {
        if (!is_numeric($profile[$key]) || (int) $profile[$key] < 0) {
            throw new InvalidArgumentException("Invalid pricing profile value: {$key}");
        }

        $profile[$key] = (int) $profile[$key];
    }

    return $profile;
}
```

---

### Issue #4: Total Price Can Become Negative

**Severity:** 🟠 HIGH  
**Category:** Financial Correctness  
**Location:** Lines 108-152

**Evidence:**
```php
if ($isReduced && $dayType !== 'happy_day') {
    $studentDiscount = (int) ($tp['student_discount'] ?? 10000);
    if ($studentDiscount > 0) {
        $surcharges['student_discount'] = [
            'label'  => 'Ưu đãi đối tượng',
            'amount' => -$studentDiscount,
        ];
    }
}
```

```php
$totalSurcharge = array_sum(array_column($surcharges, 'amount'));
$totalPrice     = $basePrice + $totalSurcharge;
```

**Problem:**
The final price is not clamped or validated. If `student_discount` exceeds `base_price + surcharges`, or if invalid profile values are negative, `$totalPrice` can become zero or negative.

**Why this matters:**
A negative ticket price can corrupt order totals, discounts, payment amounts, and financial reporting.

**How to fix:**
Validate each pricing component and enforce minimum ticket price rules.

```php
$totalPrice = $basePrice + $totalSurcharge;

if ($totalPrice <= 0) {
    throw new DomainException('Calculated ticket price is invalid.');
}
```

---

### Issue #5: Reduced Customer Types Are Collapsed Into One Discount Rule

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Pricing Correctness  
**Location:** Lines 77-80 and 108-117

**Evidence:**
```php
$isReduced = in_array($customerType, ['student', 'child', 'senior'], true);
$priceGroup = $isReduced ? 'student_child_senior' : 'adult';
```

```php
$studentDiscount = (int) ($tp['student_discount'] ?? 10000);
```

**Problem:**
`student`, `child`, and `senior` all share the same `student_discount`.

**Why this matters:**
These customer groups often have different pricing rules or eligibility requirements. Using one field for all three groups can undercharge or overcharge.

**How to fix:**
Use explicit pricing fields per customer type.

```php
$discountKey = match ($customerType) {
    'student' => 'student_discount',
    'child' => 'child_discount',
    'senior' => 'senior_discount',
    default => null,
};
```

---

### Issue #6: Invalid Customer Type Is Treated as Adult

**Severity:** 🟡 MEDIUM  
**Category:** Validation / API Correctness  
**Location:** Lines 77-80

**Evidence:**
```php
$isReduced = in_array($customerType, ['student', 'child', 'senior'], true);
$priceGroup = $isReduced ? 'student_child_senior' : 'adult';
```

**Problem:**
Any unsupported customer type becomes non-reduced adult pricing.

**Why this matters:**
Invalid API input should be rejected, not silently normalized. Silent fallback makes client bugs hard to detect and can create inconsistent audit/payment records.

**How to fix:**
Validate customer type explicitly.

```php
$allowedCustomerTypes = ['adult', 'student', 'child', 'senior'];

if (!in_array($customerType, $allowedCustomerTypes, true)) {
    throw new InvalidArgumentException('Unsupported customer type.');
}
```

---

### Issue #7: Time Slot Calculation Ignores Minutes and Labels Pre-10:00 as Evening

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Pricing Rule Correctness  
**Location:** Lines 72-82 and 194-197

**Evidence:**
```php
$hour     = (int) $scheduledAt->format('H');
$minute   = (int) $scheduledAt->format('i');
```

```php
private function resolveTimeSlot(int $hour): string
{
    return ($hour >= 10 && $hour < 18) ? '10:00-18:00' : '18:00-22:00';
}
```

**Problem:**
`$minute` is calculated but unused. Any time before 10:00 is labeled as `18:00-22:00`, and any time after 22:00 is also labeled as `18:00-22:00`.

**Why this matters:**
This is incorrect for morning/late-night showtimes and misleading in API responses. If time slots later affect pricing, this becomes a direct pricing bug.

**How to fix:**
Use full time boundaries and return explicit buckets.

```php
private function resolveTimeSlot(Carbon $scheduledAt): string
{
    $minutes = ((int) $scheduledAt->format('H')) * 60 + (int) $scheduledAt->format('i');

    return match (true) {
        $minutes < 600 => 'before_10:00',
        $minutes < 1080 => '10:00-18:00',
        $minutes < 1320 => '18:00-22:00',
        default => 'after_22:00',
    };
}
```

---

### Issue #8: `$minute` Variable Is Dead Code

**Severity:** 🔵 LOW  
**Category:** Clean Code  
**Location:** Line 73

**Evidence:**
```php
$minute   = (int) $scheduledAt->format('i');
```

**Problem:**
The `$minute` variable is assigned but never used.

**Why this matters:**
Dead code creates confusion in pricing code and suggests incomplete time-based pricing logic.

**How to fix:**
Use it in time slot resolution or remove it.

---

### Issue #9: `mad_sale_day` Is Classified But Has No Pricing Effect

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Dead Rule  
**Location:** Lines 222-225 and 90-106

**Evidence:**
```php
if ($dow === Carbon::MONDAY && $dt->day <= 7) {
    return 'mad_sale_day';
}
```

```php
if ($dayType === 'happy_day') {
    $basePrice = (int) ($tp['happy_day_price'] ?? 50000);
} else {
    $basePrice = (int) ($tp['base_price'] ?? 70000);

    if ($dayType === 'holiday') {
        ...
    } elseif ($dayType === 'weekend') {
        ...
    }
}
```

**Problem:**
`mad_sale_day` is returned from `resolveDayType()`, but `calculate()` does not apply any discount or surcharge for it.

**Why this matters:**
The API returns a special day type that has no pricing behavior. This creates misleading pricing output and indicates an incomplete business rule.

**How to fix:**
Either remove `mad_sale_day` classification or apply an explicit pricing rule.

```php
if ($dayType === 'mad_sale_day') {
    $surcharges['mad_sale'] = [
        'label' => 'Mad Sale Day',
        'amount' => -(int) ($tp['mad_sale_discount'] ?? 0),
    ];
}
```

---

### Issue #10: `beta_ten_discount` Exists in Default Profile But Is Never Used

**Severity:** 🟡 MEDIUM  
**Category:** Dead Code / Business Logic  
**Location:** Lines 62-69

**Evidence:**
```php
$defaultPricing = [
    'base_price' => 70000,
    'weekend_surcharge' => 10000,
    'holiday_surcharge' => 20000,
    'happy_day_price' => 50000,
    'student_discount' => 10000,
    'beta_ten_discount' => -10000,
];
```

**Problem:**
`beta_ten_discount` is configured but never applied.

**Why this matters:**
The class comment says priority is `beta_ten > holiday > happy_day > weekday/weekend > standard`, but there is no beta-ten condition in the implementation. This is a direct mismatch between documented pricing rules and executed pricing rules.

**How to fix:**
Implement beta-ten logic or remove it from comments/profile until supported.

---

### Issue #11: Class-Level Pricing Priority Comment Does Not Match Implementation

**Severity:** 🟡 MEDIUM  
**Category:** Documentation Correctness / Maintainability  
**Location:** Lines 10-13 and 199-203

**Evidence:**
```php
* Thứ tự ưu tiên rule:
*   beta_ten > holiday > happy_day > weekday/weekend > standard
```

```php
* holiday > happy_day > weekday/weekend > standard
* (beta_ten được xử lý riêng, mad_sale_day chỉ thêm surcharge)
```

**Problem:**
The comments reference beta-ten priority and mad-sale surcharge handling, but the implementation does not implement beta-ten and does not apply mad-sale surcharge.

**Why this matters:**
Pricing code must be auditable. Misleading comments cause reviewers, testers, support staff, and finance teams to misunderstand how prices are calculated.

**How to fix:**
Update comments to match actual behavior or implement the documented behavior.

---

### Issue #12: Holiday Rules Are Hardcoded and Incomplete

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Maintainability  
**Location:** Lines 18-25 and 206-210

**Evidence:**
```php
private const HOLIDAYS = [
    '01-01', // Tết Dương lịch
    '04-30', // Giải phóng miền Nam
    '05-01', // Quốc tế Lao động
    '09-02', // Quốc khánh
    // Tết Nguyên Đán phải tính riêng (âm lịch) — thêm theo năm
];
```

```php
$holidays = array_merge(self::HOLIDAYS, $extraHolidays);
if (in_array($mmdd, $holidays, true)) {
    return 'holiday';
}
```

**Problem:**
Holiday rules are hardcoded in the service and do not account for lunar holidays unless callers provide `$extraHolidays`.

**Why this matters:**
Holiday pricing changes yearly and may be theater-specific. Forgetting to pass extra holidays causes undercharging during major holidays.

**How to fix:**
Move holiday configuration to database/config and manage it centrally.

---

### Issue #13: Holiday Date Matching Ignores Year and Timezone Policy

**Severity:** 🟡 MEDIUM  
**Category:** Date/Time Correctness  
**Location:** Lines 75 and 206-210

**Evidence:**
```php
$mmdd      = $scheduledAt->format('m-d');
```

```php
if (in_array($mmdd, $holidays, true)) {
    return 'holiday';
}
```

**Problem:**
Holiday matching only uses `MM-DD`, and there is no explicit timezone normalization.

**Why this matters:**
Cinema showtimes are location/timezone-sensitive. If `$scheduledAt` has a different timezone than the theater/business timezone, holiday/weekend classification can be wrong.

**How to fix:**
Normalize to theater timezone before classification and support full dates for year-specific holidays.

---

### Issue #14: Double Seat Surcharge Is Hardcoded

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Maintainability  
**Location:** Lines 119-125

**Evidence:**
```php
if ($isDoubleSeat) {
    $surcharges['double_seat'] = [
        'label'  => 'Phụ thu ghế đôi',
        'amount' => 5000,
    ];
}
```

**Problem:**
Double-seat surcharge is hardcoded to `5000`.

**Why this matters:**
Seat pricing rules should be configurable and auditable. Hardcoded money values require code deployment for business changes and may conflict with `seatSurcharge` passed from database.

**How to fix:**
Move this value into pricing profile or seat type configuration.

```php
'amount' => (int) ($tp['double_seat_surcharge'] ?? 5000),
```

---

### Issue #15: Potential Double-Charging for Double Seat and Seat Type Surcharge

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Pricing Correctness  
**Location:** Lines 119-149

**Evidence:**
```php
if ($isDoubleSeat) {
    $surcharges['double_seat'] = [
        'label'  => 'Phụ thu ghế đôi',
        'amount' => 5000,
    ];
}
```

```php
if ($seatSurcharge > 0) {
    $surcharges['seat_type'] = [
        'label'  => 'Phụ thu loại ghế',
        'amount' => $seatSurcharge,
    ];
}
```

**Problem:**
If the seat type surcharge already includes couple/double seat pricing, this service adds another hardcoded double-seat surcharge.

**Why this matters:**
Customers can be overcharged depending on how seat type data is configured.

**How to fix:**
Define a single source of truth:
- Either double seat surcharge comes from seat type surcharge; or
- It is separate and seat type surcharge must exclude it.

This rule should be explicit in data model and tests.

---

### Issue #16: Surcharge Inputs Are Not Validated

**Severity:** 🟡 MEDIUM  
**Category:** Data Validation / Financial Correctness  
**Location:** Lines 127-149

**Evidence:**
```php
if ($movieSurcharge > 0) {
    $surcharges['movie'] = [
        'label'  => 'Phụ thu phim',
        'amount' => $movieSurcharge,
    ];
}
```

```php
if ($formatSurcharge > 0) {
```

```php
if ($seatSurcharge > 0) {
```

**Problem:**
Surcharge inputs are only checked as `> 0`. There are no maximum bounds.

**Why this matters:**
Corrupt database values or bad caller inputs can produce unrealistic prices and payment gateway failures.

**How to fix:**
Validate all surcharge inputs against allowed ranges.

```php
foreach ([$movieSurcharge, $formatSurcharge, $seatSurcharge] as $surcharge) {
    if ($surcharge < 0 || $surcharge > 1_000_000) {
        throw new InvalidArgumentException('Invalid surcharge amount.');
    }
}
```

---

### Issue #17: `calculateAll()` Repeats Full Calculation Four Times

**Severity:** 🔵 LOW  
**Category:** Performance / Maintainability  
**Location:** Lines 169-186

**Evidence:**
```php
foreach ($types as $type) {
    $result[$type] = $this->calculate(
        $format, $scheduledAt, $type, $isDoubleSeat, $movieSurcharge, $extraHolidays, $formatSurcharge, $seatSurcharge, $theaterPricing
    );
}
```

**Problem:**
The service recomputes day type, time slot, and common surcharges four times.

**Why this matters:**
Performance impact is small for one call, but this is avoidable and makes rule consistency harder to maintain.

**How to fix:**
Extract shared calculation context and apply only customer-type differences per iteration.

---

### Issue #18: Return Value Is a Loose Array Instead of a Pricing DTO

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Type Safety  
**Location:** Lines 154-163 and 169-186

**Evidence:**
```php
return [
    'base_price'    => $basePrice,
    'surcharges'    => $surcharges,
    'total_price'   => $totalPrice,
```

**Problem:**
Pricing output is a raw associative array.

**Why this matters:**
Money-sensitive business logic should have explicit contracts. Arrays make it easy to accidentally change keys or types without static analysis catching it.

**How to fix:**
Introduce a `TicketPriceResult` DTO/value object.

---

### Issue #19: No Tests Are Enforced by Design for Pricing Rule Edge Cases

**Severity:** 🟡 MEDIUM  
**Category:** Testability / Production Readiness  
**Location:** Lines 48-164 and 204-233

**Evidence:**
```php
public function calculate(
```

```php
private function resolveDayType(Carbon $dt, int $dow, string $mmdd, array $extraHolidays): string
```

**Problem:**
The service is testable, but the pricing rules contain many edge cases that require explicit tests: holiday vs happy day priority, Friday classification, first Monday mad-sale behavior, invalid format fallback, reduced customer types, and negative/large pricing profiles.

**Why this matters:**
Pricing changes can silently affect revenue. Without strong test coverage around boundaries, regressions are likely.

**How to fix:**
Add focused unit tests for each rule and boundary condition.

---

## Recommendations

### IMMEDIATE

1. **Reject Unsupported Formats** - Do not silently fallback to `2D`.
2. **Validate Customer Type** - Reject unknown customer types.
3. **Validate Theater Pricing Profile** - Ensure all money fields are numeric, non-negative, and bounded.
4. **Prevent Negative or Zero Ticket Prices** - Enforce minimum valid ticket amount.
5. **Implement or Remove Beta-Ten and Mad-Sale Rules** - Comments and behavior must match.
6. **Fix Time Slot Logic** - Use minutes and classify pre-10:00/after-22:00 correctly.
7. **Move Hardcoded Double-Seat Surcharge to Config/Profile**.

### SHORT TERM

8. **Separate Discounts by Customer Type** - Student, child, and senior should not share one field unless explicitly required.
9. **Centralize Holiday Rules** - Use config/database with year-specific holidays.
10. **Define Format Pricing Ownership** - This service should either own format pricing or clearly require precomputed surcharge.
11. **Add Unit Tests for All Pricing Rules**.
12. **Introduce Pricing DTOs**.

### LONG TERM

13. **Build a Rule-Based Pricing Engine** - Replace conditional growth with explicit pricing rules.
14. **Add Audit Metadata** - Return applied rule IDs/codes for finance/support traceability.
15. **Support Theater Timezone** - Classify day/time using theater-local timezone.

---

## Improved Version Snippet

```php
public function calculate(
    string $format,
    Carbon $scheduledAt,
    string $customerType = 'adult',
    bool $isDoubleSeat = false,
    int $movieSurcharge = 0,
    array $extraHolidays = [],
    int $formatSurcharge = 0,
    int $seatSurcharge = 0,
    ?array $theaterPricing = null
): array {
    $supportedFormats = ['2D', '3D', '4DX', 'IMAX'];

    if (!in_array($format, $supportedFormats, true)) {
        throw new InvalidArgumentException('Unsupported ticket format.');
    }

    $allowedCustomerTypes = ['adult', 'student', 'child', 'senior'];

    if (!in_array($customerType, $allowedCustomerTypes, true)) {
        throw new InvalidArgumentException('Unsupported customer type.');
    }

    $pricing = $this->normalizePricingProfile($theaterPricing);

    foreach ([$movieSurcharge, $formatSurcharge, $seatSurcharge] as $surcharge) {
        if ($surcharge < 0 || $surcharge > 1_000_000) {
            throw new InvalidArgumentException('Invalid surcharge amount.');
        }
    }

    // Continue deterministic calculation...

    if ($totalPrice <= 0) {
        throw new DomainException('Calculated ticket price is invalid.');
    }

    return [
        'base_price' => $basePrice,
        'surcharges' => $surcharges,
        'total_price' => $totalPrice,
        'day_type' => $dayType,
        'time_slot' => $timeSlot,
        'customer_type' => $customerType,
        'price_group' => $priceGroup,
        'format' => $format,
    ];
}
```

---

## Summary

TicketPricingService.php is a useful isolated pricing service, but it is not strict enough for production revenue logic. The most serious issues are silent fallback of unsupported formats to `2D`, unvalidated pricing profiles, possible negative prices, and documented pricing rules that are not actually implemented.

**Strengths:**
- Dedicated service
- Mostly pure and testable calculation
- Integer money values mostly used
- Structured surcharge breakdown
- Supports theater pricing overrides

**Main Gaps:**
1. Unsupported formats silently become `2D`
2. Format value itself does not affect price
3. Theater pricing profile is unvalidated
4. Total price can become negative
5. Invalid customer type is silently treated as adult
6. Time slot logic is incorrect for pre-10:00 and post-22:00
7. `mad_sale_day` and `beta_ten_discount` are incomplete/dead rules
8. Holiday configuration is hardcoded and incomplete
9. Double-seat surcharge is hardcoded and may double-charge with seat type surcharge
10. Pricing result is an untyped array

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 12:52 PM*  
*File #34/137 - Phase 3: Business Logic (6/20 complete)*
