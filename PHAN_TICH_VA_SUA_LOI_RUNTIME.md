# 🐛 Phân Tích và Sửa Lỗi Runtime

**Ngày Báo Cáo:** 10/6/2026  
**Lỗi Phát Hiện:** 2 lỗi chính  
**Mức Độ:** Medium - High Priority

---

## 📋 Tóm Tắt Lỗi Phát Hiện

### Lỗi #1: 401 Unauthorized - Auth/Me Endpoint
```
GET http://127.0.0.1:8000/api/v1/auth/me 401 (Unauthorized)
File: auth.js:274
Function: fetchAPI()
```

### Lỗi #2: 404 Not Found - Showtimes Endpoint
```
GET http://127.0.0.1:8000/[...] 404 (Not Found)
Vấn đề: Không hiển thị suất chiếu (showtimes)
```

---

## 🔍 PHÂN TÍCH CHI TIẾT

### LỖI #1: Auth/Me - 401 Unauthorized

#### Nguyên Nhân Có Thể:

1. **Token JWT hết hạn**
   - Access token có thời hạn 1 giờ
   - Cần refresh token mới
   
2. **Token không được gửi đúng**
   - Token phải ở header: `Authorization: Bearer {token}`
   - Có thể thiếu `Bearer ` prefix
   
3. **Token không được lưu trong localStorage**
   - Sau khi đăng nhập, token phải lưu
   - Cookie refresh token cũng cần HttpOnly
   
4. **Middleware JwtMiddleware không hoạt động**
   - Có thể middleware bị bỏ qua
   - Route không được bảo vệ đúng

#### Cách Kiểm Tra:

**1. Kiểm Tra localStorage:**
```javascript
// Mở DevTools Console
localStorage.getItem('access_token')
localStorage.getItem('refresh_token')
```

**2. Kiểm Tra Header Request:**
```javascript
// Xem network tab
// Authorization header phải có: Bearer eyJ...
```

**3. Kiểm Tra Token Format:**
```javascript
// Token phải có 3 phần: header.payload.signature
const token = localStorage.getItem('access_token')
console.log(token.split('.').length) // Phải = 3
```

#### Giải Pháp:

**A. Kiểm Tra auth.js - Gửi Token Đúng:**
```javascript
// auth.js - fetchAPI function
const fetchAPI = (endpoint, options = {}) => {
    const token = localStorage.getItem('access_token');
    
    // ✅ ĐÚNG: Thêm token vào header
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (token) {
        headers['Authorization'] = `Bearer ${token}`; // ⭐ Quan trọng
    }
    
    return fetch(endpoint, {
        ...options,
        headers
    });
};
```

**B. Kiểm Tra Login Response:**
```javascript
// Sau khi đăng nhập, phải lưu token
const loginResponse = await response.json();
if (loginResponse.data && loginResponse.data.access_token) {
    localStorage.setItem('access_token', loginResponse.data.access_token);
}
```

**C. Kiểm Tra Middleware Route (Backend):**
```php
// routes/api.php - /auth/me route
Route::middleware('jwt')->get('/auth/me', [AuthController::class, 'me']);

// ✅ ĐÚNG - Có middleware 'jwt'
// ❌ SAI - Không có middleware
```

### LỖI #2: Showtimes - 404 Not Found

#### Nguyên Nhân Có Thể:

1. **Endpoint URL sai**
   - URL chứa ký tự đặc biệt chưa được encode
   - Có thể thiếu query parameter
   
2. **Route không được định nghĩa**
   - `/api/v1/showtimes` route không tồn tại
   - Hoặc path khác với definition
   
3. **Movie ID không hợp lệ**
   - Nếu endpoint là `/api/v1/movies/{id}/showtimes`
   - Movie ID có thể không tồn tại
   
4. **Database không có dữ liệu**
   - Showtimes chưa được tạo
   - Movie chưa được seeded

#### Cách Kiểm Tra:

**1. Kiểm Tra Network Tab:**
```
Request URL: http://127.0.0.1:8000/api/v1/... (xem đầy đủ URL)
Method: GET
Status: 404
```

**2. Test Endpoint Trực Tiếp:**
```bash
# Terminal - test API
curl -X GET http://127.0.0.1:8000/api/v1/showtimes

# Hoặc với movie ID
curl -X GET http://127.0.0.1:8000/api/v1/movies/1/showtimes
```

**3. Kiểm Tra Routes Definition:**
```php
// routes/api.php
Route::get('/showtimes', [ShowtimeController::class, 'index']);
Route::get('/movies/{movie}/showtimes', [ShowtimeController::class, 'byMovie']);
```

#### Giải Pháp:

**A. Kiểm Tra Frontend Code:**
```javascript
// Tìm chỗ gọi showtimes API
// Ví dụ: /api/v1/showtimes
const fetchShowtimes = async (movieId) => {
    try {
        // ✅ ĐÚNG URLs:
        // GET /api/v1/showtimes
        // GET /api/v1/movies/{movieId}/showtimes
        // GET /api/v1/showtimes?movie_id={movieId}
        
        const response = await fetch(
            `/api/v1/movies/${movieId}/showtimes`,
            {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            }
        );
        
        if (response.status === 404) {
            console.log('❌ Route không tồn tại');
            return;
        }
        
        return await response.json();
    } catch (error) {
        console.error('Error:', error);
    }
};
```

**B. Kiểm Tra Backend Routes:**
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // ✅ Showtimes routes
    Route::get('/showtimes', [ShowtimeController::class, 'index']);
    Route::get('/movies/{movie}/showtimes', 
        [ShowtimeController::class, 'byMovie']);
    Route::get('/showtimes/{showtime}', 
        [ShowtimeController::class, 'show']);
});
```

**C. Kiểm Tra ShowtimeController:**
```php
class ShowtimeController extends Controller
{
    // ✅ Phải có method này
    public function index()
    {
        $showtimes = Showtime::with(['movie', 'screen'])
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->paginate(20);
            
        return $this->successResponse($showtimes);
    }
    
    public function byMovie(Movie $movie)
    {
        $showtimes = $movie->showtimes()
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->get();
            
        return $this->successResponse($showtimes);
    }
}
```

**D. Kiểm Tra Database Seeding:**
```php
// database/seeders/ShowtimeSeeder.php
// Phải có data trong database
Showtime::create([
    'movie_id' => 1,
    'screen_id' => 1,
    'start_time' => Carbon::now()->addHours(2),
    'end_time' => Carbon::now()->addHours(4),
]);
```

---

## 🔧 QƯỚC TRÌNH KHẮC PHỤC

### Bước 1: Kiểm Tra Đăng Nhập (Lỗi #1)

```bash
# 1. Xóa localStorage (reset trạng thái)
# Mở DevTools Console, gõ:
localStorage.clear()

# 2. F5 reload page
# 3. Đăng nhập lại

# 4. Kiểm Tra token:
console.log(localStorage.getItem('access_token'))
```

### Bước 2: Kiểm Tra Routes (Lỗi #2)

```bash
# Terminal - List tất cả routes
php artisan route:list | grep -i showtime

# Hoặc tìm /api/v1
php artisan route:list | grep /api/v1
```

### Bước 3: Test API Trực Tiếp

```bash
# 1. Lấy token từ login
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password"}'

# 2. Copy access_token từ response

# 3. Test /auth/me
curl -X GET http://127.0.0.1:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"

# 4. Test showtimes
curl -X GET http://127.0.0.1:8000/api/v1/showtimes \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Bước 4: Kiểm Tra Frontend Code

```javascript
// auth.js - Dòng 274
// Kiểm Tra function fetchAPI gửi token đúng

const checkAuthStatus = async () => {
    try {
        const response = await fetchAPI('/api/v1/auth/me');
        
        if (response.status === 401) {
            console.log('❌ Token hết hạn hoặc không hợp lệ');
            // Cần refresh token
            return;
        }
        
        const userData = await response.json();
        setUser(userData.data);
    } catch (error) {
        console.error('Auth check failed:', error);
    }
};
```

---

## ✅ DANH SÁCH KIỂM TRA

### Cho Lỗi #1 (401):
- [ ] Token có tồn tại trong localStorage?
- [ ] Token được gửi với `Bearer ` prefix?
- [ ] JwtMiddleware được áp dụng trên route?
- [ ] Token chưa hết hạn?
- [ ] Refresh token mechanism hoạt động?

### Cho Lỗi #2 (404):
- [ ] Route `/api/v1/showtimes` có tồn tại?
- [ ] Method là GET không?
- [ ] ShowtimeController có method?
- [ ] Database có dữ liệu showtimes?
- [ ] Query parameters đúng không?

---

## 📝 GIẢI PHÁP TẠM THỜI

### Nếu Token Hết Hạn - Implement Refresh Logic:

```javascript
// auth.js - Add token refresh
const fetchAPI = async (endpoint, options = {}) => {
    let token = localStorage.getItem('access_token');
    
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    let response = await fetch(endpoint, {
        ...options,
        headers
    });
    
    // Nếu 401, cố gắng refresh token
    if (response.status === 401) {
        const refreshToken = localStorage.getItem('refresh_token');
        if (refreshToken) {
            // Gọi refresh endpoint
            const refreshResponse = await fetch('/api/v1/auth/refresh', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${refreshToken}`
                }
            });
            
            if (refreshResponse.ok) {
                const data = await refreshResponse.json();
                localStorage.setItem('access_token', data.data.access_token);
                
                // Retry original request
                headers['Authorization'] = `Bearer ${data.data.access_token}`;
                response = await fetch(endpoint, {
                    ...options,
                    headers
                });
            }
        }
    }
    
    return response;
};
```

### Nếu Showtimes Không Hiển Thị - Seed Data:

```bash
# Terminal
php artisan db:seed --class=ShowtimeSeeder

# Hoặc refresh lại seed
php artisan migrate:refresh --seed
```

---

## 📞 KỊP THEO DÕI

1. **Lỗi #1 - 401 Unauthorized:**
   - Nguyên nhân: Token không được gửi hoặc hết hạn
   - Giải pháp: Kiểm tra auth.js, implement refresh token
   - Thời gian: 1 giờ

2. **Lỗi #2 - 404 Not Found:**
   - Nguyên nhân: Route không định nghĩa hoặc data không có
   - Giải pháp: Kiểm tra routes, seed data
   - Thời gian: 30 phút

---

**Báo Cáo Lỗi:** 10/6/2026  
**Trạng Thái:** Cần Khắc Phục Ngay  
**Ưu Tiên:** Medium - High