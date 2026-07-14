# File Review: Screen.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Screen.php  
**Lines:** 70  
**Type:** Eloquent Model - Cinema Screen / Auditorium

---

## File Summary

`Screen.php` is an Eloquent model representing a theater screen/auditorium. It defines soft deletes, mass-assignable screen metadata, relationships to theater, format, sound, seat layout template, seats, and showtimes, plus two query scopes.

This model is small and readable, but it is part of a high-risk domain because screens connect seat inventory, showtimes, and booking availability. Incorrect screen mutation can invalidate seat layouts, corrupt showtime capacity assumptions, or allow booking against operationally inactive screens if lifecycle rules are not enforced consistently.

---

## Overall Score

**Overall Score:** 6.0/10

**Decision:** REQUEST CHANGES

---

## Strengths

- Uses typed relationship return types for all relationships.
- Uses `SoftDeletes`, which is appropriate for historical showtime/booking references.
- Casts `capacity` to integer and `status` to boolean.
- Provides basic relationships needed for theater, format, sound, layout, seats, and showtimes.
- Provides simple query scopes for active screens and lookup by code.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Security / Business Logic / Mass Assignment  
**Location:** `app/Models/Screen.php:15-24`

**Problem**

Operationally sensitive and relational fields are mass assignable:

```php
protected $fillable = [
    'theater_id',
    'name',
    'code',
    'format_id',
    'sound_id',
    'seat_layout_template_id',
    'capacity',
    'status',
];
```

This allows generic `Screen::create($request->all())` or `$screen->update($request->all())` paths to change:

- `theater_id`
- `format_id`
- `sound_id`
- `seat_layout_template_id`
- `capacity`
- `status`

Those fields are not harmless metadata. They affect where the screen belongs, what format is supported, what seat template applies, whether it is bookable, and the expected capacity.

**Why this matters**

A screen is a core scheduling and booking aggregate. If untrusted or weakly validated payloads reach this model, a caller can move a screen between theaters, switch layouts, change format, or deactivate/reactivate screens without explicit business rules.

This can break production by:

- associating existing showtimes with the wrong theater/screen context;
- making showtimes point to a screen whose layout no longer matches already-created seats;
- changing capacity without regenerating or validating seats;
- bypassing operational workflows for screen deactivation;
- creating inconsistent booking availability.

**How to fix**

Do not expose lifecycle and foreign-key fields through broad mass assignment. Keep only low-risk editable fields fillable, or move all screen mutation through explicit service methods with validated DTOs.

**Example**

Before:

```php
protected $fillable = [
    'theater_id',
    'name',
    'code',
    'format_id',
    'sound_id',
    'seat_layout_template_id',
    'capacity',
    'status',
];
```

After:

```php
protected $fillable = [
    'name',
    'code',
];

protected $guarded = [
    'id',
    'theater_id',
    'format_id',
    'sound_id',
    'seat_layout_template_id',
    'capacity',
    'status',
];
```

Then mutate operational fields through explicit methods or a transaction-backed service.

---

### Issue #2

**Severity:** High  
**Category:** Database Correctness / Booking Integrity  
**Location:** `app/Models/Screen.php:15-24`

**Problem**

`capacity` and `seat_layout_template_id` are independently assignable, but the model contains no invariant tying screen capacity to actual seats or the selected seat layout template.

```php
'seat_layout_template_id',
'capacity',
```

The relationships exist:

```php
public function seats(): HasMany
{
    return $this->hasMany(Seat::class);
}
```

but there is no model-level guard or helper ensuring that:

- `capacity` equals the number of active seats;
- `capacity` matches the selected seat layout template;
- changing `seat_layout_template_id` safely regenerates seats;
- existing showtimes/bookings are protected before layout changes.

**Why this matters**

For a cinema booking system, capacity and seat layout correctness directly affect revenue and booking integrity. If a screen claims capacity 100 while only 80 seats exist, availability and analytics can be wrong. If layout changes after showtimes or bookings exist, booked seats can become invalid or unavailable seats can be sold.

**How to fix**

Do not treat capacity/layout as simple scalar updates. Enforce layout changes through a dedicated transactional workflow:

1. Lock the screen row.
2. Check no active/future showtimes or bookings would be affected.
3. Generate/update seats atomically.
4. Set `capacity` from the actual generated seat count.
5. Commit.

**Example**

```php
DB::transaction(function () use ($screen, $template) {
    $screen = Screen::query()
        ->whereKey($screen->id)
        ->lockForUpdate()
        ->firstOrFail();

    if ($screen->showtimes()->where('start_time', '>=', now())->exists()) {
        throw new DomainException('Cannot change layout for a screen with future showtimes.');
    }

    // Generate seats from template here.
    // Then derive capacity from generated seats, not request input.
    $screen->forceFill([
        'seat_layout_template_id' => $template->id,
        'capacity' => $screen->seats()->count(),
    ])->save();
});
```

---

### Issue #3

**Severity:** Medium  
**Category:** Business Logic / Lifecycle Integrity  
**Location:** `app/Models/Screen.php:23,61-64`

**Problem**

`status` is a boolean and `scopeActive()` only checks `status = 1`:

```php
'status',
```

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}
```

There is no richer lifecycle state for screen operations. A cinema screen may be:

- active;
- inactive;
- under maintenance;
- temporarily unavailable;
- retired;
- deleted.

A boolean cannot express those states. It also does not encode whether existing future showtimes should remain valid or be blocked.

**Why this matters**

Operational availability is not a simple true/false flag. Incorrectly toggling `status` can cause showtimes to be created or booked on unavailable screens, or hide a screen while future showtimes remain active.

**How to fix**

Use an enum-backed lifecycle column or constants and centralize transition rules.

**Example**

```php
enum ScreenStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Retired = 'retired';
}
```

Then:

```php
protected $casts = [
    'capacity' => 'integer',
    'status' => ScreenStatus::class,
];
```

and replace `scopeActive()` with a typed scope checking the enum value.

---

### Issue #4

**Severity:** Medium  
**Category:** Database Correctness / Uniqueness  
**Location:** `app/Models/Screen.php:18,66-69`

**Problem**

The model exposes a `code` field and a `scopeByCode()` lookup:

```php
'code',
```

```php
public function scopeByCode($query, $code)
{
    return $query->where('code', $code);
}
```

However, the model does not enforce or document whether `code` is globally unique or unique only within a theater.

**Why this matters**

Screen codes such as `A1`, `S1`, or `IMAX` are often reused across theaters. A global `byCode()` scope can return ambiguous results if codes are theater-scoped. If the application assumes a single screen by code, this can route scheduling or booking operations to the wrong screen.

**How to fix**

Make the uniqueness rule explicit and reflect it in the API.

If globally unique:

```php
// database
$table->unique('code');
```

If theater-scoped:

```php
// database
$table->unique(['theater_id', 'code']);
```

and replace the scope with:

```php
public function scopeByTheaterAndCode(Builder $query, int $theaterId, string $code): Builder
{
    return $query->where('theater_id', $theaterId)
        ->where('code', $code);
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** Laravel Best Practice / Static Analysis  
**Location:** `app/Models/Screen.php:61-69`

**Problem**

The query scopes are untyped:

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}

public function scopeByCode($query, $code)
{
    return $query->where('code', $code);
}
```

The relationship methods are typed, but scopes are not. This reduces IDE/static analysis support and makes query contracts less clear.

**Why this matters**

This project has many service/controller queries. Untyped scopes make refactoring riskier and reduce testability/readability. For production Laravel code, scopes should use `Illuminate\Database\Eloquent\Builder` types.

**How to fix**

Add `Builder` import and parameter/return types.

**Example**

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function scopeByCode(Builder $query, string $code): Builder
{
    return $query->where('code', $code);
}
```

---

### Issue #6

**Severity:** Medium  
**Category:** Data Integrity / Soft Deletes  
**Location:** `app/Models/Screen.php:13,56-59`

**Problem**

The model uses soft deletes:

```php
use HasFactory, SoftDeletes;
```

and has showtimes:

```php
public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class);
}
```

but there is no model-level deletion guard, domain method, or documented expectation preventing a screen from being deleted while it still has future showtimes or booking-related records.

**Why this matters**

Soft delete prevents physical row removal, but it does not automatically prevent operational corruption. A soft-deleted screen can still be referenced by showtimes. If future showtimes exist, public APIs may hide the screen while bookings or schedules still depend on it.

**How to fix**

Deletion should be handled by a service/policy with explicit checks, not by raw model deletion.

**Example**

```php
public function hasFutureShowtimes(): bool
{
    return $this->showtimes()
        ->where('start_time', '>=', now())
        ->exists();
}
```

Then block deletion/deactivation when future showtimes exist unless a controlled cancellation workflow is executed.

---

### Issue #7

**Severity:** Low  
**Category:** Clean Code / Magic Values  
**Location:** `app/Models/Screen.php:63`

**Problem**

The active scope uses a magic integer for a boolean column:

```php
return $query->where('status', 1);
```

The model already casts `status` as boolean:

```php
'status' => 'boolean',
```

**Why this matters**

This is minor, but inconsistent representation of booleans makes code less readable and can lead to inconsistent query style across the codebase.

**How to fix**

Use `true` or a named enum/status constant.

**Example**

```php
return $query->where('status', true);
```

---

### Issue #8

**Severity:** Low  
**Category:** Maintainability / Relationship Semantics  
**Location:** `app/Models/Screen.php:51-59`

**Problem**

The model defines `seats()` and `showtimes()` as all records:

```php
public function seats(): HasMany
{
    return $this->hasMany(Seat::class);
}

public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class);
}
```

There are no convenience relationships/scopes for active seats or future showtimes at the model boundary.

**Why this matters**

Callers will repeatedly implement their own definitions of "active seats", "future showtimes", or "bookable showtimes", increasing the risk of inconsistent behavior between admin, public listing, booking, and analytics flows.

**How to fix**

Add explicit helper relationships or query methods based on actual domain columns.

**Example**

```php
public function futureShowtimes(): HasMany
{
    return $this->showtimes()->where('start_time', '>=', now());
}
```

Only add these after confirming the actual `Showtime` date/time column names.

---

## Recommendations

### Immediate

1. Remove broad mass assignment for operational and foreign-key fields.
2. Ensure screen capacity is derived from seats/layout, not accepted as an arbitrary request value.
3. Block layout/capacity/status mutations when future showtimes or bookings would be affected.
4. Make screen code uniqueness explicit at the database and model API level.

### Short Term

5. Type all query scopes with `Builder`.
6. Replace boolean `status` with an enum-backed lifecycle state if business operations require maintenance/retired states.
7. Add domain helper methods for future showtime checks before delete/deactivate/layout changes.
8. Normalize `scopeActive()` to use boolean `true` or a status enum.

### Long Term

9. Introduce a dedicated `ScreenLayoutService` or similar transactional workflow for template changes and seat regeneration.
10. Add tests for screen deletion, deactivation, layout changes, capacity consistency, and duplicate code handling.
11. Add audit logging for screen lifecycle and layout changes because these affect sellable inventory.

---

## Summary

`Screen.php` is structurally clean and uses typed relationships, but it is too permissive for a production booking domain. The biggest risk is not syntax or readability; it is that core operational fields such as layout, capacity, theater, format, and status are mass assignable without visible invariants.

**Main concerns:**

- Broad mass assignment of booking-critical screen fields.
- No invariant tying capacity to actual seats or layout template.
- Boolean lifecycle status is too weak for real cinema operations.
- Ambiguous screen code uniqueness.
- Untyped query scopes.
- Soft delete exists without visible guard against future showtime corruption.

**Status:** Request changes before production acceptance.
