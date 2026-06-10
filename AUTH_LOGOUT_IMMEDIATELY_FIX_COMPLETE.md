# Auth "Logout Immediately" Fix - Complete Summary

## Problem Description
User đăng ký/đăng nhập thành công nhưng bị logout ngay lập tức khi refresh page hoặc navigate. Blade SSR render như guest user ngay sau khi login.

## Root Causes Found

### 1. Blade Auth Guard Mismatch
**Issue**: `.env` có `AUTH_GUARD=api` (JWT guard) làm default guard. Khi Blade templates sử dụng:
- `auth()->check()`
- `auth()->user()`
- `@auth` / `@guest`
- `Auth::check()` / `Auth::user()`

Chúng check **default guard** (`api`) thay vì `web` guard. JWT guard không persist user state trong request lifecycle như session guard, nên luôn trả về `false`/`null` ngay cả khi middleware đã set user.

**Fix**: Thay đổi tất cả Blade templates sử dụng explicitly `web` guard:
- `Auth::guard('web')->check()`
- `Auth::guard('web')->user()`
- `@if (Auth::guard('web')->check())` thay vì `@auth`
- `@if (!Auth::guard('web')->check())` thay vì `@guest`

### 2. EncryptCookies Middleware Conflict
**Issue**: API routes set JWT cookies (`access_token`, `refresh_token`) as **plain HttpOnly cookies**. Nhưng Laravel web middleware có `EncryptCookies` mặc định sẽ cố decrypt mọi cookies. Vì JWT cookies không phải Laravel encrypted cookies, web requests không thể đọc được cookies này. Middleware SSR nhận `null` khi gọi `$request->cookie('access_token')`.

**Fix**: Exclude JWT cookies khỏi encryption trong `bootstrap/app.php`:
```php
$middleware->encryptCookies(except: [
    'access_token',
    'refresh_token',
]);
```

## Files Modified

### 1. `resources/views/layouts/app.blade.php`
```php
// Before
auth: {
    checked: true,
    authenticated: @json(auth()->check()),
    user: @json(auth()->user()),
}

// After
auth: {
    checked: true,
    authenticated: @json(Auth::guard('web')->check()),
    user: @json(Auth::guard('web')->user()),
}
```

### 2. `resources/views/partials/header.blade.php`
```php
// Before
@guest
    <a href="#" class="btn btn-danger cinema-login-btn">Đăng nhập</a>
@endguest

@auth
    <div class="dropdown" id="userDropdown">
        <span class="user-name">{{ Auth::user()->name }}</span>
    </div>
@endauth

// After
@if (!Auth::guard('web')->check())
    <a href="#" class="btn btn-danger cinema-login-btn">Đăng nhập</a>
@else
    <div class="dropdown" id="userDropdown">
        <span class="user-name">{{ Auth::guard('web')->user()->name }}</span>
    </div>
@endif
```

### 3. `resources/views/users/tickets/index.blade.php`
```php
// Before
@guest
    <div class="tickets-auth-required">...</div>
@endguest

@auth
    <div id="ticketsContent">...</div>
@endauth

// After
@if (!Auth::guard('web')->check())
    <div class="tickets-auth-required">...</div>
@else
    <div id="ticketsContent">...</div>
@endif
```

### 4. `resources/views/users/profile/index.blade.php`
```php
// Before
@guest
    <div class="profile-auth-required">...</div>
@endguest

@auth
    <div id="profileContent">...</div>
@endauth

// After
@if (!Auth::guard('web')->check())
    <div class="profile-auth-required">...</div>
@else
    <div id="profileContent">...</div>
@endif
```

### 5. `bootstrap/app.php`
```php
->withMiddleware(function (Middleware $middleware): void {
    // Apply security headers to all responses
    $middleware->append(SecurityHeaders::class);

    // NEW: JWT auth cookies are plain HttpOnly cookies, exclude from encryption
    $middleware->encryptCookies(except: [
        'access_token',
        'refresh_token',
    ]);

    // API routes: Convert cookie to Bearer token
    $middleware->api(prepend: [
        CookieToBearerToken::class,
    ]);

    // Web routes: Authenticate from JWT cookie for SSR
    $middleware->web(append: [
        AuthenticateFromCookie::class,
    ]);
    
    // ... rest of middleware config
})
```

## Test Results

Created automated HTTP test (`test_auth_guard_fix.php`) that verifies:

1. ✓ User registration successful
2. ✓ SSR home page shows authenticated state (user dropdown visible)
3. ✓ `APP_CONFIG.auth.authenticated` is `true`
4. ✓ Profile page shows authenticated content
5. ✓ API `/auth/profile` endpoint returns user data

**All tests PASSED** ✓

## How It Works Now

### Authentication Flow
1. User logs in/registers via API `/api/v1/auth/login` or `/api/v1/auth/register`
2. `AuthController` sets JWT tokens in HttpOnly cookies (`access_token`, `refresh_token`)
3. Cookies are plain JWT, NOT encrypted by Laravel

### SSR Flow (Web Routes)
1. User navigates to web route (e.g., `/`, `/profile`, `/tickets`)
2. `EncryptCookies` middleware runs but **skips** `access_token`/`refresh_token` (excluded)
3. `AuthenticateFromCookie` middleware runs:
   - Reads `$request->cookie('access_token')` ✓ (now readable)
   - Validates JWT token with `JWTAuth::setToken($token)->authenticate()`
   - Sets user in both guards: `Auth::guard('web')->setUser($user)` and `Auth::guard('api')->setUser($user)`
4. Blade template renders:
   - Calls `Auth::guard('web')->check()` ✓ (returns true)
   - Calls `Auth::guard('web')->user()` ✓ (returns user object)
   - Shows authenticated UI (user dropdown, profile content, etc.)

### Why Previous Attempts Failed
- Only fixing Blade guard → Still failed because EncryptCookies blocked cookie reading
- Only fixing EncryptCookies → Still failed because Blade checked wrong guard
- **Both fixes needed** for complete solution

## Verification Steps

1. Clear all caches:
```bash
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
```

2. Test registration/login flow:
```bash
php test_auth_guard_fix.php
```

3. Manual browser test:
   - Open browser
   - Register/login
   - Check that user dropdown shows in header
   - Navigate to `/profile` - should show profile content, not login prompt
   - Refresh page - should stay logged in
   - Open DevTools → Application → Cookies - verify `access_token` and `refresh_token` present

## Important Notes

1. **Default Auth Guard Remains `api`**: `.env` still has `AUTH_GUARD=api` for API routes. This is correct for JWT-based API authentication.

2. **Explicit Guard Usage in Blade**: All Blade templates must explicitly use `Auth::guard('web')` for SSR auth checks. Do NOT use:
   - `auth()->check()` - checks default `api` guard
   - `@auth` / `@guest` - uses default `api` guard
   - `Auth::check()` / `Auth::user()` - uses default `api` guard

3. **Cookie Security**: JWT cookies remain HttpOnly and Secure (in production), protecting against XSS. The EncryptCookies exclusion does NOT reduce security - JWT tokens are cryptographically signed and validated.

4. **Middleware Order**: `AuthenticateFromCookie` runs AFTER `EncryptCookies` in web middleware stack. This order is critical.

## Future Development

When adding new Blade views that show/hide content based on auth:
- **DO**: Use `@if (Auth::guard('web')->check())` and `Auth::guard('web')->user()`
- **DON'T**: Use `@auth`, `@guest`, `auth()->check()`, or `Auth::user()` without explicit guard

## Conclusion

The "logout immediately" issue was caused by two interacting problems:
1. Blade checking wrong auth guard (api instead of web)
2. EncryptCookies blocking plain JWT cookie access

Both fixes were required. With these changes, SSR authentication now works correctly and users stay logged in across page navigation/refresh.

**Status**: ✅ FIXED and VERIFIED
**Test Status**: ✅ ALL TESTS PASSED
**Date**: 2026-06-11