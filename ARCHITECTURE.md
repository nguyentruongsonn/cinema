# 🏗️ Cinema - Kiến Trúc Hệ Thống

## 📋 Tổng Quan

Cinema là một ứng dụng web đặt vé xem phim được xây dựng theo mô hình **MVC (Model-View-Controller)** với **RESTful API** backend và **Single Page Application** frontend.

### Nguyên Tắc Thiết Kế
- ✅ **Separation of Concerns**: Tách biệt logic, presentation, data
- ✅ **DRY (Don't Repeat Yourself)**: Tái sử dụng code qua Traits
- ✅ **SOLID Principles**: Single Responsibility, Open/Closed, Liskov, Interface Segregation, Dependency Inversion
- ✅ **Clean Code**: Readable, maintainable, testable code
- ✅ **RESTful Design**: Standard HTTP methods, proper status codes

## 🏛️ Kiến Trúc Tổng Thể

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (SPA)                          │
│  HTML5 + CSS3 + JavaScript + Bootstrap 5 + Fetch API       │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTP/JSON
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                   API Gateway                               │
│  Routes (api.php) + Middleware (JWT, CORS, Admin)          │
└────────────────────┬────────────────────────────────────────┘
                     │
        ┌────────────┼────────────┐
        ▼            ▼            ▼
    ┌────────┐  ┌────────┐  ┌────────┐
    │ Auth   │  │ Movie  │  │ Order  │
    │ Ctrl   │  │ Ctrl   │  │ Ctrl   │
    └────┬───┘  └────┬───┘  └────┬───┘
         │           │           │
         └───────────┼───────────┘
                     ▼
        ┌────────────────────────┐
        │   Business Logic       │
        │   (Models + Traits)    │
        └────────────┬───────────┘
                     │
                     ▼
        ┌────────────────────────┐
        │   Database Layer       │
        │   (Eloquent ORM)       │
        └────────────┬───────────┘
                     │
                     ▼
        ┌────────────────────────┐
        │   MySQL Database       │
        └────────────────────────┘
```

## 📦 Các Thành Phần Chính

### 1. Controllers (app/Http/Controllers/)

#### AuthController
- `register()` - Đăng ký người dùng mới
- `login()` - Xác thực & cấp JWT token
- `logout()` - Hủy token
- `me()` - Lấy thông tin user hiện tại
- `refresh()` - Làm mới token

#### MovieController
- `index()` - Danh sách phim (public)
- `show()` - Chi tiết phim (public)
- `store()` - Tạo phim (admin)
- `update()` - Cập nhật phim (admin)
- `destroy()` - Xóa phim (admin)

#### TheaterController
- `index()` - Danh sách rạp (public)
- `show()` - Chi tiết rạp (public)
- `store()` - Tạo rạp (admin)
- `update()` - Cập nhật rạp (admin)
- `destroy()` - Xóa rạp (admin)

#### ShowtimeController
- `index()` - Danh sách suất chiếu (public)
- `show()` - Chi tiết suất chiếu (public)
- `store()` - Tạo suất chiếu (admin)
- `update()` - Cập nhật suất chiếu (admin)
- `destroy()` - Xóa suất chiếu (admin)

#### SeatController
- `getByShowtime()` - Lấy ghế theo suất chiếu
- `lock()` - Khóa ghế (tạm giữ)
- `unlock()` - Mở khóa ghế

#### OrderController
- `store()` - Tạo đơn hàng
- `show()` - Chi tiết đơn hàng
- `userOrders()` - Đơn hàng của user
- `cancel()` - Hủy đơn hàng

#### PaymentController
- `store()` - Tạo thanh toán
- `show()` - Chi tiết thanh toán
- `verify()` - Xác nhận thanh toán

### 2. Models (app/Models/)

```
User
├── orders (1:N)
└── payments (1:N)

Movie
├── showtimes (1:N)
└── genres (M:N)

Theater
├── screens (1:N)
└── locations (1:N)

Screen
├── seats (1:N)
├── showtimes (1:N)
└── theater (N:1)

Seat
├── screen (N:1)
└── orders (M:N)

Showtime
├── movie (N:1)
├── screen (N:1)
├── orders (1:N)
└── seats (M:N)

Order
├── user (N:1)
├── showtime (N:1)
├── seats (M:N)
├── payment (1:1)
└── items (1:N)

Payment
├── order (N:1)
└── transactions (1:N)
```

### 3. Middleware (app/Http/Middleware/)

#### JwtMiddleware
- Xác thực JWT token từ header
- Kiểm tra token hợp lệ & chưa hết hạn
- Gắn user info vào request

#### AdminMiddleware
- Kiểm tra user có role 'admin'
- Từ chối truy cập nếu không phải admin

### 4. Traits (app/Traits/)

#### ApiResponse
- `success()` - Trả về response thành công
- `error()` - Trả về response lỗi
- `paginate()` - Trả về response phân trang
- Định dạng JSON chuẩn cho tất cả API

## 🔄 Luồng Dữ Liệu

### Luồng Đặt Vé

```
1. User chọn phim & suất chiếu
   └─> GET /api/showtimes?movie_id=1&date=2026-05-30

2. Hệ thống trả về danh sách suất chiếu
   └─> Response: [showtime1, showtime2, ...]

3. User chọn ghế
   └─> GET /api/seats/showtime/1

4. Hệ thống trả về danh sách ghế
   └─> Response: [seat1, seat2, ...]

5. User khóa ghế (tạm giữ)
   └─> POST /api/seats/lock
   └─> Body: {showtime_id: 1, seat_ids: [1, 2, 3]}

6. Hệ thống khóa ghế trong 10 phút
   └─> Response: {hold_id: "abc123", expires_at: "..."}

7. User tạo đơn hàng
   └─> POST /api/orders
   └─> Body: {showtime_id: 1, seat_ids: [1, 2, 3]}

8. Hệ thống tạo đơn hàng (status: pending)
   └─> Response: {order_id: 1, total_price: 300000}

9. User thanh toán
   └─> POST /api/payments
   └─> Body: {order_id: 1, method: "credit_card"}

10. Hệ thống xử lý thanh toán
    └─> Response: {payment_id: 1, status: "completed"}

11. Hệ thống cập nhật đơn hàng (status: confirmed)
    └─> Ghế được đánh dấu là "booked"

12. User nhận xác nhận
    └─> Email: Vé xem phim của bạn
```

### Luồng Xác Thực

```
1. User đăng nhập
   └─> POST /api/auth/login
   └─> Body: {email: "test@example.com", password: "password"}

2. Server xác thực credentials
   └─> Kiểm tra email tồn tại
   └─> Kiểm tra password đúng (bcrypt)

3. Server tạo JWT token
   └─> Header: {alg: "HS256", typ: "JWT"}
   └─> Payload: {sub: user_id, email: "...", role: "user"}
   └─> Signature: HMAC-SHA256(header.payload, secret)

4. Server trả về token
   └─> Response: {access_token: "eyJ...", expires_in: 3600}

5. Client lưu token vào localStorage
   └─> localStorage.setItem('token', token)

6. Client gửi token trong mỗi request
   └─> Header: Authorization: Bearer eyJ...

7. Server xác thực token
   └─> Kiểm tra signature
   └─> Kiểm tra expiration
   └─> Kiểm tra user tồn tại

8. Server cho phép truy cập
   └─> Gắn user info vào request
   └─> Tiếp tục xử lý request
```

## 🗄️ Database Schema

### Users Table
```sql
CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  full_name VARCHAR(255),
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  role ENUM('user', 'admin') DEFAULT 'user',
  email_verified_at TIMESTAMP NULL,
  remember_token VARCHAR(100),
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Movies Table
```sql
CREATE TABLE movies (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  genre VARCHAR(255),
  rating DECIMAL(3,1),
  duration INT,
  release_date DATE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Theaters Table
```sql
CREATE TABLE theaters (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255),
  city VARCHAR(100),
  phone VARCHAR(20),
  total_screens INT DEFAULT 0,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Screens Table
```sql
CREATE TABLE screens (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  theater_id BIGINT NOT NULL,
  name VARCHAR(100),
  screen_number INT,
  total_seats INT DEFAULT 100,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (theater_id) REFERENCES theaters(id) ON DELETE CASCADE
);
```

### Seats Table
```sql
CREATE TABLE seats (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  screen_id BIGINT NOT NULL,
  row VARCHAR(10),
  column INT,
  status ENUM('available', 'booked', 'locked') DEFAULT 'available',
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE
);
```

### Showtimes Table
```sql
CREATE TABLE showtimes (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  movie_id BIGINT NOT NULL,
  screen_id BIGINT NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME,
  price DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
  FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE
);
```

### Orders Table
```sql
CREATE TABLE orders (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  showtime_id BIGINT NOT NULL,
  total_price DECIMAL(10,2),
  status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (showtime_id) REFERENCES showtimes(id) ON DELETE CASCADE
);
```

### Payments Table
```sql
CREATE TABLE payments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT NOT NULL,
  amount DECIMAL(10,2),
  payment_method VARCHAR(50),
  status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  transaction_id VARCHAR(255),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

## 🔐 Bảo Mật

### Authentication
- JWT tokens với expiration 1 giờ
- Refresh tokens cho session dài hạn
- Password hashing với Bcrypt (cost: 12)

### Authorization
- Role-based access control (RBAC)
- Admin middleware cho protected routes
- User ownership validation cho personal data

### Input Validation
- Server-side validation cho tất cả inputs
- Sanitization để prevent XSS
- Parameterized queries để prevent SQL injection

### API Security
- CORS configuration
- Rate limiting (optional)
- Request validation middleware
- Error handling không leak sensitive info

## ⚡ Performance Optimization

### Database
- Indexing trên foreign keys
- Eager loading với Eloquent relationships
- Query optimization & caching

### API
- Response pagination (20 items/page)
- Selective field loading
- Compression (gzip)

### Frontend
- Lazy loading images
- Caching API responses
- Minified CSS/JS
- Async/await cho HTTP requests

## 📊 Monitoring & Logging

### Logs
- Laravel logs: `storage/logs/laravel.log`
- Request logs: Middleware logging
- Error tracking: Exception handling

### Metrics
- API response time
- Database query time
- User activity tracking
- Payment success rate

---

**Phiên bản**: 1.0.0  
**Cập nhật**: 2026-05-29
