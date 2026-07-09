# 📊 PHÂN TÍCH KHÔI PHỤC MODULE BOOKING

**Baseline Commit:** `ae217f8dfaad0bc8f6a643974cb924e3dc9f1132`  
**Ngày:** 2026-07-09  
**Mục tiêu:** Khôi phục module Booking về trạng thái hoạt động đúng

---

## 🔍 **BƯỚC 1-2: DANH SÁCH FILE THAY ĐỔI**

### **Files Liên Quan Trực Tiếp Booking Module:**

| File | Trạng thái | Lines Thay Đổi | Ghi chú |
|------|------------|-----------------|---------|
| `public/js/pages/booking.js` | **M** (Modified) | -1477 lines | 🚨 REWRITTEN |
| `resources/views/users/booking/index.blade.php` | **M** | +3/-3 | CSS paths only |
| `resources/views/users/payment/index.blade.php` | **M** | TBD | Need check |
| `routes/api.php` | **M** | TBD | Need check |
| `routes/web.php` | **M** | TBD | Need check |
| `public/css/booking.css` | **R100** | → `css/users/pages/booking.css` | 100% same content |

**Legend:**
- **M** = Modified (nội dung thay đổi)
- **R100** = Renamed 100% similarity (chỉ đổi tên/đường dẫn)
- **A** = Added (file mới)
- **D** = Deleted (file bị xóa)

---

## 🔍 **BƯỚC 3: PHÂN TÍCH CHI TIẾT**

### **1. File: `public/js/pages/booking.js`** 🚨

#### **COMMIT CHUẨN (ae217f8):**
```javascript
/**
 * Booking Page JavaScript
 * Handles seat selection, locking, timer, and order creation
 */

class BookingManager {
    constructor() {
        this.config = window.BOOKING_CONFIG || {};
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        this.auth = window.authManager;

        // State Management
        this.seats = [];
        this.selectedSeats = new Set();
        this.currentHold = null;
        this.isLockingSeats = false;
        this.timer = null;
        this.timerSeconds = 600; // 10 minutes
        this.basePrice = parseFloat(this.config.basePrice) || 0;
        this.products = [];
        this.selectedProducts = new Map();
        this.appliedPromotion = null;
        this.registeredPromotions = [];
        this.currentStep = 1;
        this.steps = ['seats', 'food', 'promotion', 'confirm'];

        // 40+ DOM element references
        // 50+ methods:
        // - subscribeToRealtimeChannels()
        // - lockSeats()
        // - startTimer() / stopTimer()
        // - loadSeats()
        // - renderSeats()
        // - handleSeatClick()
        // - updateSummary()
        // - calculateTotalPrice()
        // - loadProducts()
        // - renderProducts()
        // - handleProductQuantityChange()
        // - loadRegisteredPromotions()
        // - applyPromotion()
        // - validatePromotion()
        // - proceedToPayment()
        // - createOrder()
        // - ... và nhiều methods khác
    }
}

// 1,916 LINES TOTAL
```

**Tính năng đầy đủ:**
- ✅ Class-based architecture
- ✅ Complete state management
- ✅ Seat locking với timer countdown (10 phút)
- ✅ WebSocket real-time updates (Laravel Reverb)
- ✅ Step wizard: Seats → Food → Promotion → Confirm
- ✅ Product/Combo selection với quantity management
- ✅ Promotion system với validation
- ✅ Auth integration
- ✅ Loading states & skeletons
- ✅ Toast notifications
- ✅ Error handling
- ✅ URL params handling (payment success/cancel)
- ✅ Comprehensive event listeners
- ✅ Price calculations với surcharges

#### **HEAD HIỆN TẠI:**
```javascript
/**
 * BOOKING PAGE MODULE
 * Handles seat selection, food/combo selection, promotions, and order creation
 */

(function() {
    'use strict';
    
    // Global state
    let showtimeId = null;
    let selectedSeats = [];
    let selectedProducts = {};
    let currentTab = 'seats';
    let promotionCode = '';
    
    // Basic init
    async function init() {
        showtimeId = document.querySelector('[data-showtime-id]')?.dataset.showtimeId;
        await loadSeats();
        await loadProducts();
        setupEventListeners();
        switchTab('seats');
    }
    
    // Simplified methods
    // ... basic implementations
    
    init();
})();

// CHỈ CÒN 439 LINES
```

**Tính năng BỊ MẤT:**
- ❌ No class structure (IIFE thay vì class)
- ❌ No seat locking mechanism
- ❌ No timer countdown
- ❌ No WebSocket real-time
- ❌ Simplified state management
- ❌ No comprehensive error handling
- ❌ No URL params handling
- ❌ No loading overlays
- ❌ Toast notifications removed
- ❌ Promotion validation simplified
- ❌ Missing many helper methods

#### **SO SÁNH LINES:**
- Commit chuẩn: **1,916 lines**
- HEAD hiện tại: **439 lines**
- **MẤT ĐI: 1,477 lines (77%)**

#### **ĐÁNH GIÁ:**
🔴 **CRITICAL** - File bị viết lại hoàn toàn, mất đi phần lớn chức năng nghiệp vụ.

---

### **2. File: `resources/views/users/booking/index.blade.php`**

#### **THAY ĐỔI:**
```diff
-<link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ time() }}">
-<link rel="stylesheet" href="{{ asset('css/booking-toast.css') }}?v={{ time() }}">
-<link rel="stylesheet" href="{{ asset('css/skeleton.css') }}">
+<link rel="stylesheet" href="{{ asset('css/users/pages/booking.css') }}?v={{ time() }}">
+<link rel="stylesheet" href="{{ asset('css/users/booking-toast.css') }}?v={{ time() }}">
+<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
```

#### **ĐÁNH GIÁ:**
🟢 **MINOR** - Chỉ đổi CSS paths do restructure thư mục. Nội dung HTML không đổi, tương thích với booking.js chuẩn.

**Hành động:** Giữ nguyên (đã đúng với structure mới)

---

### **3. File: `public/css/booking.css` → `public/css/users/pages/booking.css`**

#### **TRẠNG THÁI:**
**R100** - Renamed với 100% similarity

#### **ĐÁNH GIÁ:**
🟢 **OK** - File đã được rename trong quá trình restructure, nội dung không đổi.

**Hành động:** Không cần xử lý (đã đúng)

---

## 🔍 **BƯỚC 4: KIỂM TRA CHỨC NĂNG**

### **Checklist So Sánh Với Commit Chuẩn:**

| Chức năng | Commit Chuẩn | HEAD Hiện Tại | Root Cause |
|-----------|--------------|---------------|------------|
| **Chọn ghế** | ✅ Hoạt động | ⚠️ Basic only | booking.js simplified |
| **Hiển thị sơ đồ ghế** | ✅ Full rendering | ⚠️ Basic rendering | booking.js simplified |
| **Giá ghế** | ✅ Dynamic pricing | ⚠️ Basic calculation | Missing PricingService logic |
| **Giữ ghế (Seat Hold)** | ✅ With timer | ❌ KHÔNG CÓ | Missing lockSeats() method |
| **Timer countdown** | ✅ 10 phút | ❌ KHÔNG CÓ | Missing timer logic |
| **Tổng tiền** | ✅ Real-time update | ⚠️ Basic | Simplified calculation |
| **Bước chọn đồ ăn** | ✅ Tab wizard | ⚠️ Simplified | Tab logic còn nhưng thiếu features |
| **Bước giảm giá** | ✅ Validation | ⚠️ Basic | Missing validation logic |
| **Bước thanh toán** | ✅ Complete flow | ⚠️ Basic | Simplified |
| **Tạo Booking** | ✅ With hold_id | ⚠️ Simplified | Missing seat hold integration |
| **Tạo Order** | ✅ Complete | ⚠️ Basic | Order creation simplified |
| **Payment redirect** | ✅ With params | ⚠️ Basic | URL params handling removed |
| **Cập nhật trạng thái ghế** | ✅ Real-time | ❌ KHÔNG CÓ | WebSocket removed |
| **Chặn suất chiếu đã kết thúc** | ❌ THIẾU | ✅ ĐÃ FIX | BookingController validation added |
| **WebSocket real-time** | ✅ Laravel Reverb | ❌ KHÔNG CÓ | subscribeToRealtimeChannels() removed |
| **Loading states** | ✅ Overlays | ❌ KHÔNG CÓ | showLoading()/hideLoading() removed |
| **Toast notifications** | ✅ Bootstrap Toast | ❌ KHÔNG CÓ | showToast() removed |
| **Error handling** | ✅ Comprehensive | ⚠️ Basic | try-catch simplified |

### **Tổng Kết:**

**Hoạt động:** 1/14 chức năng  
**Hoạt động một phần:** 7/14 chức năng  
**Không hoạt động:** 6/14 chức năng

**Tỷ lệ mất mát chức năng: 43% (6/14)**

---

## 🔍 **BƯỚC 5: BẢNG QUYẾT ĐỊNH KHÔI PHỤC**

| File | Khác Với Commit Chuẩn | Mức Độ Ảnh Hưởng | Có Nên Khôi Phục | Lý Do |
|------|----------------------|-------------------|------------------|-------|
| **public/js/pages/booking.js** | ✅ HOÀN TOÀN KHÁC (77% code mất) | 🔴 **CRITICAL** | ✅ **BẮT BUỘC** | File bị viết lại, mất 6 chức năng quan trọng: seat locking, timer, WebSocket, toast notifications, loading states, comprehensive error handling |
| **resources/views/users/booking/index.blade.php** | ❌ Chỉ CSS paths | 🟢 MINOR | ❌ KHÔNG | Thay đổi hợp lý (CSS restructure), HTML tương thích với booking.js chuẩn |
| **public/css/users/pages/booking.css** | ❌ Rename only (R100) | 🟢 OK | ❌ KHÔNG | Nội dung 100% giống, chỉ đổi path |
| **resources/views/users/payment/index.blade.php** | ⏳ Cần kiểm tra | 🟡 TBD | ⏳ TBD | Chưa phân tích chi tiết |
| **routes/api.php** | ⏳ Cần kiểm tra | 🟡 TBD | ⏳ TBD | Có thể có thêm routes không liên quan |
| **routes/web.php** | ⏳ Cần kiểm tra | 🟡 TBD | ⏳ TBD | Có thể có thêm routes không liên quan |

---

## 🎯 **BƯỚC 6: KẾ HOẠCH KHÔI PHỤC**

### **PRIORITY 1: CRITICAL**

#### **File 1: `public/js/pages/booking.js`**

**Nguyên nhân:**
- AI Agent đã viết lại file từ class-based (1916 lines) thành function-based (439 lines)
- Mất đi 77% code và 43% chức năng nghiệp vụ

**Khác Gì So Với Commit Chuẩn:**
```
COMMIT CHUẨN:
- Class BookingManager với full state management
- Seat locking với timer 10 phút
- WebSocket real-time (Laravel Reverb)
- Step wizard hoàn chỉnh
- Loading overlays, toast notifications
- Comprehensive error handling
- URL params handling
- 50+ methods

HEAD HIỆN TẠI:
- Simple IIFE function
- Basic seat selection (no locking)
- No timer
- No WebSocket
- Simplified wizard
- No loading states
- No toast
- Basic error handling
- ~10 methods
```

**Vì Sao Cần Khôi Phục:**

1. **Seat Locking bị mất** → User có thể chọn ghế đã bị người khác giữ
2. **Timer bị mất** → Không có timeout cho việc giữ ghế
3. **WebSocket bị mất** → Không cập nhật real-time trạng thái ghế
4. **Toast notifications bị mất** → User không biết action thành công/thất bại
5. **Loading states bị mất** → Poor UX khi call API
6. **Error handling đơn giản** → Không handle edge cases

**Tác Động Nghiệp Vụ:**

🔴 **CRITICAL:**
- Có thể xảy ra race condition khi 2 user chọn cùng 1 ghế
- Không timeout ghế giữ → database lock lâu dài
- User experience kém (no feedback, no loading)
- Real-time sync bị mất

**Hành Động:**
```bash
# Khôi phục file từ commit chuẩn
git show ae217f8dfaad0bc8f6a643974cb924e3dc9f1132:public/js/pages/booking.js > public/js/pages/booking.js
```

**Sau Khi Khôi Phục - Cần Test:**
- ✅ Chọn ghế hoạt động
- ✅ Timer countdown hiển thị
- ✅ Seat locking với API call
- ✅ WebSocket cập nhật real-time
- ✅ Loading overlay khi API call
- ✅ Toast notifications
- ✅ Tab wizard: Seats → Food → Promotion → Confirm
- ✅ Product selection
- ✅ Promotion apply
- ✅ Order creation
- ✅ Redirect đúng sau payment

---

### **PRIORITY 2: INVESTIGATE**

Cần kiểm tra thêm các file sau trước khi quyết định:

1. **resources/views/users/payment/index.blade.php**
2. **routes/api.php** 
3. **routes/web.php**

---

## 📋 **BƯỚC 7: TESTING CHECKLIST**

Sau khi khôi phục `booking.js`, chạy test:

### **Phase 1: Core Functions**
- [ ] Load booking page không lỗi JavaScript
- [ ] Seat map hiển thị đúng
- [ ] Click chọn ghế hoạt động
- [ ] Selected seats highlight đúng màu
- [ ] Summary sidebar cập nhật real-time

### **Phase 2: Seat Locking**
- [ ] Click "Tiếp tục" → API call lock seats
- [ ] Timer countdown bắt đầu (10:00)
- [ ] Ghế được giữ trong database
- [ ] WebSocket broadcast seat locked

### **Phase 3: Products**
- [ ] Tab Food hiển thị
- [ ] Products/Combos load từ API
- [ ] Click +/- quantity hoạt động
- [ ] Total price cập nhật

### **Phase 4: Promotion**
- [ ] Tab Promotion hiển thị
- [ ] Nhập mã giảm giá
- [ ] Validation promotion
- [ ] Discount áp dụng đúng

### **Phase 5: Confirm & Payment**
- [ ] Tab Confirm hiển thị summary
- [ ] Click "Thanh toán" → Create order
- [ ] Redirect đến /payment/{order_id}
- [ ] No lỗi 422
- [ ] No lỗi JavaScript

### **Phase 6: Edge Cases**
- [ ] Không thể đặt vé cho suất đã kết thúc (BookingController validation)
- [ ] Timer hết → release seats
- [ ] WebSocket disconnect → reconnect
- [ ] API error → show toast
- [ ] Empty products → no crash

---

## 🔄 **WORKFLOW THỰC THI**

### **Bước 1: Backup Hiện Tại**
```bash
cp public/js/pages/booking.js public/js/pages/booking.js.backup.$(date +%s)
```

### **Bước 2: Khôi Phục Từ Commit Chuẩn**
```bash
git show ae217f8dfaad0bc8f6a643974cb924e3dc9f1132:public/js/pages/booking.js > public/js/pages/booking.js
```

### **Bước 3: Test Toàn Bộ Flow**
- Chạy testing checklist Phase 1-6

### **Bước 4: Nếu Có Lỗi**
- Debug từng phase
- Check console errors
- Check network errors
- Check backend logs

### **Bước 5: Verify**
- Booking flow hoàn chỉnh
- No JavaScript errors
- No 422 errors
- Real-time WebSocket hoạt động

---

## 📌 **NOTES**

### **Về BookingController Validation**

Đã fix trong task trước:
```php
// app/Http/Controllers/BookingController.php
// Added validation for expired showtimes
if ($showtime->status != 1) {
    abort(403, 'Suất chiếu này không khả dụng.');
}

$now = Carbon::now();
$cutoffTime = $now->copy()->subMinutes(20);

if ($showtime->scheduled_at <= $cutoffTime) {
    abort(403, 'Suất chiếu này đã bắt đầu hoặc kết thúc.');
}
```

→ Keep this validation (đã đúng)

### **Về CSS Restructure**

CSS files đã được move vào `public/css/users/pages/` và blade views đã update paths.

→ Không ảnh hưởng booking.js logic

### **Về WebSocket Configuration**

Commit chuẩn có integration với Laravel Reverb. Cần verify `.env`:

```env
BROADCAST_DRIVER=reverb
```

Nếu chưa có reverb, WebSocket sẽ fail gracefully (feature detection trong code).

---

## ✅ **FINAL DECISION**

**CẦN KHÔI PHỤC:**
1. ✅ `public/js/pages/booking.js` - CRITICAL

**KHÔNG CẦN KHÔI PHỤC:**
1. ❌ `resources/views/users/booking/index.blade.php` - Chỉ CSS paths
2. ❌ `public/css/users/pages/booking.css` - R100 rename

**CẦN ĐIỀU TRA THÊM:**
1. ⏳ `resources/views/users/payment/index.blade.php`
2. ⏳ `routes/api.php`
3. ⏳ `routes/web.php`

---

**Status:** ✅ Phân tích hoàn tất - Chờ xác nhận khôi phục

**Next Action:** Restore `booking.js` từ commit ae217f8dfaad0bc8f6a643974cb924e3dc9f1132