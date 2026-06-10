# ✅ SỬA LỖI PROFILE AUTHENTICATION

**Ngày:** 10/6/2026  
**Trạng Thái:** FIXED  
**Lỗi:** Profile page yêu cầu đăng nhập dù đã login

---

## 🔍 ROOT CAUSE

### Vấn Đề
User đã đăng nhập (có JWT token) nhưng khi truy cập `/profile` vẫn bị yêu cầu đăng nhập lại.

### Nguyên Nhân
```php
// routes/web.php - BEFORE
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
Route::get('/my-tickets', [ProfileController::class, 'tickets'])->name('tickets.index');
```

**Issues:**
1. ❌ Không có authentication middleware
2. ❌ Web routes dùng session auth (default guard: 'web')
3. ❌ App dùng JWT auth (stored in HttpOnly cookie)
4. ❌ CookieToBearerToken middleware chỉ apply cho API routes

### Authentication Flow Analysis

**API Routes (Working):**
```
Request → CookieToBearerToken → auth:api → Controller
         ↓
    Cookie → Bearer Token → JWT Verify → Success
```

**Web Routes `/profile` (Broken):**
```
Request → auth (default: session) → Fail
         ↓
    No session → Redirect to login
```

---

## 🔧 FIX APPLIED

### File: `routes/web.php`

**BEFORE:**
```php
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
Route::get('/my-tickets', [ProfileController::class, 'tickets'])->name('tickets.index');
```

**AFTER:**
```php
// Protected routes - require JWT authentication
Route::middleware([\App\Http\Middleware\CookieToBearerToken::class, 'auth:api'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('/my-tickets', [ProfileController::class, 'tickets'])->name('tickets.index');
});
```

### Middleware Chain

**New Flow:**
```
1. CookieToBearerToken: access_token cookie → Authorization: Bearer {token}
2. auth:api: Verify JWT token
3. If valid → Allow access to ProfileController
4. If invalid → 401 Unauthorized
```

---

## ✅ VERIFICATION

### Test Steps

1. **Login first:**
   ```
   POST /api/v1/auth/login
   Body: { email, password }
   → Returns JWT in access_token cookie
   ```

2. **Access profile:**
   ```
   GET /profile
   → Should load profile page (not redirect to login)
   ```

3. **Check cookie:**
   ```javascript
   // In DevTools Console
   document.cookie.includes('access_token')
   // Should return true if logged in
   ```

### Expected Behavior

**Before Fix:**
```
✓ User logs in → JWT token saved
✗ Access /profile → Redirected to login (session check fails)
```

**After Fix:**
```
✓ User logs in → JWT token saved in cookie
✓ Access /profile → CookieToBearerToken converts cookie
✓ auth:api verifies JWT → Success
✓ Profile page loads
```

---

## 🏗️ ARCHITECTURE NOTES

### Authentication System

**Guards:**
```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',  // Traditional session auth
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',      // JWT auth (tymon/jwt-auth)
        'provider' => 'users',
    ],
],
```

**JWT Flow:**
1. Login → Server generates JWT
2. JWT stored in HttpOnly cookie `access_token`
3. Frontend auto-sends cookie with requests
4. Backend validates JWT

**Why HttpOnly Cookie?**
- ✅ XSS protection (JavaScript cannot access)
- ✅ CSRF protection (with SameSite flag)
- ✅ Automatic inclusion in requests
- ✅ Secure storage

---

## 📋 RELATED ROUTES

### Public Routes (No Auth)
```php
Route::get('/', [HomeController::class, 'index']);
Route::view('/movies', 'users.movies.index');
Route::view('/movies/{idOrSlug}', 'users.movies.show');
```

### Protected Routes (Auth Required)
```php
// Web
Route::get('/profile', ...)         // Fixed ✓
Route::get('/my-tickets', ...)      // Fixed ✓

// API
Route::get('/api/v1/profile', ...)  // Already working ✓
Route::get('/api/v1/orders', ...)   // Already working ✓
```

### Special Routes (No Auth)
```php
Route::get('/booking/{id}', ...)      // Guest can start booking
Route::get('/payment/payos/callback', ...)  // Payment callback
```

---

## ⚠️ IMPORTANT NOTES

### 1. Booking Flow
```php
// /booking/{id} - No auth required initially
// User can view seats and prices
// Auth required when clicking "Chọn ghế" (handled by JavaScript)
```

### 2. 401 Handling
When JWT invalid/expired:
- API calls → JSON 401 response
- Web pages → May need custom handler to redirect

**Potential Enhancement:**
```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof AuthenticationException) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return redirect('/')->with('error', 'Vui lòng đăng nhập');
    }
    return parent::render($request, $exception);
}
```

### 3. Token Refresh
JWT tokens expire. Frontend should:
- Check token expiry
- Refresh before expiry
- Handle 401 gracefully

---

## 🎯 FILES CHANGED

1. **routes/web.php**
   - Added middleware group for protected routes
   - Applied CookieToBearerToken + auth:api

---

## 🚀 TESTING CHECKLIST

- [ ] Clear browser cookies
- [ ] Login via website
- [ ] Verify `access_token` cookie set
- [ ] Access `/profile` → Should load
- [ ] Access `/my-tickets` → Should load
- [ ] Logout
- [ ] Access `/profile` → Should redirect/401
- [ ] Login again → Should work

---

## 📚 REFERENCES

**Config Files:**
- `config/auth.php` - Auth guards configuration
- `bootstrap/app.php` - Middleware registration
- `routes/web.php` - Web routes
- `routes/api.php` - API routes

**Middleware:**
- `CookieToBearerToken` - Cookie → Bearer token conversion
- `auth:api` - JWT authentication (tymon/jwt-auth)

**Related Docs:**
- JWT Auth: https://jwt-auth.readthedocs.io/
- Laravel Auth: https://laravel.com/docs/authentication

---

**Status:** ✅ FIXED  
**Impact:** High (affects all authenticated web pages)  
**Testing:** Required before production deployment