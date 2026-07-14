# File Review: ShowtimeSeatLayoutSnapshot.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/ShowtimeSeatLayoutSnapshot.php  
**Lines:** 28  
**Type:** Eloquent Model - Showtime Seat Layout Snapshot

---

## File Summary

`ShowtimeSeatLayoutSnapshot.php` represents a stored snapshot of the seat layout for a showtime. It maps to the `showtime_seat_layout_snapshots` table, exposes `showtime_id`, `layout_data`, `checksum`, and `version` as mass assignable, casts `layout_data` to JSON and `version` to integer, and defines a `belongsTo` relationship to `Showtime`.

This model is important for booking correctness because seat layout snapshots must remain immutable and consistent once a showtime starts selling seats. Any corruption or uncontrolled mutation can cause mismatches between seats displayed, seats held, tickets issued, and historical booking records.

---

## Overall Score

**Overall Score:** 5.3/10

**Decision:** REQUEST CHANGES

---

## Strengths

- Explicitly sets the table name.
- Defines the `showtime()` relationship.
- Casts `layout_data` as JSON and `version` as integer.
- The model is short and readable.
- No raw SQL or dynamic query construction is present.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Mass Assignment / Booking Integrity  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:12-17`

**Problem**

All snapshot integrity fields are mass assignable:

```php
protected $fillable = [
    'showtime_id',
    'layout_data',
    'checksum',
    'version',
];
```

**Why this matters**

A seat-layout snapshot is booking-critical state. If any controller or service passes user-controlled request data into `create()` or `update()`, a caller could alter:

- the showtime associated with a snapshot;
- the seat layout structure;
- the checksum used to verify integrity;
- the version used to identify the snapshot revision.

This can cause incorrect seat availability, mismatched seat maps, duplicate seat assignment, corrupted ticket references, and difficult-to-reconcile booking state.

**How to fix**

Do not expose booking-critical identity and integrity fields through broad mass assignment. Create snapshots only through a dedicated service that derives `layout_data`, `checksum`, and `version` server-side.

**Example**

```php
protected $guarded = ['id'];
```

And in a service:

```php
$snapshot = new ShowtimeSeatLayoutSnapshot();
$snapshot->showtime()->associate($showtime);
$snapshot->forceFill([
    'layout_data' => $generatedLayoutData,
    'checksum' => hash('sha256', json_encode($generatedLayoutData, JSON_THROW_ON_ERROR)),
    'version' => $nextVersion,
])->save();
```

---

### Issue #2

**Severity:** High  
**Category:** Data Integrity / Immutability  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:12-22`

**Problem**

The model represents a snapshot but does not enforce immutability.

```php
'layout_data',
'checksum',
'version',
```

```php
protected $casts = [
    'layout_data' => 'json',
    'version' => 'integer',
];
```

**Why this matters**

A snapshot should be an immutable historical record. Updating `layout_data` after seats are held or tickets are issued can break the relationship between:

- seat map displayed to users;
- seat holds;
- booked tickets;
- order items;
- showtime seat availability.

This can create duplicate bookings or make booked seats disappear from the rendered layout.

**How to fix**

Prevent updates after creation or after the showtime becomes sellable. Prefer append-only versioning.

**Example**

```php
protected static function booted(): void
{
    static::updating(function (): void {
        throw new LogicException('Seat layout snapshots are immutable.');
    });
}
```

If versioning is required, insert a new snapshot row instead of mutating an existing one.

---

### Issue #3

**Severity:** High  
**Category:** Database Correctness / Concurrency  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:13,16`

**Problem**

The model exposes `showtime_id` and `version`, but there is no visible uniqueness guarantee for one version per showtime.

```php
'showtime_id',
```

```php
'version',
```

**Why this matters**

Concurrent snapshot creation can create duplicate versions for the same showtime unless a database constraint prevents it. Duplicate snapshot versions make it unclear which seat layout should be used for booking and can cause inconsistent API responses.

**How to fix**

Add a database-level unique constraint and create snapshots inside a transaction.

**Example**

```php
$table->unique(['showtime_id', 'version']);
```

When creating the next version:

```php
DB::transaction(function () use ($showtime) {
    $latest = ShowtimeSeatLayoutSnapshot::where('showtime_id', $showtime->id)
        ->lockForUpdate()
        ->max('version');

    // create next version
});
```

---

### Issue #4

**Severity:** Medium  
**Category:** Data Integrity / Checksum Correctness  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:14-15`

**Problem**

`layout_data` and `checksum` are both mass assignable, but the model does not derive or verify that the checksum matches the layout.

```php
'layout_data',
'checksum',
```

**Why this matters**

A checksum field is only useful if it is generated from canonical data and verified before persistence or read use. If clients or arbitrary services can set both values, the checksum does not protect integrity.

**How to fix**

Generate the checksum server-side from canonical JSON and reject mismatches.

**Example**

```php
$canonical = json_encode($layoutData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$checksum = hash('sha256', $canonical);
```

Do not accept `checksum` from external input.

---

### Issue #5

**Severity:** Medium  
**Category:** Validation / Schema Safety  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:19-22`

**Problem**

`layout_data` is cast to JSON, but there is no visible structure validation.

```php
protected $casts = [
    'layout_data' => 'json',
    'version' => 'integer',
];
```

**Why this matters**

Seat layout data must have a strict schema. Invalid rows, duplicate seat coordinates, missing seat IDs, invalid seat types, or malformed rows can cause broken seat maps and incorrect booking availability.

**How to fix**

Validate layout data before persistence with a dedicated validator/value object.

**Example**

```php
final class SeatLayoutSnapshotData
{
    public function __construct(public readonly array $rows)
    {
        // validate row/column uniqueness, seat identifiers, seat types, disabled seats, etc.
    }
}
```

Use an Eloquent custom cast instead of a raw JSON cast for production-critical structure.

---

### Issue #6

**Severity:** Medium  
**Category:** Laravel Best Practice / Type Safety  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:20`

**Problem**

The model uses the `json` cast:

```php
'layout_data' => 'json',
```

**Why this matters**

Laravel's `array` cast is the more common choice when application code expects an array. A raw JSON cast can make expectations less obvious and still does not provide schema safety.

**How to fix**

Use an explicit custom cast or at least an array cast:

```php
'layout_data' => 'array',
```

For production booking state, prefer a custom cast/value object that validates the shape.

---

### Issue #7

**Severity:** Medium  
**Category:** Authorization / Lifecycle Safety  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:24-27`

**Problem**

The model links snapshots to showtimes:

```php
public function showtime(): BelongsTo
{
    return $this->belongsTo(Showtime::class);
}
```

But there is no lifecycle guard preventing snapshot deletion or reassignment while a showtime has holds, orders, or tickets.

**Why this matters**

Deleting or reassigning snapshots used by active bookings can make the booking system unable to reconstruct the seat layout used at purchase time.

**How to fix**

Forbid deletion once created, or only allow deletion before a showtime is published and before any dependent booking state exists.

**Example**

```php
protected static function booted(): void
{
    static::deleting(function (): void {
        throw new LogicException('Seat layout snapshots cannot be deleted.');
    });
}
```

Also enforce referential integrity with foreign keys.

---

### Issue #8

**Severity:** Low  
**Category:** Laravel Best Practice / Factory Support  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:5-8`

**Problem**

The model does not use `HasFactory`.

```php
use Illuminate\Database\Eloquent\Model;
```

```php
class ShowtimeSeatLayoutSnapshot extends Model
```

**Why this matters**

Factories are useful for testing showtime booking, seat layout rendering, snapshot versioning, and concurrency behavior.

**How to fix**

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShowtimeSeatLayoutSnapshot extends Model
{
    use HasFactory;
}
```

---

### Issue #9

**Severity:** Low  
**Category:** Maintainability / Static Analysis  
**Location:** `app/Models/ShowtimeSeatLayoutSnapshot.php:12-22`

**Problem**

The model properties are untyped.

```php
protected $fillable = [
```

```php
protected $casts = [
```

**Why this matters**

Typed properties improve static analysis and make model metadata easier to reason about.

**How to fix**

Where supported by the Laravel/PHP version in use:

```php
protected array $fillable = [
    'showtime_id',
    'layout_data',
    'checksum',
    'version',
];

protected array $casts = [
    'layout_data' => 'array',
    'version' => 'integer',
];
```

---

## Recommendations

### Immediate

1. Stop accepting `layout_data`, `checksum`, `version`, and `showtime_id` through broad mass assignment.
2. Make snapshots immutable or append-only.
3. Add a unique database constraint on `showtime_id` + `version`.
4. Generate checksums server-side from canonical layout data.

### Short Term

5. Add strict schema validation for `layout_data`.
6. Add lifecycle guards preventing deletion/reassignment once created.
7. Add transaction and row-locking around snapshot version creation.

### Long Term

8. Replace raw JSON/array handling with a value object or custom Eloquent cast.
9. Add factories and concurrency tests for snapshot creation and booking flows.

---

## Summary

`ShowtimeSeatLayoutSnapshot.php` is simple, but it models critical booking state. The main production risk is that mutable, mass-assignable snapshot data can corrupt the seat map used for selling tickets. Snapshot rows should be generated server-side, checksum-protected, immutable, versioned safely, and constrained at the database level.

**Main concerns:**

- Booking-critical snapshot fields are mass assignable.
- Snapshot immutability is not enforced.
- No visible uniqueness rule for `showtime_id` + `version`.
- Checksum is not derived or verified in the model.
- `layout_data` has no schema validation.
- No lifecycle guard prevents deletion or mutation after bookings exist.

**Status:** Request changes before production acceptance.