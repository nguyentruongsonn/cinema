# 🎯 KẾ HOẠCH FIX AUTH SYSTEM TRIỆT ĐỂ

> **Mục tiêu:** Fix toàn bộ hệ thống JWT Auth + SSR + Token Refresh theo chuẩn production  
> **Thời gian:** 1-2 ngày  
> **Người thực hiện:** Developer team  
> **Trạng thái:** 🔴 READY TO IMPLEMENT

---

## 📋 OVERVIEW

### Vấn đề hiện tại
1. ❌ Hardcode `secure: false` trong AuthController
2. ❌ Cookie config không nhất quán giữa dev/prod
3. ❌ Middleware SSR chưa optimal
4. ❌ Frontend refresh flow có thể race condition
5. ❌ Quá nhiều file debug/test không cần thiết

### Mục tiêu cuối cùng
1. ✅ Access token: 15 phút → auto refresh seamless
2. ✅ Refresh token: 14 ngày → long-lived
3. ✅ SSR auth: Zero flicker, instant render
4. ✅ Environment-aware: Dev (HTTP) vs Prod (HTTPS)
5. ✅ Production-ready: Clean code, no hardcode

---

## 🗂️ PHASE 1: CLEANUP & PREPARATION (30 phút)

### 1.1. Xóa các file debug không cần thiết

```bash
rm public/diagnostic.html
rm public/check_auth_config.php
rm fix_auth_final.sh
rm test_auth_cookies.php
rm AUTH_FIX_COMPLETE_GUIDE.md
rm AUTH_SYSTEM_COMPLETE_GUIDE.md
rm HOMEPAGE_*.md
rm *_SUMMARY.md
```

**Lý do:** Clean codebase, chỉ giữ file production

### 1.2. Backup current working state

```bash
git add .
git commit -m "backup: before auth refactor"
git branch backup/auth-before-refactor
```

### 1.3. Clear all caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

---

## 🔧 PHASE 2: FIX COOKIE CONFIGURATION (1 giờ)

### 2.1. Update `config/session.php`

**File:** `config/session.php`

**Line 172 - Đã fix rồi, verify:**
```php
'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),
```

**Explanation:** 
- Dev: `SESSION_SECURE_COOKIE=false` → cookies work over HTTP
- Prod: auto-detect `APP_ENV=production` → secure=true

### 2.2. Chuẩn hóa `.env`

**File:** `.env`

**Required config:**
```env
APP_ENV=local
APP_URL=http://127.0.0.1:8000
APP_DEBUG=true

# JWT Config
JWT_TTL=15                 # Access token: 15 minutes
JWT_REFRESH_TTL=20160      # Refresh token: 14 days (60*24*14)

# Cookie Security
SESSION_SECURE_COOKIE=false    # false for HTTP dev
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
SESSION_PATH=/
```

### 2.3. Update `.env.example`

**File:** `.env.example`

**Add documentation:**
```env
# JWT Configuration
JWT_TTL=15                      # Access token lifetime (minutes)
JWT_REFRESH_TTL=20160           # Refresh token lifetime (minutes, 14 days)

# Cookie Security (IMPORTANT for auth)
SESSION_SECURE_COOKIE=false     # Set to false for HTTP (dev), true for HTTPS (prod)
SESSION_SAME_SITE=lax           # CSRF protection
SESSION_DOMAIN=null             # null = current domain
SESSION_PATH=/                  # Cookie available on all paths
```

### 2.4. Remove hardcode từ `AuthController.php`

**File:** `app/Http/Controllers/AuthController.php`

**Lines 314, 325 - CHANGE FROM:**
```php
secure: false,  // FORCED FALSE for local development
```

**TO:**
```php
secure: config('session.secure'),
```

**Critical:** Đây là fix quan trọng nhất!

---

## 🔐 PHASE 3: FIX JWT TOKEN TYPE (1 giờ)

### 3.1. Add token type to JWT payload

**File:** `app/Services/AuthService.php`

**Method: `generateAccessToken()` - ADD:**
```php
$payload = [
    'sub' => $user->id,
    'type' => 'access',        // ← ADD THIS
    'iat' => time(),
    'exp' => time() + ($ttl * 60),
    'jti' => Str::uuid()->toString(),
];
```

**Method: `generateRefreshToken()` - ADD:**
```php
$payload = [
    'sub' => $user->id,
    'type' => 'refresh',       // ← ADD THIS
    'iat' => time(),
    'exp' => time() + ($refreshTtl * 60),
    'jti' => Str::uuid()->toString(),
];
```

### 3.2. Validate token type

**Add validation method:**
```php
public function validateTokenType(array $payload, string $expectedType): bool
{
    return ($payload['type'] ?? null) === $expectedType;
}
```

---

## 🛡️ PHASE 4: FIX SSR MIDDLEWARE (1 giờ)

### 4.1. Update `AuthenticateFromCookie.php`

**File:** `app/Http/Middleware/AuthenticateFromCookie.php`

**Replace entire `handle()` method với:**

```php
public function handle(Request $request, Closure $next): Response
{
    $token = $request->cookie('access_token');

    if (!$token) {
        Log::debug('🔧 [SSR Auth] No access_token cookie', [
            'url' => $request->url()
        ]);
        return $next($request);
    }

    try {
        $payload = JWT::decode(
            $token,
            new Key(config('jwt.secret'), config('jwt.algo'))
        );

        // Validate token type
        if (($payload->type ?? null) !== 'access') {
            Log::warning('⚠️ [SSR Auth] Invalid token type', [
                'expected' => 'access',
                'got' => $payload->type ?? 'null'
            ]);
            return $next($request);
        }

        $userId = $payload->sub ?? null;

        if (!$userId) {
            return $next($request);
        }

        // Authenticate user for this request
        if (Auth::loginUsingId($userId, false)) {
            Log::info('✅ [SSR Auth] Success', [
                'user_id' => $userId,
                'route' => $request->path()
            ]);
        }

    } catch (ExpiredException $e) {
        Log::debug('⏰ [SSR Auth] Token expired', [
            'url' => $request->url()
        ]);
        // Token expired - continue as guest
        // Frontend will handle refresh on API 401
    } catch (\Throwable $e) {
        Log::error('❌ [SSR Auth] Token invalid', [
            'error' => $e->getMessage()
        ]);
    }

    return $next($request);
}
```

**Key changes:**
- ✅ Validate token type = 'access'
- ✅ No auto-refresh in middleware (SSR should be read-only)
- ✅ Clear logging for debugging
- ✅ Graceful fallback to guest

---

## 🌐 PHASE 5: FIX FRONTEND AUTH.JS (1.5 giờ)

### 5.1. Add refresh lock mechanism

**File:** `public/js/auth.js`

**Add to class properties (top of AuthManager):**
```javascript
constructor() {
    // ... existing code ...
    this.refreshPromise = null;  // ← ADD THIS
}
```

### 5.2. Fix `refreshAccessToken()` với lock

**Replace method:**
```javascript
async refreshAccessToken() {
    // Prevent multiple simultaneous refresh calls
    if (this.refreshPromise) {
        console.log('🔒 Refresh already in progress, waiting...');
        return this.refreshPromise;
    }

    console.log('🔄 Starting token refresh...');
    
    this.refreshPromise = (async () => {
        try {
            const response = await fetch(`${this.apiUrl}/auth/refresh`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.user) {
                    this.user = data.data.user;
                    console.log('✅ Token refresh successful');
                    return true;
                }
            }
            
            console.log('❌ Token refresh failed');
            return false;
        } catch (error) {
            console.error('❌ Token refresh error:', error);
            return false;
        }
    })();

    try {
        return await this.refreshPromise;
    } finally {
        this.refreshPromise = null;
    }
}
```

### 5.3. Fix `checkAuthStatus()` - không gọi nếu SSR đã render

**Replace method:**
```javascript
async checkAuthStatus() {
    // Check if SSR already provided auth state
    if (window.__AUTH_USER__ !== undefined) {
        this.user = window.__AUTH_USER__;
        this.authChecked = true;
        this.updateUI();
        console.log('✅ Using SSR auth state');
        return;
    }

    // Prevent multiple simultaneous checks
    if (this.isCheckingAuth) {
        return;
    }

    this.isCheckingAuth = true;

    try {
        const response = await this.fetchAPI('/auth/profile', {
            skipRefresh: true,
            silentAuth: true,
        });

        if (response.success && response.data) {
            this.user = response.data.user || response.data;
        } else {
            this.user = null;
        }
    } catch (error) {
        this.user = null;
    } finally {
        this.isCheckingAuth = false;
        this.authChecked = true;
        this.updateUI();
    }
}
```

---

## 🎨 PHASE 6: FIX BLADE SSR STATE (30 phút)

### 6.1. Update `resources/views/layouts/app.blade.php`

**Add BEFORE closing `</head>` tag:**
```blade
{{-- Provide SSR auth state to JavaScript --}}
<script>
    window.__AUTH_USER__ = @json(Auth::check() ? Auth::user() : null);
</script>
```

### 6.2. Verify header uses SSR correctly

**File:** `resources/views/partials/header.blade.php`

**Should already have:**
```blade
@auth
    {{-- User dropdown --}}
@endauth

@guest
    {{-- Login/Register buttons --}}
@endguest
```

**This ensures zero flicker!**

---

## 🧪 PHASE 7: TESTING (2 giờ)

### 7.1. Test Environment Setup

```bash
# Set JWT_TTL to 1 minute for quick testing
# In .env temporarily:
JWT_TTL=1

php artisan config:clear
```

### 7.2. Test Checklist

**Test 1: Login Flow**
```txt
✅ POST /api/v1/auth/login
✅ Response: 200 OK
✅ Cookies set: access_token, refresh_token
✅ Cookie flags: HttpOnly=true, Secure=false (dev), SameSite=lax
✅ Page reload
✅ User dropdown appears INSTANTLY (SSR)
✅ No flicker
```

**Test 2: SSR Auth**
```txt
✅ Visit any page: /, /profile, /movies
✅ middleware logs show: "✅ [SSR Auth] Success"
✅ Auth::check() returns true
✅ Blade @auth sections render
✅ User dropdown visible immediately
```

**Test 3: Token Expiry (JWT_TTL=1)**
```txt
✅ Login successfully
✅ Wait 2 minutes
✅ Make API request (e.g., GET /api/v1/movies)
✅ First request: 401 (token expired)
✅ Frontend auto calls /api/v1/auth/refresh
✅ Refresh returns 200
✅ Original request retried automatically
✅ Request succeeds with new token
✅ User still logged in (no logout)
```

**Test 4: Refresh Token Expired**
```txt
✅ Clear cookies manually
✅ Set old/invalid refresh_token cookie
✅ Make API request
✅ Gets 401
✅ Frontend calls /api/v1/auth/refresh
✅ Refresh fails: 401
✅ User logged out
✅ Redirect to login (if on protected page)
```

**Test 5: Logout**
```txt
✅ Click logout
✅ POST /api/v1/auth/logout
✅ Cookies cleared
✅ Page reload
✅ @guest sections render
✅ Login button visible
```

### 7.3. Reset JWT_TTL after testing

```env
# Back to normal
JWT_TTL=15
```

```bash
php artisan config:clear
```

---

## 📦 PHASE 8: PRODUCTION PREPARATION (1 giờ)

### 8.1. Create production `.env` template

**File:** `docs/ENV_PRODUCTION_TEMPLATE.md`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# JWT Config
JWT_TTL=15
JWT_REFRESH_TTL=20160

# Cookie Security (CRITICAL for production)
SESSION_SECURE_COOKIE=true      # MUST be true for HTTPS
SESSION_SAME_SITE=strict        # Stricter for production
SESSION_DOMAIN=.yourdomain.com  # Support subdomains if needed
SESSION_PATH=/
```

### 8.2. Security Checklist

```txt
✅ HTTPS configured với valid SSL
✅ Force HTTPS redirect
✅ HSTS header enabled
✅ SESSION_SECURE_COOKIE=true
✅ SESSION_SAME_SITE=strict
✅ Rate limiting enabled
✅ CORS config tightened
✅ CSP headers configured
✅ XSS protection headers
✅ Remove debug routes
```

### 8.3. Performance Optimization

```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

---

## 📊 SUCCESS CRITERIA

### Functional Requirements

- [x] Login sets access_token + refresh_token cookies
- [x] SSR middleware authenticates from cookies
- [x] User dropdown shows instantly (no API call)
- [x] Zero visual flicker on page load
- [x] Access token expires after 15 min → auto refresh
- [x] Refresh token valid for 14 days
- [x] Frontend handles 401 gracefully
- [x] Logout clears cookies completely
- [x] Environment-aware security (dev vs prod)

### Performance Metrics

- Page load time: < 200ms (SSR, no /api/auth/me)
- Token refresh time: < 100ms
- Zero unnecessary API calls
- Zero visual flicker
- Seamless UX during token refresh

### Code Quality

- No hardcoded values
- Config-driven security
- Clean separation of concerns
- Comprehensive error handling
- Clear logging for debugging
- Production-ready code

---

## 🚀 IMPLEMENTATION ORDER

```
Day 1 Morning (3 hours):
├─ PHASE 1: Cleanup (30 min)
├─ PHASE 2: Cookie Config (1 hour)
├─ PHASE 3: JWT Token Type (1 hour)
└─ PHASE 4: SSR Middleware (30 min)

Day 1 Afternoon (4 hours):
├─ PHASE 5: Frontend auth.js (1.5 hours)
├─ PHASE 6: Blade SSR State (30 min)
└─ PHASE 7: Testing (2 hours)

Day 2 (Optional - Production):
└─ PHASE 8: Production Prep (1 hour)
```

**Total Time:** ~7 hours (1 working day)

---

## 🎯 NEXT STEPS

1. **Backup hiện tại:**
   ```bash
   git commit -m "backup before auth refactor"
   git branch backup/auth-before-refactor
   ```

2. **Bắt đầu implement từ Phase 1**

3. **Test sau mỗi phase**

4. **Commit sau mỗi phase hoàn thành:**
   ```bash
   git add .
   git commit -m "feat(auth): complete phase X - description"
   ```

5. **Final test toàn bộ flow:**
   - Login
   - SSR render
   - Token refresh
   - Logout

6. **Deploy to production khi pass all tests**

---

## 📞 SUPPORT & QUESTIONS

Nếu gặp issue trong quá trình implement:

1. Check logs: `storage/logs/laravel.log`
2. Check browser console: F12
3. Check cookies: F12 → Application → Cookies
4. Review AUTH_IMPLEMENTATION_PLAN.md (this file)

---

**🎉 READY TO IMPLEMENT!**

*Plan created: June 10, 2026*  
*Status: 🟢 APPROVED*  
*Estimated time: 7 hours (1 day)*