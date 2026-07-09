# 📊 BÁO CÁO ĐIỀU TRA BOOKING WIZARD - CINEMA PROJECT

**Ngày:** 2026-07-09  
**Người thực hiện:** Principal Laravel Architect  
**Trạng thái:** Đã xác định ROOT CAUSE

---

## 📋 TÓM TẮT EXECUTIVE

Module Booking có **2 ROOT CAUSES chính**:

1. **BookingController thiếu validation showtime** → Cho phép booking suất chiếu đã kết thúc (LỖI NGHIỆP VỤ NGHIÊM TRỌNG)
2. **Tab switching logic đúng** nhưng user report không thấy UI → Cần điều tra thêm về user experience

**Mức độ ưu tiên:** 🔴 **CRITICAL** (Lỗi #1 ảnh hưởng trực tiếp revenue và user experience)

---

## 🔍 BƯỚC 1: LUỒNG HIỆN TẠI

```
┌─────────────────┐
│  Movie Listing  │
│   (Home Page)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Movie Detail   │
│ + Showtimes     │ ← getFilteredShowtimes() ✅ CÓ FILTER
└────────┬────────┘
         │ Click showtime
         ▼
┌─────────────────────────────────────────────┐
│         BOOKING PAGE                         │
│  /booking/{encrypted_showtime_id}           │
│                                              │
│  Controller: BookingController.show()       │
│  ❌ KHÔNG CÓ VALIDATION SHOWTIME             │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────┐
│        TAB WIZARD (4 TABS)                    │
│                                               │
│  1. SEATS    → switchTab('food')             │
│  2. FOOD     → switchTab('promotion')        │
│  3. PROMOTION → switchTab('confirm')         │
│  4. CONFIRM  → handleConfirmBooking()        │
│                ↓                             │
│           Create Order                       │
│                ↓                             │
│     Redirect /payment/{order_id}             │
└───────────────────────────────────────────────┘
```

**CHƯƠNG TRÌNH DỪNG Ở:** Không dừng - logic chuyển tab hoạt động đúng

**VẤN ĐỀ THỰC TẾ:** User báo không thấy UI Food, có thể do:
- Products không load được
- Tab render nhưng empty
- User experience issue

---

## 🔍 BƯỚC 2: KIỂM TRA TỪNG BƯỚC

### **Bước 1: SEATS**

**Chức năng:**
- ✅ Load seat map từ API `/api/v1/seats/showtime/{encrypted_id}`
- ✅ Render seat grid với CSS Grid
- ✅ User chọn ghế
- ✅ Update summary sidebar
- ✅ Enable button "Tiếp tục"

**Trạng thái:** **HOẠT ĐỘNG ĐÚNG**

---

### **Bước 2: FOOD**

**Chức năng theo code:**
```javascript
// booking.js line 43
await loadProducts(); 
// → GET /api/v1/products?type=combo

// booking.js line 145-168
function renderProducts() {
    container.innerHTML = products.map(product => `
        <div class="product-card">...</div>
    `).join('');
}
```

**HTML Template:**
```html
<!-- index.blade.php line 87 -->
<div class="tab-content" id="tab-food">
    <div id="productsContainer" class="products-grid">
        <div class="text-center py-5">
            <div class="spinner-border text-danger">Loading...</div>
        </div>
    </div>
</div>
```

**CSS:**
```css
/* booking.css */
.tab-content { display: none; }
.tab-content.active { display: block; }
```

**Phân tích:**
- ✅ HTML tồn tại
- ✅ CSS logic đúng
- ✅ JavaScript load products trong init()
- ✅ renderProducts() được gọi

**Trạng thái:** **LOGIC ĐÚNG** - Cần test thực tế

**Vấn đề có thể:**
1. API `/api/v1/products?type=combo` trả về empty array
2. renderProducts() failed silent
3. Tab switch successful nhưng products empty → user tưởng không hiển thị

---

### **Bước 3: PROMOTION**

**Chức năng theo template:**
```html
<!-- index.blade.php line 93 -->
<div class="tab-content" id="tab-promotion">
    <input id="promotionCodeInput" placeholder="Nhập mã khuyến mãi">
    <button id="applyPromotionBtn">Đăng ký</button>
</div>
```

**JavaScript:**
```javascript
// booking.js line 19
let promotionCode = '';
```

**Phân tích:**
- ✅ HTML tồn tại
- ❓ KHÔNG CÓ event listener cho promotion input
- ❓ KHÔNG CÓ API call để validate/apply promotion
- ❓ promotionCode chỉ là state variable, không được xử lý

**Trạng thái:** **INCOMPLETE** - Tab tồn tại nhưng chức năng chưa implement

---

### **Bước 4: CONFIRM**

**Chức năng:**
```javascript
// booking.js line 489-517
async function handleConfirmBooking() {
    // 1. Lock seats
    const holdId = await lockSeats();
    
    // 2. Create order
    const orderData = {
        showtime_id: showtimeId,
        seat_ids: selectedSeats.map(s => s.id),
        seat_hold_id: holdId,
        products: Object.keys(selectedProducts).map(...),
        promotion_code: promotionCode || null
    };
    
    const result = await window.apiClient.post('/orders', orderData);
    
    // 3. Redirect to payment
    window.location.href = `/payment/${order.id}`;
}
```

**Trạng thái:** **HOẠT ĐỘNG ĐÚNG**

---

## 🔍 BƯỚC 3-5: TAB NAVIGATION

### **Tab Structure**
```javascript
// booking.js line 397
const tabs = ['seats', 'food', 'promotion', 'confirm'];
```

### **Navigation Functions**
```javascript
// line 396-402
function goToNextTab() {
    const currentIndex = tabs.indexOf(currentTab);
    if (currentIndex < tabs.length - 1) {
        switchTab(tabs[currentIndex + 1]);
    }
}

// line 371-394
function switchTab(tabName) {
    // Validation
    if (tabName === 'food' && selectedSeats.length === 0) {
        alert('Vui lòng chọn ít nhất một ghế');
        return;
    }
    
    currentTab = tabName;
    
    // Update DOM
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === `tab-${tabName}`);
    });
}
```

**Trạng thái:** **LOGIC HOÀN TOÀN ĐÚNG**

**Kết luận:** Tab switching KHÔNG phải root cause của vấn đề

---

## 🔍 BƯỚC 6-7: SHOWTIME FILTERING (🔴 CRITICAL)

### **User-Facing Showtime API**

**File:** `app/Services/ShowtimeService.php`

**Method:** `getFilteredShowtimes()` (line 116-135)

```php
private function getFilteredShowtimes(int $movieId): Collection
{
    $now = Carbon::now();
    $cutoffTime = $now->copy()->subMinutes(20);
    $endDate = $now->copy()->addDays(5)->endOfDay();

    return Showtime::with([...])
        ->where('movie_id', $movieId)
        ->where('status', 1)                          // ✅ CHỈ ACTIVE
        ->where('scheduled_at', '>', $cutoffTime)    // ✅ LOẠI BỎ QUÁ 20 PHÚT
        ->where('scheduled_at', '<=', $endDate)       // ✅ CHỈ 5 NGÀY TỚI
        ->orderBy('scheduled_at')
        ->get();
}
```

**Trạng thái:** ✅ **HOẠT ĐỘNG ĐÚNG** - Movie detail page có filter

---

### **🚨 ROOT CAUSE #1: BookingController KHÔNG VALIDATE**

**File:** `app/Http/Controllers/BookingController.php`

**Method:** `show()` (line 15-32)

```php
public function show(Request $request, string $encryptedShowtimeId)
{
    try {
        $showtimeId = (int) Crypt::decryptString($encryptedShowtimeId);
    } catch (DecryptException) {
        abort(404, 'Invalid showtime identifier.');
    }
    
    $showtime = Showtime::with([
        'movie',
        'screen.theater',
        'format',
        'versionType',
    ])->findOrFail($showtimeId);

    return view('users.booking.index', [
        'showtime' => $showtime,
    ]);
}
```

### **VẤN ĐỀ NGHIÊM TRỌNG:**

❌ **KHÔNG CÓ VALIDATION:**
1. Không check `$showtime->status`
2. Không check `$showtime->scheduled_at` vs current time
3. Không check showtime có còn hợp lệ để booking

### **HẬU QUẢ:**

User có thể:
1. ✅ Truy cập trực tiếp `/booking/{encrypted_id}` của **BẤT KỲ** showtime nào
2. ✅ Booking cho suất chiếu **ĐÃ KẾT THÚC**
3. ✅ Booking cho suất **BỊ HỦY** (status = 0)
4. ✅ Đặt vé cho suất chiếu **1 NĂM TRƯỚC**

### **BUSINESS IMPACT:**

🔴 **CRITICAL:**
- Tạo orders không hợp lệ
- Ảnh hưởng revenue tracking
- User experience tồi (đặt vé xong mới phát hiện suất đã chiếu)
- Compliance risk (bán vé cho suất không tồn tại)

---

## 📊 BƯỚC 8: BẢNG TỔNG HỢP

| Tên File | Chức năng | Root Cause | Mức độ ảnh hưởng | Đề xuất sửa |
|----------|-----------|------------|------------------|-------------|
| **BookingController.php** | Load booking page | ❌ Thiếu validation showtime (status, scheduled_at) | 🔴 **CRITICAL** | Thêm validation: status=1, scheduled_at > now-20min |
| **booking.js** | Tab switching logic | ✅ Logic đúng | ✅ OK | Không cần sửa |
| **booking.css** | Tab display | ✅ CSS đúng | ✅ OK | Không cần sửa |
| **ShowtimeService.php** | Filter showtimes for movie detail | ✅ Logic đúng | ✅ OK | Không cần sửa |
| **booking.js** | Promotion functionality | ⚠️ Chưa implement | 🟡 MEDIUM | Implement promotion apply logic |
| **index.blade.php** | HTML structure | ✅ Đầy đủ | ✅ OK | Không cần sửa |

---

## 🎯 KẾT LUẬN & ĐỀ XUẤT

### **ROOT CAUSES XÁC ĐỊNH:**

#### **1. BookingController.show() - 🔴 CRITICAL**

**Vấn đề:** Không validate showtime trước khi cho phép booking

**Impact:**
- User có thể booking suất chiếu đã kết thúc
- User có thể booking suất bị hủy
- Tạo orders không hợp lệ

**Fix:**
```php
// Thêm vào BookingController.show() sau khi load $showtime

use Carbon\Carbon;

// Validate status
if ($showtime->status != 1) {
    abort(403, 'Suất chiếu này không khả dụng.');
}

// Validate scheduled_at (with 20min buffer like getFilteredShowtimes)
$now = Carbon::now();
$cutoffTime = $now->copy()->subMinutes(20);

if ($showtime->scheduled_at <= $cutoffTime) {
    abort(403, 'Suất chiếu này đã bắt đầu hoặc kết thúc.');
}

// Optional: Validate not too far in future (e.g., max 30 days)
$maxFutureDate = $now->copy()->addDays(30);
if ($showtime->scheduled_at > $maxFutureDate) {
    abort(403, 'Suất chiếu này chưa mở bán vé.');
}
```

---

#### **2. User Report: "Không hiển thị UI Food"**

**Phân tích:**

Logic code ĐÚNG:
- ✅ Tab structure correct
- ✅ switchTab() logic correct
- ✅ CSS display logic correct
- ✅ HTML template exists
- ✅ loadProducts() called

**Giả thuyết:**
1. **Products API trả về empty** → Tab hiển thị nhưng không có sản phẩm → User tưởng không chuyển tab
2. **loadProducts() failed silent** → Tab hiển thị nhưng còn skeleton loading
3. **User experience issue** → Tab buttons không rõ ràng đang ở tab nào

**Cần test:**
1. Check API `/api/v1/products?type=combo` có data không
2. Console log có errors không
3. Tab buttons có update `.active` class đúng không

**Đề xuất:**
- Không sửa code ngay (logic đã đúng)
- Test thực tế trước
- Nếu API empty, cần thêm products vào database
- Nếu UX issue, improve tab button styling

---

### **PRIORITY FIXES:**

**🔴 URGENT (Must fix immediately):**
1. BookingController validation - CRITICAL business logic

**🟡 MEDIUM (Fix sau khi test):**
2. Investigate products API if empty
3. Implement promotion apply functionality

**🟢 LOW (Enhancement):**
4. Improve tab button UX if needed

---

### **ACTION PLAN:**

1. **Ngay lập tức:**
   - Fix BookingController.show() validation
   - Test booking flow với suất chiếu đã kết thúc
   - Verify không thể đặt vé cho suất cũ

2. **Sau khi fix #1:**
   - Test toàn bộ booking wizard
   - Check products API có data
   - Verify tab switching UX

3. **Optional improvements:**
   - Implement promotion functionality
   - Add more user-friendly error messages
   - Improve tab navigation UX

---

## 📝 NOTES

**Về "Không hiển thị UI Food":**

Code analysis cho thấy logic ĐÚNG. Vấn đề có thể là:
- Data issue (no products)
- UX issue (tab hiển thị nhưng user không nhận ra)
- Timing issue (products load chậm)

→ Cần test thực tế để xác định

**Về quy trình 5 bước:**

User yêu cầu: Seat → Food → Discount → Payment → Complete

Thực tế hiện tại: Seat → Food → Promotion → Confirm → (redirect) Payment Page

Đây là kiến trúc ĐÚNG:
- Tab "Confirm" = review order
- "Payment" là trang riêng (không phải tab)
- Sau confirm → create order → redirect payment

→ Không cần thay đổi kiến trúc

---

**END OF REPORT**

**Status:** ✅ Root Cause Identified - Chờ xác nhận để bắt đầu fix