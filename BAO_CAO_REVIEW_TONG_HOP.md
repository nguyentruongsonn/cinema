# BÁO CÁO REVIEW TOÀN DIỆN HỆ THỐNG ĐẶT VÉ XEM PHIM
## Đánh giá từ Senior Software Architect (10+ năm kinh nghiệm)
**Ngày**: 08/06/2026 | **Trạng thái**: CHƯA SẴN SÀNG PRODUCTION

---

## TỔNG QUAN ĐÁNH GIÁ: 6.5/10 ⚠️ CÓ LỖI NGHIÊM TRỌNG

| Hạng mục | Điểm | Tình trạng |
|----------|------|------------|
| Kiến trúc | 6/10 | Nền tảng tốt, cần củng cố |
| Chất lượng Code | 6/10 | Pattern tốt, triển khai không đồng nhất |
| Laravel Best Practices | 6/10 | Theo chuẩn nhưng còn lỗi |
| Database | 4/10 | 🔴 Thiếu index, N+1 queries |
| API Design | 5/10 | Format response không nhất quán |
| Bảo mật | **3/10** | 🔴 **NHIỀU LỖ HỔNG NGHIÊM TRỌNG** |
| Frontend | 5/10 | Rủi ro XSS, lưu token sai |
| Hiệu năng | 5/10 | Xử lý đồng bộ, thiếu cache |
| Kiểm thử | **0/10** | 🔴 **KHÔNG CÓ TEST NÀO** |
| DevOps/Cấu hình | 6/10 | Cơ bản, chưa tối ưu |

---

## 🔴 LỖ HỔNG NGHIÊM TRỌNG (PHẢI SỬA NGAY)

### 1. THIẾU KIỂM TRA QUYỀN SỞ HỮU ĐƠN HÀNG
**Vị trí**: BookingController & OrderController  
**Mô tả**: Bất kỳ user nào cũng có thể xem đơn hàng của người khác
```php
// CODE HIỆN TẠI (SAI)
$order = Order::where('gateway_order_code', $orderCode)->first();
// KHÔNG kiểm tra user_id

// CÁCH SỬA
$order = Order::where('gateway_order_code', $orderCode)
    ->where('user_id', auth()->id())
    ->firstOrFail();
```
**Tác động**: Lộ thông tin đơn hàng, vi phạm quyền riêng tư  
**Thời gian sửa**: 2 giờ

---

### 2. WEBHOOK PAYOS KHÔNG XÁC THỰC CHỮ KÝ
**Vị trí**: PaymentController::handleWebhook()  
**Mô tả**: Không kiểm tra chữ ký HMAC từ PayOS
```php
// CODE HIỆN TẠI (SAI)
public function handleWebhook(Request $request) {
    return $this->paymentService->handleWebhook($request->all());
    // KHÔNG xác thực webhook
}
```
**Tác động**: Giả mạo webhook, đánh cắp thanh toán, thất thu doanh thu  
**Thời gian sửa**: 4 giờ

**Cách sửa**:
```php
// Tạo Middleware xác thực
// app/Http/Middleware/VerifyPayOSWebhook.php
public function handle($request, Closure $next) {
    $signature = $request->header('x-payos-signature');
    $payload = $request->getContent();
    $secret = config('payos.webhook_secret');
    
    if (!hash_equals(
        hash_hmac('sha256', $payload, $secret),
        $signature
    )) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
    
    return $next($request);
}
```

---

### 3. LƯU TOKEN AUTH TRONG localStorage (XSS)
**Vị trí**: public/js/pages/tickets.js  
**Mô tả**: Token JWT được lưu trong localStorage, dễ bị đánh cắp qua XSS
```javascript
// CODE HIỆN TẠI (SAI)
let authToken = localStorage.getItem('authToken');

// CÁCH SỬA: Xóa hoàn toàn việc dùng localStorage
// Backend đã set HttpOnly cookie - trình duyệt tự động gửi kèm
```
**Tác động**: Token bị đánh cắp, tài khoản bị chiếm quyền  
**Thời gian sửa**: 1 giờ

---

### 4. RACE CONDITION (ĐẶT VÉ TRÙNG / DÙNG MÃ GIẢM GIÁ 2 LẦN)
**Vị trí**: OrderService & PromotionController  
**Mô tả**: Hai người dùng có thể đặt cùng một ghế hoặc dùng mã giảm giá vượt quá số lần cho phép
```php
// CODE HIỆN TẠI (SAI)
if ($promo->used_count >= $promo->usage_limit) {
    // RACE: 2 request cùng đến đây
    // Cả 2 đều thấy used_count = 48, limit = 50
    // Cả 2 đều được áp dụng mã
}
$promo->increment('used_count');

// CÁCH SỬA
$promo = Promotion::lockForUpdate()->find($id);
if ($promo->used_count >= $promo->usage_limit) abort(422);
$promo->increment('used_count');
```
**Tác động**: Bán vé quá số lượng, mã giảm giá bị dùng tràn, mất doanh thu  
**Thời gian sửa**: 6 giờ

---

### 5. THIẾU INDEX TRONG DATABASE
**Vị trí**: Database schema  
**Mô tả**: Các cột quan trọng không có index, dẫn đến full table scan
```sql
-- CÁC INDEX CẦN THÊM NGAY
ALTER TABLE orders ADD INDEX idx_user_id (user_id);
ALTER TABLE orders ADD INDEX idx_gateway_order_code (gateway_order_code);
ALTER TABLE orders ADD INDEX idx_status (status);
ALTER TABLE order_items ADD INDEX idx_order_id (order_id);
ALTER TABLE seat_holds ADD INDEX idx_user_id (user_id);
ALTER TABLE seat_holds ADD INDEX idx_expires_at (expires_at);
ALTER TABLE seat_holds ADD INDEX idx_showtime_id (showtime_id);
```
**Tác động**: Truy vấn chậm gấp 100+ lần, nguy cơ timeout  
**Thời gian sửa**: 1 giờ

---

### 6. KHÔNG CÓ TEST
**Vị trí**: Thư mục tests/  
**Mô tả**: Không có bất kỳ unit test, integration test hay feature test nào
**Tác động**: Không rõ độ tin cậy, lỗi không được phát hiện khi thay đổi code  
**Các test cần viết**:
- Test quyền: user không thể xem đơn hàng của người khác
- Test race condition: không thể đặt ghế trùng
- Test promotion: mã giảm giá không thể dùng 2 lần
- Test webhook: chữ ký sai bị từ chối
- Test concurrency: 100 request đồng thời không gây lỗi

**Thời gian sửa**: 80-120 giờ

---

## 🟠 VẤN ĐỀ CAO (Cần sửa trước public release)

| # | Vấn đề | File | Tác động | Giờ |
|---|--------|------|----------|-----|
| 7 | Không rate limiting | routes/* | Dễ bị DDoS | 2 |
| 8 | Webhook xử lý đồng bộ | PaymentController | Timeout | 3 |
| 9 | Không CSRF token | Frontend | Tấn công CSRF | 2 |
| 10 | Thông báo lỗi lộ chi tiết | Controllers | Lộ thông tin | 2 |
| 11 | Pagination không giới hạn | Controllers | DoS qua kết quả lớn | 1 |
| 12 | Code đơn hàng có thể trùng | PaymentService | Xung đột đơn | 2 |
| 13 | Tính toán sai số thực | PricingService | Sai tiền | 2 |
| 14 | Không xác thực email | AuthController | Spam tài khoản | 4 |
| 15 | Token hết hạn sau 30 ngày | AuthController | Rủi ro bảo mật | 1 |

---

## PHẦN TỔNG QUAN TẤT CẢ CONTROLLER

### AuthController (6/10)
**Điểm tốt**: ✅ Dùng Form Request, cookie HttpOnly, xử lý lỗi  
**Điểm yếu**: ❌ Không rate limiting, lộ thông tin lỗi, không verify email, token 30 ngày quá dài

### BookingController (5/10)
**Điểm tốt**: ✅ Service pattern  
**Điểm yếu**: ❌ **THIẾU KIỂM TRA QUYỀN NGHIÊM TRỌNG**, bỏ qua lỗi sync

### MovieController (8/10)
**Điểm tốt**: ✅ Tốt nhất trong các controller  
**Điểm yếu**: ❌ Validation lẫn lộn, không check soft delete

### OrderController (5/10)
**Điểm yếu**: ❌ **Rủi ro authorization**, exception mapping sai, pagination không giới hạn

### PaymentController (4/10)
**Điểm yếu**: ❌ **KHÔNG XÁC THỰC WEBHOOK**, xử lý đồng bộ, không idempotency

---

## TỔNG QUAN SERVICES

### PaymentService (4/10)
- ❌ Xác thực webhook không rõ ràng
- ❌ Không timeout gọi API PayOS
- ❌ Code đơn hàng có thể trùng

### OrderService (5/10)
- ❌ Race condition đặt vé
- ❌ Lock không đúng cách (deadlock potential)
- ❌ Không kiểm tra tồn kho

### PricingService (6/10)
- ❌ Lỗi số thực (float point)
- ❌ Không kiểm tra tồn kho trước khi tính giá
- ❌ Logic promotion không rõ ràng

---

## PHÂN TÍCH BẢO MẬT

### Rủi ro cao nhất:
1. **Authorization**: User có thể xem/sửa đơn hàng người khác
2. **Payment**: Webhook giả mạo = thanh toán giả
3. **XSS**: Token trong localStorage
4. **CSRF**: Không bảo vệ form
5. **Rate Limiting**: Không giới hạn request

### Checklist bảo mật:
- [ ] XSS: Chưa kiểm tra output
- [ ] SQL Injection: Dùng Eloquent nên an toàn
- [ ] CSRF: Chưa triển khai
- [ ] Authentication: JWT cơ bản
- [ ] Authorization: **THIẾU**
- [ ] Rate Limiting: **KHÔNG CÓ**
- [ ] Webhook: **KHÔNG XÁC THỰC**

---

## LỘ TRÌNH SỬA LỖI

### GIAI ĐOẠN 1: NGHIÊM TRỌNG (1 Tuần, 40-50 Giờ)
**Bắt buộc trước khi deploy production:**

1. **Ngày 1**: Fix authorization bypass (cao nhất)
2. **Ngày 2**: Thêm xác thực webhook PayOS
3. **Ngày 3**: Xóa localStorage token, chỉ dùng HttpOnly cookie
4. **Ngày 4**: Thêm pessimistic locking cho ghế & promotion
5. **Ngày 5-7**: Database index + rate limiting + CSRF

### GIAI ĐOẠN 2: CHẤT LƯỢNG (2 Tuần, 60-80 Giờ)
**Hoàn thành trước public release:**

- Viết test suite (70%+ coverage)
- Fix N+1 queries
- Queue webhook
- Xác thực email
- Error boundary frontend

### GIAI ĐOẠN 3: TỐI ƯU (2 Tuần, 50-60 Giờ)
- Cache layer
- Structured logging
- Soft deletes
- API documentation

---

## KẾT LUẬN

### Điểm mạnh:
✅ Kiến trúc Service layer tốt  
✅ Transaction đúng cách  
✅ Model relationships chuẩn  
✅ Form request validation  
✅ Middleware pattern đúng  

### Điểm yếu cốt lõi:
❌ **Bảo mật thiếu** - lỗ hổng authorization, webhook, XSS  
❌ **Race condition** - đặt vé trùng, promotion dùng quá số lần  
❌ **Không có kiểm thử** - không biết độ tin cậy  
❌ **Database chưa tối ưu** - thiếu index, N+1 queries  
❌ **Thiếu các biện pháp phòng thủ** - rate limiting, CSRF  

### Khuyến nghị:
**KHÔNG TRIỂN KHAI PRODUCTION KHI CHƯA HOÀN THÀNH GIAI ĐOẠN 1.**

Sau Giai đoạn 1: Điểm 7.0/10 - có thể deploy nội bộ  
Sau Giai đoạn 2: Điểm 8.0/10 - có thể public release  
Sau Giai đoạn 3: Điểm 8.5/10 - tối ưu cho scale

---

*Báo cáo được tạo bởi Senior Software Architect (10+ năm kinh nghiệm)*  
*Ngày: 08/06/2026*  
*Tất cả source code đã được phân tích*
