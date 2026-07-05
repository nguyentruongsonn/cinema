# Logic Tính Giá Vé - Cinema Booking System

## Tổng Quan

Hệ thống tính giá vé sử dụng **TicketPricingService** kết hợp với **PricingService** để tính toán giá vé động dựa trên nhiều yếu tố khác nhau.

---

## Kiến Trúc Tính Giá

```
User Booking Request
        ↓
BookingController
        ↓
PricingService.buildSnapshot()
        ↓
TicketPricingService.calculate()
        ↓
Pricing Result (base_price + surcharges = total_price)
```

---

## 1. Các Yếu Tố Ảnh Hưởng Đến Giá Vé

### 1.1. Format Phim
- **2D**: Giá cơ bản từ 50,000đ - 95,000đ
- **3D**: Giá cơ bản từ 70,000đ - 115,000đ

### 1.2. Loại Khách Hàng
- **Adult (Người lớn)**: Giá đầy đủ
- **Student (Học sinh)**: Giá ưu đãi (yêu cầu thẻ sinh viên, ≤22 tuổi)
- **Child (Trẻ em)**: Giá ưu đãi (chiều cao ≤1.3m)
- **Senior (Người cao tuổi)**: Giá ưu đãi (≥55 tuổi, yêu cầu CMND)

### 1.3. Khung Giờ Chiếu
- **10:00-18:00**: Khung giờ ban ngày (giá thấp hơn)
- **18:00-22:00**: Khung giờ tối (giá cao hơn)
- **Beta Ten** (Trước 10h & Sau 22h): Giá ưu đãi đặc biệt (thấp nhất)

### 1.4. Ngày Đặc Biệt

#### Thứ tự ưu tiên (cao → thấp):
1. **Beta Ten** (Ưu tiên cao nhất)
2. **Holiday** (Ngày lễ)
3. **Happy Day** (Thứ 3)
4. **Weekday** (T2, T4, T5)
5. **Weekend** (T6, T7, CN)
6. **Standard** (Giá chuẩn)

#### Chi tiết từng loại ngày:

**Beta Ten** (Trước 10h & Sau 22h)
- Áp dụng: Cả tuần
- Giá: Thấp nhất (dùng giá khung 10:00-18:00 của Standard)
- Ví dụ 2D Adult: 50,000đ

**Holiday** (Ngày lễ)
- Các ngày: 01-01, 04-30, 05-01, 09-02
- 2D Adult: 95,000đ (all day)
- 3D Adult: 115,000đ (all day)
- Không có giá ưu đãi cho Student/Child/Senior (dùng giá Adult)

**Happy Day** (Thứ 3)
- 2D Adult: 60,000-70,000đ (theo khung giờ)
- 3D Adult: 80,000-90,000đ (theo khung giờ)
- Student/Child/Senior: Dùng giá Standard

**Weekday** (Thứ 2, 4, 5)
- 2D Adult: 60,000-70,000đ
- 3D Adult: 80,000-90,000đ
- Student/Child/Senior: Dùng giá Standard

**Weekend** (Thứ 6, 7, CN)
- 2D Adult: 80,000đ (all day)
- 2D Student/Child/Senior: 55,000đ (all day)
- 3D Adult: 100,000đ (all day)
- 3D Student/Child/Senior: 75,000đ (all day)

**Mad Sale Day** (Thứ 2 đầu tháng, tầng 01)
- Giá cơ sở: Theo Weekday
- Phụ thu ghế: +5,000đ

### 1.5. Phụ Thu

#### Phụ thu ghế đôi (Double/Couple Seat)
- Mức phụ thu: +5,000đ
- Áp dụng cho tất cả các loại ngày
- Nhận diện qua: Tên hoặc slug của seat_type chứa từ khóa:
  - 'double', 'couple', 'đôi', 'sweetbox', 'sweet-box'

#### Phụ thu phim
- Mức phụ thu: Từ field `movies.surcharge`
- Ví dụ: Phim blockbuster có thể có phụ thu +10,000đ
- Áp dụng cho tất cả ghế

#### Phụ thu Mad Sale Day
- Chỉ áp dụng cho ngày Mad Sale Day
- Mức phụ thu: +5,000đ cho mỗi ghế

---

## 2. Bảng Giá Chi Tiết

### 2.1. Format 2D

| Loại Ngày | Loại Khách | 10:00-18:00 | 18:00-22:00 | All Day | Beta Ten |
|-----------|------------|-------------|-------------|---------|----------|
| Standard | Adult | 50,000đ | 50,000đ | - | 50,000đ |
| Standard | Student/Child/Senior | 45,000đ | 45,000đ | - | 45,000đ |
| Happy Day | Adult | 60,000đ | 70,000đ | - | 50,000đ |
| Happy Day | Student/Child/Senior | 45,000đ | 45,000đ | - | 45,000đ |
| Weekday | Adult | 60,000đ | 70,000đ | - | 50,000đ |
| Weekday | Student/Child/Senior | 45,000đ | 45,000đ | - | 45,000đ |
| Weekend | Adult | - | - | 80,000đ | 50,000đ |
| Weekend | Student/Child/Senior | - | - | 55,000đ | 45,000đ |
| Holiday | Adult | - | - | 95,000đ | 50,000đ |
| Mad Sale Day | Adult | 60,000đ + 5k | 70,000đ + 5k | - | 50,000đ |

### 2.2. Format 3D

| Loại Ngày | Loại Khách | 10:00-18:00 | 18:00-22:00 | All Day | Beta Ten |
|-----------|------------|-------------|-------------|---------|----------|
| Standard | Adult | 70,000đ | 70,000đ | - | 70,000đ |
| Standard | Student/Child/Senior | 65,000đ | 65,000đ | - | 65,000đ |
| Happy Day | Adult | 80,000đ | 90,000đ | - | 70,000đ |
| Happy Day | Student/Child/Senior | 65,000đ | 65,000đ | - | 65,000đ |
| Weekday | Adult | 80,000đ | 90,000đ | - | 70,000đ |
| Weekday | Student/Child/Senior | 65,000đ | 65,000đ | - | 65,000đ |
| Weekend | Adult | - | - | 100,000đ | 70,000đ |
| Weekend | Student/Child/Senior | - | - | 75,000đ | 65,000đ |
| Holiday | Adult | - | - | 115,000đ | 70,000đ |
| Mad Sale Day | Adult | 80,000đ + 5k | 90,000đ + 5k | - | 70,000đ |

---

## 3. Ví Dụ Tính Giá Cụ Thể

### Ví dụ 1: Vé Thường
**Điều kiện:**
- Format: 2D
- Ngày: Thứ 4 (Weekday)
- Giờ: 19:30 (18:00-22:00)
- Khách: Adult
- Ghế: Standard (không phải ghế đôi)
- Phim: Không có phụ thu

**Tính toán:**
```
Base Price = 70,000đ (2D Adult Weekday 18:00-22:00)
Surcharges = 0đ
Total = 70,000đ
```

### Ví dụ 2: Vé Ngày Lễ + Ghế Đôi
**Điều kiện:**
- Format: 3D
- Ngày: 01-01 (Holiday)
- Giờ: 20:00
- Khách: Adult
- Ghế: Couple Seat (ghế đôi)
- Phim: Có phụ thu 10,000đ

**Tính toán:**
```
Base Price = 115,000đ (3D Adult Holiday all_day)
Surcharges:
  + Double Seat: 5,000đ
  + Movie: 10,000đ
Total = 130,000đ
```

### Ví dụ 3: Vé Beta Ten
**Điều kiện:**
- Format: 2D
- Ngày: Chủ Nhật (Weekend)
- Giờ: 22:30 (Beta Ten - sau 22h)
- Khách: Student
- Ghế: Standard
- Phim: Không có phụ thu

**Tính toán:**
```
Base Price = 45,000đ (2D Student Beta Ten - giá thấp nhất)
Surcharges = 0đ
Total = 45,000đ
```

### Ví dụ 4: Vé Mad Sale Day
**Điều kiện:**
- Format: 2D
- Ngày: Thứ 2 đầu tháng (Mad Sale Day)
- Giờ: 14:00 (10:00-18:00)
- Khách: Adult
- Ghế: Standard
- Phim: Không có phụ thu

**Tính toán:**
```
Base Price = 60,000đ (Giá Weekday cho T2)
Surcharges:
  + Mad Sale Day: 5,000đ
Total = 65,000đ
```

---

## 4. Flow Code

### 4.1. Entry Point - BookingController

```php
// User gửi request đặt vé
POST /api/bookings/create
{
  "showtime_id": "...",
  "seats": [{"id": 1}, {"id": 2}],
  "products": [{"id": 1, "quantity": 2}],
  "voucher_code": "SUMMER2026",
  "points_used": 100
}
```

### 4.2. PricingService - Build Snapshot

```php
// app/Services/PricingService.php

public function buildSnapshot(
    User $user,
    Showtime $showtime,
    array $seatRequests,
    array $productRequests,
    ?string $voucherCode,
    int $pointsUsed
): array {
    // Load thông tin cần thiết
    $showtime->load(['format', 'movie']);
    
    // Lấy thông tin format và phụ thu phim
    $formatName = $showtime->format?->name ?? '2D'; // '2D' hoặc '3D'
    $movieSurcharge = (int) ($showtime->movie?->surcharge ?? 0);
    $scheduledAt = $showtime->scheduled_at; // Carbon instance
    
    // Load danh sách ghế
    $seats = Seat::with('seatType')->whereIn('id', $seatIds)->get();
    
    $seatItems = [];
    $seatTotal = 0;
    
    foreach ($seats as $seat) {
        // Kiểm tra ghế đôi
        $isDoubleSeat = $this->isDoubleSeat(
            $seat->seatType?->name ?? '',
            $seat->seatType?->slug ?? ''
        );
        
        // Tính giá vé bằng TicketPricingService
        $pricingResult = $this->ticketPricingService->calculate(
            format: $formatName,           // '2D' hoặc '3D'
            scheduledAt: $scheduledAt,     // Carbon datetime
            customerType: 'adult',         // 'adult', 'student', 'child', 'senior'
            isDoubleSeat: $isDoubleSeat,   // true/false
            movieSurcharge: $movieSurcharge // VNĐ
        );
        
        // Lưu thông tin vào snapshot
        $seatItems[] = [
            'id' => $seat->id,
            'name' => $seat->label ?: ($seat->row . $seat->number),
            'price' => $pricingResult['total_price'],
            'pricing_details' => [
                'base_price' => $pricingResult['base_price'],
                'surcharges' => $pricingResult['surcharges'],
                'day_type' => $pricingResult['day_type'],
                'time_slot' => $pricingResult['time_slot'],
            ],
        ];
        
        $seatTotal += $pricingResult['total_price'];
    }
    
    // ... tính toán products, voucher, points ...
    
    return [
        'seats' => $seatItems,
        'products' => $productItems,
        'subtotal' => $seatTotal + $productTotal,
        'discount' => $discountAmount,
        'points_discount' => $pointsDiscount,
        'total' => $finalTotal,
        // ...
    ];
}
```

### 4.3. TicketPricingService - Calculate Price

```php
// app/Services/TicketPricingService.php

public function calculate(
    string $format,           // '2D' | '3D'
    Carbon $scheduledAt,      // Ngày giờ chiếu
    string $customerType = 'adult',
    bool $isDoubleSeat = false,
    int $movieSurcharge = 0,
    array $extraHolidays = []
): array {
    // 1. Xác định format key
    $formatKey = in_array($format, ['2D', '3D']) ? $format : '2D';
    
    // 2. Lấy thông tin thời gian
    $hour = (int) $scheduledAt->format('H');
    $minute = (int) $scheduledAt->format('i');
    $dayOfWeek = $scheduledAt->dayOfWeek; // 0=Sun, 1=Mon, ..., 6=Sat
    $mmdd = $scheduledAt->format('m-d');
    
    // 3. Xác định customer type group
    $priceGroup = in_array($customerType, ['student', 'child', 'senior']) 
        ? 'student_child_senior' 
        : 'adult';
    
    // 4. Kiểm tra Beta Ten (trước 10h hoặc sau 22h)
    $timeInMinutes = $hour * 60 + $minute;
    $isBetaTen = ($timeInMinutes < 10 * 60) || ($timeInMinutes >= 22 * 60);
    
    // 5. Xác định time slot
    $timeSlot = ($hour >= 10 && $hour < 18) ? '10:00-18:00' : '18:00-22:00';
    
    // 6. Xác định day type (holiday > happy_day > weekday/weekend > standard)
    $dayType = $this->resolveDayType($scheduledAt, $dayOfWeek, $mmdd, $extraHolidays);
    
    // 7. Lấy base price từ bảng giá
    $basePrice = $this->resolveBasePrice(
        self::PRICING[$formatKey],
        $dayType,
        $priceGroup,
        $timeSlot,
        $isBetaTen
    );
    
    // 8. Tính phụ thu
    $surcharges = [];
    
    // 8a. Phụ thu ghế đôi
    if ($isDoubleSeat) {
        $surcharges['double_seat'] = [
            'label' => 'Phụ thu ghế đôi',
            'amount' => 5000,
        ];
    }
    
    // 8b. Phụ thu Mad Sale Day
    if ($dayType === 'mad_sale_day') {
        $surcharges['mad_sale_day'] = [
            'label' => 'Phụ thu Ngày Mad Sale',
            'amount' => 5000,
        ];
    }
    
    // 8c. Phụ thu phim
    if ($movieSurcharge > 0) {
        $surcharges['movie'] = [
            'label' => 'Phụ thu phim',
            'amount' => $movieSurcharge,
        ];
    }
    
    // 9. Tính tổng
    $totalSurcharge = array_sum(array_column($surcharges, 'amount'));
    $totalPrice = $basePrice + $totalSurcharge;
    
    return [
        'base_price' => $basePrice,
        'surcharges' => $surcharges,
        'total_price' => $totalPrice,
        'day_type' => $dayType,
        'time_slot' => $isBetaTen ? 'beta_ten' : $timeSlot,
        'customer_type' => $customerType,
        'price_group' => $priceGroup,
        'format' => $formatKey,
        'is_beta_ten' => $isBetaTen,
    ];
}
```

### 4.4. Helper Methods

```php
// Xác định loại ngày
private function resolveDayType(Carbon $dt, int $dow, string $mmdd, array $extraHolidays): string
{
    // Priority 1: Holiday
    if (in_array($mmdd, array_merge(self::HOLIDAYS, $extraHolidays))) {
        return 'holiday';
    }
    
    // Priority 2: Happy Day (Thứ 3)
    if ($dow === Carbon::TUESDAY) {
        return 'happy_day';
    }
    
    // Priority 3: Weekend (T6, T7, CN)
    if (in_array($dow, [Carbon::FRIDAY, Carbon::SATURDAY, Carbon::SUNDAY])) {
        return 'weekend';
    }
    
    // Priority 4: Mad Sale Day (Thứ 2 đầu tháng)
    if ($dow === Carbon::MONDAY && $dt->day <= 7) {
        return 'mad_sale_day';
    }
    
    // Priority 5: Weekday (T2, T4, T5)
    if (in_array($dow, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::THURSDAY])) {
        return 'weekday';
    }
    
    // Default: Standard
    return 'standard';
}

// Lấy base price từ bảng
private function resolveBasePrice(
    array $pricing,
    string $dayType,
    string $priceGroup,
    string $timeSlot,
    bool $isBetaTen
): int {
    // Beta Ten có giá thấp nhất (dùng giá standard 10:00-18:00)
    if ($isBetaTen) {
        return $pricing['standard'][$priceGroup]['10:00-18:00']
            ?? $pricing['standard']['adult']['10:00-18:00'];
    }
    
    // Các loại ngày khác
    $special = $pricing['special_days'];
    $standard = $pricing['standard'];
    
    switch ($dayType) {
        case 'holiday':
            return $special['holiday'][$priceGroup]['all_day']
                ?? $special['holiday']['adult']['all_day']
                ?? $standard[$priceGroup][$timeSlot];
        
        case 'happy_day':
            return $special['happy_day'][$priceGroup][$timeSlot]
                ?? $standard[$priceGroup][$timeSlot];
        
        case 'weekday':
            return $special['weekday'][$priceGroup][$timeSlot]
                ?? $standard[$priceGroup][$timeSlot];
        
        case 'weekend':
            return $special['weekend'][$priceGroup]['all_day']
                ?? $special['weekend']['adult']['all_day']
                ?? $standard[$priceGroup][$timeSlot];
        
        case 'mad_sale_day':
            // Giá cơ sở như weekday, phụ thu thêm riêng
            return $special['weekday'][$priceGroup][$timeSlot]
                ?? $standard[$priceGroup][$timeSlot];
        
        default: // standard
            return $standard[$priceGroup][$timeSlot]
                ?? $standard['adult'][$timeSlot];
    }
}

// Kiểm tra ghế đôi
private function isDoubleSeat(string $name, string $slug): bool
{
    $nameLower = mb_strtolower($name);
    $slugLower = mb_strtolower($slug);
    
    $keywords = ['double', 'couple', 'đôi', 'sweetbox', 'sweet-box'];
    
    foreach ($keywords as $keyword) {
        if (str_contains($nameLower, $keyword) || str_contains($slugLower, $keyword)) {
            return true;
        }
    }
    
    return false;
}
```

---

## 5. Database Schema

### Showtimes Table
```sql
CREATE TABLE showtimes (
    id BIGINT PRIMARY KEY,
    movie_id BIGINT,
    screen_id BIGINT,
    format_id BIGINT,           -- Link to formats table (2D, 3D, IMAX, ...)
    scheduled_at DATETIME,       -- Ngày giờ chiếu
    price DECIMAL(10,2),        -- Giá cũ (deprecated, dùng dynamic pricing)
    pricing_snapshot JSON,       -- Snapshot giá khi tạo order
    -- ...
);
```

### Movies Table
```sql
CREATE TABLE movies (
    id BIGINT PRIMARY KEY,
    title VARCHAR(255),
    surcharge DECIMAL(10,2),    -- Phụ thu phim
    -- ...
);
```

### Seat Types Table
```sql
CREATE TABLE seat_types (
    id BIGINT PRIMARY KEY,
    name VARCHAR(50),           -- VIP, Standard, Couple, ...
    slug VARCHAR(50),           -- vip, standard, couple, ...
    price_multiplier DECIMAL(3,2), -- Deprecated
    -- ...
);
```

### Formats Table
```sql
CREATE TABLE formats (
    id BIGINT PRIMARY KEY,
    name VARCHAR(20),           -- 2D, 3D, IMAX, 4DX, ...
    -- ...
);
```

---

## 6. API Response Example

### Request
```http
POST /api/bookings/calculate-price
Content-Type: application/json

{
  "showtime_id": "abc123",
  "seats": [
    {"id": 101},
    {"id": 102}
  ]
}
```

### Response
```json
{
  "success": true,
  "data": {
    "seats": [
      {
        "id": 101,
        "name": "A1",
        "price": 75000,
        "pricing_details": {
          "base_price": 70000,
          "surcharges": {
            "double_seat": {
              "label": "Phụ thu ghế đôi",
              "amount": 5000
            }
          },
          "day_type": "weekday",
          "time_slot": "18:00-22:00",
          "is_beta_ten": false
        }
      },
      {
        "id": 102,
        "name": "A2",
        "price": 70000,
        "pricing_details": {
          "base_price": 70000,
          "surcharges": {},
          "day_type": "weekday",
          "time_slot": "18:00-22:00",
          "is_beta_ten": false
        }
      }
    ],
    "subtotal": 145000,
    "total": 145000
  }
}
```

---

## 7. Chính Sách Áp Dụng

### 7.1. Chính sách cho Student/Child/Senior
```php
'policy' => [
    'member_only_discount' => true,
    'student' => [
        'max_age' => 22,
        'requires_id' => true
    ],
    'child' => [
        'max_height_m' => 1.3,
        'free_under_height_m' => 0.7,
        'free_condition' => 'Ngồi cùng ghế với người lớn'
    ],
    'senior' => [
        'min_age' => 55,
        'requires_id' => true
    ],
    'excluded_showtypes' => [
        'Suất chiếu sớm',
        'Suất chiếu đặc biệt'
    ]
]
```

### 7.2. Lưu ý đặc biệt
- Giảm giá chỉ áp dụng cho thành viên (member_only_discount = true)
- Học sinh phải xuất trình thẻ sinh viên/học sinh
- Trẻ em dưới 0.7m được miễn phí nếu ngồi cùng ghế với người lớn
- Người cao tuổi phải xuất trình CMND
- Không áp dụng cho suất chiếu sớm và suất chiếu đặc biệt

---

## 8. Testing

### Unit Test Example
```php
// tests/Unit/TicketPricingServiceTest.php

public function test_calculate_weekend_3d_adult()
{
    $service = new TicketPricingService();
    
    // Tạo ngày Weekend (Chủ Nhật)
    $scheduledAt = Carbon::parse('2026-07-05 20:00:00'); // Sunday
    
    $result = $service->calculate(
        format: '3D',
        scheduledAt: $scheduledAt,
        customerType: 'adult',
        isDoubleSeat: false,
        movieSurcharge: 0
    );
    
    $this->assertEquals(100000, $result['base_price']);
    $this->assertEquals(100000, $result['total_price']);
    $this->assertEquals('weekend', $result['day_type']);
    $this->assertFalse($result['is_beta_ten']);
}

public function test_calculate_with_double_seat_surcharge()
{
    $service = new TicketPricingService();
    
    $scheduledAt = Carbon::parse('2026-07-02 14:00:00'); // Wednesday
    
    $result = $service->calculate(
        format: '2D',
        scheduledAt: $scheduledAt,
        customerType: 'adult',
        isDoubleSeat: true, // Ghế đôi
        movieSurcharge: 10000
    );
    
    $this->assertEquals(60000, $result['base_price']);
    $this->assertArrayHasKey('double_seat', $result['surcharges']);
    $this->assertArrayHasKey('movie', $result['surcharges']);
    $this->assertEquals(75000, $result['total_price']); // 60k + 5k + 10k
}
```

---

## 9. Mở Rộng Tương Lai

### 9.1. Thêm Customer Type Selection
Hiện tại mặc định là 'adult'. Cần thêm:
- UI cho user chọn loại khách hàng
- Validation thẻ sinh viên/CMND
- Kiểm tra tuổi/chiều cao

### 9.2. Dynamic Holiday Calendar
- Admin panel quản lý ngày lễ
- API cập nhật danh sách ngày lễ
- Support Tết Âm lịch (động theo năm)

### 9.3. Price Preview API
```php
GET /api/showtimes/{id}/price-preview?customer_type=student

Response:
{
  "adult": 70000,
  "student": 45000,
  "child": 45000,
  "senior": 45000
}
```

### 9.4. Special Promotions
- Flash sale giờ vàng
- Member loyalty tiers
- Group booking discounts
- Birthday specials

---

## 10. Best Practices

1. **Cache bảng giá**: Lưu pricing rules vào cache để tăng performance
2. **Audit trail**: Log mọi thay đổi giá vé
3. **Price freeze**: Giá trong order snapshot không thay đổi sau khi tạo
4. **Testing**: Viết unit tests cho tất cả edge cases
5. **Documentation**: Cập nhật docs khi có thay đổi pricing rules

---

## Tổng Kết

Hệ thống tính giá vé hiện tại đã được thiết kế:
- ✅ **Linh hoạt**: Dễ dàng thêm format mới, ngày đặc biệt
- ✅ **Chính xác**: Logic rõ ràng, thứ tự ưu tiên minh bạch
- ✅ **Audit-able**: Lưu snapshot đầy đủ chi tiết
- ✅ **Maintainable**: Code sạch, dễ mở rộng
- ✅ **Testable**: Unit tests coverage cao

Hệ
