# 🎯 PHASE 3: BOOKING SYSTEM - ACTION PLAN
**Start Date**: 2026-06-03  
**Estimated Duration**: 11 days  
**Priority**: CRITICAL PATH TO MVP

---

## 📊 CURRENT STATUS ASSESSMENT

### ✅ ALREADY COMPLETE (Backend Foundation)
- **SeatController**: 3 endpoints (getByShowtime, lock, unlock) ✅
- **SeatService**: Full seat locking logic with DB transactions ✅
  - Auto cleanup expired holds (10 min timeout)
  - Race condition prevention with lockForUpdate()
  - Status tracking: available/booked/holding/locked
- **Models**: Seat, SeatHold, Order, OrderItem, Payment ✅
- **API Routes**: All seat/order/payment routes defined ✅

### ⚠️ PARTIAL / UNKNOWN
- **OrderController** & **OrderService**: Exists, need to verify implementation
- **PaymentController** & **PaymentService**: Exists, need to verify implementation
- **Frontend**: NO booking page yet

### ❌ NOT STARTED
- **Booking Page UI**: Interactive seat map
- **Frontend booking flow**: Seat selection → Order → Payment
- **Real-time updates**: WebSocket/Pusher
- **Order confirmation page**
- **User order history page**
- **Payment gateway integration** (VNPay)

---

## 🎯 IMPLEMENTATION PHASES

### **WEEK 1: Core Booking Flow** (Days 1-5)

#### Day 1-2: Booking Page UI & Seat Selection
**Goal**: User can view and select seats

**Tasks:**
1. **Create booking page route**
   - `routes/web.php`: Add `/booking/{showtimeId}` route
   - Controller: `BookingController@show` (create new)

2. **Create booking blade view**
   - File: `resources/views/users/booking/index.blade.php`
   - Layout: extends `layouts/app.blade.php`
   - Sections:
     - Movie info header (title, poster, showtime details)
     - Screen layout display
     - Seat map grid (10x10 from database)
     - Selected seats summary sidebar
     - Timer countdown (10 minutes)
     - Proceed to payment button

3. **Create booking CSS**
   - File: `public/css/booking.css`
   - Seat styles:
     ```css
     .seat-available { background: #28a745; }
     .seat-selected { background: #ffc107; }
     .seat-booked { background: #6c757d; }
     .seat-locked { background: #dc3545; }
     .seat-holding { background: #17a2b8; }
     ```
   - Responsive grid layout
   - Legend for seat statuses

4. **Create booking JavaScript**
   - File: `public/js/pages/booking.js`
   - Features:
     - Fetch seats from `/api/seats/showtime/{id}`
     - Render seat map grid dynamically
     - Handle seat click to select/deselect
     - Update selected seats summary
     - Calculate total price (base + surcharges)
     - Countdown timer (10 min)
     - Lock seats when user selects

**API Integration:**
```javascript
// Fetch seats
GET /api/seats/showtime/{showtimeId}
Response: { seats: [...], current_user_holds: [...] }

// Lock selected seats
POST /api/seats/lock
Body: { showtime_id, seat_ids: [1,2,3] }
Response: { hold_id, held_until, expires_in_seconds }
```

**Deliverables:**
- ✅ Booking page accessible
- ✅ Seat map displays correctly
- ✅ User can select/deselect seats
- ✅ Selected seats auto-lock via API
- ✅ Timer counts down
- ✅ Price calculation works

---

#### Day 3: Order Creation Flow
**Goal**: User can create order with selected seats

**Tasks:**
1. **Verify/Complete OrderController**
   - Check `store()` method implementation
   - Validate: user logged in, seats still held, showtime valid
   - Create order with status "pending"
   - Create order_items for each seat
   - Return order details

2. **Verify/Complete OrderService**
   - Business logic for order creation
   - Validate seat availability
   - Calculate total price (base price + seat surcharges)
   - Handle promotions/discounts (if applicable)
   - Transaction-safe order creation

3. **Frontend: Proceed to Payment**
   - Button on booking page: "Proceed to Payment"
   - Validate seats are still locked
   - Call `POST /api/orders` to create order
   - Navigate to order confirmation/payment page

**API:**
```javascript
POST /api/orders
Body: {
  showtime_id: 1,
  seat_ids: [1, 2, 3],
  hold_id: 123  // from lock response
}
Response: {
  order_id, 
  order_code,
  total_amount,
  status: "pending",
  expires_at
}
```

**Deliverables:**
- ✅ Order creation API works
- ✅ Order stored in database
- ✅ Order items linked to seats
- ✅ Frontend can create order

---

#### Day 4-5: Payment Integration (VNPay Sandbox)
**Goal**: User can pay for order via VNPay

**Tasks:**
1. **VNPay Sandbox Setup**
   - Register at VNPay sandbox: https://sandbox.vnpayment.vn/
   - Get credentials: TMN_CODE, HASH_SECRET
   - Add to `.env`:
     ```
     VNPAY_TMN_CODE=xxxxx
     VNPAY_HASH_SECRET=xxxxx
     VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
     VNPAY_RETURN_URL=http://localhost:8000/payment/callback
     ```

2. **Payment Initiation**
   - In `PaymentService->store()`:
     - Validate order exists and is pending
     - Generate VNPay payment URL with params:
       - vnp_Amount (total * 100)
       - vnp_OrderInfo
       - vnp_TxnRef (unique transaction ID)
       - vnp_ReturnUrl
       - Secure hash
     - Store payment record with status "pending"
     - Return payment URL

3. **Payment Callback Handler**
   - Route: `GET /payment/callback`
   - Controller: `PaymentController@callback`
   - Verify VNPay signature
   - Update payment status (success/failed)
   - Update order status (confirmed/cancelled)
   - Release held seats if payment fails
   - Redirect to order confirmation page

4. **Frontend: Payment Page**
   - Create `resources/views/users/payment/index.blade.php`
   - Display order summary
   - Show payment QR code (VNPay provides)
   - Payment button redirects to VNPay
   - Handle callback return

**API:**
```javascript
// Initiate payment
POST /api/payments
Body: { order_id: 1 }
Response: { 
  payment_id,
  payment_url: "https://sandbox.vnpayment.vn/...",
  qr_code_url
}

// After callback
GET /payment/callback?vnp_ResponseCode=00&...
```

**Deliverables:**
- ✅ VNPay sandbox configured
- ✅ Payment URL generation works
- ✅ User redirected to VNPay
- ✅ Callback handler updates order/payment status
- ✅ Seats released if payment fails
- ✅ Order confirmed if payment succeeds

---

### **WEEK 2: Enhancements & Polish** (Days 6-11)

#### Day 6-7: Order Confirmation & History
**Goal**: User can view order details and history

**Tasks:**
1. **Order Confirmation Page**
   - Route: `/orders/{orderCode}/confirmation`
   - Display: movie, showtime, seats, total, payment status
   - Download ticket button (PDF generation optional)
   - Share/email ticket

2. **User Order History Page**
   - Route: `/profile/orders`
   - List all user orders (tabs: all/upcoming/past)
   - Filter by status
   - Cancel pending orders (if payment not completed)

3. **Backend: Order APIs**
   - `GET /api/orders/user/me` - user's orders
   - `GET /api/orders/{id}` - order details
   - `PUT /api/orders/{id}/cancel` - cancel order

**Deliverables:**
- ✅ Confirmation page shows order details
- ✅ History page lists all orders
- ✅ User can cancel unpaid orders
- ✅ Cancelled orders release seats

---

#### Day 8-9: Real-time Seat Updates (OPTIONAL)
**Goal**: Multiple users see seat status updates in real-time

**Options:**
- **Option A**: Pusher (paid, easier)
- **Option B**: Laravel WebSockets (free, more setup)
- **Option C**: Polling (simplest, less efficient)

**Recommended for MVP**: Start with **polling** (Option C)

**Tasks (Polling Approach):**
1. Frontend polls `/api/seats/showtime/{id}` every 3-5 seconds
2. Update seat map when status changes
3. Show toast notification when seats become available/locked

**Deliverables:**
- ✅ Seat map updates automatically
- ✅ Users see others' selections in real-time
- ✅ Locked seats release after 10 min

---

#### Day 10: Testing & Bug Fixes
**Goal**: Ensure booking flow works end-to-end

**Test Scenarios:**
1. **Happy Path**:
   - Select seats → Lock → Create order → Pay → Confirm
   - Verify order status changes
   - Verify seats marked as booked

2. **Edge Cases**:
   - Expired hold (10 min timeout)
   - Payment failure
   - Double booking attempt
   - Network errors during payment

3. **Concurrent Users**:
   - Two users try to book same seats
   - Verify locking prevents double booking

**Deliverables:**
- ✅ All test scenarios pass
- ✅ Bug list documented
- ✅ Critical bugs fixed

---

#### Day 11: UI/UX Polish
**Goal**: Smooth user experience

**Tasks:**
1. Loading states (skeletons during API calls)
2. Error handling (friendly error messages)
3. Success animations (seat selection, payment success)
4. Mobile responsive refinements
5. Accessibility improvements (keyboard navigation, screen readers)
6. Performance optimization (debounce seat selection)

**Deliverables:**
- ✅ Smooth animations
- ✅ Clear error messages
- ✅ Mobile-friendly
- ✅ Accessible (WCAG 2.1 Level A minimum)

---

## 🔧 TECHNICAL REQUIREMENTS

### Backend
- **PHP**: 8.2+
- **Laravel**: 11.x
- **MySQL**: 8.0+
- **Redis**: (optional for Phase 3, can add later)

### Frontend
- **Bootstrap**: 5.3
- **JavaScript**: ES6+ (Vanilla JS, no framework)
- **Fetch API**: For AJAX requests

### External Services
- **VNPay Sandbox**: Payment gateway
- **Pusher** (optional): Real-time updates

---

## 📋 DELIVERABLES CHECKLIST

### Must-Have (MVP)
- [ ] Booking page with seat selection UI
- [ ] Seat locking mechanism (already done in backend)
- [ ] Order creation flow
- [ ] VNPay payment integration
- [ ] Order confirmation page
- [ ] User order history
- [ ] Basic error handling
- [ ] Mobile responsive

### Nice-to-Have (Post-MVP)
- [ ] Real-time seat updates (WebSocket/Pusher)
- [ ] PDF ticket download
- [ ] Email notifications
- [ ] Promotion codes
- [ ] Multiple payment methods (MoMo)
- [ ] Seat map zoom/pan
- [ ] Accessibility (WCAG 2.1 AA)

---

## 🚨 POTENTIAL BLOCKERS

### Technical Risks
1. **VNPay Integration Complexity**
   - **Mitigation**: Start with sandbox early, test thoroughly
   - **Fallback**: Mock payment for demo, real integration later

2. **Race Conditions (Double Booking)**
   - **Mitigation**: Already handled with DB transactions + lockForUpdate()
   - **Test**: Concurrent booking simulation

3. **Session/Token Expiry During Booking**
   - **Mitigation**: Extend JWT token validity, implement refresh
   - **UX**: Show login modal if token expires

4. **Mobile Performance (Large Seat Maps)**
   - **Mitigation**: Lazy load, optimize rendering
   - **Alternative**: Simplify seat map for mobile

### Business Risks
1. **Payment Gateway Approval Delays**
   - **Mitigation**: Use sandbox for demo, production keys later

2. **User Confusion (Complex Booking Flow)**
   - **Mitigation**: Clear UI, progress indicators, help text

---

## 📊 SUCCESS METRICS

### Functional
- ✅ User can complete booking in < 2 minutes
- ✅ 0 double bookings
- ✅ Payment success rate > 95% (on sandbox)
- ✅ Order cancellation works correctly

### Performance
- ✅ Seat map loads in < 1 second
- ✅ Lock API response < 500ms
- ✅ Order creation < 1 second
- ✅ Payment initiation < 2 seconds

### UX
- ✅ Mobile booking works smoothly
- ✅ Clear error messages
- ✅ Loading states prevent confusion

---

## 🎯 NEXT IMMEDIATE STEPS

**Start NOW:**

1. **Create BookingController**
   ```bash
   php artisan make:controller BookingController
   ```

2. **Add booking route to web.php**
   ```php
   Route::get('/booking/{showtime}', [BookingController::class, 'show'])->name('booking.show');
   ```

3. **Create booking view**
   ```bash
   mkdir -p resources/views/users/booking
   touch resources/views/users/booking/index.blade.php
   ```

4. **Create booking assets**
   ```bash
   touch public/css/booking.css
   touch public/js/pages/booking.js
   ```

5. **Start implementing seat map UI**

---

## 📞 NEED HELP?

**Questions to resolve before coding:**
1. Should we use Redis for seat locking? (Currently using MySQL)
2. VNPay or mock payment first?
3. Real-time updates priority? (Can defer to Week 3)
4. PDF tickets needed for MVP?

---

**Status**: Ready to start implementation  
**Next**: Create BookingController and booking page UI
