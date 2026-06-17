# Kế hoạch chuẩn hóa kiến trúc: Blade View Shell + REST API /api/v1

## 1. Quyết định kiến trúc

Project sẽ đi theo hướng:

```text
Laravel Blade View Shell + JavaScript Page Modules + REST API /api/v1
```

### Nguyên tắc tổng quát

```text
Web route = mở trang / render Blade
API route = lấy dữ liệu / xử lý thao tác
Blade = layout, container, skeleton, modal
JavaScript = gọi API, render dữ liệu, xử lý tương tác
Service = xử lý nghiệp vụ
Controller = điều phối request/response, không chứa logic phức tạp
Model = tương tác database
```

---

## 2. Phân vai rõ ràng

## 2.1. Web routes

File:

```text
routes/web.php
```

Chỉ dùng để:

- Render Blade view.
- Redirect.
- Điều hướng route trình duyệt.
- Callback đặc biệt bắt buộc chạy qua web, ví dụ PayOS callback/webhook nếu đang dùng kiểu hiện tại.

Không dùng để:

- Trả JSON chính cho frontend.
- Xử lý CRUD động bằng AJAX nếu đã có API tương ứng.
- Trộn logic nghiệp vụ phức tạp trong closure.

Ví dụ đúng:

```php
Route::view('/movies', 'users.movies.index')->name('movies.index');
Route::view('/admin/movies', 'admin.movies.index')->name('admin.movies.index');
```

Ví dụ nên tránh:

```php
Route::get('/admin/movies/data', function () {
    return response()->json(...);
});
```

---

## 2.2. API routes

File:

```text
routes/api.php
```

Tất cả API chính phải nằm dưới version:

```text
/api/v1
```

API dùng để:

- Auth.
- Home data.
- Movies.
- Showtimes.
- Seats.
- Booking/order.
- Payment.
- Tickets.
- Profile.
- Admin dashboard/stats.
- Admin CRUD.

Ví dụ:

```text
GET    /api/v1/home
GET    /api/v1/movies
GET    /api/v1/movies/{slug}
GET    /api/v1/auth/me
POST   /api/v1/auth/login
POST   /api/v1/orders
POST   /api/v1/payments
GET    /api/v1/admin/dashboard/stats
POST   /api/v1/admin/movies
PUT    /api/v1/admin/movies/{id}
DELETE /api/v1/admin/movies/{id}
```

---

## 2.3. Blade views

Blade chỉ nên chứa:

- Layout chính.
- HTML khung.
- Container để JS render dữ liệu.
- Skeleton/loading state.
- Modal/form markup.
- Data attribute tối thiểu nếu cần truyền id ban đầu.
- `@push('scripts')` để load JS page.

Blade không nên chứa:

- Logic xử lý dữ liệu phức tạp.
- Query database trực tiếp.
- Render danh sách lớn nếu dữ liệu đó sẽ được load qua API.
- Inline JavaScript lớn.

Ví dụ:

```blade
<div id="moviesGrid" class="row" data-loading="true"></div>

@push('scripts')
<script src="{{ asset('js/pages/movies.js') }}"></script>
@endpush
```

---

## 2.4. JavaScript

JavaScript chịu trách nhiệm:

- Gọi API qua API client chung.
- Render dữ liệu vào DOM.
- Xử lý form submit.
- Xử lý loading/error/empty state.
- Xử lý toast/alert.
- Không hard-code `/api` lung tung.

Tất cả JS phải lấy base API từ:

```js
window.APP_CONFIG.apiUrl
```

Mặc định:

```js
/api/v1
```

---

## 3. Quy tắc API URL

## 3.1. Chỉ dùng API versioned

Chuẩn chính:

```text
/api/v1/...
```

Không dùng trong code mới:

```text
/api/home
/api/auth/me
/api/movies
```

Các route legacy nếu có chỉ là tạm thời để tránh lỗi cache và sẽ gỡ sau.

---

## 3.2. Không hard-code base API trong từng file

Không viết:

```js
fetch('/api/v1/movies')
fetch('/api/movies')
fetch('http://localhost:8000/api/home')
```

Phải viết:

```js
window.apiClient.get('/movies')
```

hoặc nếu chưa refactor sang apiClient:

```js
const API_BASE = window.APP_CONFIG?.apiUrl || '/api/v1';
fetch(`${API_BASE}/movies`)
```

---

## 4. API response format

Tất cả API nên trả format thống nhất.

### Thành công

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

### Danh sách có phân trang

```json
{
  "success": true,
  "message": "OK",
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

### Lỗi validation

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email không hợp lệ"]
  }
}
```

### Lỗi thường

```json
{
  "success": false,
  "message": "Không tìm thấy dữ liệu"
}
```

Project đã có:

```text
app/Traits/ApiResponse.php
```

Nên ưu tiên dùng trait này trong API controller.

---

## 5. Quy tắc Auth

## 5.1. User auth

Flow:

```text
Blade hiển thị modal login/register
auth.js gọi POST /api/v1/auth/login
Server xác thực và set cookie/token
auth.js gọi GET /api/v1/auth/me
Header UI cập nhật trạng thái đăng nhập
```

Các endpoint chính:

```text
POST /api/v1/auth/login
POST /api/v1/auth/register
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

---

## 5.2. Admin auth

Flow:

```text
Admin truy cập /admin/dashboard
Middleware admin kiểm tra quyền
Blade admin render page
Admin JS gọi /api/v1/admin/...
API admin kiểm tra auth + role
```

Admin API phải có middleware:

```text
auth + role/admin
```

---

## 6. Quy tắc tổ chức file

## 6.1. Views

```text
resources/views/
├── layouts/
│   ├── app.blade.php
│   └── admin.blade.php
├── users/
│   ├── home.blade.php
│   ├── movies/
│   ├── booking/
│   ├── payment/
│   └── profile/
└── admin/
    ├── dashboard.blade.php
    ├── movies/
    ├── theaters/
    ├── showtimes/
    ├── revenue/
    └── tickets/
```

---

## 6.2. JavaScript

```text
public/js/
├── core/
│   ├── api-client.js
│   ├── dom.js
│   ├── toast.js
│   └── format.js
├── auth.js
├── app.js
└── pages/
    ├── home.js
    ├── movies.js
    ├── movie-detail.js
    ├── booking.js
    ├── payment.js
    ├── profile.js
    └── admin/
        ├── dashboard.js
        ├── movies.js
        ├── theaters.js
        ├── showtimes.js
        ├── revenue.js
        └── tickets.js
```

---

## 6.3. Controllers dài hạn

Có thể refactor dần sang cấu trúc:

```text
app/Http/Controllers/
├── Web/
│   ├── HomePageController.php
│   ├── BookingPageController.php
│   ├── PaymentPageController.php
│   └── Admin/
└── Api/
    └── V1/
        ├── HomeController.php
        ├── MovieController.php
        ├── ShowtimeController.php
        ├── SeatController.php
        ├── OrderController.php
        ├── PaymentController.php
        └── Admin/
```

Không bắt buộc làm ngay vì project hiện tại đã có nhiều controller đang dùng chung.

---

# 7. Lộ trình thực hiện

## Phase 1: Chuẩn hóa nền tảng frontend/API

Mục tiêu:

- Có API client chung.
- Layout user/admin load API client.
- JS có điểm gọi API thống nhất.
- Không phá chức năng hiện tại.

Việc cần làm:

- [ ] Tạo `public/js/core/api-client.js`.
- [ ] Load `api-client.js` trong `layouts/app.blade.php`.
- [ ] Load `api-client.js` trong `layouts/admin.blade.php`.
- [ ] Đảm bảo `window.APP_CONFIG.apiUrl = '/api/v1'`.
- [ ] Kiểm tra các hard-code `/api` trong JS.
- [ ] Refactor nhẹ các file quan trọng để dùng apiClient nếu ít rủi ro.

---

## Phase 2: Chuẩn hóa User JS

Thứ tự ưu tiên:

- [ ] `public/js/auth.js`
- [ ] `public/js/pages/home.js`
- [ ] `public/js/pages/movies.js`
- [ ] `public/js/pages/movie-detail.js`
- [ ] `public/js/pages/booking.js`
- [ ] `public/js/pages/payment.js`
- [ ] `public/js/pages/profile.js`

Quy tắc:

- Không hard-code `/api`.
- Dùng `window.apiClient`.
- Xử lý loading/error/empty state rõ ràng.
- Không duplicate hàm fetch.

---

## Phase 3: Chuẩn hóa Admin JS

Thứ tự ưu tiên:

- [ ] `public/js/pages/admin/dashboard.js`
- [ ] `public/js/pages/admin/revenue.js`
- [ ] `public/js/pages/admin/ticket_stats.js`
- [ ] `public/js/pages/admin/combo_stats.js`
- [ ] `public/js/pages/admin/branches.js`
- [ ] `public/js/pages/admin/theaters.js`
- [ ] `public/js/pages/admin/seat-layout-templates.js`

Quy tắc:

- Admin page vẫn là Blade.
- Data/stat/CRUD đi qua `/api/v1/admin/...`.
- Không dùng web resource route để trả JSON mới.

---

## Phase 4: Chuẩn hóa routes

Việc cần làm:

- [ ] Rà soát `routes/web.php`.
- [ ] Giữ web route cho render view.
- [ ] Rà soát `routes/api.php`.
- [ ] Đưa endpoint CRUD/data về `/api/v1`.
- [ ] Giữ legacy `/api/...` tạm thời nếu cần tương thích cache.
- [ ] Gỡ legacy sau khi frontend đã chuẩn hóa.

---

## Phase 5: Chuẩn hóa controller/service

Việc cần làm:

- [ ] API controller trả JSON format thống nhất.
- [ ] Controller mỏng, nghiệp vụ đưa vào Service.
- [ ] Dùng `ApiResponse` trait.
- [ ] Tách Web/API controller khi module đủ lớn hoặc đang gây lẫn trách nhiệm.

---

## Phase 6: Kiểm thử và cleanup

Việc cần làm:

- [ ] Chạy `php -l` cho file PHP đã sửa.
- [ ] Chạy route list kiểm tra endpoint chính.
- [ ] Test các trang user chính.
- [ ] Test các trang admin chính.
- [ ] Gỡ route legacy nếu không còn cần.
- [ ] Update tài liệu kiến trúc.

---

# 8. Definition of Done

Một module được xem là chuẩn Hướng 2 khi đạt đủ:

- [ ] Web route chỉ mở trang.
- [ ] Blade chỉ dựng shell/container.
- [ ] JS gọi API qua `window.apiClient`.
- [ ] API nằm dưới `/api/v1`.
- [ ] Response JSON thống nhất.
- [ ] Không hard-code `/api` trong JS.
- [ ] Không gọi web route để lấy JSON.
- [ ] Auth/CSRF/credentials được xử lý qua API client.
- [ ] Có xử lý loading/error/empty state.
