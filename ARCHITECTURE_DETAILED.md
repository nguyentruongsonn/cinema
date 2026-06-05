# Kế Hoạch Xây Dựng Website Đặt Vé Xem Phim

## Kiến Trúc Tổng Thể

```
cinema/
├── app/
│   ├── Http/
│   │   ├── Controllers/API/    # API Controllers theo module
│   │   ├── Middleware/          # JWT, Admin, CORS
│   │   ├── Requests/           # Form requests validation
│   │   └── Resources/          # API Resources (transformer)
│   ├── Models/                 # Eloquent Models
│   ├── Services/               # Business Logic Layer
│   ├── Repositories/           # Data Access Layer
│   └── Traits/                 # Reusable traits
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/                  # Blade layouts
│   └── js/                     # Frontend JS modules
└── routes/
    └── api.php                 # API routes
```

## Công Nghệ & Skill

| Skill | Ứng dụng |
|-------|----------|
| **Laravel 11** | Backend framework (MVC) |
| **MySQL** | Database (đã có sẵn) |
| **JWT (tymon/jwt-auth)** | Authentication |
| **RESTful API** | Kiến trúc API |
| **Bootstrap 5** | Frontend UI framework |
| **Responsive Design** | Mobile-first, responsive grid |
| **JavaScript (Vanilla + Fetch API)** | AJAX calls, DOM manipulation |
| **Realtime (Pusher/Laravel Echo)** | Seat locking, notifications |
| **API Resources** | Data transformation |
| **Form Requests** | Validation logic |
| **Repository Pattern** | Data access abstraction |
| **Service Layer** | Business logic separation |

## Các Module Chính

### 1. Authentication & Authorization
- **Models**: User, Role, Permission
- **API Routes**:
  - `POST /api/auth/register` - Đăng ký
  - `POST /api/auth/login` - Đăng nhập
  - `POST /api/auth/logout` - Đăng xuất
  - `POST /api/auth/refresh` - Refresh token
  - `GET /api/auth/me` - Thông tin user hiện tại
  - `POST /api/auth/verify-email` - Xác thực email
  - `POST /api/auth/forgot-password` - Quên mật khẩu
  - `POST /api/auth/reset-password` - Đặt lại mật khẩu
  - `PUT /api/auth/profile` - Cập nhật profile
  - `POST /api/auth/change-password` - Đổi mật khẩu
- **Frontend Pages**:
  - Login, Register, Forgot Password, Reset Password
  - Profile Page

### 2. Movie Management
- **Models**: Movie, Category, Format, Subtitle
- **API Routes (Public)**:
  - `GET /api/movies` - Danh sách phim (now showing, coming soon)
  - `GET /api/movies/{id}` - Chi tiết phim
  - `GET /api/movies/{id}/showtimes` - Lịch chiếu theo phim
- **API Routes (Admin)**:
  - CRUD movies + categories + formats + subtitles
- **Frontend Pages**:
  - Home (Now Showing + Coming Soon)
  - Movie Detail
  - Admin Movie Management

### 3. Theater & Screen Management
- **Models**: Theater, Screen, Seat, SeatType
- **API Routes (Public)**:
  - `GET /api/theaters` - Danh sách rạp
  - `GET /api/theaters/{id}` - Chi tiết rạp + screens
- **API Routes (Admin)**:
  - CRUD theaters, screens, seats
  - Upload seat layout
- **Frontend Pages**:
  - Theater List
  - Admin Theater Management

### 4. Showtime Management
- **Models**: Showtime, ShowtimeSeatLayoutSnapshot
- **API Routes (Public)**:
  - `GET /api/showtimes` - Lịch chiếu
  - `GET /api/showtimes/{id}` - Chi tiết suất chiếu + ghế
- **API Routes (Admin)**:
  - CRUD showtimes
  - Snapshot seat layout
- **Frontend Pages**:
  - Showtime Selection
  - Admin Showtime Management

### 5. Seat Selection & Booking
- **Models**: SeatHold
- **API Routes (Auth)**:
  - `GET /api/seats/showtime/{showtimeId}` - Sơ đồ ghế
  - `POST /api/seats/lock` - Giữ ghế tạm thời
  - `DELETE /api/seats/unlock/{holdId}` - Hủy giữ ghế
- **Realtime (Pusher)**:
  - Cập nhật trạng thái ghế realtime
- **Frontend Pages**:
  - Seat Selection UI (interactive seat map)
  - Booking Summary

### 6. Order Management
- **Models**: Order, OrderItem, Payment, Promotion
- **API Routes (Auth)**:
  - `POST /api/orders` - Tạo đơn hàng
  - `GET /api/orders/{id}` - Chi tiết đơn hàng
  - `GET /api/orders/user/me` - Lịch sử đơn hàng
  - `PUT /api/orders/{id}/cancel` - Hủy đơn hàng
- **API Routes (Admin)**:
  - `GET /api/admin/orders` - Tất cả đơn hàng
  - `PUT /api/admin/orders/{id}/status` - Cập nhật trạng thái
- **Frontend Pages**:
  - Checkout Page
  - Order History
  - Order Detail
  - Admin Order Management

### 7. Payment Integration
- **Methods**: VNPAY, Momo, VNPay QR
- **API Routes**:
  - `POST /api/payments` - Tạo thanh toán
  - `POST /api/payments/{id}/verify` - Xác nhận thanh toán
  - `GET /api/payments/{id}/status` - Kiểm tra trạng thái
- **Frontend Pages**:
  - Payment Selection
  - Payment Confirmation

### 8. Promotion & Discount
- **Models**: Promotion, UserPromotion
- **API Routes (Auth)**:
  - `POST /api/promotions/validate` - Kiểm tra mã giảm giá
- **API Routes (Admin)**:
  - CRUD promotions
- **Frontend**:
  - Apply coupon in checkout

## Quy Tắc Clean Code

### 1. Service Layer Pattern
```php
// Controller chỉ gọi Service
class MovieController extends Controller
{
    public function index(Request $request)
    {
        return $this->movieService->getAllMovies($request);
    }
}

// Service chứa business logic
class MovieService
{
    public function getAllMovies(Request $request): array
    {
        // Phân trang, filter, sort
    }
}
```

### 2. Repository Pattern
```php
interface MovieRepositoryInterface
{
    public function find($id);
    public function paginate($perPage, $filters);
}

class MovieRepository implements MovieRepositoryInterface
{
    public function paginate($perPage, $filters)
    {
        return Movie::query()
            ->when($filters['status'], fn($q) => $q->where('status', $filters['status']))
            ->when($filters['category'], fn($q) => $q->whereHas('categories', fn($q) => $q->where('slug', $filters['category'])))
            ->paginate($perPage);
    }
}
```

### 3. API Resources (Transformers)
```php
class MovieResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'poster_url' => $this->poster_url,
            'duration' => $this->duration,
            'age_rating' => $this->age_rating,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
```

### 4. Form Requests (Validation)
```php
class StoreMovieRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'duration' => 'required|integer|min:1',
            'release_date' => 'required|date',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
        ];
    }
}
```

### 5. API Response Format
```json
{
    "success": true,
    "message": "Movies retrieved successfully",
    "data": { ... },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 72
    }
}
```

## Luồng Đặt Vé (Booking Flow)

```
1. User chọn phim → Xem lịch chiếu
2. Chọn suất chiếu → Xem sơ đồ ghế
3. Chọn ghế → Hệ thống hold ghế (5 phút)
4. Áp dụng mã giảm giá (optional)
5. Xác nhận → Tạo Order (status: pending)
6. Chọn phương thức thanh toán
7. Thanh toán thành công → Order status: confirmed
8. Hết thời gian hold → Giải phóng ghế
```

## Realtime (Pusher)

### Channels & Events
```
- seat-hold.{showtimeId}       → SeatLocked event
- seat-released.{showtimeId}   → SeatReleased event
- order-updated.{userId}       → OrderStatusUpdated event
```

## Cấu Trúc Frontend

```
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php          # Main layout (Bootstrap 5)
│   ├── auth/
│   │   ├── login.blade.php
│   │   ├── register.blade.php
│   │   ├── forgot-password.blade.php
│   │   └── reset-password.blade.php
│   ├── profile/
│   │   └── index.blade.php
│   ├── movies/
│   │   ├── index.blade.php        # Now Showing + Coming Soon
│   │   └── show.blade.php         # Movie Detail
│   ├── theaters/
│   │   └── index.blade.php
│   ├── showtimes/
│   │   └── show.blade.php         # Seat Selection
│   ├── orders/
│   │   ├── checkout.blade.php
│   │   ├── history.blade.php
│   │   └── show.blade.php
│   ├── payments/
│   │   └── checkout.blade.php
│   └── admin/
│       ├── layouts/
│       │   └── admin.blade.php
│       ├── movies/
│       │   ├── index.blade.php
│       │   └── form.blade.php
│       ├── theaters/
│       │   ├── index.blade.php
│       │   └── form.blade.php
│       ├── showtimes/
│       │   ├── index.blade.php
│       │   └── form.blade.php
│       └── orders/
│           └── index.blade.php
├── js/
│   ├── app.js                     # Bootstrap JS
│   ├── auth.js                    # Login/Register logic
│   ├── seat-map.js                # Seat selection UI
│   ├── booking.js                 # Booking flow
│   └── admin.js                   # Admin functionality
└── css/
    └── style.css                  # Custom styles
```

## Thứ Tự Ưu Tiên Phát Triển

### Phase 1: Foundation (Hoàn thành)
- [x] Cấu trúc Laravel project
- [x] JWT authentication setup
- [x] Middleware (JWT, Admin)
- [x] API Response Trait
- [x] Database migrations
- [x] User Model + relationships

### Phase 2: Core Features (Đang thực hiện)
- [ ] **2.1 Models & Relationships**
  - [x] Role, Permission, Promotion, Category
  - [x] Format, Subtitle
  - [x] Full Movie model
  - [ ] Full Theater, Screen, Seat models
  - [ ] Full Showtime, Order, Payment models
  - [ ] SeatHold model

- [ ] **2.2 API Controllers**
  - [ ] AuthController (register, login, logout, refresh, me, verify-email, forgot-password, reset-password)
  - [ ] MovieController (CRUD + filters + showtimes)
  - [ ] TheaterController (CRUD + screens)
  - [ ] ScreenController (CRUD + seats)
  - [ ] SeatController (layout, lock/unlock)
  - [ ] ShowtimeController (CRUD + seat snapshots)
  - [ ] OrderController (create, show, cancel, history)
  - [ ] PaymentController (create, verify, status)
  - [ ] PromotionController (validate, CRUD admin)

- [ ] **2.3 Services & Repositories**
  - [ ] AuthService
  - [ ] MovieService
  - [ ] BookingService (seat lock/unlock logic)
  - [ ] OrderService
  - [ ] PaymentService
  - [ ] PromotionService

- [ ] **2.4 API Resources**
  - [ ] MovieResource, CategoryResource
  - [ ] TheaterResource, ScreenResource, SeatResource
  - [ ] ShowtimeResource
  - [ ] OrderResource
  - [ ] UserResource

- [ ] **2.5 Form Requests**
  - [ ] StoreMovieRequest, UpdateMovieRequest
  - [ ] StoreTheaterRequest, StoreScreenRequest
  - [ ] StoreShowtimeRequest
  - [ ] StoreOrderRequest
  - [ ] UpdateProfileRequest

### Phase 3: Frontend - Public Pages
- [ ] Layout chính (Bootstrap 5, responsive)
- [ ] Home page (Now Showing, Coming Soon)
- [ ] Movie Detail page
- [ ] Theater list page
- [ ] Showtime selection
- [ ] Seat selection UI (interactive seat map)
- [ ] Checkout page
- [ ] Order history
- [ ] Order detail

### Phase 4: Frontend - Auth Pages
- [ ] Login
- [ ] Register
- [ ] Forgot Password
- [ ] Reset Password
- [ ] Profile management

### Phase 5: Admin Panel
- [ ] Admin layout (responsive sidebar)
- [ ] Movie management (CRUD + categories)
- [ ] Theater management (CRUD + screens)
- [ ] Showtime management (CRUD)
- [ ] Order management (list, status update)
- [ ] Promotion management (CRUD)

### Phase 6: Advanced Features
- [ ] Realtime seat updates (Pusher)
- [ ] Payment gateway integration (VNPAY, Momo)
- [ ] Email notifications
- [ ] Coupon/promotion validation
- [ ] Search & filter movies
- [ ] SEO optimization

## Kế Hoạch Chi Tiết Theo Ngày

### Session 1: Models & Relationships (2-3 giờ)
1. Cập nhật tất cả Models còn trống
2. Định nghĩa relationships, fillable, casts
3. Tạo Repositories cho từng module

### Session 2: API Controllers Core (3-4 giờ)
1. Viết AuthController hoàn chỉnh
2. Viết MovieController với filters
3. Viết TheaterController, ScreenController
4. Viết ShowtimeController

### Session 3: Booking Logic (3-4 giờ)
1. Viết SeatController (lock/unlock)
2. Viết OrderController
3. Viết PaymentController
4. Viết PromotionController

### Session 4: Frontend Layout (2-3 giờ)
1. Layout chính Bootstrap 5
2. Home page
3. Movie detail page
4. Theater list

### Session 5: Frontend Booking (3-4 giờ)
1. Showtime selection
2. Interactive seat map (Canvas/JS)
3. Checkout page
4. Order history

### Session 6: Frontend Auth + Admin (3-4 giờ)
1. Auth pages (login, register, forgot/reset)
2. Profile management
3. Admin layout + CRUD pages
4. Admin order management

### Session 7: Advanced + Polish (3-4 giờ)
1. Realtime với Pusher
2. Payment integration
3. Testing
4. Deployment preparation
