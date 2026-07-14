# Code Review: SeatService.php

**File:** `app/Services/SeatService.php`  
**Score:** 8.0/10  
**Decision:** ✅ **ACCEPTABLE - Minor Improvements Needed**

---

## Overview

**This is one of the BEST services reviewed.**

Excellent implementation with:
- ✅ Proper lockForUpdate() usage
- ✅ Transaction wrapping
- ✅ Authorization checks
- ✅ Real-time broadcasting
- ✅ Comprehensive conflict detection
- ✅ Clean code structure

---

## Medium Priority Issues

### 🟡 MEDIUM #1: N+1 Query in Conflict Detection

**Location:** Lines 156-176  
**Severity:** MEDIUM - Performance

```php
$lockedSeats = $lockedSeatHolds->flatMap(function ($hold) {
    return $hold->seat_ids; // Accesses relationship for each hold
});
```

**Issue:**
If 100 holds exist, this triggers 100 queries.

**Fix:**
```php
$lockedSeats = $lockedSeatHolds
    ->pluck('seat_ids')
    ->flatten()
    ->unique()
    ->values()
    ->all();
```

---

### 🟡 MEDIUM #2: unlock() Could Use Transaction

**Location:** Lines 244-275

**Current:**
```php
public function unlock(int $holdId, $user): array
{
    $hold = SeatHold::lockForUpdate()->findOrFail($holdId);
    
    // Validation
    // Delete
    // Broadcast
    // NO TRANSACTION
}
```

**Fix:**
```php
public function unlock(int $holdId, $user): array
{
    return DB::transaction(function () use ($holdId, $user) {
        $hold = SeatHold::lockForUpdate()->findOrFail($holdId);
        
        if ((int) $hold->user_id !== (int) $user->id) {
            throw new \RuntimeException(__('seats.unauthorized'), 403);
        }
        
        $seatIds = $hold->seat_ids;
        $showtimeId = $hold->showtime_id;
        
        $hold->delete();
        
        broadcast(new SeatUnlockedEvent($showtimeId, $seatIds))->toOthers();
        
        Log::info('Seat hold unlocked', [
            'hold_id' => $holdId,
            'seat_ids' => $seatIds,
            'user_id' => $user->id,
        ]);
        
        return [
            'unlocked_count' => count($seatIds),
            'seat_ids' => $seatIds,
        ];
    });
}
```

---

### 🟡 MEDIUM #3: Minimal Logging

**Location:** Throughout file

Only errors logged, not successful operations.

**Fix:**
```php
Log::info('Seats locked successfully', [
    'hold_id' => $hold->id,
    'seat_ids' => $seatIds,
    'user_id' => $user->id,
    'expires_at' => $hold->held_until,
]);
```

---

## Low Priority Issues

### 🔵 LOW #1: Magic Number

**Location:** Line 135

```php
$expirationMinutes = 15; // Hardcoded
```

**Fix:**
```php
$expirationMinutes = config('booking.seat_hold_duration', 15);
```

---

## Positive Findings

✅ **Perfect** pessimistic locking with `lockForUpdate()`  
✅ **Excellent** conflict detection logic  
✅ **Good** real-time broadcasting  
✅ **Proper** authorization checks  
✅ **Clean** code organization  
✅ **Comprehensive** seat status checking  

---

## Summary

**Issues:** 4 (0 Critical, 0 High, 3 Medium, 1 Low)

**Recommended Improvements:**
1. Fix N+1 query with pluck()
2. Wrap unlock() in transaction
3. Add comprehensive logging
4. Move magic number to config

**Status:** ✅ Production ready with minor optimizations

**Estimated Fix Time:** 2 hours

---

## This Service is a Great Example

Use this as a reference for other services:
- Proper locking patterns
- Transaction usage
- Broadcasting implementation
- Authorization checks
