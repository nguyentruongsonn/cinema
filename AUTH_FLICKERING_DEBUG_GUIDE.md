# 🔍 AUTH FLICKERING - DEBUG & FIX GUIDE

**Ngày:** 10/6/2026  
**Vấn đề:** Header account/user nhấp nháy liên tục, profile yêu cầu login lại

---

## 🎯 PHÂN TÍCH NGUYÊN NHÂN

### Root Cause: RACE CONDITION + MULTIPLE UI UPDATES

**Triệu chứng quan sát:**
- ✓ User đã login thành công
- ✗ Profile page yêu cầu login lại
- ✗ Header account/user **FLICKERING** (nhấp nháy liên tục)
- ✗ Trạng thái logged-in ⟷ logged-out thay đổi liên tục

### Timeline of Events (Causing Flickering)

```
T0: Page Load
    ├─ AuthManager constructor
    │  └─ this.user = null ❌ (initial state)
    │
T1: updateUI() called (somewhere)
    └─ Show: Login Button (user = null) 👁️ FLICKER #1
    
T2: DOMContentLoaded fired
    ├─ checkAuthStatus() starts (async)
    └─ updateUI() called immediately
       └─ Show: Login Button (user still null) 👁️ FLICKER #2
       
T3: API /auth/profile pending...
    └─ User sees: Login Button
    
T4: API returns 200 OK with user data
    ├─ this.user = userData ✅
    └─ updateUI() called
       └─ Show: User Dropdown 👁️ FLICKER #3
       
T5: Something triggers checkAuthStatus() again
    └─ Cycle repeats... 👁️ FLICKER #4, #5, #6...
```

**Result:** User thấy Login Button → User Dropdown → Login Button... (flickering)

---

## 🔬 PHÂN TÍCH CHI TIẾT

### 1. ⚠️ CRITICAL: Initial State Problem

**File:** `public/js/auth.js`

```javascript
constructor() {
    this.apiUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
    this.user = null;  // ❌ PROBLEM: Default to null
    this.modal = null;
    this.init();
}
```

**Issue:**
- User khởi tạo = `null`
- updateUI() được gọi ngay → Show logged-out state
- Sau đó mới check API → Show logged-in state
- **Kết quả:** Flickering!

**Khả năng:** ⭐⭐⭐⭐⭐ (VERY HIGH)

---

### 2. ⚠️ HIGH: Race Condition trong checkAuthStatus()

**File:** `public/js/auth.js`

```javascript
async checkAuthStatus() {
    try {
        const response = await this.fetchAPI('/auth/profile', {
            skipRefresh: true,
            silentAuth: true,
        });

        if (response.success && response.data) {
            this.user = response.data.user || response.data;
            this.updateUI();  // ← Called multiple times
        }
    } catch (error) {
        this.user = null;
        this.updateUI();  // ← Called on error too
    }
}
```

**Issue:**
- Method này có thể được gọi nhiều lần
- Mỗi lần gọi → updateUI() → Flickering
- Không có lock/guard để prevent multiple calls

**Khả năng:** ⭐⭐⭐⭐⭐ (VERY HIGH)

---

### 3. ⚠️ MEDIUM: updateUI() Called Too Early

**File:** `public/js/auth.js`

```javascript
init() {
    document.addEventListener('DOMContentLoaded', () => {
        this.modal = new bootstrap.Modal(document.getElementById('authModal'));
        this.setupEventListeners();
        this.checkAuthStatus();  // Async - takes time
    });
}
```

**Issue:**
- updateUI() có thể được gọi trước khi checkAuthStatus() complete
- Nếu có code khác gọi updateUI() → Flickering

**Khả năng:** ⭐⭐⭐⭐ (HIGH)

---

### 4. ⚠️ MEDIUM: Profile.js Race Condition

**File:** `public/js/pages/profile.js`

```javascript
async loadProfile() {
    if (!window.authManager?.isAuthenticated()) {
        this.showAuthRequired();  // ← Gọi quá sớm
        return;
    }
    // Load profile data...
}
```

**Issue:**
- Profile.js chạy ngay khi page load
- Nếu authManager.checkAuthStatus() chưa complete
- isAuthenticated() return false (vì user = null)
- Show auth required dù user đã login!

**Khả năng:** ⭐⭐⭐⭐ (HIGH)

---

### 5. ⚠️ LOW-MEDIUM: Cookie Configuration

**Potential Issues:**
- SameSite policy blocking cookies
- Secure flag requiring HTTPS
- Domain/Path mismatch
- HttpOnly preventing JavaScript access (this is correct)

**Check:** `app/Http/Controllers/AuthController.php`

```php
// How are cookies set?
Cookie::queue('access_token', $token, $minutes, '/', null, true, true);
//                                               ↑     ↑    ↑     ↑
//                                            path domain secure httponly
```

**Khả năng:** ⭐⭐⭐ (MEDIUM)

---

### 6. ⚠️ LOW: Token Refresh Loop

**File:** `public/js/auth.js`

```javascript
async fetchAPI(endpoint, options = {}) {
    // ...
    if (response.status === 401) {
        if (options.silentAuth || options.skipRefresh) {
            // Skip - OK ✓
        } else {
            const refreshed = await this.refreshAccessToken();
            // Could cause loop if refresh also fails
        }
    }
}
```

**Issue:**
- Nếu refresh token expired → 401 → retry → 401 → loop
- Nhưng có `skipRefresh` flag nên OK

**Khả năng:** ⭐⭐ (LOW)

---

## 🔧 GIẢI PHÁP

### ✅ FIX #1: Add Loading State (CRITICAL)

**File:** `public/js/auth.js`

```javascript
class AuthManager {
    constructor() {
        this.apiUrl = window.APP_CONFIG?.apiUrl || '/api/v1';
        this.user = null;
        this.modal = null;
        this.isCheckingAuth = false;  // ← NEW: Loading flag
        this.authChecked = false;     // ← NEW: Completed flag
        this.init();
    }

    async checkAuthStatus() {
        // Prevent multiple simultaneous calls
        if (this.isCheckingAuth) {
            return;  // ← GUARD: Already checking
        }

        this.isCheckingAuth = true;  // ← Set flag

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
            this.isCheckingAuth = false;  // ← Clear flag
            this.authChecked = true;      // ← Mark as completed
            this.updateUI();              // ← Update UI once
        }
    }

    updateUI() {
        // Don't update UI until auth check is complete
        if (!this.authChecked) {
            return;  // ← GUARD: Wait for auth check
        }

        const loginBtn = document.querySelector('[data-auth-action="login"]');
        const userDropdown = document.getElementById('userDropdown');

        if (this.user) {
            // User is logged in
            if (loginBtn) loginBtn.classList.add('d-none');
            if (userDropdown) {
                userDropdown.classList.remove('d-none');
                const userName = userDropdown.querySelector('.user-name');
                if (userName) userName.textContent = this.user.name;
            }
        } else {
            // User is not logged in
            if (loginBtn) loginBtn.classList.remove('d-none');
            if (userDropdown) userDropdown.classList.add('d-none');
        }
    }

    isAuthenticated() {
        // Wait for auth check to complete
        if (!this.authChecked) {
            return false;  // ← Conservative: assume not authenticated
        }
        return !!this.user;
    }
}
```

**Giải thích:**
1. ✅ Add `isCheckingAuth` flag → prevent multiple calls
2. ✅ Add `authChecked` flag → wait for completion
3. ✅ updateUI() chỉ chạy sau khi auth check complete
4. ✅ isAuthenticated() return false until check complete

---

### ✅ FIX #2: Profile.js Wait for Auth

**File:** `public/js/pages/profile.js`

```javascript
async loadProfile() {
    this.setLoading(true);

    // ← NEW: Wait for authManager to complete initial check
    if (window.authManager && !window.authManager.authChecked) {
        // Wait up to 5 seconds for auth check
        let attempts = 0;
        while (!window.authManager.authChecked && attempts < 50) {
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
    }

    // Now check authentication
    if (!window.authManager?.isAuthenticated()) {
        this.showAuthRequired();
        return;
    }

    // Continue loading profile...
    try {
        const response = await this.apiRequest('/auth/profile');
        // ...
    }
}
```

**Giải thích:**
- Wait for authManager to complete initial check
- Timeout after 5 seconds (50 attempts × 100ms)
- Prevents race condition

---

### ✅ FIX #3: Add Loading Spinner to Header

**File:** `resources/views/layouts/app.blade.php` (or header partial)

```html
<!-- Loading state during auth check -->
<div id="authLoading" class="d-none">
    <span class="spinner-border spinner-border-sm"></span>
</div>

<!-- Login button (hidden initially) -->
<button data-auth-action="login" class="d-none">Đăng nhập</button>

<!-- User dropdown (hidden initially) -->
<div id="userDropdown" class="d-none">
    <!-- User menu -->
</div>
```

```javascript
// In auth.js
updateUI() {
    const authLoading = document.getElementById('authLoading');
    const loginBtn = document.querySelector('[data-auth-action="login"]');
    const userDropdown = document.getElementById('userDropdown');

    if (!this.authChecked) {
        // Show loading, hide everything else
        if (authLoading) authLoading.classList.remove('d-none');
        if (loginBtn) loginBtn.classList.add('d-none');
        if (userDropdown) userDropdown.classList.add('d-none');
        return;
    }

    // Hide loading
    if (authLoading) authLoading.classList.add('d-none');

    if (this.user) {
        // Show user dropdown
        if (loginBtn) loginBtn.classList.add('d-none');
        if (userDropdown) {
            userDropdown.classList.remove('d-none');
            const userName = userDropdown.querySelector('.user-name');
            if (userName) userName.textContent = this.user.name;
        }
    } else {
        // Show login button
        if (loginBtn) loginBtn.classList.remove('d-none');
        if (userDropdown) userDropdown.classList.add('d-none');
    }
}
```

---

## 🧪 DEBUG STEPS

### Step 1: Check Cookie

**DevTools → Application → Cookies**

```
Name: access_token
Value: eyJ0eXAiOiJKV1QiLCJhb...
Domain: 127.0.0.1 (or your domain)
Path: /
HttpOnly: ✓ Yes
Secure: (depends on HTTPS)
SameSite: Lax or None
```

**Issues to check:**
- ❌ Cookie missing → User not logged in
- ❌ Domain mismatch → Cookie not sent
- ❌ Path mismatch → Cookie not sent
- ❌ SameSite=Strict with CORS → Cookie blocked

---

### Step 2: Check Network Requests

**DevTools → Network → Filter: XHR**

Look for `/api/v1/auth/profile`:
```
Request Headers:
  Cookie: access_token=eyJ0...  ← Should be present!
  
Response:
  Status: 200 OK
  Body: {
    "success": true,
    "data": {
      "user": { ... }
    }
  }
```

**Issues:**
- ❌ 401 Unauthorized → Token invalid/expired
- ❌ Cookie missing from request → Cookie config wrong
- ❌ 404 Not Found → Endpoint wrong

---

### Step 3: Check Console Logs

**DevTools → Console**

Add debug logs:
```javascript
async checkAuthStatus() {
    console.log('[Auth] Checking auth status...');
    
    if (this.isCheckingAuth) {
        console.log('[Auth] Already checking, skip');
        return;
    }

    this.isCheckingAuth = true;
    console.log('[Auth] Set isCheckingAuth = true');

    try {
        const response = await this.fetchAPI('/auth/profile');
        console.log('[Auth] API response:', response);
        
        if (response.success && response.data) {
            this.user = response.data.user || response.data;
            console.log('[Auth] User authenticated:', this.user.name);
        } else {
            this.user = null;
            console.log('[Auth] User not authenticated');
        }
    } catch (error) {
        console.error('[Auth] Error:', error);
        this.user = null;
    } finally {
        this.isCheckingAuth = false;
        this.authChecked = true;
        console.log('[Auth] Check complete, calling updateUI()');
        this.updateUI();
    }
}
```

**Expected output:**
```
[Auth] Checking auth status...
[Auth] Set isCheckingAuth = true
[Auth] API response: {success: true, data: {user: {...}}}
[Auth] User authenticated: John Doe
[Auth] Check complete, calling updateUI()
```

**If flickering:**
```
[Auth] Checking auth status...
[Auth] Checking auth status...  ← DUPLICATE! Race condition!
[Auth] Checking auth status...  ← Multiple calls!
```

---

### Step 4: Check Timing

```javascript
console.time('[Auth] Check duration');

await this.fetchAPI('/auth/profile');

console.timeEnd('[Auth] Check duration');
// Expected: [Auth] Check duration: 50-200ms
```

If > 1 second → API slow → More likely to cause race conditions

---

## 📝 IMPLEMENTATION CHECKLIST

- [ ] Add `isCheckingAuth` flag to prevent multiple calls
- [ ] Add `authChecked` flag to wait for completion
- [ ] Guard `updateUI()` to wait for auth check
- [ ] Update `isAuthenticated()` to check `authChecked`
- [ ] Add wait logic in profile.js
- [ ] Add loading spinner to header
- [ ] Add debug console logs
- [ ] Test with DevTools Network/Console
- [ ] Check cookie configuration
- [ ] Verify no multiple checkAuthStatus() calls

---

## 🎯 PRIORITY

1. **CRITICAL:** Fix #1 - Add loading state & guards
2. **HIGH:** Fix #2 - Profile.js wait for auth
3. **MEDIUM:** Fix #3 - Add loading spinner
4. **LOW:** Add debug logs (for testing)

---

**Status:** Analysis Complete  
**Next:** Implement fixes in auth.js