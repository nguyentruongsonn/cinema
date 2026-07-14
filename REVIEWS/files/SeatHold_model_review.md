# File Review: SeatHold.php (Model)

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/SeatHold.php  
**Lines:** 58  
**Type:** Eloquent Model - Temporary Seat Reservations

---

## File Information

**Path:** `app/Models/SeatHold.php`  
**Type:** Eloquent Model  
**Lines:** 58  
**Complexity:** Low  

**Purpose:**  
SeatHold model for temporary seat reservations during booking:
- Holds seats while user completes checkout
- Prevents double-booking during payment
- Time-limited holds (expires after timeout)
- Critical for booking concurrency

**Database Table:** `seat_holds`

---

## Overall Score

**Code Quality:** 5.0/10  
**Security:** 4.0/10 ⚠️  
**Performance:** 5.0/10  
**Maintainability:** 4.5/10  
**Laravel Best Practice:** 4.0/10  
**Architecture:** 3.0/10 🔴  

**Overall Score:** 4.3/10

**Decision:** 🔴 **REQUEST CRITICAL CHANGES - ARCHITECTURE REDESIGN REQUIRED**

---

## Strengths

1. ✅ **Helper Methods** - isValid() for checking expiration
2. ✅ **Scopes** - Valid and expired scopes for querying
3. ✅ **Datetime Casting** - held_until properly cast

---

## CRITICAL ISSUES

### Issue #1: Fundamentally Flawed Design - JSON seat_ids

**Severity:** 🔴 BLOCKING  
**Category:** Architecture & Concurrency  
**Location:** Line 16, Line 21

**Evidence:**
```php
protected $fillable = [
    'showtime_id',
    'user_id',
    'seat_ids',  // ← CRITICAL FLAW: JSON array instead of foreign key
];

protected $casts = [
    'seat_ids' => 'json',  // ← Storing multiple seat IDs as JSON
];
```

**Problem:**
Storing seat_ids as a JSON array is a **fundamental architecture flaw** for a booking system. This design prevents:

1. **Database-level locking** - Cannot use `lockForUpdate()` on individual seats
2. **Unique constraints** - Cannot prevent duplicate holds at database level
3. **Proper indexes** - Cannot efficiently query "which holds exist for seat X"
4. **Atomic operations** - Cannot atomically check and create holds
5. **Relational integrity** - No foreign key constraints

**Why This Is Critical:**
```php
// Current design - RACE CONDITION GUARANTEED:

// User A starts booking seats [1, 2, 3] at 10:00:00.000
SeatHold::create([
    'seat_ids' => [1, 2, 3],
    'held_until' => now()->addMinutes(5)
]);

// User B starts booking seats [2, 3, 4] at 10:00:00.001
// ← No database-level check prevents this!
SeatHold::create([
    'seat_ids' => [2, 3, 4],  // Overlapping seats 2 and 3!
    'held_until' => now()->addMinutes(5)
]);

// RESULT: Seats 2 and 3 are double-booked!
```

**Why lockForUpdate() Doesn't Help:**
```php
// Cannot do this with JSON seat_ids:
DB::transaction(function () {
    $seats = Seat::whereIn('id', [1, 2, 3])
        ->lockForUpdate()  // ← This locks Seat rows
        ->get();
    
    // But checking for existing holds requires:
    $existingHolds = SeatHold::where('showtime_id', $showtimeId)
        ->where('held_until', '>', now())
        ->get();
    
    foreach ($existingHolds as $hold) {
        $heldSeatIds = $hold->seat_ids;  // JSON decode
        if (array_intersect($heldSeatIds, [1, 2, 3])) {
            // Conflict found - but this check is NOT atomic!
        }
    }
    
    // RACE CONDITION: Another transaction can create hold between
    // the check above and the insert below!
});
```

**Impact:**
- **DOUBLE BOOKING GUARANTEED** under concurrent load
- Lost revenue (overbooking refunds)
- Customer complaints
- System credibility loss
- Race conditions in production

**Correct Architecture:**
```php
// seat_holds table should be:
// - id
// - user_id
// - showtime_id
// - seat_id (foreign key, NOT JSON)
// - expires_at
// - status (enum: 'active', 'expired', 'converted')
// - created_at, updated_at
// 
// UNIQUE INDEX on (showtime_id, seat_id) WHERE status = 'active'

class SeatHold extends Model
{
    protected $fillable = [
        'user_id',
        'showtime_id',
        'seat_id',  // ← Single seat per row
        'expires_at',
        'status',
    ];
    
    // Relationships
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
    
    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// Usage - PROPER ATOMIC LOCKING:
DB::transaction(function () use ($seatIds, $showtimeId, $userId) {
    // 1. Lock the seat rows
    $seats = Seat::whereIn('id', $seatIds)
        ->lockForUpdate()
        ->get();
    
    // 2. Check for conflicts using database constraint
    // If unique constraint exists, this will throw exception automatically
    $holds = [];
    foreach ($seatIds as $seatId) {
        try {
            $holds[] = SeatHold::create([
                'user_id' => $userId,
                'showtime_id' => $showtimeId,
                'seat_id' => $seatId,
                'expires_at' => now()->addMinutes(5),
                'status' => 'active',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation = seat already held
            throw new \DomainException("Seat $seatId is already held");
        }
    }
    
    return $holds;
});
```

**Alternative - Keep JSON but Add Lock Table:**
```php
// If you MUST keep JSON design, add a locking mechanism:

// Create seat_locks table:
// - showtime_id
// - seat_id
// - locked_at
// - PRIMARY KEY (showtime_id, seat_id)

// Before creating SeatHold:
DB::transaction(function () use ($seatIds, $showtimeId) {
    foreach ($seatIds as $seatId) {
        DB::table('seat_locks')->insert([
            'showtime_id' => $showtimeId,
            'seat_id' => $seatId,
            'locked_at' => now(),
        ]);
        // ← Unique constraint will throw if already locked
    }
    
    // Now create the hold
    SeatHold::create([...]);
});
```

---

### Issue #2: No Status Tracking

**Severity:** 🟠 HIGH  
**Category:** Data Integrity  
**Location:** Missing field

**Evidence:**
```php
protected $fillable = [
    'showtime_id',
    'user_id',
    'seat_ids',
    'held_until',
    // Missing: 'status'
];
```

**Problem:**
No status field to track hold lifecycle:
- Active (currently holding)
- Expired (time expired)
- Converted (became order)
- Released (manually released)
- Cancelled (user cancelled)

Without status:
- Cannot tell if hold was converted to order
- Cannot prevent reusing expired holds
- Cannot track hold outcomes
- Difficult to debug issues

**Impact:**
- Data ambiguity
- Cannot audit hold lifecycle
- Cleanup scripts might delete active holds
- Business analytics impossible

**Recommended Fix:**
```php
// Migration
$table->enum('status', ['active', 'expired', 'converted', 'released', 'cancelled'])
    ->default('active');

// Model
const STATUS_ACTIVE = 'active';
const STATUS_EXPIRED = 'expired';
const STATUS_CONVERTED = 'converted';
const STATUS_RELEASED = 'released';
const STATUS_CANCELLED = 'cancelled';

protected $fillable = [
    'showtime_id',
    'user_id',
    'seat_ids',
    'held_until',
    'status',
];

// Scopes
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', self::STATUS_ACTIVE)
                 ->where('held_until', '>', now());
}

// Methods
public function expire(): void
{
    $this->update(['status' => self::STATUS_EXPIRED]);
}

public function convertToOrder(): void
{
    if ($this->status !== self::STATUS_ACTIVE) {
        throw new \DomainException('Can only convert active holds');
    }
    $this->update(['status' => self::STATUS_CONVERTED]);
}
```

---

### Issue #3: No Relationship to Seat Model

**Severity:** 🟠 HIGH  
**Category:** Domain Modeling  
**Location:** Missing relationship

**Evidence:**
```php
// Has relationships to:
public function showtime(): BelongsTo
public function user(): BelongsTo

// Missing:
// public function seats(): BelongsToMany or HasMany
```

**Problem:**
Cannot query relationships because seat_ids is JSON:
```php
// Cannot do:
$hold->seats  // ← Doesn't exist

// Must do:
$seatIds = $hold->seat_ids;
$seats = Seat::whereIn('id', $seatIds)->get();

// This is verbose and not Eloquent-like
```

**Recommended Fix:**
With proper table design (one row per seat), you could have:
```php
// If using pivot table approach:
public function seats(): BelongsToMany
{
    return $this->belongsToMany(Seat::class, 'seat_hold_seat')
        ->withTimestamps();
}

// Or with one-to-many:
public function seatHoldItems(): HasMany
{
    return $this->hasMany(SeatHoldItem::class);
}
```

---

### Issue #4: Naming Inconsistency - held_until vs expires_at

**Severity:** 🔵 LOW  
**Category:** Code Consistency  
**Location:** Line 17

**Evidence:**
```php
'held_until',  // ← This model uses held_until
```

**Problem:**
Inconsistent naming across codebase:
- SeatHold uses `held_until`
- Other models typically use `expires_at` or `expired_at`
- This makes code harder to understand

**Impact:**
- Confusing for developers
- Harder to maintain
- Pattern inconsistency

**Recommended Fix:**
```php
// Standardize on expires_at
protected $fillable = [
    'showtime_id',
    'user_id',
    'seat_ids',
    'expires_at',  // ← Consistent with other models
];

protected $casts = [
    'expires_at' => 'datetime',
];

public function isValid(): bool
{
    return $this->expires_at->isFuture();
}
```

---

### Issue #5: No Automatic Cleanup

**Severity:** 🟡 MEDIUM  
**Category:** Maintenance  
**Location:** Missing functionality

**Evidence:**
```php
// Expired holds just sit in database
// No automatic cleanup
```

**Problem:**
Expired holds accumulate in database causing:
- Database bloat
- Slower queries
- Wasted storage

**Recommended Fix:**
```php
// Option 1: Scheduled command
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        SeatHold::expired()
            ->where('status', '!=', 'converted')
            ->update(['status' => 'expired']);
        
        // Optional: delete very old expired holds
        SeatHold::where('status', 'expired')
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();
    })->hourly();
}

// Option 2: Model event
protected static function boot()
{
    parent::boot();
    
    static::retrieved(function ($hold) {
        if ($hold->isExpired() && $hold->status === 'active') {
            $hold->expire();
        }
    });
}
```

---

### Issue #6: Scope Methods Missing Return Types

**Severity:** 🔵 LOW  
**Category:** Type Safety  
**Location:** Lines 46-57

**Evidence:**
```php
public function scopeValid($query)
{
    return $query->where('held_until', '>', now());
}
```

**Problem:**
Missing parameter and return type declarations.

**Recommended Fix:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeValid(Builder $query): Builder
{
    return $query->where('held_until', '>', now());
}

public function scopeExpired(Builder $query): Builder
{
    return $query->where('held_until', '<=', now());
}
```

---

## Recommendations

### IMMEDIATE (CRITICAL - BLOCKING)

1. **⚠️ REDESIGN TABLE STRUCTURE** - One row per seat, not JSON array
2. **Add Unique Constraint** - (showtime_id, seat_id) where status='active'
3. **Add seat_id Foreign Key** - Proper relational design
4. **Implement Atomic Locking** - Use lockForUpdate() on Seat rows

### HIGH PRIORITY

5. **Add Status Field** - Track hold lifecycle (active/expired/converted)
6. **Add Relationships** - Proper Eloquent relationships to Seat
7. **Implement Status Transitions** - Methods for expire(), convert(), release()
8. **Add Unique Constraint** - Prevent duplicate holds per user/showtime

### MEDIUM PRIORITY

9. **Rename held_until to expires_at** - Consistency with codebase
10. **Add Automatic Cleanup** - Scheduled command for expired holds
11. **Add Return Types** - Type safety for scopes
12. **Add Soft Deletes** - Preserve historical data

---

## Migration Path

**Step 1: Create new table structure**
```sql
CREATE TABLE seat_hold_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    showtime_id BIGINT UNSIGNED NOT NULL,
    seat_id BIGINT UNSIGNED NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active', 'expired', 'converted') DEFAULT 'active',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (seat_id) REFERENCES seats(id),
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    
    UNIQUE KEY unique_active_hold (showtime_id, seat_id, status),
    INDEX idx_user_showtime (user_id, showtime_id),
    INDEX idx_expires (expires_at, status)
);
```

**Step 2: Migrate data**
```php
// One-time migration script
SeatHold::chunk(100, function ($holds) {
    foreach ($holds as $hold) {
        $seatIds = $hold->seat_ids;
        foreach ($seatIds as $seatId) {
            DB::table('seat_hold_items')->insert([
                'user_id' => $hold->user_id,
                'showtime_id' => $hold->showtime_id,
                'seat_id' => $seatId,
                'expires_at' => $hold->held_until,
                'status' => $hold->held_until->isPast() ? 'expired' : 'active',
                'created_at' => $hold->created_at,
                'updated_at' => $hold->updated_at,
            ]);
        }
    }
});
```

---

## Summary

SeatHold model has a **fundamental architecture flaw** that makes race conditions inevitable under concurrent load.

**Critical Issue:**
Storing seat_ids as JSON array prevents database-level locking and atomic operations, **guaranteeing double-booking** in production.

**Why This Matters:**
This isn't just a code quality issue - this is a **business-critical bug** that will cause:
- Double bookings
- Revenue loss
- Customer complaints
- System credibility damage

**Required Action:**
Complete table redesign from:
- `seat_ids` JSON array (one hold = multiple seats)

To:
- One row per seat (one hold = one seat)
- Unique constraint on (showtime_id, seat_id)
- Proper foreign keys and locking

This is not optional. The current design **will fail** under production load.

**Status:** 🔴 CRITICAL REDESIGN REQUIRED - BLOCKING FOR PRODUCTION

---

*Review completed: 2026-07-14 03:12 AM*
