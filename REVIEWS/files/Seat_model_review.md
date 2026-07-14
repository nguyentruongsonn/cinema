# File Review: Seat.php (Model)

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Seat.php  
**Lines:** 51  
**Type:** Eloquent Model - Seat Records

---

## File Information

**Path:** `app/Models/Seat.php`  
**Type:** Eloquent Model  
**Lines:** 51  
**Complexity:** Low  

**Purpose:**  
Seat model representing physical cinema seats:
- Seat positioning (row, column, label)
- Seat type (standard, VIP, etc.)
- Seat status (active/inactive)
- Screen relationship

**Database Table:** `seats`

---

## Overall Score

**Code Quality:** 6.5/10  
**Security:** 7.0/10  
**Performance:** 7.5/10  
**Maintainability:** 6.0/10  
**Laravel Best Practice:** 6.5/10  

**Overall Score:** 6.7/10

**Decision:** ⚠️ **APPROVE WITH IMPROVEMENTS**

---

## Strengths

1. ✅ **Simple Structure** - Clean, minimal model
2. ✅ **Proper Relationships** - Screen and SeatType relationships
3. ✅ **Type Casting** - Boolean status, integer indexes
4. ✅ **Factory Support** - HasFactory trait for testing

---

## Issues Found

### Issue #1: Duplicate Scopes - Active vs Available

**Severity:** 🟡 MEDIUM  
**Category:** Code Quality & Maintainability  
**Location:** Lines 41-50

**Evidence:**
```php
// Scope: active seats
public function scopeActive($query)
{
    return $query->where('status', 1);
}

// Scope: available seats
public function scopeAvailable($query)
{
    return $query->where('status', 1);  // ← IDENTICAL!
}
```

**Problem:**
Two scopes do exactly the same thing. This creates confusion:
- Is "active" the same as "available"?
- Which one should developers use?
- What's the semantic difference?

**Why This Matters:**
```php
// Both queries return the same results - confusing!
$activeSeats = Seat::active()->get();
$availableSeats = Seat::available()->get();

// Are these the same concept or different?
```

**Impact:**
- Confusing API
- Code duplication
- Maintenance burden
- Unclear business logic

**Recommended Fix - Clarify Distinction:**
```php
// "Active" = seat physically exists (not removed/disabled)
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

// "Available" = seat is active AND not currently booked
public function scopeAvailable(Builder $query, int $showtimeId): Builder
{
    return $query->where('status', true)
        ->whereDoesntHave('seatHolds', function ($q) use ($showtimeId) {
            $q->where('showtime_id', $showtimeId)
              ->where('status', 'held');
        })
        ->whereDoesntHave('orderItems', function ($q) use ($showtimeId) {
            $q->whereHas('order', function ($q2) {
                $q2->where('status', Order::STATUS_CONFIRMED);
            })
            ->where('showtime_id', $showtimeId);
        });
}
```

**Alternative - Remove Redundant Scope:**
```php
// Keep only one
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

// Remove scopeAvailable - check availability at service layer
```

---

### Issue #2: Oversimplified Status Field

**Severity:** 🟡 MEDIUM  
**Category:** Domain Modeling  
**Location:** Line 21, Line 27

**Evidence:**
```php
protected $fillable = [
    'status',  // ← Boolean: active (1) or inactive (0)
];

protected $casts = [
    'status' => 'boolean',
];
```

**Problem:**
Boolean status is too simple for a booking system. Seats have multiple states:

**Physical Status:**
- Active (1) - seat exists physically
- Inactive (0) - seat removed/disabled

**Booking Status (per showtime):**
- Available
- Held (temporarily reserved)
- Booked (order confirmed)

Mixing these in a single boolean is problematic.

**Impact:**
- Cannot represent held seats
- Cannot track seat history
- Availability must be calculated elsewhere
- Confusing semantics

**Recommended Fix - Separate Concerns:**
```php
// Seat model only tracks physical status
protected $fillable = [
    'is_active',  // ← Renamed for clarity
];

protected $casts = [
    'is_active' => 'boolean',
];

public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}

// Booking status tracked in SeatHold and OrderItem
// Check availability via relationships:
public function isAvailableForShowtime(int $showtimeId): bool
{
    if (!$this->is_active) {
        return false;
    }
    
    // Check if held
    $hasActiveHold = $this->seatHolds()
        ->where('showtime_id', $showtimeId)
        ->where('expires_at', '>', now())
        ->exists();
    
    if ($hasActiveHold) {
        return false;
    }
    
    // Check if booked
    $isBooked = $this->orderItems()
        ->where('showtime_id', $showtimeId)
        ->whereHas('order', fn($q) => $q->where('status', Order::STATUS_CONFIRMED))
        ->exists();
    
    return !$isBooked;
}
```

---

### Issue #3: Missing Unique Constraint on Seat Position

**Severity:** 🟡 MEDIUM  
**Category:** Data Integrity  
**Location:** Lines 13-22

**Evidence:**
```php
protected $fillable = [
    'screen_id',
    'row',
    'number',
    // ← No uniqueness enforcement
];
```

**Problem:**
No model-level validation that (screen_id, row, number) is unique. Could create duplicate seats:
- Screen 1, Row A, Seat 5 (duplicate!)
- Screen 1, Row A, Seat 5 (duplicate!)

**Impact:**
- Data integrity issues
- Confusing seat maps
- Booking conflicts
- User confusion

**Recommended Fix:**
```php
use Illuminate\Validation\Rule;

// In FormRequest or validation
public function rules(): array
{
    return [
        'screen_id' => ['required', 'exists:screens,id'],
        'row' => [
            'required',
            'string',
            Rule::unique('seats')
                ->where('screen_id', $this->screen_id)
                ->where('number', $this->number)
                ->ignore($this->seat), // For updates
        ],
        'number' => ['required', 'integer'],
    ];
}

// Or in model boot
protected static function boot()
{
    parent::boot();
    
    static::creating(function ($seat) {
        $exists = self::where('screen_id', $seat->screen_id)
            ->where('row', $seat->row)
            ->where('number', $seat->number)
            ->exists();
        
        if ($exists) {
            throw new \DomainException(
                "Seat {$seat->row}{$seat->number} already exists in this screen"
            );
        }
    });
}
```

---

### Issue #4: Missing Critical Relationships

**Severity:** 🟡 MEDIUM  
**Category:** Domain Modeling  
**Location:** Missing code

**Evidence:**
```php
// Only has:
public function screen(): BelongsTo
public function seatType(): BelongsTo

// Missing:
// - seatHolds relationship
// - orderItems relationship
```

**Problem:**
Seat model doesn't define relationships to:
- SeatHold (temporary reservations)
- OrderItem (confirmed bookings)

These are critical for checking availability.

**Impact:**
- Must write manual joins
- Harder to query availability
- Less expressive code

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function seatHolds(): HasMany
{
    return $this->hasMany(SeatHold::class);
}

public function orderItems(): HasMany
{
    return $this->hasMany(OrderItem::class);
}

// Helper methods
public function hasActiveHoldForShowtime(int $showtimeId): bool
{
    return $this->seatHolds()
        ->where('showtime_id', $showtimeId)
        ->where('expires_at', '>', now())
        ->exists();
}

public function isBookedForShowtime(int $showtimeId): bool
{
    return $this->orderItems()
        ->where('showtime_id', $showtimeId)
        ->whereHas('order', function ($q) {
            $q->where('status', Order::STATUS_CONFIRMED);
        })
        ->exists();
}
```

---

### Issue #5: Scope Methods Missing Return Types

**Severity:** 🔵 LOW  
**Category:** Type Safety  
**Location:** Lines 41-50

**Evidence:**
```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}
```

**Problem:**
Missing parameter and return type declarations.

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}
```

---

### Issue #6: Status Mass-Assignable

**Severity:** 🔵 LOW  
**Category:** Security  
**Location:** Line 21

**Evidence:**
```php
protected $fillable = [
    'status',  // ← Can be mass-assigned
];
```

**Problem:**
While less critical than Order/Payment status, seat status being mass-assignable could allow:
- Disabling all seats
- Creating fake availability

**Impact:**
- Potential for abuse
- Should be controlled

**Recommended Fix:**
```php
protected $fillable = [
    'screen_id',
    'seat_type_id',
    'row',
    'number',
    'row_index',
    'column_index',
    'label',
    // Remove: 'status'
];

protected $guarded = [
    'status',  // Or 'is_active' if renamed
];

// Add controlled methods
public function activate(): void
{
    $this->update(['status' => true]);
}

public function deactivate(): void
{
    $this->update(['status' => false]);
}
```

---

## Recommendations

### Immediate (Medium Priority)

1. **Remove Duplicate Scope** - Keep scopeActive, remove scopeAvailable
2. **Add Missing Relationships** - seatHolds(), orderItems()
3. **Clarify Status Semantics** - Rename to is_active for clarity
4. **Add Unique Constraint Validation** - Prevent duplicate seats

### Short Term

5. **Add Return Types** - Better type safety for scopes
6. **Guard Status Field** - Prevent mass assignment
7. **Add Availability Helpers** - isAvailableForShowtime() method
8. **Add Soft Deletes** - Preserve historical seat data

### Long Term

9. **Separate Physical vs Booking Status** - Clear domain separation
10. **Add Seat Map Generation** - Helper methods for UI
11. **Add Seat Pricing Logic** - If seats have different prices
12. **Implement Seat Blocking** - For maintenance/repairs

---

## Test Requirements

```php
// Test 1: Duplicate seat prevention
public function test_cannot_create_duplicate_seats()
{
    Seat::factory()->create([
        'screen_id' => 1,
        'row' => 'A',
        'number' => 5
    ]);
    
    $this->expectException(\Exception::class);
    Seat::factory()->create([
        'screen_id' => 1,
        'row' => 'A',
        'number' => 5
    ]);
}

// Test 2: Availability checking
public function test_held_seats_are_not_available()
{
    $seat = Seat::factory()->create(['status' => true]);
    $showtime = Showtime::factory()->create();
    
    SeatHold::factory()->create([
        'seat_id' => $seat->id,
        'showtime_id' => $showtime->id,
        'expires_at' => now()->addMinutes(5)
    ]);
    
    $this->assertFalse($seat->isAvailableForShowtime($showtime->id));
}

// Test 3: Booked seats not available
public function test_booked_seats_are_not_available()
{
    $seat = Seat::factory()->create();
    $showtime = Showtime::factory()->create();
    $order = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
    
    OrderItem::factory()->create([
        'seat_id' => $seat->id,
        'showtime_id' => $showtime->id,
        'order_id' => $order->id
    ]);
    
    $this->assertFalse($seat->isAvailableForShowtime($showtime->id));
}
```

---

## Summary

Seat model is a simple, clean model but has some design clarity issues:

**Main Issues:**
1. **Duplicate scopes** (active = available) - remove one
2. **Oversimplified status** - boolean doesn't capture booking states
3. **Missing relationships** - no links to SeatHold/OrderItem
4. **No unique constraints** - could create duplicate seats

These aren't critical security issues like Payment/Order models, but they affect data integrity and code clarity.

The model would benefit from:
- Clear separation of physical status (is_active) vs booking status (calculated)
- Relationships to SeatHold and OrderItem
- Unique constraint enforcement
- Availability checking helpers

After implementing these improvements, the model will be more robust and easier to work with.

**Status:** ⚠️ Improvements recommended (not blocking)

---

*Review completed: 2026-07-14 03:10 AM*
