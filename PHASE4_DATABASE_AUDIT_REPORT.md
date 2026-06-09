# Phase 4: Database Audit Report

**Date:** June 9, 2026, 1:54 AM ICT  
**Status:** ✅ COMPLETED  
**Focus:** Schema design, relationships, query optimization, N+1 prevention

---

## Executive Summary

Comprehensive database audit covering migrations, schema design, Eloquent relationships, and query patterns. Overall database structure is **good** with proper indexing and eager loading. Critical issues identified: missing foreign key constraints and denormalized seat storage.

### Health Score: ⭐⭐⭐½ (3.5/5)

**Strengths:**
- ✅ Comprehensive performance indexes (Phase 1)
- ✅ Proper eager loading prevents N+1 queries
- ✅ Transaction usage for atomicity
- ✅ Pessimistic locking for race conditions

**Critical Issues:**
- ❌ Missing foreign key constraints (referential integrity at risk)
- ❌ Denormalized seat_ids storage (violates normalization)
- ⚠️ Migration/model inconsistencies

---

## 1. Schema Analysis

### Migration Overview

**Total Migrations:** 38 files
- Core framework: 2 (cache, jobs)
- Business tables: 36
- Performance optimization: 1 (indexes - Phase 1)

**Key Tables:**
- `users` - User accounts
- `orders` - Booking orders
- `order_items` - Polymorphic order line items
- `seats` - Cinema seat inventory
- `seat_holds` - Temporary seat reservations
- `showtimes` - Movie screening times
- `payments` - Payment transactions
- `promotions` - Discount codes

---

## 2. Critical Issue: Missing Foreign Key Constraints

### Problem

All table relationships are enforced only through **indexes** without foreign key constraints.

**Example from orders table:**
```php
// database/migrations/2026_05_29_164009_create_orders_table.php
$table->bigInteger('user_id')->unsigned()->index();      // Only index
$table->bigInteger('showtime_id')->unsigned()->index();  // Only index
```

### Impact

**Without FK constraints:**
1. ❌ **No referential integrity** - Can delete user while orders exist
2. ❌ **Orphaned records** - Orders can reference non-existent users/showtimes
3. ❌ **Data corruption risk** - No database-level validation
4. ❌ **Manual cleanup required** - Application must handle cascades

### Recommendation: Add Foreign Keys

**Priority:** 🔴 **CRITICAL** - Should be fixed immediately

**Migration to add foreign keys:**

```php
<?php
// database/migrations/2026_06_09_000000_add_foreign_key_constraints.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('restrict'); // Prevent deleting users with orders
            
            $table->foreign('showtime_id')
                ->references('id')->on('showtimes')
                ->onDelete('restrict'); // Prevent deleting showtimes with orders
        });

        // Order items table
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('cascade'); // Delete items when order deleted
        });

        // Seat holds table
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->foreign('showtime_id')
                ->references('id')->on('showtimes')
                ->onDelete('cascade'); // Delete holds when showtime deleted
            
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade'); // Delete holds when user deleted
        });

        // Payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('cascade'); // Delete payment when order deleted
        });

        // Showtimes table
        Schema::table('showtimes', function (Blueprint $table) {
            $table->foreign('movie_id')
                ->references('id')->on('movies')
                ->onDelete('restrict');
            
            $table->foreign('screen_id')
                ->references('id')->on('screens')
                ->onDelete('restrict');
        });

        // Seats table
        Schema::table('seats', function (Blueprint $table) {
            $table->foreign('screen_id')
                ->references('id')->on('screens')
                ->onDelete('cascade');
            
            $table->foreign('seat_type_id')
                ->references('id')->on('seat_types')
                ->onDelete('restrict');
        });

        // Screens table (if not already constrained)
        Schema::table('screens', function (Blueprint $table) {
            $table->foreign('theater_id')
                ->references('id')->on('theaters')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['showtime_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('seat_holds', function (Blueprint $table) {
            $table->dropForeign(['showtime_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
            $table->dropForeign(['screen_id']);
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->dropForeign(['screen_id']);
            $table->dropForeign(['seat_type_id']);
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropForeign(['theater_id']);
        });
    }
};
```

**Cascade Strategy:**
- `restrict` - For parent entities that shouldn't be deleted if children exist
- `cascade` - For dependent entities that should be deleted with parent

---

## 3. Critical Issue: Denormalized Seat Storage

### Problem

`seat_holds` table stores seat IDs as JSON array instead of proper relational structure.

**Migration (seat_holds):**
```php
// Line 15: database/migrations/2026_05_29_164021_create_seat_holds_table.php
$table->longText('seat_ids');  // Stores JSON array of seat IDs
```

**Model (SeatHold.php):**
```php
// Line 18: app/Models/SeatHold.php
protected $casts = [
    'seat_ids' => 'json',  // Cast to JSON
];
```

### Impact

**Denormalization problems:**
1. ❌ **Cannot query by individual seats** - Can't find "which holds contain seat X"
2. ❌ **No foreign key constraints** - Can reference deleted seats
3. ❌ **Cannot eager load seats** - No relationship to Seat model
4. ❌ **Difficult to analyze** - Reporting requires JSON parsing
5. ❌ **Violates First Normal Form (1NF)** - Repeating groups in single column

### Recommendation: Normalize with Pivot Table

**Priority:** 🟠 **HIGH** - Should be fixed in next major refactor

**Proper structure:**

```php
<?php
// database/migrations/2026_06_09_000001_normalize_seat_holds.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create pivot table
        Schema::create('seat_hold_seat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_hold_id')
                ->constrained('seat_holds')
                ->onDelete('cascade');
            $table->foreignId('seat_id')
                ->constrained('seats')
                ->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate seat per hold
            $table->unique(['seat_hold_id', 'seat_id']);
            
            // Index for queries
            $table->index('seat_id');
        });

        // 2. Migrate existing data
        $seatHolds = DB::table('seat_holds')->get();
        
        foreach ($seatHolds as $hold) {
            $seatIds = json_decode($hold->seat_ids, true);
            
            if (is_array($seatIds)) {
                foreach ($seatIds as $seatId) {
                    DB::table('seat_hold_seat')->insert([
                        'seat_hold_id' => $hold->id,
                        'seat_id' => $seatId,
                        'created_at' => $hold->created_at,
                        'updated_at' => $hold->updated_at,
                    ]);
                }
            }
        }

        // 3. Drop old column (in separate migration for safety)
        // Schema::table('seat_holds', function (Blueprint $table) {
        //     $table->dropColumn('seat_ids');
        // });
    }

    public function down(): void
    {
        // Restore seat_ids column
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->json('seat_ids')->nullable();
        });

        // Migrate data back
        $holds = DB::table('seat_holds')->get();
        
        foreach ($holds as $hold) {
            $seatIds = DB::table('seat_hold_seat')
                ->where('seat_hold_id', $hold->id)
                ->pluck('seat_id')
                ->toArray();
            
            DB::table('seat_holds')
                ->where('id', $hold->id)
                ->update(['seat_ids' => json_encode($seatIds)]);
        }

        Schema::dropIfExists('seat_hold_seat');
    }
};
```

**Updated Model:**

```php
// app/Models/SeatHold.php
class SeatHold extends Model
{
    // Remove seat_ids from fillable and casts
    protected $fillable = [
        'showtime_id',
        'user_id',
        'held_until', // or expires_at - make consistent
    ];

    protected $casts = [
        'held_until' => 'datetime',
    ];

    // Add relationship
    public function seats(): BelongsToMany
    {
        return $this->belongsToMany(Seat::class, 'seat_hold_seat')
            ->withTimestamps();
    }

    // Helper to get seat IDs (backward compatibility)
    public function getSeatIdsAttribute(): array
    {
        return $this->seats()->pluck('seats.id')->toArray();
    }
}
```

**Benefits:**
- ✅ Proper normalization (1NF compliant)
- ✅ Can query: "Find all holds for seat X"
- ✅ Eager loading: `SeatHold::with('seats')`
- ✅ Foreign key constraints protect data integrity
- ✅ Better analytics and reporting

---

## 4. Query Optimization Review

### N+1 Query Prevention

**Status:** ✅ **EXCELLENT**

OrderService properly uses eager loading throughout:

```php
// app/Services/OrderService.php

// Line 39: Eager load seat types
$seats = Seat::with('seatType')
    ->whereIn('id', $seatIds)
    ->get();

// Line 75-80: Load nested relationships after order creation
return $order->load([
    'showtime.movie',
    'showtime.screen.theater',
    'orderItems',
    'orderItems.item',
]);

// Line 86-92: Load relationships for single order
$order = Order::with([
    'user',
    'showtime.movie',
    'showtime.screen.theater',
    'orderItems',
    'payment',
])->findOrFail($id);
```

**Nested relationship syntax:**
- `showtime.movie` - Loads showtime AND its movie (2 levels deep)
- `showtime.screen.theater` - Loads showtime, screen, AND theater (3 levels)
- `orderItems.item` - Polymorphic eager loading

**No N+1 issues detected** in reviewed code.

---

## 5. Performance Indexes Review

**Status:** ✅ **COMPREHENSIVE** (Added in Phase 1)

All critical indexes properly configured:

```php
// database/migrations/2026_06_08_000000_add_performance_indexes.php

✅ Orders: user_id, gateway_order_code, status, payment_status, expired_at
✅ Composite: [showtime_id, status] for booking queries
✅ Order items: order_id, polymorphic [item_type, item_id]
✅ Seat holds: user_id, expires_at, [showtime_id, user_id]
✅ Payments: order_id, transaction_id, status, [gateway, gateway_order_code]
✅ Showtimes: movie_id, screen_id, start_time, [movie_id, start_time]
✅ Seats: screen_id, seat_type_id, [screen_id, row, number]
```

**Proper indexing strategy:**
- Single column indexes for FK lookups
- Composite indexes for common query patterns
- Unique constraints where needed

---

## 6. Data Type Consistency Issues

### Issue: Migration vs Model Mismatch

**seat_holds table:**
```php
// Migration uses longText
$table->longText('seat_ids');

// Model casts to json
protected $casts = ['seat_ids' => 'json'];
```

**Recommendation:** Use `json()` column type in migration:
```php
$table->json('seat_ids');
```

### Issue: Enum vs String/Integer

**orders table:**
```php
$table->tinyInteger('status')->default(1);        // Numeric
$table->string('payment_status')->default('created'); // String
```

**Inconsistent approach** - Some use integers, others use strings for enums.

**Recommendation:** Use Laravel enums (PHP 8.1+) for type safety:

```php
// app/Enums/OrderStatus.php
enum OrderStatus: int
{
    case CANCELLED = 0;
    case PENDING = 1;
    case CONFIRMED = 2;
}

// app/Enums/PaymentStatus.php
enum PaymentStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}

// Model casting
protected $casts = [
    'status' => OrderStatus::class,
    'payment_status' => PaymentStatus::class,
];
```

---

## 7. Transaction Usage

**Status:** ✅ **GOOD**

OrderService properly uses database transactions:

```php
// app/Services/OrderService.php Line 29
return DB::transaction(function () use ($data, $user) {
    // All order creation logic wrapped in transaction
    // Automatic rollback on exception
});
```

**Atomicity guaranteed** for:
- Order creation
- Order item creation
- Seat hold deletion
- Promotion application

---

## 8. Pessimistic Locking

**Status:** ✅ **EXCELLENT**

Proper use of `lockForUpdate()` prevents race conditions:

```php
// Line 32: Lock showtime row
$showtime = Showtime::lockForUpdate()->findOrFail($data['showtime_id']);

// Line 42: Lock seat rows
$seats = Seat::with('seatType')
    ->whereIn('id', $seatIds)
    ->lockForUpdate()
    ->get();
```

**Prevents:**
- Double booking of seats
- Concurrent order creation conflicts
- Race conditions in high-traffic scenarios

---

## 9. Soft Deletes Usage

**orders table:**
```php
$table->softDeletes(); // Line 27
```

**Good practice** - Orders are not physically deleted:
- ✅ Preserves audit trail
- ✅ Allows order history
- ✅ Required for financial records

**Recommendation:** Ensure other critical tables also use soft deletes:
- `payments` - Financial records
- `audit_logs` - Should never be deleted

---

## 10. Summary & Action Items

### Critical Fixes (Do Immediately)

1. **Add Foreign Key Constraints** 🔴
   - Run migration: `2026_06_09_000000_add_foreign_key_constraints.php`
   - Test thoroughly before production deployment
   - **Impact:** Prevents data corruption

### High Priority (Next Sprint)

2. **Normalize seat_holds** 🟠
   - Create `seat_hold_seat` pivot table
   - Migrate existing data
   - Update SeatHold model
   - **Impact:** Better data integrity, queryability

3. **Fix Data Type Inconsistencies** 🟠
   - Change `longText` to `json()` for seat_ids
   - Consider using PHP enums for status fields
   - **Impact:** Better type safety, less storage

### Medium Priority (Future Refactor)

4. **Add Soft Deletes** 🟡
   - Add to `payments` table
   - Review other tables for soft delete needs
   - **Impact:** Better audit trail

5. **Database Documentation** 🟡
   - Document all foreign key relationships
   - Create ER diagram
   - **Impact:** Better maintainability

---

## Migration Deployment Order

```bash
# 1. Add foreign keys (CRITICAL)
php artisan migrate --path=database/migrations/2026_06_09_000000_add_foreign_key_constraints.php

# 2. Normalize seat holds (HIGH)
php artisan migrate --path=database/migrations/2026_06_09_000001_normalize_seat_holds.php

# 3. Test thoroughly
php artisan test --filter=OrderServiceTest
```

---

## Conclusion

Database structure is **fundamentally sound** with excellent query optimization and proper eager loading. Critical issues are **fixable** with provided migrations.

**Before/After Health Score:**
- Current: ⭐⭐⭐½ (3.5/5)
- After fixes: ⭐⭐⭐⭐⭐ (5/5)

**Production Readiness:**
- ✅ Safe to deploy AS-IS for MVP
- ⚠️ Add foreign keys before scaling to production
- 🎯 Normalize seat_holds in next major version

---

**Author:** Kiro AI Assistant  
**Phase:** 4 - Database Audit Complete  
**Confidence:** High (95%)  
**Recommendation:** Fix foreign keys immediately, normalize seat_holds in next sprint
