# 🔍 BÁO CÁO ĐIỀU TRA LUỒNG BOOKING

**Ngày:** 2026-07-08  
**Trạng thái:** PHÁT HIỆN NHIỀU LỖI NGHIÊM TRỌNG

---

## 📋 BƯỚC 1: SƠ ĐỒ LUỒNG HIỆN TẠI

### **Frontend Flow (Client)**

```
User clicks seat
    ↓
handleSeatClick() (booking.js:317)
    ↓
Toggle selectedSeats[] array
    ↓
Toggle CSS class (seat-selected)
    ↓
updateSummary() (booking.js:398)
    ↓
calculateTotal() (booking.js:413)
    ↓
❌ Update DOM (#summarySeats, #summaryTotal) - FAIL!
    ↓
User clicks "Tiếp tục"
    ↓
handleConfirmBooking() (booking.js:442)
    ↓
POST /api/v1/orders
    ↓
Redirect to /payment/{order.id}
```

### **Backend Flow (Server)**

```
POST /api/v1/orders
    ↓
OrderController@store (line 24)
    ↓
StoreOrderRequest validation
    ↓
OrderService->create()
    ↓
❓ Create Order in DB?
    ↓
❓ Hold seats?
    ↓
❓ Create order details?
    ↓
Return order data
```

---

## ⚠️ BƯỚC 2: PHÁT HIỆN LỖI FRONTEND

### **LỖI #1: DOM ID MISMATCH** 🔴 NGHIÊM TRỌNG

**File:** `public/js/pages/booking.js`

**Vấn đề:**
```javascript
// booking.js line 398-411
function updateSummary() {
    const summarySeats = document.getElementById('summarySeats'); // ❌ KHÔNG TỒN TẠI
    const summaryTotal = document.getElementById('totalPriceDisplay'); // ❌ SAI ID
    
    if (summarySeats) {
        summarySeats.textContent = selectedSeats.map(s => s.seat_number).join(', ');
    }
    
    if (summaryTotal) {
        summaryTotal.textContent = formatPrice(calculateTotal());
    }
}
```

**Blade Template:** `resources/views/users/booking/index.blade.php`
```html
<!-- Line 259: ID sai -->
<span id="selectedSeatsDisplay">Chưa chọn</span>

<!-- Line 276: ID đúng một phần -->
<span id="totalPriceDisplay">0đ</span>
```

**Kết quả:**
- ✅ `totalPriceDisplay` - Match (may mắn!)
- ❌ `summarySeats` không tồn tại → Không hiển thị ghế đã chọn
- ❌ User không thấy ghế nào được chọn trong summary

**Impact:** 🔴 HIGH - User experience broken

---

### **LỖI #2: THIẾU GIÁ GHẾ CƠ BẢN** 🔴 NGHIÊM TRỌNG

**File:** `public/js/pages/booking.js`

**Vấn đề:**
```javascript
// Line 92-100: Data normalization
const normalizedSeats = seats.map(seat => ({
    id: seat.id,
    row_label: seat.row,
    col_number: seat.number,
    type: seat.seat_type,
    seat_number: `${seat.row}${seat.number}`,
    status: seat.status,
    price: seat.surcharge || 0  // ❌ CHỈ LẤY SURCHARGE!
}));
```

**Logic sai:**
```
Giá thực tế = showtime.price (base) + seat.surcharge (phụ thu)
```

**Hiện tại:**
```javascript
// Line 413-430: calculateTotal()
let total = 0;
selectedSeats.forEach(seat => {
    total += parseFloat(seat.price || 0); // ❌ Chỉ cộng surcharge
});
```

**Ví dụ:**
- Showtime price: 100,000đ
- VIP surcharge: 30,000đ
- **Tổng đúng:** 130,000đ
- **Tổng hiện tại:** 30,000đ ❌

**Kết quả:**
- Frontend tính giá SAI
- Backend phải tính lại (nếu có)
- Mất đồng bộ giữa frontend/backend

**Impact:** 🔴 CRITICAL - Sai logic nghiệp vụ

---

### **LỖI #3: KHÔNG HOLD SEAT** 🔴 NGHIÊM TRỌNG

**File:** `public/js/pages/booking.js`

**Vấn đề:**
```javascript
// Line 317-334: handleSeatClick()
function handleSeatClick(seatId) {
    const seat = seatMapData.find(s => s.id === seatId);
    
    // Toggle local state only
    const index = selectedSeats.findIndex(s => s.id === seatId);
    if (index !== -1) {
        selectedSeats.splice(index, 1);
    } else {
        selectedSeats.push(seat);
    }
    
    // Toggle CSS
    seatElement.classList.toggle('seat-selected');
    
    updateSummary(); // ❌ KHÔNG GỌI API HOLD SEAT!
}
```

**Không có:**
- ❌ API call để hold seat
- ❌ Lock seat trong database
- ❌ Prevent race condition (2 user chọn cùng ghế)
- ❌ Countdown timer thật

**Kết quả:**
- User A chọn ghế → Chỉ lưu local state
- User B chọn cùng ghế → Cũng được
- Cả 2 submit → Conflict!

**Impact:** 🔴 CRITICAL - Race condition

---

### **LỖI #4: COUNTDOWN TIMER KHÔNG HOẠT ĐỘNG** 🟡 MEDIUM

**Blade có element:**
```html
<!-- Line 288 -->
<span id="bookingTimer" class="timer-display">10:00</span>
```

**JS không update:**
- ❌ Không có interval/timeout
- ❌ Không countdown
- ❌ Không release seats khi hết giờ

**Impact:** 🟡 MEDIUM - UX issue

---

### **LỖI #5: KHÔNG VALIDATE SEATS TRƯỚC KHI SUBMIT** 🟠 HIGH

**File:** `public/js/pages/booking.js`

```javascript
// Line 442-457: handleConfirmBooking()
async function handleConfirmBooking() {
    if (selectedSeats.length === 0) { // ✅ Check empty
        alert('Vui lòng chọn ít nhất một ghế');
        return;
    }
    
    // ❌ KHÔNG CHECK:
    // - Seats still available?
    // - Hold expired?
    // - Status changed?
    
    const orderData = {
        showtime_id: showtimeId,
        seat_ids: selectedSeats.map(s => s.id),
        products: ...,
        promotion_code: ...
    };
    
    // POST trực tiếp
    const response = await fetch('/api/v1/orders', {...});
}
```

**Kết quả:**
- Ghế có thể đã bị người khác đặt
- Giữ ghế đã hết hạn
- Submit → Backend reject → Bad UX

**Impact:** 🟠 HIGH - Race condition

---

## 🔧 BƯỚC 3: CẤU TRÚC DỮ LIỆU GHẾ

### **Backend Response** (API `/api/v1/seats/showtime/{id}`)

**Expected structure:**
```json
{
  "data": [
    {
      "id": 123,
      "row": "A",
      "number": 1,
      "seat_type": "standard",  // or "vip", "couple"
      "status": "available",     // or "booked", "holding"
      "surcharge": 0,            // Phụ thu (VIP: 30000)
      "screen_id": 1
    }
  ]
}
```

**❌ THIẾU fields:**
- `base_price` - Giá vé cơ bản từ showtime
- `final_price` - Tổng giá (base + surcharge)
- `hold_until` - Thời gian hết hạn hold (nếu holding)
- `held_by` - User đang hold (nếu holding)

**✅ CÓ fields:**
- `id` - OK
- `row` - OK
- `number` - OK
- `seat_type` - OK
- `status` - OK
- `surcharge` - OK nhưng THIẾU base_price

---

## 🌐 BƯỚC 4: KIỂM TRA API ENDPOINTS

### **API Routes** (`routes/api.php`)

#### 1. **GET Seat Map** ✅ CÓ
```php
GET /api/v1/seats/showtime/{encrypted_id}
```
**Status:** ✅ Có endpoint  
**Controller:** SeatController@index  
**Note:** Cần verify response structure

#### 2. **POST Hold Seat** ❌ KHÔNG CÓ
```php
POST /api/v1/seats/hold  // ❌ KHÔNG TỒN TẠI!
```
**Status:** 🔴 THIẾU HOÀN TOÀN  
**Impact:** CRITICAL - Không thể hold seat

#### 3. **POST Create Order** ⚠️ CONFLICT
```php
// Route 1
Route::post('orders', [OrderController::class, 'store']);

// Route 2 (SAME URL!)
Route::post('orders', [UserPaymentController::class, 'createPayment']);
```
**Status:** ⚠️ CONFLICT - Laravel chỉ match route đầu tiên  
**Kết quả:** OrderController@store được gọi, UserPaymentController bị bỏ qua  
**Impact:** 🟠 HIGH - Route confusion

#### 4. **GET Order Summary** ✅ CÓ
```php
GET /api/v1/orders/{orderCode}
```
**Status:** ✅ Có endpoint  
**Controller:** UserPaymentController@showOrderSummary

---

## 🗄️ BƯỚC 5: KIỂM TRA BACKEND LOGIC

### **OrderController@store** (`app/Http/Controllers/OrderController.php`)

```php
// Line 24-41
public function store(StoreOrderRequest $request)
{
    try {
        $user = Auth::user();
        $order = $this->orderService->create($request->validated(), $user);
        
        return $this->successResponse(
            $this->orderService->format($order),
            'Order created successfully',
            201
        );
    } catch (\RuntimeException $e) {
        $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
        return $this->errorResponse($e->getMessage(), $statusCode);
    } catch (\Exception $e) {
        return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
    }
}
```

**✅ Có:**
- Validation (StoreOrderRequest)
- Service layer separation
- Error handling
- Response formatting

**❓ CẦN KIỂM TRA:**
- `OrderService->create()` implementation
- Database transaction?
- Seat locking?
- Order details creation?
- Payment record creation?

---

## 🔒 BƯỚC 6: KIỂM TRA HOLD SEAT MECHANISM

### **Database Tables**

**Có table:** `seat_holds` (model SeatHold exists)

**❓ CẦN XÁC NHẬN:**
- Schema của `seat_holds`?
- Có `expires_at` column?
- Có `user_id` column?
- Có index cho performance?

**❓ CẦN KIỂM TRA:**
- `SeatService` có method `holdSeats()`?
- Có job `CleanupExpiredSeatHolds`?
- Job có chạy định kỳ?
- Transaction locking khi hold?

---

## 💰 BƯỚC 7: LOGIC TÍNH TIỀN

### **Frontend Calculation** (booking.js)

```javascript
// ❌ SAI
function calculateTotal() {
    let total = 0;
    
    // Chỉ cộng surcharge
    selectedSeats.forEach(seat => {
        total += parseFloat(seat.price || 0);
    });
    
    // Cộng products
    Object.keys(selectedProducts).forEach(productId => {
        const product = products.find(p => p.id == productId);
        total += product.price * selectedProducts[productId];
    });
    
    return total;
}
```

**Đúng phải là:**
```javascript
// ✅ ĐÚNG
function calculateTotal() {
    let total = 0;
    const basePrice = showtimeData.price; // Lấy từ showtime
    
    // Base price + surcharge
    selectedSeats.forEach(seat => {
        total += basePrice + parseFloat(seat.price || 0);
    });
    
    // Products
    Object.keys(selectedProducts).forEach(productId => {
        const product = products.find(p => p.id == productId);
        total += product.price * selectedProducts[productId];
    });
    
    return total;
}
```

### **Backend Calculation** ❓ CẦN XÁC NHẬN

**PricingService hoặc OrderService phải:**
1. ✅ Tính lại giá từ database (KHÔNG tin frontend)
2. ✅ Validate showtime price
3. ✅ Validate seat surcharge
4. ✅ Validate product prices
5. ✅ Apply promotion discount
6. ✅ Calculate convenience fee

**❓ CẦN KIỂM TRA:**
- `PricingService->calculateOrderTotal()`
- `OrderService->create()` có tính lại không?

---

## 💳 BƯỚC 8: LUỒNG THANH TOÁN

### **Current Flow** ❓

```
handleConfirmBooking()
    ↓
POST /api/v1/orders
    ↓
OrderService->create()
    ↓
❓ Create Order record?
    ↓
❓ Create OrderDetails?
    ↓
❓ Update seat status?
    ↓
❓ Create Payment record?
    ↓
Return order data
    ↓
Redirect to /payment/{order.id}
    ↓
PaymentController@index
    ↓
Show payment page
    ↓
User pays
    ↓
❓ Payment success webhook?
    ↓
❓ Update order status?
    ↓
❓ Confirm seat booking?
```

**❓ CẦN XÁC NHẬN:**
- Order có status gì? (pending, paid, confirmed?)
- Payment record tạo khi nào?
- Seats lock khi nào? (order tạo hay payment success?)
- Rollback logic nếu payment fail?

---

## 📊 BƯỚC 9: BẢNG TỔNG HỢP LỖI

| # | File | Chức năng | Lỗi | Nguyên nhân | Mức độ | Cách sửa |
|---|------|-----------|-----|-------------|--------|----------|
| 1 | `booking.js` | Update summary | DOM ID không match | AI Agent sửa sai | 🔴 HIGH | Đổi `#summarySeats` → `#selectedSeatsDisplay` |
| 2 | `booking.js` | Calculate price | Thiếu base price | Logic sai | 🔴 CRITICAL | Add `showtime.price` vào calculation |
| 3 | `booking.js` | Seat selection | Không hold seat | Thiếu API call | 🔴 CRITICAL | Gọi API `/api/v1/seats/hold` sau click |
| 4 | `booking.js` | Timer | Không countdown | Thiếu logic | 🟡 MEDIUM | Add setInterval countdown |
| 5 | `booking.js` | Submit order | Không validate seats | Thiếu check | 🟠 HIGH | Verify seats trước submit |
| 6 | `routes/api.php` | Order routes | Route conflict | Duplicate route | 🟠 HIGH | Remove duplicate |
| 7 | **API** | Hold seat | Endpoint không tồn tại | Thiếu implement | 🔴 CRITICAL | Tạo endpoint + logic |
| 8 | **Backend** | Seat locking | ❓ Chưa rõ | Cần kiểm tra | 🔴 CRITICAL | Verify transaction locking |
| 9 | **Backend** | Price calculation | ❓ Chưa rõ | Cần kiểm tra | 🔴 CRITICAL | Verify backend tính lại |
| 10 | **Backend** | Order flow | ❓ Chưa rõ | Cần kiểm tra | 🟠 HIGH | Verify complete flow |

---

## 🎯 BƯỚC 10: ĐỀ XUẤT CÁCH SỬA

### **Thứ tự ưu tiên:**

#### **PHASE 1: FIX FRONTEND CRITICAL** 🔴

1. **Fix DOM ID mismatch**
   - File: `booking.js` line 398
   - Change: `#summarySeats` → `#selectedSeatsDisplay`
   - Time: 2 phút

2. **Fix price calculation**
   - File: `booking.js` line 92-100, 413-430
   - Add: `showtime.price` to seat price
   - Time: 5 phút

#### **PHASE 2: ADD HOLD SEAT** 🔴

3. **Create Hold Seat API**
   - File: `routes/api.php`, `SeatController.php`, `SeatService.php`
   - Endpoint: `POST /api/v1/seats/hold`
   - Logic: Lock seats with expiry
   - Time: 30 phút

4. **Call Hold API from Frontend**
   - File: `booking.js`
   - After seat selection
   - Time: 10 phút

5. **Add countdown timer**
   - File: `booking.js`
   - Update `#bookingTimer` every second
   - Time: 10 phút

#### **PHASE 3: VERIFY BACKEND** ❓

6. **Verify OrderService**
   - File: `OrderService.php`
   - Check: Transaction, locking, price calculation
   - Time: 20 phút investigation

7. **Verify Payment flow**
   - Files: `PaymentController.php`, `PaymentService.php`
   - Check: Complete flow
   - Time: 15 phút investigation

#### **PHASE 4: FIX BACKEND** (If needed)

8. **Fix backend issues found**
   - Based on Phase 3 findings
   - Time: TBD

---

## ⚠️ NGUY CƠ

1. **OrderService có thể đã bị AI Agent sửa sai**
   - Cần đọc toàn bộ để verify logic
   - Không trust existing code

2. **Database schema có thể thiếu columns**
   - Cần check migrations
   - Verify foreign keys, indexes

3. **Race conditions chưa handle**
   - 2 users book cùng ghế
   - Payment timeout
   - Hold expiry

---

## 📝 KẾT LUẬN

### **Lỗi chính:**

1. 🔴 **Frontend không hold seat** → Race condition
2. 🔴 **Giá vé tính sai** → Business logic error
3. 🔴 **DOM IDs không match** → UI broken
4. ⚠️ **Routes conflict** → Confusion
5. ❓ **Backend chưa verify** → Unknown risks

### **Cần làm ngay:**

1. ✅ Sửa DOM IDs
2. ✅ Sửa price calculation
3. ✅ Add Hold Seat API + frontend call
4. ❓ Verify backend logic
5. ❓ Add proper testing

### **Thời gian ước tính:**

- **Phase 1 (Frontend fixes):** 15 phút
- **Phase 2 (Hold Seat):** 50 phút
- **Phase 3 (Verification):** 35 phút
- **Phase 4 (Backend fixes):** TBD based on findings

**Total:** ~2 giờ (minimum, nếu backend OK)

---

## 🚀 NEXT STEPS

1. **Chờ user xác nhận** report này
2. **Bắt đầu Phase 1** - Fix frontend critical issues
3. **Mỗi lần sửa 1 file** theo quy trình
4. **Test sau mỗi fix**
5. **Document changes**

---

**Generated:** 2026-07-08 21:59:00 +07:00  
**By:** Kiro AI Investigation  
**Status:** ⏸️ AWAITING USER CONFIRMATION
