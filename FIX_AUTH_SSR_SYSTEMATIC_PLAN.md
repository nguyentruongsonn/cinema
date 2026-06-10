# 🔧 KẾ HOẠCH SỬA LỖI AUTH SSR TRIỆT ĐỂ

> **Ngày:** 10/06/2026  
> **Vấn đề:** SSR middleware không hoạt động, server không nhận biết user đã login  
> **Mục tiêu:** Fix triệt để để SSR auth hoạt động 100%

---

## 📋 PHÂN TÍCH HIỆN TRẠNG

### Triệu chứng quan sát được:
1. ❌ Server render `@guest` section ("Vui lòng đăng nhập")
2. ❌ User dropdown KHÔNG hiển thị trong header
3. ✅ JavaScript detect được user authenticated (console logs)
4. ✅ Profile data load được (qua API)

### Kết luận:
- **Client-side auth WORKS** (JS/API hoạt động)
- **Server-side auth BROKEN** (SSR middleware không hoạt động)

---

## 🔍 ROOT CAUSE ANALYSIS

### Có 4 khả năng:

#### 1. Middleware không được register
- `AuthenticateFromCookie` không có trong web middleware group
- Hoặc được register nhưng ở vị trí sai

#### 2. Middleware chạy nhưng không nhận được cookie
- Cookie không được gửi từ browser
- Cookie path/domain không match
- Cookie name sai

#### 3. Middleware nhận cookie nhưng JWT validation fail
- Token expired
- Secret key sai
- Token format invalid

#### 4. JWT valid nhưng Auth::login() không persist
- Session driver issue
- Auth guard config sai

---

## 🎯 KẾ HOẠCH HÀNH ĐỘNG

### PHASE 1: VERIFY MIDDLEWARE REGISTRATION ⚡ URGENT

**Task 1.1:** Check bootstrap/app.php
- [ ] Xác nhận `AuthenticateFromCookie::class` có trong web middleware
- [ ] Xác nhận vị trí đúng (sau StartSession, trước SubstituteBindings)

**Task 1.2:** Verify middleware file exists
- [ ] File `app/Http/Middleware/AuthenticateFromCookie.php` tồn tại
- [ ] Namespace và class name đúng

**Task 1.3:** Test middleware directly
- [ ] Create test route with middleware explicitly
- [ ] Verify nó chạy

---

### PHASE 2: DEBUG MIDDLEWARE EXECUTION

**Task 2.1:** Add logging to middleware
- [ ] Log khi middleware được gọi
- [ ] Log giá trị cookie nhận được
- [ ] Log kết quả JWT validation
- [ ] Log Auth::check() result

**Task 2.2:** Check cookie transmission
- [ ] Browser DevTools → Network → Headers
- [ ] Verify `Cookie: access_token=...` được gửi
- [ ] Verify cookie không có Secure flag

**Task 2.3:** Test JWT validation
- [ ] Copy access_token từ browser
- [ ] Test validate bằng debug script
- [ ] Check expiry time

---

### PHASE 3: FIX ROOT CAUSE

**Based on findings, fix:**

**If middleware not registered:**
- [ ] Register properly in bootstrap/app.php
- [ ] Clear route cache: `php artisan route:clear`

**If cookie not transmitted:**
- [ ] Fix SESSION_SECURE_COOKIE setting
- [ ] Check cookie domain/path
- [ ] Clear browser cookies and retry

**If JWT validation fails:**
- [ ] Check JWT_SECRET in .env
- [ ] Check token expiry
- [ ] Regenerate token

**If Auth::login() not persisting:**
- [ ] Check AUTH_GUARD setting
- [ ] Check session configuration
- [ ] Use correct guard in middleware

---

### PHASE 4: COMPREHENSIVE TESTING

**Test 1: Fresh login**
- [ ] Clear all cookies
- [ ] Login
- [ ] Verify user dropdown shows IMMEDIATELY (no JS delay)
- [ ] Verify no flicker

**Test 2: Page reload**
- [ ] Refresh homepage
- [ ] Verify user dropdown persists
- [ ] Check Network tab: no /api/auth/me calls

**Test 3: Direct profile access**
- [ ] Visit /profile directly
- [ ] Should see loading skeleton (NOT "Vui lòng đăng nhập")
- [ ] Profile loads

**Test 4: Multiple pages**
- [ ] Navigate: Home → Profile → Tickets → Movies
- [ ] User dropdown shows on ALL pages
- [ ] Zero flicker on any page

---

## 🚀 EXECUTION ORDER

### Step 1: Verify Middleware Registration (5 mins)
```bash
# Check bootstrap/app.php content
# Verify AuthenticateFromCookie is listed
```

### Step 2: Add Debug Logging (10 mins)
```php
// In AuthenticateFromCookie middleware
\Log::info('Middleware executing...');
\Log::info('Cookie value:', ['token' => $accessToken]);
\Log::info('Auth result:', ['check' => Auth::check()]);
```

### Step 3: Test with Real Request (5 mins)
```bash
# Login on browser
# Check storage/logs/laravel.log
# See if middleware logs appear
```

### Step 4: Fix Based on Logs (15 mins)
```bash
# Apply fix based on what logs reveal
# Could be: middleware registration, cookie config, JWT validation, etc.
```

### Step 5: Verify Fix (10 mins)
```bash
# Test all scenarios
# Confirm SSR works
# Document solution
```

**Total estimated time: 45 minutes**

---

## 📊 SUCCESS CRITERIA

### Phải đạt được:

1. ✅ Server renders `@auth` section when user logged in
2. ✅ User dropdown appears IMMEDIATELY (no JS delay)
3. ✅ Profile page shows loading skeleton (not "please login")
4. ✅ Zero API calls to /api/auth/me on page load
5. ✅ Zero flicker on any page navigation
6. ✅ Auth state persists across page reloads
7. ✅ Middleware logs show it's executing
8. ✅ `Auth::check()` returns true in middleware

---

## 🎯 BẮT ĐẦU NGAY

Tôi sẽ bắt đầu với **PHASE 1, Task 1.1**: Verify middleware registration trong bootstrap/app.php

Sau đó sẽ tiếp tục theo plan trên cho đến khi fix triệt để!

---

*Plan created: June 10, 2026*  
*Status: Ready to execute*  
*Estimated completion: 45 minutes*