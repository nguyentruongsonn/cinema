# Payment Flow Refactor & Critical Fixes - Implementation Plan

**Document Version:** 1.0  
**Created:** 2026-06-11  
**Status:** Draft  
**Priority:** CRITICAL  

---

## Executive Summary

Hệ thống booking/payment hiện tại có các lỗi nghiêm trọng có thể gây mất tiền và bán trùng ghế:

1. **CRITICAL:** Payment flow không validate SeatHold → client có thể bypass seat lock
2. **CRITICAL:** Có thể bán trùng ghế do OrderItem chưa được tạo khi order pending
3. **CRITICAL:** Không có payment ledger → khó đối soát PayOS
4. **HIGH:** Flow `/orders` và `/payments` song song → logic duplicate, risk cao
5. **HIGH:** Inventory combo có thể âm stock

Plan này chia thành 5 phases ưu tiên theo mức độ critical.

---

## Phase 1: Chặn Lỗi Mất Tiền (CRITICAL)

**Timeline:** 3-5 days  
**Priority:** P0 - Must fix before production  
**Risk Level:** HIGH if not fixed  

### Objectives

- Chặn bypass seat lock
- Đảm bảo seat thuộc screen đúng
- Ngăn bán trùng ghế
- Đảm bảo payment flow atomic

### Tasks

#### 1.1. Thêm Validation SeatHold vào PaymentService

**File:** `app/Services/PaymentService.php`

**Changes Required:**

```php
private function validateSeatHold(User $user, Showtime $showtime, array $seatIds): void
{
    // 1. Validate seats belong to showtime's screen
    $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
    sort($seatIds);

    $seats = Seat::query()
        ->whereIn('id', $seatIds)
        ->where('screen_id', $showtime->screen_id)
        ->lockForUpdate()
        ->get();

    if ($seats->count() !== count($seatIds)) {
        throw new \RuntimeException('Một hoặc nhiều ghế không thuộc phòng chiếu này.');
    }

    // 2. Validate user has valid hold for these seats
    $hold = SeatHold::query()
        ->valid()
        ->where('user_id', $user->id)
        ->where('showtime_id', $showtime->id)
        ->lockForUpdate()
        ->first();

    if (!$hold) {
        throw new \RuntimeException('Phiên giữ ghế đã hết hạn. Vui lòng chọn lại ghế.');
    }

    $heldSeatIds = array_values(array_unique(array_map('intval', (array) $hold->seat_ids)));
    sort($heldSeatIds);

    if ($seatIds !== $heldSeatIds) {
        throw new \RuntimeException('Danh sách ghế không khớp với phiên giữ ghế.');
    }

    // 3. Check seats not already booked
    $bookedSeatIds = OrderItem::query()
        ->where('item_type', Seat::class)
        ->whereIntegerInRaw('item_id', $seatIds)
        ->whereHas('order', function ($query) use ($showtime) {
            $query->where('showtime_id', $showtime->id)
                ->whereIn('status', [1, 2]); // pending + confirmed
        })
        ->pluck('item_id')
        ->all();

    if (!empty($bookedSeatIds)) {
        throw new \RuntimeException('Một số ghế đã được đặt bởi người dùng khác.');
    }
}
```

**Call validateSeatHold() in initiate():**

```php
public function initiate(User $user, Showtime $showtime, array $data, string $baseUrl): array
{
    return DB::transaction(function () use ($user, $showtime, $data, $baseUrl) {
        // Extract seat IDs
        $seatIds = collect($data['seats'] ?? [])
            ->map(fn($seat) => (int)($seat['id'] ?? $seat))
            ->all();

        // VALIDATE SEAT HOLD
        $this->validateSeatHold($user, $showtime, $seatIds);

        // Continue with existing pricing logic...
        $snapshot = $this->pricingService->buildSnapshot(...);
        
        // Rest of method...
    });
}
```

**Estimated Time:** 4 hours  
**Testing Required:** Yes (critical path)  

---

#### 1.2. Wrap PaymentService::initiate() in DB Transaction

**File:** `app/Services/PaymentService.php`

**Current Issue:** Method không wrap toàn bộ trong transaction

**Required Changes:**

- Ensure entire `initiate()` runs in single transaction
- Lock order creation
- Lock seat validation
- Atomic order + gateway creation

**Testing Points:**

- Concurrent payment requests with same seats
- Payment request with expired hold
- Payment request with wrong seat IDs

**Estimated Time:** 2 hours  
**Testing Required:** Yes  

---

#### 1.3. Add Integration Tests

**File:** `tests/Feature/Payment/PaymentSecurityTest.php` (new)

**Test Cases:**

```php
test('cannot create payment without seat hold')
test('cannot create payment with expired hold')
test('cannot create payment with different seats than hold')
test('cannot create payment with seats from wrong screen')
test('cannot create payment for already booked seats')
test('concurrent payment requests fail appropriately')
```

**Estimated Time:** 6 hours  
**Testing Required:** Self-testing  

---

#### 1.4. Update API Documentation

**Files:**
- `README.md`
- `PAYMENT_SYSTEM_SUMMARY.md`

**Changes:**

- Document seat hold requirement
- Update payment flow diagram
- Add error codes/messages

**Estimated Time:** 2 hours  

---

### Phase 1 Checklist

- [ ] Add `validateSeatHold()` to PaymentService
- [ ] Wrap `initiate()` in DB transaction
- [ ] Write integration tests
- [ ] Run existing tests - ensure no regression
- [ ] Manual testing on staging
- [ ] Update documentation
- [ ] Code review
- [ ] Deploy to staging
- [ ] QA sign-off
- [ ] Deploy to production

**Total Estimated Time:** 14 hours (2 days)  

---

## Phase 2: Payment Ledger & Audit Trail (CRITICAL)

**Timeline:** 3-4 days  
**Priority:** P0 - Required for reconciliation  
**Risk Level:** MEDIUM if not fixed  

### Objectives

- Tạo payment records khi initiate
- Update payment khi webhook success/fail
- Có transaction_code từ PayOS
- Có audit trail đầy đủ

### Tasks

#### 2.1. Review Payment Model & Migration

**Files:**
- `app/Models/Payment.php`
- `database/migrations/*_create_payments_table.php`

**Verify Schema:**

```sql
CREATE TABLE payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    method VARCHAR(50) NOT NULL DEFAULT 'payos',
    transaction_code VARCHAR(255) NULL,
    gateway_order_code VARCHAR(255) NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payload JSON NULL,
    paid_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_gateway_order (gateway_order_code),
    INDEX idx_order_id (order_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);
```

**Status Values:** pending, processing, success, failed, cancelled, refunded

**Estimated Time:** 2 hours  

---

#### 2.2. Create Payment on Order Creation

**File:** `app/Services/PaymentService.php`

**In initiate() method:**

```php
// After creating order
$payment = Payment::create([
    'order_id' => $order->id,
    'user_id' => $user->id,
    'method' => 'payos',
    'gateway_order_code' => $gatewayOrderCode,
    'amount' => $order->total_amount,
    'status' => 'pending',
    'payload' => [
        'request_data' => $safePayloadForLogging,
        'showtime_id' => $showtime->id,
        'seat_ids' => $seatIds,
        'created_at' => now()->toISOString(),
    ],
]);
```

**Estimated Time:** 2 hours  

---

#### 2.3. Update Payment on Webhook

**File:** `app/Services/OrderFulfillmentService.php`

**In finalize() method:**

```php
// After updating order
Payment::where('order_id', $order->id)
    ->update([
        'status' => 'success',
        'transaction_code' => $webhookData['code'] ?? null,
        'paid_at' => now(),
        'payload->webhook_data' => $webhookData,
        'updated_at' => now(),
    ]);

// Or if using updateOrCreate:
Payment::updateOrCreate(
    ['order_id' => $order->id],
    [
        'user_id' => $order->user_id,
        'method' => 'payos',
        'transaction_code' => $webhookData['code'] ?? null,
        'gateway_order_code' => $gatewayOrderCode,
        'amount' => $order->total_amount,
        'status' => 'success',
        'payload' => array_merge(
            $existingPayload ?? [],
            ['webhook_data' => $webhookData]
        ),
        'paid_at' => now(),
    ]
);
```

**Estimated Time:** 3 hours  

---

#### 2.4. Add Payment Reconciliation Tools

**File:** `app/Console/Commands/ReconcilePayments.php` (new)

**Purpose:** Daily reconciliation với PayOS

**Features:**

- Check payments pending > 1 hour
- Mark as failed/expired
- Report discrepancies
- Generate reconciliation report

**Estimated Time:** 4 hours  

---

### Phase 2 Checklist

- [ ] Review/update payments table schema
- [ ] Add payment creation in PaymentService::initiate()
- [ ] Add payment update in OrderFulfillmentService::finalize()
- [ ] Handle payment failed cases
- [ ] Create reconciliation command
- [ ] Write unit tests for payment updates
- [ ] Test reconciliation command
- [ ] Update monitoring/alerts
- [ ] Documentation
- [ ] Deploy

**Total Estimated Time:** 11 hours (1.5 days)  

---

## Phase 3: Order Fulfillment Enhancement (HIGH)

**Timeline:** 4-5 days  
**Priority:** P1 - Required for complete flow  
**Risk Level:** MEDIUM  

### Objectives

- Tạo OrderItems sau webhook paid
- Tạo Tickets cho từng seat
- Release SeatHolds sau paid
- Chặn stock âm cho products
- Đảm bảo atomic fulfillment

### Tasks

#### 3.1. Enhance OrderFulfillmentService::finalize()

**File:** `app/Services/OrderFulfillmentService.php`

**Current Issues:**

- Không release seat holds
- Product stock decrement không safe
- Promotion increment không có limit check

**Required Enhancements:**

```php
public function finalize(string $gatewayOrderCode, array $webhookData): array
{
    return DB::transaction(function () use ($gatewayOrderCode, $webhookData) {
        // 1. Lock and validate order
        $order = $this->findByGatewayCode($gatewayOrderCode, lock: true);
        
        if ($order->isPaid()) {
            return ['already_processed' => true];
        }

        // 2. Validate seats still available
        $this->validateSeatsStillAvailable($order);

        // 3. Lock and decrement product stock safely
        $this->decrementProductStockSafely($order);

        // 4. Update order
        $order->update([...]);

        // 5. Create order items
        $this->createOrderItems($order);

        // 6. Create tickets
        $this->createTickets($order);

        // 7. Release seat holds
        $this->releaseSeatHolds($order);

        // 8. Update payment record
        $this->updatePaymentRecord($order, $webhookData);

        // 9. Increment promotion usage
        $this->incrementPromotionUsage($order);

        // 10. Deduct user points
        $this->deductUserPoints($order);

        // 11. Broadcast events
        broadcast(new OrderPaid($order));

        return ['order' => $order];
    });
}
```

**Estimated Time:** 8 hours  

---

#### 3.2. Create Ticket Model & Migration

**Files:**
- `database/migrations/2026_06_11_create_tickets_table.php`
- `app/Models/Ticket.php`

**Schema:**

```sql
CREATE TABLE tickets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    showtime_id BIGINT NOT NULL,
    seat_id BIGINT NOT NULL,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    qr_code TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'valid',
    checked_in_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_user_id (user_id),
    INDEX idx_ticket_code (ticket_code),
    INDEX idx_status (status)
);
```

**Status Values:** valid, used, cancelled, refunded

**Estimated Time:** 3 hours  

---

#### 3.3. Implement Safe Product Stock Decrement

**File:** `app/Services/OrderFulfillmentService.php`

**Method:**

```php
private function decrementProductStockSafely(Order $order): void
{
    $products = $order->snapshot['products'] ?? [];

    foreach ($products as $productData) {
        $product = Product::query()
            ->whereKey($productData['id'])
            ->lockForUpdate()
            ->firstOrFail();

        $quantity = (int) $productData['quantity'];

        if ($product->stock < $quantity) {
            throw new \RuntimeException(
                "Sản phẩm {$product->name} không đủ tồn kho."
            );
        }

        $product->decrement('stock', $quantity);
    }
}
```

**Estimated Time:** 2 hours  

---

#### 3.4. Implement Ticket Creation

**File:** `app/Services/OrderFulfillmentService.php`

**Method:**

```php
private function createTickets(Order $order): void
{
    $seats = $order->snapshot['seats'] ?? [];

    foreach ($seats as $seatData) {
        Ticket::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'showtime_id' => $order->showtime_id,
            'seat_id' => $seatData['id'],
            'ticket_code' => $this->generateTicketCode(),
            'qr_code' => $this->generateQRCode($order, $seatData),
            'status' => 'valid',
        ]);
    }
}

private function generateTicketCode(): string
{
    return 'TKT-' . strtoupper(Str::random(10));
}
```

**Estimated Time:** 3 hours  

---

#### 3.5. Release Seat Holds After Payment

**File:** `app/Services/OrderFulfillmentService.php`

**Method:**

```php
private function releaseSeatHolds(Order $order): void
{
    $seatIds = collect($order->snapshot['seats'] ?? [])
        ->pluck('id')
        ->all();

    SeatHold::query()
        ->where('user_id', $order->user_id)
        ->where('showtime_id', $order->showtime_id)
        ->get()
        ->each(function (SeatHold $hold) use ($seatIds) {
            $holdSeatIds = (array) $hold->seat_ids;
            if (!empty(array_intersect($holdSeatIds, $seatIds))) {
                $hold->delete();
            }
        });
}
```

**Estimated Time:** 2 hours  

---

### Phase 3 Checklist

- [ ] Create tickets table migration
- [ ] Create Ticket model
- [ ] Implement createTickets()
- [ ] Implement releaseSeatHolds()
- [ ] Implement decrementProductStockSafely()
- [ ] Update finalize() to call all new methods
- [ ] Write tests for each method
- [ ] Integration test full fulfillment
- [ ] Update seeder if needed
- [ ] Documentation
- [ ] Deploy

**Total Estimated Time:** 18 hours (2.5 days)  

---

## Phase 4: Unify Order Creation Flow (HIGH)

**Timeline:** 3-4 days  
**Priority:** P1 - Reduce maintenance burden  
**Risk Level:** LOW  

### Objectives

- Remove or deprecate duplicate `/orders` endpoint
- Single source of truth for booking flow
- Clear documentation

### Decision Points

**Option A: Deprecate `/orders` completely**

- Remove `Route::post('/orders', ...)`
- Return 410 Gone for any requests
- Update all clients

**Option B: Redirect `/orders` to use PaymentService**

- Keep endpoint
- Refactor OrderService to call PaymentService internally
- Maintain backward compatibility

**Recommendation:** Option A (clean break)

### Tasks

#### 4.1. Audit Current Usage

**Action:** Search codebase for `/orders` API calls

```bash
grep -r "POST.*\/orders" --include="*.js" --include="*.php"
grep -r "api\/v1\/orders" --include="*.js" --include="*.php"
```

**Check:**

- Frontend calls
- Mobile app calls (if any)
- Third-party integrations
- Test files

**Estimated Time:** 2 hours  

---

#### 4.2. Deprecate OrderController@store

**File:** `app/Http/Controllers/OrderController.php`

**Option A - Return 410:**

```php
public function store(StoreOrderRequest $request)
{
    return $this->errorResponse(
        'This endpoint has been deprecated. Please use POST /api/v1/payments instead.',
        410
    );
}
```

**Option B - Proxy to PaymentService:**

```php
public function store(StoreOrderRequest $request)
{
    // Validate request has showtime
    $data = $request->validated();
    $showtime = Showtime::findOrFail($data['showtime_id']);
    
    // Call payment service
    $result = $this->paymentService->initiate(
        $request->user(),
        $showtime,
        $data,
        url('')
    );
    
    return $this->successResponse($result, 'Order created via payment flow');
}
```

**Estimated Time:** 2 hours  

---

#### 4.3. Update Documentation

**Files:**
- `README.md`
- `ARCHITECTURE.md`
- `BOOKING_FLOW_TEST_ANALYSIS.md`

**Changes:**

- Document single flow
- Remove references to old flow
- Add migration guide

**Estimated Time:** 3 hours  

---

### Phase 4 Checklist

- [ ] Audit current usage of `/orders`
- [ ] Decide deprecation strategy
- [ ] Update OrderController
- [ ] Update route definitions
- [ ] Update tests
- [ ] Update documentation
- [ ] Notify stakeholders
- [ ] Monitor logs after deployment
- [ ] Remove dead code after grace period

**Total Estimated Time:** 7 hours (1 day)  

---

## Phase 5: User Experience & Admin Tools (MEDIUM)

**Timeline:** 5-7 days  
**Priority:** P2 - Nice to have  
**Risk Level:** LOW  

### Objectives

- Complete My Tickets page
- Admin payment reconciliation dashboard
- Webhook event logging
- Scheduled cleanup jobs

### Tasks

#### 5.1. Complete ProfileController@tickets

**File:** `app/Http/Controllers/ProfileController.php`

```php
public function tickets(Request $request): View
{
    $tickets = Ticket::query()
        ->where('user_id', Auth::id())
        ->with([
            'order',
            'showtime.movie',
            'showtime.screen.cinema',
            'seat.seatType',
        ])
        ->when($request->query('status'), fn($q, $status) => 
            $q->where('status', $status)
        )
        ->latest()
        ->paginate(15);

    return view('users.tickets.index', compact('tickets'));
}
```

**Estimated Time:** 3 hours  

---

#### 5.2. Update Tickets Blade View

**File:** `resources/views/users/tickets/index.blade.php`

**Display:**

- Ticket list
- Movie poster
- Showtime info
- Seat info
- QR code
- Status badge

**Estimated Time:** 4 hours  

---

#### 5.3. Create Webhook Event Log

**Migration:** `create_webhook_events_table.php`

**Model:** `WebhookEvent.php`

**Purpose:** Audit all webhook calls

**Estimated Time:** 3 hours  

---

#### 5.4. Scheduled Cleanup Jobs

**File:** `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // Expire old holds every minute
    $schedule->call(function () {
        SeatHold::query()->expired()->delete();
    })->everyMinute();

    // Expire pending orders every 5 minutes
    $schedule->call(function () {
        app(OrderExpirationService::class)->expirePendingOrders();
    })->everyFiveMinutes();

    // Daily payment reconciliation
    $schedule->command('payments:reconcile')
        ->daily()
        ->at('02:00');
}
```

**Estimated Time:** 2 hours  

---

### Phase 5 Checklist

- [ ] Implement ProfileController@tickets with queries
- [ ] Update tickets Blade view
- [ ] Create webhook events table
- [ ] Log all webhook events
- [ ] Create scheduled jobs
- [ ] Test scheduled jobs
- [ ] Admin reconciliation dashboard
- [ ] Documentation

**Total Estimated Time:** 12 hours (1.5 days)  

---

## Testing Strategy

### Unit Tests

- PricingService validation
- PaymentService seat hold checks
- OrderFulfillmentService each method
- Ticket generation logic

### Integration Tests

- Full payment flow
- Webhook handling
- Concurrent bookings
- Stock depletion
- Hold expiration

### Manual QA Checklist

- [ ] Select seats → lock → payment → success
- [ ] Try bypass seat lock → blocked
- [ ] Try wrong seat IDs → blocked
- [ ] Concurrent users same seat → one succeeds
- [ ] Hold expires during payment → fail gracefully
- [ ] Product out of stock → fail gracefully
- [ ] Webhook retry → idempotent
- [ ] My Tickets displays correctly
- [ ] QR codes generated
- [ ] Payment reconciliation report accurate

---

## Rollback Plan

### Phase 1 Rollback

If validation breaks existing flow:

1. Revert PaymentService changes
2. Deploy previous version
3. Hot-fix specific validation
4. Re-test
5. Re-deploy

**Rollback Time:** 15 minutes

### Phase 2 Rollback

Payment table changes are additive:

- Remove payment creation calls
- Payments table remains (no data loss)
- Orders still work

**Rollback Time:** 10 minutes

### Phase 3 Rollback

- Tickets optional
- Can disable ticket creation
- Orders still complete

**Rollback Time:** 10 minutes

---

## Success Metrics

### Technical Metrics

- Zero duplicate seat bookings
- 100% payment reconciliation accuracy
- < 1% failed payments due to validation
- < 500ms payment creation time
- Zero stock going negative

### Business Metrics

- User trust in seat selection
- Accurate revenue reporting
- Reduced support tickets for "lost payment"
- Clean audit trail for disputes

---

## Risk Matrix

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Validation breaks existing bookings | Medium | High | Thorough testing, staged rollout |
| Performance degradation | Low | Medium | Load testing, query optimization |
| Payment gateway timeout | Low | High | Async processing, retry logic |
| Data migration issues | Low | Medium | Backup before deploy |
| User confusion during transition | Medium | Low | Clear error messages |

---

## Communication Plan

### Stakeholders

- **Engineering:** Daily standup updates
- **QA:** Test plan shared Phase 1
- **Product:** Weekly progress review
- **Support:** Training before Phase 5

### Status Updates

- Daily: Commit messages
- Weekly: Progress report to PM
- Milestone: Demo to stakeholders

---

## Appendix

### Related Documents

- `PAYMENT_SYSTEM_SUMMARY.md`
- `BOOKING_FLOW_TEST_ANALYSIS.md`
- `ARCHITECTURE.md`

### Key Files

**Backend:**
- `app/Services/PaymentService.php`
- `app/Services/OrderFulfillmentService.php`
- `app/Services/PricingService.php`
- `app/Services/SeatService.php`

**Frontend:**
- `public/js/pages/booking.js`

**Database:**
- `orders`
- `payments`
- `tickets`
- `seat_holds`
- `order_items`

---

**END OF IMPLEMENTATION PLAN**