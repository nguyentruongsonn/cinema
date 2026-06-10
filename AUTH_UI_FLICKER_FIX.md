# FIX: UserDropdown Render Nhiều Lần Khi Navigate

## 🎯 VẤN ĐỀ

**Triệu chứng:** Khi navigate qua các trang khác, userDropdown bị render/hiển thị nhiều lần, gây flicker

## 🔍 NGUYÊN NHÂN

### FOUC (Flash of Unstyled Content)

```
Page Load Timeline:
T0: HTML loads → Login button VISIBLE (default state)
T1: JavaScript loads
T2: AuthManager init
T3: checkAuthStatus() starts (async)
T4: API call pending... (user sees login button)
T5: API returns → authChecked = true
T6: updateUI() → Switch to user dropdown
Result: User sees LOGIN BUTTON → USER DROPDOWN flash!
```

## ✅ GIẢI PHÁP

### Fix #1: Hide Auth UI Initially (CSS)

**File: `public/css/main.css` or in layout**

```css
/* Hide auth UI until JavaScript determines correct state */
[data-auth-action="login"],
#userDropdown {
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}

/* Show when auth checked */
body.auth-checked [data-auth-action="login"],
body.auth-checked #userDropdown {
    opacity: 1;
}

/* Optional: Loading spinner during auth check */
.auth-loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #ccc;
    border-top-color: #e50914;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
```

### Fix #2: Update auth.js to Add Body Class

**File: `public/js/auth.js`**

```javascript
updateUI() {
    if (!this.authChecked) {
        return;
    }

    // Add class to body to show auth UI
    document.body.classList.add('auth-checked');

    const loginBtn = document.querySelector('[data-auth-action="login"]');
    const userDropdown = document.getElementById('userDropdown');

    if (this.user) {
        if (loginBtn) loginBtn.classList.add('d-none');
        if (userDropdown) {
            userDropdown.classList.remove('d-none');
            const userName = userDropdown.querySelector('.user-name');
            if (userName) userName.textContent = this.user.name;
        }
    } else {
        if (loginBtn) loginBtn.classList.remove('d-none');
        if (userDropdown) userDropdown.classList.add('d-none');
    }
}
```

### Fix #3: Alternative - Show Loading Spinner

**File: Header/Navbar template**

```html
<!-- Add loading spinner -->
<div id="authLoadingSpinner" class="auth-loading-spinner"></div>

<!-- Login button (hidden initially) -->
<button data-auth-action="login" class="d-none">Đăng nhập</button>

<!-- User dropdown (hidden initially) -->
<div id="userDropdown" class="d-none">
    <!-- User menu -->
</div>
```

```javascript
// In auth.js updateUI()
updateUI() {
    // Hide loading spinner
    const spinner = document.getElementById('authLoadingSpinner');
    if (spinner) spinner.style.display = 'none';

    if (!this.authChecked) {
        // Show spinner while checking
        if (spinner) spinner.style.display = 'inline-block';
        return;
    }

    // ... rest of updateUI code ...
}
```

## 🎯 RECOMMENDED FIX

Sử dụng **opacity approach** (Fix #1 + #2):

1. CSS sets opacity: 0 cho cả login button và dropdown
2. JavaScript adds 'auth-checked' class to body sau khi check complete
3. CSS shows UI với smooth fade-in transition
4. No flicker, smooth experience!

## 📝 IMPLEMENTATION

Chỉ cần thêm 1 dòng vào updateUI():

```javascript
updateUI() {
    if (!this.authChecked) {
        return;
    }

    // Add this line:
    document.body.classList.add('auth-checked');

    // ... existing code ...
}
```

Và thêm CSS vào stylesheet:

```css
[data-auth-action="login"],
#userDropdown {
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}

body.auth-checked [data-auth-action="login"],
body.auth-checked #userDropdown {
    opacity: 1;
}
```

Done! No more flicker! ✅