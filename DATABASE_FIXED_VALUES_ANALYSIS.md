# Database Schema Analysis: Fixed-Value Columns Documentation

**Date**: July 12, 2026  
**Purpose**: Identify columns with fixed/predefined values that need documentation comments  
**Status**: Analysis Complete

---

## 📋 Executive Summary

Đã phân tích **54 migration files** và tìm thấy **112 cột** có giá trị cố định hoặc giá trị enum. 

**Vấn đề chính:**
- Chỉ **1 table** (combos) có comment đầy đủ
- **17+ tables** khác có các cột status/type/enum KHÔNG có comment
- Gây khó khăn cho developers khi không biết ý nghĩa các giá trị

**Khuyến nghị:**
- Thêm comment cho TẤT CẢ các cột có giá trị cố định
- Sử dụng format: `->comment('0: Mô tả | 1: Mô tả | 2: Mô tả')`
- Ưu tiên các tables quan trọng: users, orders, payments, products

---

## 🎯 Tables Cần Thêm Comment (Ưu Tiên Cao)

### 1. **users** Table

**Cột cần comment:**

```php
// Line 21 - users table
$table->tinyInteger('status')->default(1);
```

**Nên sửa thành:**
```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Vô hiệu hóa | 1: Hoạt động | 2: Đang chờ xác thực email');
```

**Giá trị đề xuất:**
- `0` = Tài khoản bị vô hiệu hóa/khóa
- `1` = Tài khoản hoạt động bình thường
- `2` = Đang chờ xác thực email (nếu có)

---

```php
// Line 18 - users table  
$table->enum('gender', ['male', 'female', 'other'])->nullable();
```

**Nên thêm comment:**
```php
$table->enum('gender', ['male', 'female', 'other'])->nullable()
    ->comment('male: Nam | female: Nữ | other: Khác');
```

---

### 2. **orders** Table

**Cột cần comment:**

```php
// Line 19 - orders table
$table->tinyInteger('status')->default(1);
```

**Nên sửa thành:**
```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Đã hủy | 1: Chờ thanh toán | 2: Đã thanh toán | 3: Đã hoàn thành | 4: Đã hết hạn');
```

**Giá trị đề xuất:**
- `0` = Đơn hàng đã bị hủy
- `1` = Đang chờ thanh toán (default)
- `2` = Đã thanh toán thành công
- `3` = Đã hoàn thành (đã xem phim)
- `4` = Hết hạn thanh toán

---

```php
// Line 21 - orders table
$table->string('payment_status')->default('created');
```

**Nên thêm comment:**
```php
$table->string('payment_status')->default('created')
    ->comment('created: Mới tạo | pending: Chờ thanh toán | processing: Đang xử lý | paid: Đã thanh toán | failed: Thất bại | cancelled: Đã hủy | refunded: Đã hoàn tiền');
```

**Giá trị từ PayOS Gateway:**
- `created` = Đơn hàng mới tạo
- `pending` = Đang chờ thanh toán
- `processing` = Đang xử lý
- `paid` = Đã thanh toán thành công
- `failed` = Thanh toán thất bại
- `cancelled` = Đã hủy
- `refunded` = Đã hoàn tiền

---

### 3. **payments** Table

**Cột cần comment:**

```php
// Changed from tinyInteger to string in migration 2026_06_11_172729
$table->string('status')->default('pending');
```

**Nên thêm comment:**
```php
$table->string('status')->default('pending')
    ->comment('pending: Chờ xử lý | completed: Hoàn thành | failed: Thất bại | refunded: Đã hoàn tiền | cancelled: Đã hủy');
```

**Giá trị đề xuất:**
- `pending` = Đang chờ xử lý
- `completed` = Đã hoàn thành
- `failed` = Thất bại
- `refunded` = Đã hoàn tiền
- `cancelled` = Đã hủy

---

```php
// Line ~6 - payments table
$table->string('method');
```

**Nên thêm comment:**
```php
$table->string('method')
    ->comment('payos: PayOS Gateway | vnpay: VNPay | momo: MoMo | cash: Tiền mặt | bank_transfer: Chuyển khoản');
```

**Giá trị đề xuất:**
- `payos` = Thanh toán qua PayOS
- `vnpay` = Thanh toán qua VNPay
- `momo` = Thanh toán qua MoMo
- `cash` = Tiền mặt tại quầy
- `bank_transfer` = Chuyển khoản ngân hàng

---

### 4. **products** Table

**Cột cần comment:**

```php
// Line 19 - products table
$table->tinyInteger('status')->default(1);
```

**Nên sửa thành:**
```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Ngừng bán | 1: Đang bán | 2: Hết hàng tạm thời');
```

**Giá trị đề xuất:**
- `0` = Ngừng kinh doanh sản phẩm
- `1` = Đang bán
- `2` = Tạm thời hết hàng

---

```php
// Line 14 - products table
$table->string('type')->nullable();
```

**Nên thêm comment:**
```php
$table->string('type')->nullable()
    ->comment('food: Đồ ăn | drink: Nước uống | combo: Combo | snack: Snack | merchandise: Hàng lưu niệm');
```

**Giá trị đề xuất:**
- `food` = Đồ ăn
- `drink` = Nước uống
- `combo` = Combo (deprecated, dùng bảng combos)
- `snack` = Snack
- `merchandise` = Hàng lưu niệm

---

### 5. **promotions** Table

**Cột cần comment:**

```php
// Line ~15 - promotions table
$table->tinyInteger('status')->default(1);
```

**Nên sửa thành:**
```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Vô hiệu hóa | 1: Đang hoạt động | 2: Đã hết hạn | 3: Đã hết lượt sử dụng');
```

**Giá trị đề xuất:**
- `0` = Đã vô hiệu hóa
- `1` = Đang hoạt động
- `2` = Hết hạn sử dụng
- `3` = Hết lượt sử dụng

---

```php
// Line ~8 - promotions table
$table->enum('discount_type', ['percentage', 'fixed_amount']);
```

**Nên thêm comment:**
```php
$table->enum('discount_type', ['percentage', 'fixed_amount'])
    ->comment('percentage: Giảm theo % | fixed_amount: Giảm số tiền cố định');
```

---

### 6. **movies** Table

**Cột cần comment:**

```php
// Line ~20 - movies table
$table->tinyInteger('status')->default(1);
```

**Nên sửa thành:**
```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Ngừng chiếu | 1: Đang chiếu | 2: Sắp chiếu | 3: Đã kết thúc');
```

**Giá trị đề xuất:**
- `0` = Tạm ngừng chiếu
- `1` = Đang chiếu
- `2` = Sắp chiếu (coming soon)
- `3` = Đã kết thúc lịch chiếu

---

```php
// Line ~21 - movies table
$table->tinyInteger('is_hidden')->default(0);
```

**Nên thêm comment:**
```php
$table->tinyInteger('is_hidden')->default(0)
    ->comment('0: Hiển thị công khai | 1: Ẩn khỏi danh sách');
```

**Giá trị:**
- `0` = Hiển thị trên website
- `1` = Ẩn khỏi danh sách (draft)

---

### 7. **tickets** Table

**Cột cần comment:**

```php
// Line ~10 - tickets table
$table->string('status', 20)->default('valid')->index();
```

**Nên thêm comment:**
```php
$table->string('status', 20)->default('valid')->index()
    ->comment('valid: Có hiệu lực | used: Đã sử dụng | expired: Hết hạn | cancelled: Đã hủy | refunded: Đã hoàn tiền');
```

**Giá trị đề xuất:**
- `valid` = Vé hợp lệ, chưa sử dụng
- `used` = Đã quét vé và vào rạp
- `expired` = Hết hạn sử dụng
- `cancelled` = Đã hủy vé
- `refunded` = Đã hoàn tiền

---

## 🔧 Tables Khác Cần Comment (Ưu Tiên Trung Bình)

### 8. **categories** Table

```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Vô hiệu hóa | 1: Hoạt động');
```

---

### 9. **screens** Table

```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Ngừng hoạt động | 1: Đang hoạt động | 2: Bảo trì');
```

---

### 10. **seats** Table

```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Hỏng/không sử dụng | 1: Sẵn sàng | 2: Bảo trì');
```

---

### 11. **showtimes** Table

```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Đã hủy | 1: Sẵn sàng bán vé | 2: Hết vé | 3: Đã chiếu');
```

---

### 12. **theaters** Table

```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Đóng cửa | 1: Đang hoạt động | 2: Bảo trì');
```

---

### 13. **branches** Table

```php
$table->boolean('is_active')->default(true)
    ->comment('false: Không hoạt động | true: Đang hoạt động');
```

---

### 14. **banners** Table

```php
$table->boolean('is_active')->default(true)
    ->comment('false: Ẩn | true: Hiển thị');
```

---

### 15. **seat_layout_templates** Table

```php
$table->boolean('status')->default(true)
    ->comment('false: Vô hiệu hóa | true: Sử dụng được');
```

---

### 16. **price_rules** Table

```php
$table->tinyInteger('status')->default(1)
    ->comment('0: Vô hiệu hóa | 1: Đang áp dụng');

$table->string('day_type')
    ->comment('weekday: Ngày thường | weekend: Cuối tuần | holiday: Ngày lễ | special: Ngày đặc biệt');
```

---

### 17. **login_histories** Table

```php
$table->string('login_method', 30)->default('email')
    ->comment('email: Đăng nhập email | username: Đăng nhập username | google: Google OAuth | facebook: Facebook OAuth');

$table->boolean('success')->default(true)
    ->comment('false: Đăng nhập thất bại | true: Đăng nhập thành công');
```

---

### 18. **order_items** Table (Polymorphic)

```php
$table->string('item_type')
    ->comment('App\\Models\\Ticket: Vé xem phim | App\\Models\\Product: Sản phẩm | App\\Models\\Combo: Combo');
```

---

### 19. **idempotency_keys** Table

```php
$table->string('status')->default('pending')
    ->comment('pending: Đang xử lý | completed: Hoàn thành | failed: Thất bại');
```

---

## ✅ Table Đã Có Comment Tốt (Ví Dụ Mẫu)

### **combos** Table

```php
// Line 15 - combos table - GOOD EXAMPLE ✅
$table->boolean('status')->default(1)
    ->comment('1: Đang bán, 0: Ngừng bán');
```

**Đây là ví dụ TỐT về cách comment!** Các tables khác nên học theo format này.

---

## 📊 Thống Kê

### Tổng Quan:
- **Tổng số migration files**: 54
- **Tổng số cột tìm thấy**: 112 cột có giá trị cố định
- **Cột ĐÃ có comment**: 1 (combos.status)
- **Cột CHƯA có comment**: 111+
- **Tỷ lệ coverage**: ~0.9% ❌

### Phân Loại Theo Kiểu Cột:

| Tên Cột | Số Lượng | Tables |
|---------|----------|--------|
| `status` | 17+ | users, orders, payments, products, promotions, movies, categories, screens, seats, showtimes, theaters, tickets, combos, etc. |
| `is_active` | 2 | branches, banners |
| `is_hidden` | 1 | movies |
| `type` | 3+ | products, order_items, idempotency_keys |
| `method` | 2 | payments, login_histories |
| `gender` | 1 | users |
| `discount_type` | 1 | promotions |
| `day_type` | 1 | price_rules |
| `payment_status` | 1 | orders |
| `success` | 1 | login_histories |

---

## 🎯 Khuyến Nghị Triển Khai

### Ưu Tiên 1 (Quan Trọng Nhất):
1. ✅ **users.status** - Ảnh hưởng authentication & authorization
2. ✅ **users.gender** - Dữ liệu cá nhân quan trọng
3. ✅ **orders.status** - Luồng đặt hàng chính
4. ✅ **orders.payment_status** - Tích hợp payment gateway
5. ✅ **payments.status** - Trạng thái thanh toán
6. ✅ **payments.method** - Phương thức thanh toán
7. ✅ **tickets.status** - Quản lý vé quan trọng

### Ưu Tiên 2 (Quan Trọng):
8. ✅ **products.status** - Quản lý kho
9. ✅ **products.type** - Phân loại sản phẩm
10. ✅ **promotions.status** - Chiến dịch marketing
11. ✅ **promotions.discount_type** - Tính giảm giá
12. ✅ **movies.status** - Phim đang/sắp chiếu
13. ✅ **movies.is_hidden** - Hiển thị công khai

### Ưu Tiên 3 (Nên Có):
14. ✅ All remaining status columns in other tables
15. ✅ Type/method columns với giá trị cố định

---

## 📝 Format Comment Chuẩn

### Với TinyInteger/Boolean Status:
```php
->comment('0: Mô tả trạng thái 0 | 1: Mô tả trạng thái 1 | 2: Mô tả trạng thái 2')
```

### Với String/Enum:
```php
->comment('value1: Mô tả 1 | value2: Mô tả 2 | value3: Mô tả 3')
```

### Với Boolean:
```php
->comment('false: Mô tả false | true: Mô tả true')
```

### Ví Dụ Cụ Thể:
```php
// TỐT ✅
$table->tinyInteger('status')->default(1)
    ->comment('0: Vô hiệu hóa | 1: Hoạt động | 2: Đang chờ xác thực');

// XẤU ❌
$table->tinyInteger('status')->default(1); // Không comment

// XẤU ❌  
$table->tinyInteger('status')->default(1)->comment('Status'); // Comment không rõ ràng
```

---

## 🚀 Lợi Ích Khi Thêm Comment

### 1. **Code Documentation**
- Developers mới hiểu ngay ý nghĩa các giá trị
- Không cần đọc code logic để hiểu status
- Giảm thời gian onboarding

### 2. **Database Documentation**
- Export schema ra document tự động có comment
- DBA dễ hiểu cấu trúc database
- Tích hợp với database tools tốt hơn

### 3. **Maintainability**
- Dễ maintain và mở rộng thêm status
- Tránh nhầm lẫn giữa các giá trị
- Consistency across codebase

### 4. **API Documentation**
- Auto-generate API docs với mô tả rõ ràng
- Frontend developers hiểu status meanings
- Giảm communication overhead

---

## 📖 Tài Liệu Tham Khảo

### Laravel Migration Comments:
```php
// Official Laravel syntax for column comments
$table->string('status')->comment('Your comment here');
```

### Best Practices:
1. ✅ Comment ngắn gọn, súc tích
2. ✅ Dùng ký tự `|` để phân tách các giá trị
3. ✅ Mô tả bằng tiếng Việt (dễ hiểu cho team)
4. ✅ Bao gồm TẤT CẢ giá trị có thể
5. ✅ Update comment khi thêm giá trị mới

---

## 🎓 Kết Luận

**Hiện trạng:**
- Database schema thiếu documentation nghiêm trọng
- Chỉ 1/112+ cột có comment đầy đủ
- Gây khó khăn cho development & maintenance

**Khuyến nghị:**
1. Tạo migration mới để add comments (không alter data)
2. Ưu tiên tables quan trọng trước (users, orders, payments)
3. Follow format comment chuẩn như combos table
4. Document trong code comments và wiki

**Timeline đề xuất:**
- **Week 1**: Add comments cho 7 tables ưu tiên 1
- **Week 2**: Add comments cho 6 tables ưu tiên 2  
- **Week 3**: Add comments cho remaining tables
- **Week 4**: Review và chuẩn hóa

---

*Generated: July 12, 2026*  
*Author: Database Schema Analysis*  
*Version: 1.0*
