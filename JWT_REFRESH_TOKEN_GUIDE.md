php s# JWT + Refresh Token Authentication Guide

## 📋 Tổng Quan

Hệ thống authentication của Cinema sử dụng kiến trúc **Access Token + Refresh Token** với JWT (JSON Web Tokens) để đảm bảo bảo mật cao và trải nghiệm người dùng tốt.

### Kiến Trúc

```
┌─────────────┐         ┌──────────────┐         ┌──────────────┐
│   Client    │         │   Laravel    │         │   Database   │
│  (Browser)  │         │   Backend    │         │              │
└──────┬──────┘         └──────┬───────┘         └──────┬───────┘
       │                       │                        │
       │  1. Login Request     │                        │
       ├──────────────────────>│                        │
       │                       │  2. Validate User      │
       │                       ├───────────────────────>│
       │                       │<───────────────────────┤
       │                       │  3. Create Tokens      │
       │                       │    - Access Token      │
       │                       │    - Refresh Token     │
       │                       ├───────────────────────>│
       │  4. Response          │                        │
       │  - access_token (JSON)│                        │
       │  - refresh_token      │                        │
       │    (HttpOnly Cookie)  │                        │
       │<──────────────────────┤                        │
       │                       │                        │
       │  5. API Request       │                        │
       │  + Authorization:     │                        │
       │    Bearer {access}    │                        │
       ├──────────────────────>│  6. Verify Token       │
       │                       ├───────────────────────>│
       │                       │<───────────────────────┤
       │  7. Response          │                        │
       │<──────────────────────┤                        │
       │                       │                        │
       │  8. Access Expired    │                        │
       │  (401 Unauthorized)   │                        │
       │<──────────────────────┤                        │
       │                       │                        │
       │  9. Refresh Request   │                        │
       │  + Cookie: refresh    │                        │
       ├──────────────────────>│ 10. Verify Refresh     │
       │                       ├───────────────────────>│
       │                       │<───────────────────────┤
       │ 11. New Access Token  │                        │
       │<──────────────────────┤                        │
```

### Đặc Điểm Chính

✅ **Access Token (Short-lived)**
- Thời gian sống: 15 phút
- Lưu trữ: localStorage
- Sử dụng: Authorization header
- Mục đích: Xác thực API requests

✅ **Refresh Token (Long-lived)**
- Thời gian sống: 30 ngày
- Lưu trữ: HttpOnly Cookie (không thể truy cập từ JavaScript)
- Sử dụng: Tự động gửi với mọi request
- Mục đích: Làm mới access token

✅ **Security Features**
- HttpOnly cookies chống XSS
- Token rotation (refresh token mới sau mỗi lần refresh)
- Device tracking
- Automatic token refresh
- Secure logout (xóa cả 2 tokens)

---

## 🔧 Cấu Hình

### 1. Environment Variables (.env)

```env
# JWT Configuration
JWT_SECRET=your-secret-key-here-min-32-chars
JWT_TTL=15                    # Access token: 15 minutes
JWT_REFRESH_TTL=20160         # Refresh token: 14 days (in minutes)
JWT_ALGO=HS256
JWT_SHOW_BLACKLIST_EXCEPTION=true

# Refresh Token Configuration
REFRESH_TOKEN_TTL=30          # 30 days

# Cookie Security
SESSION_SECURE=false          # Set true in production (HTTPS only)
SESSION_SAME_SITE=lax         # lax, strict, or none

# Authentication Guard
AUTH_GUARD=api
```

### 2. Database Migration

Migration đã được tạo: `database/migrations/2026_06_01_163000_create_refresh_tokens_table.php`

```bash
php artisan migrate
```

Bảng `refresh_tokens`:
- `id`: Primary key
- `user_id`: Foreign key to users
- `token`: Hashed refresh token
- `device_name`: Tên thiết bị
- `ip_address`: IP address
- `user_agent`: Browser/device info
- `expires_at`: Thời gian hết hạn
- `last_used_at`: Lần sử dụng cuối
- `created_at`, `updated_at`

### 3. Config Files

**config/auth.php**
```php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],

'refresh_token_ttl' => env('REFRESH_TOKEN_TTL', 30),
```

---

## 🔐 Backend Implementation

### AuthService (app/Services/AuthService.php)

Service chính xử lý authentication logic:

**Key Methods:**
- `login()`: Đăng nhập, tạo tokens
- `register()`: Đăng ký user mới
- `logout()`: Đăng xuất, xóa tokens
- `refresh()`: Làm mới access token
- `me()`: Lấy thông tin user hiện tại

**Token Management:**
- `createAccessToken()`: Tạo JWT access token
- `createRefreshToken()`: Tạo refresh token và lưu DB
- `rotateRefreshToken()`: Xoay refresh token (tạo mới, xóa cũ)
- `revokeRefreshToken()`: Thu hồi refresh token

### AuthController (app/Http/Controllers/AuthController.php)

Controller xử lý HTTP requests:

**Endpoints:**
- `POST /api/auth/login`: Đăng nhập
- `POST /api/auth/register`: Đăng ký
- `POST /api/auth/logout`: Đăng xuất
- `POST /api/auth/refresh`: Làm mới token
- `GET /api/auth/me`: Thông tin user

**Response Format:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "expires_in": 900
    }
}
```

### RefreshToken Model (app/Models/RefreshToken.php)

Eloquent model cho bảng refresh_tokens:

**Relationships:**
- `belongsTo(User::class)`

**Scopes:**
- `valid()`: Chỉ lấy tokens chưa hết hạn
- `forUser($userId)`: Lọc theo user

**Methods:**
- `isExpired()`: Kiểm tra hết hạn
- `updateLastUsed()`: Cập nhật thời gian sử dụng

---

## 🌐 Frontend Integration

### AuthManager (public/js/auth.js)

JavaScript class quản lý authentication ở client:

**Key Features:**

1. **Automatic Token Refresh**
```javascript
async fetchAPI(endpoint, options = {}) {
    // Tự động thêm credentials: 'include' để gửi cookies
    config.credentials = 'include';
    
    // Nếu nhận 401, tự động refresh token
    if (response.status === 401) {
        const refreshed = await this.refreshAccessToken();
        if (refreshed) {
            // Retry request với token mới
        }
    }
}
```

2. **Token Storage**
```javascript
// Access token lưu trong localStorage
saveToken(token) {
    localStorage.setItem('auth_token', token);
}

// Refresh token tự động lưu trong HttpOnly cookie (backend)
```

3. **Login Flow**
```javascript
async handleLogin(e) {
    const response = await this.fetchAPI('/auth/login', {
        method: 'POST',
        body: JSON.stringify(credentials)
    });
    
    // Lưu access_token
    this.saveToken(response.data.access_token);
    
    // Refresh token đã được set trong cookie bởi backend
}
```

### Usage Example

```javascript
// Gọi protected API
const response = await window.authManager.fetchAPI('/auth/me');

// Nếu access token hết hạn:
// 1. fetchAPI tự động gọi refreshAccessToken()
// 2. Lấy token mới từ refresh token (cookie)
// 3. Retry request ban đầu
// 4. Trả về kết quả
```

---

## 🔄 Authentication Flow

### 1. Login Flow

```
User Input Credentials
        ↓
Frontend: POST /api/auth/login
        ↓
Backend: Validate credentials
        ↓
Backend: Create access_token (JWT, 15min)
        ↓
Backend: Create refresh_token (Random, 30 days)
        ↓
Backend: Save refresh_token to database
        ↓
Backend: Set refresh_token in HttpOnly cookie
        ↓
Backend: Return access_token in JSON
        ↓
Frontend: Save access_token to localStorage
        ↓
Frontend: Update UI (show user info)
```

### 2. API Request Flow

```
Frontend: Make API request
        ↓
Frontend: Add Authorization: Bearer {access_token}
        ↓
Frontend: Add credentials: 'include' (send cookies)
        ↓
Backend: Verify access_token
        ↓
Backend: Check expiration
        ↓
[If Valid]
Backend: Process request
Backend: Return response
        ↓
[If Expired - 401]
Frontend: Detect 401 error
        ↓
Frontend: Call /api/auth/refresh
        ↓
Backend: Read refresh_token from cookie
        ↓
Backend: Verify refresh_token in database
        ↓
Backend: Create new access_token
        ↓
Backend: Rotate refresh_token (optional)
        ↓
Backend: Return new access_token
        ↓
Frontend: Save new access_token
        ↓
Frontend: Retry original request
```

### 3. Logout Flow

```
User clicks Logout
        ↓
Frontend: POST /api/auth/logout
        ↓
Backend: Read refresh_token from cookie
        ↓
Backend: Delete refresh_token from database
        ↓
Backend: Clear refresh_token cookie
        ↓
Backend: Return success
        ↓
Frontend: Clear access_token from localStorage
        ↓
Frontend: Redirect to home
```

---

## 🧪 Testing Guide

### 1. Test Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "login": "user@example.com",
    "password": "password123"
  }' \
  -c cookies.txt
```

Response:
```json
{
    "success": true,
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "user": {...}
    }
}
```

### 2. Test Protected Endpoint

```bash
curl -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer {access_token}" \
  -b cookies.txt
```

### 3. Test Token Refresh

```bash
curl -X POST http://localhost/api/auth/refresh \
  -b cookies.txt \
  -c cookies.txt
```

### 4. Test Logout

```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Authorization: Bearer {access_token}" \
  -b cookies.txt
```

---

## 🛡️ Security Best Practices

### 1. Token Security

✅ **DO:**
- Sử dụng HTTPS trong production
- Set `SESSION_SECURE=true` khi dùng HTTPS
- Sử dụng strong JWT secret (min 32 chars)
- Rotate refresh tokens sau mỗi lần sử dụng
- Giới hạn số lượng refresh tokens per user

❌ **DON'T:**
- Lưu refresh token trong localStorage
- Gửi refresh token qua URL parameters
- Sử dụng access token quá dài (>30 min)
- Bỏ qua CSRF protection

### 2. Cookie Configuration

Production settings:
```env
SESSION_SECURE=true           # HTTPS only
SESSION_SAME_SITE=strict      # Strict CSRF protection
SESSION_HTTP_ONLY=true        # Prevent XSS (default)
```

### 3. Rate Limiting

Routes đã được bảo vệ bởi throttle middleware:
```php
Route::prefix('auth')
    ->middleware('throttle:auth')  // 5 requests/minute
    ->group(function () {
        Route::post('login', ...);
        Route::post('register', ...);
    });
```

### 4. Token Cleanup

Tạo scheduled job để xóa expired tokens:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        RefreshToken::where('expires_at', '<', now())->delete();
    })->daily();
}
```

---

## 🐛 Troubleshooting

### Issue 1: "Token has expired"

**Cause:** Access token hết hạn (15 phút)

**Solution:** Frontend tự động refresh. Nếu không:
```javascript
await window.authManager.refreshAccessToken();
```

### Issue 2: "Refresh token not found"

**Cause:** 
- Cookie không được gửi (thiếu `credentials: 'include'`)
- Refresh token đã hết hạn (30 ngày)
- User đã logout

**Solution:**
- Kiểm tra `credentials: 'include'` trong fetch
- Yêu cầu user login lại

### Issue 3: "CORS error with cookies"

**Cause:** CORS configuration không cho phép credentials

**Solution:** Backend config:
```php
// config/cors.php
'supports_credentials' => true,
'allowed_origins' => ['http://localhost:3000'],
```

### Issue 4: "Token mismatch"

**Cause:** JWT secret key thay đổi

**Solution:**
- Đảm bảo `JWT_SECRET` không thay đổi
- Nếu cần thay đổi, logout tất cả users

---

## 📊 Monitoring & Logging

### Login History

Mỗi login được log vào bảng `login_histories`:
```php
LoginHistory::create([
    'user_id' => $user->id,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'login_at' => now(),
    'status' => 'success'
]);
```

### Refresh Token Usage

Track last_used_at để phát hiện suspicious activity:
```php
$refreshToken->update([
    'last_used_at' => now()
]);
```

### Security Alerts

Implement alerts cho:
- Multiple failed login attempts
- Login from new device/location
- Unusual refresh token usage patterns

---

## 🚀 Deployment Checklist

### Production Environment

- [ ] Set `JWT_SECRET` to strong random string (32+ chars)
- [ ] Set `SESSION_SECURE=true`
- [ ] Set `SESSION_SAME_SITE=strict`
- [ ] Enable HTTPS
- [ ] Configure CORS properly
- [ ] Set up token cleanup job
- [ ] Enable rate limiting
- [ ] Configure monitoring/logging
- [ ] Test all authentication flows
- [ ] Document API for frontend team

### Performance Optimization

- [ ] Add database indexes on refresh_tokens table
- [ ] Implement Redis for token blacklist (optional)
- [ ] Cache user data to reduce DB queries
- [ ] Monitor token refresh frequency

---

## 📚 API Reference

### POST /api/auth/login
Đăng nhập user

**Request:**
```json
{
    "login": "user@example.com",
    "password": "password123",
    "remember": true
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {...},
        "access_token": "eyJ0eXAi...",
        "token_type": "Bearer",
        "expires_in": 900
    }
}
```

### POST /api/auth/refresh
Làm mới access token

**Request:** (refresh token tự động gửi qua cookie)

**Response:**
```json
{
    "success": true,
    "data": {
        "access_token": "eyJ0eXAi...",
        "token_type": "Bearer",
        "expires_in": 900,
        "user": {...}
    }
}
```

### POST /api/auth/logout
Đăng xuất user

**Headers:**
```
Authorization: Bearer {access_token}
```

**Response:**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

### GET /api/auth/me
Lấy thông tin user hiện tại

**Headers:**
```
Authorization: Bearer {access_token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "user"
    }
}
```

---

## 🎯 Summary

Hệ thống JWT + Refresh Token đã được implement hoàn chỉnh với:

✅ **Backend:**
- AuthService với đầy đủ token management
- RefreshToken model và migration
- Secure cookie handling
- Token rotation
- Device tracking

✅ **Frontend:**
- Automatic token refresh
- Secure token storage
- Seamless user experience
- Error handling

✅ **Security:**
- HttpOnly cookies
- Short-lived access tokens
- Token rotation
- Rate limiting
- CORS protection

✅ **Documentation:**
- Complete API reference
- Security best practices
- Troubleshooting guide
- Deployment checklist

Hệ thống sẵn sàng cho production! 🚀
