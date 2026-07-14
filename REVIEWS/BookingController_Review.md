# Code Review: BookingController.php

**File:** `app/Http/Controllers/BookingController.php`  
**Score:** 4.5/10  
**Decision:** 🔴 **BLOCKING - Multiple Critical Issues**

---

## Critical Issues

### 🔴 CRITICAL #1: Information Disclosure via Exception

**Location:** Lines 49-54, 67-72, 85-90  
**Severity:** CRITICAL

```php
catch (\Exception $e) {
    return $this->errorResponse('Failed: ' . $e->getMessage(), 500);
}
```

**Exposes:**
- SQL errors
- File paths
- Internal business logic

**Fix:**
```php
catch (\Throwable $e) {
    Log::error('Booking failed', [
        'showtime_id' => $showtimeId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    return $this->errorResponse(__('booking.failed'), 500);
}
```

---

### 🟠 HIGH #2: No FormRequest Validation

**Location:** All methods  
**Severity:** HIGH

No input validation for showtime_id or any parameters.

**Fix:**
```php
// Create BookingRequest.php
class BookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],
        ];
    }
}

// Use in controller
public function show(BookingRequest $request, $encryptedShowtimeId)
```

---

### 🟠 HIGH #3: No Rate Limiting

**Location:** All endpoints

Could be abused to enumerate showtimes or spam bookings.

**Fix:**
```php
// In routes
Route::middleware(['throttle:booking'])->group(function () {
    Route::get('/booking/{encryptedShowtimeId}', [BookingController::class, 'show']);
});

// In RouteServiceProvider
RateLimiter::for('booking', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});
```

---

### 🟡 MEDIUM #4: No Audit Logging

No logging for:
- Booking page views
- Failed decryption attempts
- Errors

**Fix:**
```php
Log::info('Booking page accessed', [
    'showtime_id' => $showtime->id,
    'user_id' => Auth::id(),
    'ip' => $request->ip(),
]);
```

---

### 🟡 MEDIUM #5: Decrypt Errors Not Differentiated

Line 48: Both "invalid format" and "not found" return same error.

**Fix:**
```php
try {
    $showtimeId = decrypt($encryptedShowtimeId);
} catch (DecryptException $e) {
    return $this->errorResponse(__('showtime.invalid_link'), 400);
}

$showtime = Showtime::find($showtimeId);
if (!$showtime) {
    return $this->errorResponse(__('showtime.not_found'), 404);
}
```

---

## Summary

**Issues:** 5 (1 Critical, 2 High, 2 Medium)

**Must Fix:**
1. Stop exposing exception messages
2. Add FormRequest validation
3. Add rate limiting
4. Add audit logging

**Estimated Fix Time:** 4-6 hours
