# Frontend Refactor Implementation Plan

> **Mục tiêu:** Fix critical issues, refactor architecture, improve maintainability
> **Timeline:** 5 weeks (25 working days)
> **Approach:** Incremental refactoring - không break existing features

---

## Overview - Priority-Driven Roadmap

| Phase | Focus | Duration | Risk |
|-------|-------|----------|------|
| Phase 1 | Security Fixes (P0) | 3 days | LOW - isolated changes |
| Phase 2 | Error Handling (P0) | 2 days | LOW - defensive code |
| Phase 3 | Architecture Foundation | 5 days | MEDIUM - new patterns |
| Phase 4 | app.js Refactoring | 8 days | HIGH - major changes |
| Phase 5 | Testing & Quality | 5 days | LOW - validation |
| Phase 6 | Performance & Accessibility | 2 days | LOW - enhancements |

---

## Phase 1: Security Fixes (P0) - Days 1-3

### Objective
Eliminate XSS vulnerabilities and unsafe HTML manipulation.

### Tasks

#### Task 1.1: Create Security Utilities (Day 1 - Morning)

**File:** `public/js/utils/security.js`

```javascript
/**
 * Security utilities for safe HTML manipulation
 */
export const Security = {
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(unsafe) {
        if (unsafe == null) return '';
        
        return String(unsafe)
            .replace(/&/g, "&")
            .replace(/</g, "<")
            .replace(/>/g, ">")
            .replace(/"/g, """)
            .replace(/'/g, "&#039;");
    },
    
    /**
     * Sanitize HTML - allow safe tags only
     */
    sanitizeHtml(html) {
        const allowedTags = ['b', 'i', 'em', 'strong', 'br', 'p', 'span'];
        const doc = new DOMParser().parseFromString(html, 'text/html');
        
        // Remove script tags and event handlers
        const scripts = doc.querySelectorAll('script');
        scripts.forEach(s => s.remove());
        
        const allElements = doc.querySelectorAll('*');
        allElements.forEach(el => {
            // Remove disallowed tags
            if (!allowedTags.includes(el.tagName.toLowerCase())) {
                el.replaceWith(...el.childNodes);
                return;
            }
            
            // Remove event handlers
            Array.from(el.attributes).forEach(attr => {
                if (attr.name.startsWith('on')) {
                    el.removeAttribute(attr.name);
                }
            });
        });
        
        return doc.body.innerHTML;
    },
    
    /**
     * Safe DOM insertion - use textContent for user data
     */
    setTextContent(element, text) {
        if (!element) return;
        element.textContent = text || '';
    },
    
    /**
     * Safe HTML insertion - only for trusted content
     */
    setInnerHTML(element, html, trusted = false) {
        if (!element) return;
        
        if (trusted) {
            element.innerHTML = html;
        } else {
            element.innerHTML = this.sanitizeHtml(html);
        }
    }
};
```

**Testing:**
```javascript
// Test in browser console
console.assert(Security.escapeHtml('<script>alert("xss")</script>') === '<script>alert("xss")</script>');
console.assert(Security.sanitizeHtml('<p>Safe</p><script>Bad</script>') === '<p>Safe</p>');
```

---

#### Task 1.2: Fix XSS in app.js (Day 1 - Afternoon + Day 2)

**Target:** Lines with `.innerHTML = ` in app.js

**Step 1:** Import Security utils in app.js
```javascript
// Add at top of app.js
import { Security } from './utils/security.js';
```

**Step 2:** Fix notification system (Line ~156-170)
```javascript
// BEFORE (UNSAFE):
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="bi bi-${getIconForType(type)}"></i>
        <span>${message}</span>
        <button class="btn-close"></button>
    `;
    document.body.appendChild(notification);
}

// AFTER (SAFE):
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icon = document.createElement('i');
    icon.className = `bi bi-${getIconForType(type)}`;
    
    const messageSpan = document.createElement('span');
    Security.setTextContent(messageSpan, message); // Use textContent
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('aria-label', 'Close');
    
    notification.appendChild(icon);
    notification.appendChild(messageSpan);
    notification.appendChild(closeBtn);
    
    document.body.appendChild(notification);
}
```

**Step 3:** Fix movie card rendering (Line ~450-490)
```javascript
// BEFORE (UNSAFE):
function renderMovieCard(movie) {
    return `
        <div class="movie-card" data-movie-id="${movie.id}">
            <img src="${movie.poster_url}" alt="${movie.title}">
            <h3>${movie.title}</h3>
            <p>${movie.description}</p>
        </div>
    `;
}

// AFTER (SAFE):
function renderMovieCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    card.setAttribute('data-movie-id', movie.id);
    
    const img = document.createElement('img');
    img.src = Security.escapeHtml(movie.poster_url);
    img.alt = Security.escapeHtml(movie.title);
    
    const title = document.createElement('h3');
    Security.setTextContent(title, movie.title);
    
    const desc = document.createElement('p');
    Security.setTextContent(desc, movie.description);
    
    card.appendChild(img);
    card.appendChild(title);
    card.appendChild(desc);
    
    return card;
}
```

**Step 4:** Fix search results (Line ~700-750)
```javascript
// BEFORE (UNSAFE):
function displaySearchResults(results) {
    const container = document.getElementById('search-results');
    container.innerHTML = results.map(r => `
        <div class="result">
            <h4>${r.title}</h4>
            <p>${r.snippet}</p>
        </div>
    `).join('');
}

// AFTER (SAFE):
function displaySearchResults(results) {
    const container = document.getElementById('search-results');
    container.innerHTML = ''; // Clear first
    
    results.forEach(result => {
        const div = document.createElement('div');
        div.className = 'result';
        
        const title = document.createElement('h4');
        Security.setTextContent(title, result.title);
        
        const snippet = document.createElement('p');
        Security.setTextContent(snippet, result.snippet);
        
        div.appendChild(title);
        div.appendChild(snippet);
        container.appendChild(div);
    });
}
```

**Checklist for Day 2:**
- [ ] Find all `.innerHTML =` in app.js (use search)
- [ ] Replace with DOM API or Security.setTextContent()
- [ ] Test each UI component manually
- [ ] No user input should reach innerHTML directly

---

#### Task 1.3: Add CSP Headers (Day 3)

**File:** `app/Http/Middleware/SecurityHeaders.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Content Security Policy
        $response->headers->set('Content-Security-Policy', 
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "connect-src 'self' wss://echo-server.example.com; " .
            "frame-ancestors 'none';"
        );
        
        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        return $response;
    }
}
```

Register in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SecurityHeaders::class,
    ]);
})
```

**Testing:**
```bash
# Check headers
curl -I http://localhost:8000 | grep -i "content-security-policy"
```

---

## Phase 2: Error Handling (P0) - Days 4-5

### Objective
Prevent white screen of death with global error boundary.

#### Task 2.1: Global Error Handler (Day 4)

**File:** `public/js/utils/error-handler.js`

```javascript
/**
 * Global error handling system
 */
export class ErrorHandler {
    constructor() {
        this.setupGlobalHandlers();
        this.errorLog = [];
        this.maxLogSize = 50;
    }
    
    setupGlobalHandlers() {
        // Catch unhandled JS errors
        window.addEventListener('error', (event) => {
            this.handleError(event.error || event.message, {
                type: 'unhandled_error',
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            });
        });
        
        // Catch unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.handleError(event.reason, {
                type: 'unhandled_rejection',
                promise: event.promise
            });
        });
    }
    
    handleError(error, context = {}) {
        // Log error
        const errorEntry = {
            message: error?.message || String(error),
            stack: error?.stack,
            context,
            timestamp: new Date().toISOString(),
            url: window.location.href,
            userAgent: navigator.userAgent
        };
        
        this.errorLog.push(errorEntry);
        if (this.errorLog.length > this.maxLogSize) {
            this.errorLog.shift();
        }
        
        // Log to console in development
        if (window.location.hostname === 'localhost') {
            console.error('Caught error:', errorEntry);
        }
        
        // Send to backend
        this.reportError(errorEntry);
        
        // Show user-friendly message
        this.showErrorUI(error);
    }
    
    async reportError(errorEntry) {
        try {
            await fetch('/api/errors/report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(errorEntry)
            });
        } catch (e) {
            // Silently fail - don't show error for error reporting
            console.warn('Failed to report error:', e);
        }
    }
    
    showErrorUI(error) {
        // Don't show error UI multiple times
        if (document.getElementById('global-error-overlay')) {
            return;
        }
        
        const overlay = document.createElement('div');
        overlay.id = 'global-error-overlay';
        overlay.className = 'error-overlay';
        overlay.innerHTML = `
            <div class="error-dialog">
                <div class="error-icon">⚠️</div>
                <h2>Đã có lỗi xảy ra</h2>
                <p>Chúng tôi đã ghi nhận sự cố. Vui lòng thử lại hoặc liên hệ hỗ trợ.</p>
                <div class="error-actions">
                    <button class="btn btn-primary" onclick="location.reload()">
                        Tải lại trang
                    </button>
                    <button class="btn btn-secondary" onclick="history.back()">
                        Quay lại
                    </button>
                </div>
                ${window.location.hostname === 'localhost' ? `
                    <details class="error-details">
                        <summary>Chi tiết lỗi (dev only)</summary>
                        <pre>${error?.stack || error?.message || String(error)}</pre>
                    </details>
                ` : ''}
            </div>
        `;
        
        document.body.appendChild(overlay);
    }
    
    // Wrap async functions with error handling
    wrapAsync(fn) {
        return async (...args) => {
            try {
                return await fn(...args);
            } catch (error) {
                this.handleError(error, {
                    type: 'wrapped_function',
                    function: fn.name
                });
                throw error; // Re-throw for caller to handle
            }
        };
    }
    
    getErrorLog() {
        return [...this.errorLog];
    }
}

// Create global instance
export const errorHandler = new ErrorHandler();
```

**CSS:** Add to `public/css/app.css`
```css
.error-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: fadeIn 0.3s;
}

.error-dialog {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    max-width: 500px;
    text-align: center;
}

.error-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1.5rem;
}

.error-details {
    margin-top: 1rem;
    text-align: left;
}

.error-details pre {
    background: #f5f5f5;
    padding: 1rem;
    border-radius: 4px;
    overflow-x: auto;
    max-height: 200px;
}
```

---

#### Task 2.2: Backend Error Logging Endpoint (Day 4 - Afternoon)

**File:** `app/Http/Controllers/ErrorController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErrorController extends Controller
{
    public function report(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'stack' => 'nullable|string',
            'context' => 'nullable|array',
            'timestamp' => 'required|string',
            'url' => 'required|string',
            'userAgent' => 'required|string'
        ]);
        
        // Log frontend error
        Log::channel('frontend')->error('Frontend error', [
            'message' => $validated['message'],
            'stack' => $validated['stack'] ?? null,
            'context' => $validated['context'] ?? [],
            'url' => $validated['url'],
            'user_agent' => $validated['userAgent'],
            'user_id' => auth()->id(),
            'ip' => $request->ip()
        ]);
        
        return response()->json(['status' => 'logged']);
    }
}
```

**Route:** `routes/api.php`
```php
Route::post('/errors/report', [ErrorController::class, 'report'])
    ->name('api.errors.report');
```

**Config:** `config/logging.php`
```php
'channels' => [
    // ... existing channels
    
    'frontend' => [
        'driver' => 'daily',
        'path' => storage_path('logs/frontend.log'),
        'level' => 'error',
        'days' => 14,
    ],
],
```

---

#### Task 2.3: Integrate Error Handler (Day 5)

**Update app.js:**
```javascript
import { errorHandler } from './utils/error-handler.js';

// Wrap main initialization
document.addEventListener('DOMContentLoaded', errorHandler.wrapAsync(async () => {
    try {
        await initializeApp();
    } catch (error) {
        console.error('App initialization failed:', error);
        errorHandler.handleError(error, { type: 'init_error' });
    }
}));

// Wrap all async API calls
async function fetchMovies() {
    return errorHandler.wrapAsync(async () => {
        const response = await fetch('/api/movies');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })();
}
```

**Testing Day 5:**
```javascript
// Test error handling
throw new Error('Test error');
Promise.reject('Test rejection');

// Should show error overlay
// Should log to backend
```

---

## Phase 3: Architecture Foundation - Days 6-10

### Objective
Create reusable services and utilities to support refactoring.

#### Task 3.1: HTTP Client Service (Day 6)

**File:** `public/js/services/HttpClient.js`

```javascript
/**
 * Centralized HTTP client with error handling, loading states, and interceptors
 */
export class HttpClient {
    constructor(baseURL = '/api') {
        this.baseURL = baseURL;
        this.interceptors = {
            request: [],
            response: []
        };
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }
    
    // Add request interceptor
    useRequestInterceptor(interceptor) {
        this.interceptors.request.push(interceptor);
    }
    
    // Add response interceptor
    useResponseInterceptor(interceptor) {
        this.interceptors.response.push(interceptor);
    }
    
    // Get CSRF token
    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
    
    // Build full URL
    buildURL(endpoint) {
        if (endpoint.startsWith('http')) {
            return endpoint;
        }
        return `${this.baseURL}${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;
    }
    
    // Process request through interceptors
    async processRequest(config) {
        let processedConfig = { ...config };
        
        for (const interceptor of this.interceptors.request) {
            processedConfig = await interceptor(processedConfig);
        }
        
        return processedConfig;
    }
    
    // Process response through interceptors
    async processResponse(response) {
        let processedResponse = response;
        
        for (const interceptor of this.interceptors.response) {
            processedResponse = await interceptor(processedResponse);
        }
        
        return processedResponse;
    }
    
    // Core request method
    async request(endpoint, options = {}) {
        const config = await this.processRequest({
            url: this.buildURL(endpoint),
            method: options.method || 'GET',
            headers: {
                ...this.defaultHeaders,
                'X-CSRF-TOKEN': this.getCSRFToken(),
                ...options.headers
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
            ...options
        });
        
        try {
            const response = await fetch(config.url, config);
            const processedResponse = await this.processResponse(response);
            
            if (!processedResponse.ok) {
                const error = new Error(`HTTP ${processedResponse.status}`);
                error.response = processedResponse;
                
                try {
                    error.data = await processedResponse.json();
                } catch (e) {
                    error.data = { message: processedResponse.statusText };
                }
                
                throw error;
            }
            
            // Handle 204 No Content
            if (processedResponse.status === 204) {
                return null;
            }
            
            return await processedResponse.json();
        } catch (error) {
            // Network error or other fetch errors
            if (!error.response) {
                error.message = 'Lỗi kết nối. Vui lòng kiểm tra internet.';
            }
            throw error;
        }
    }
    
    // Convenience methods
    get(endpoint, options = {}) {
        return this.request(endpoint, { ...options, method: 'GET' });
    }
    
    post(endpoint, body, options = {}) {
        return this.request(endpoint, { ...options, method: 'POST', body });
    }
    
    put(endpoint, body, options = {}) {
        return this.request(endpoint, { ...options, method: 'PUT', body });
    }
    
    patch(endpoint, body, options = {}) {
        return this.request(endpoint, { ...options, method: 'PATCH', body });
    }
    
    delete(endpoint, options = {}) {
        return this.request(endpoint, { ...options, method: 'DELETE' });
    }
}

// Create global instance
export const http = new HttpClient('/api');

// Add loading state interceptor
let activeRequests = 0;

http.useRequestInterceptor(async (config) => {
    activeRequests++;
    document.body.classList.add('loading');
    return config;
});

http.useResponseInterceptor(async (response) => {
    activeRequests--;
    if (activeRequests === 0) {
        document.body.classList.remove('loading');
    }
    return response;
});

// Add auth interceptor
http.useResponseInterceptor(async (response) => {
    if (response.status === 401) {
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
    }
    return response;
});
```

**Usage examples:**
```javascript
import { http } from './services/HttpClient.js';

// Simple GET
const movies = await http.get('/movies');

// POST with body
const order = await http.post('/orders', {
    showtime_id: 123,
    seats: [1, 2, 3]
});

// Error handling
try {
    const data = await http.get('/api/protected');
} catch (error) {
    if (error.response?.status === 404) {
        console.log('Not found');
    } else {
        console.error(error.message);
    }
}
```

---

#### Task 3.2: Utility Modules (Days 7-8)

**File:** `public/js/utils/formatters.js`

```javascript
/**
 * Data formatting utilities
 */
export const Formatters = {
    currency(amount, currency = 'VND') {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },
    
    date(date, format = 'short') {
        const options = {
            short: { day: '2-digit', month: '2-digit', year: 'numeric' },
            long: { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' },
            time: { hour: '2-digit', minute: '2-digit' },
            datetime: { 
                day: '2-digit', 
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit', 
                minute: '2-digit' 
            }
        }[format] || {};
        
        return new Intl.DateTimeFormat('vi-VN', options).format(new Date(date));
    },
    
    duration(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours}h ${mins}m`;
    },
    
    truncate(text, maxLength, suffix = '...') {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength - suffix.length) + suffix;
    },
    
    phone(phone) {
        // Format: 0123456789 -> 0123 456 789
        return phone.replace(/(\d{4})(\d{3})(\d{3})/, '$1 $2 $3');
    }
};
```

**File:** `public/js/utils/validators.js`

```javascript
/**
 * Form validation utilities
 */
export const Validators = {
    required(value, fieldName = 'Trường này') {
        if (!value || String(value).trim() === '') {
            return `${fieldName} là bắt buộc`;
        }
        return null;
    },
    
    email(value) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regex.test(value)) {
            return 'Email không hợp lệ';
        }
        return null;
    },
    
    phone(value) {
        const regex = /^(0|\+84)[0-9]{9,10}$/;
        if (!regex.test(value.replace(/\s/g, ''))) {
            return 'Số điện thoại không hợp lệ';
        }
        return null;
    },
    
    password(value) {
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
    
    match(value1, value2, fieldName = 'Trường') {
        if (value1 !== value2) {
            return `${fieldName} không khớp`;
        }
        return null;
    },
    
    minLength(value, min, fieldName = 'Trường này') {
        if (value.length < min) {
            return `${fieldName} phải có ít nhất ${min} ký tự`;
        }
        return null;
    },
    
    maxLength(value, max, fieldName = 'Trường này') {
        if (value.length > max) {
            return `${fieldName} không được vượt quá ${max} ký tự`;
        }
        return null;
    },
    
    // Validate multiple rules
    validate(value, rules, fieldName) {
        for (const rule of rules) {
            const error = rule(value, fieldName);
            if (error) return error;
        }
        return null;
    }
};

/**
 * Form validator class
 */
export class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = {};
    }
    
    validateField(name, value, rules) {
        const error = Validators.validate(value, rules, name);
        
        if (error) {
            this.errors[name] = error;
            return false;
        } else {
            delete this.errors[name];
            return true;
        }
    }
    
    showError(fieldName, message) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        field.classList.add('is-invalid');
        
        let errorEl = field.nextElementSibling;
        if (!errorEl || !errorEl.classList.contains('invalid-feedback')) {
            errorEl = document.createElement('div');
            errorEl.className = 'invalid-feedback';
            field.parentNode.insertBefore(errorEl, field.nextSibling);
        }
        
        errorEl.textContent = message;
    }
    
    clearError(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        field.classList.remove('is-invalid');
        
        const errorEl = field.nextElementSibling;
        if (errorEl && errorEl.classList.contains('invalid-feedback')) {
            errorEl.remove();
        }
    }
    
    clearAllErrors() {
        this.errors = {};
        this.form.querySelectorAll('.is-invalid').forEach(field => {
            field.classList.remove('is-invalid');
        });
        this.form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
    }
    
    isValid() {
        return Object.keys(this.errors).length === 0;
    }
    
    getErrors() {
        return { ...this.errors };
    }
}
```

**File:** `public/js/utils/dom.js`

```javascript
/**
 * DOM manipulation utilities
 */
export const DOM = {
    /**
     * Cache for querySelector results
     */
    cache: new Map(),
    
    /**
     * Cached querySelector
     */
    qs(selector, useCache = true) {
        if (useCache && this.cache.has(selector)) {
            return this.cache.get(selector);
        }
        
        const element = document
querySelector(selector); 
        if (useCache) { 
            this.cache.set(selector, element); 
        } 
        return element; 
    }, 
ECHO is on.
    qsAll(selector) { 
        return Array.from(document.querySelectorAll(selector)); 
    }, 
ECHO is on.
    clearCache() { 
        this.cache.clear(); 
    }, 
ECHO is on.
    show(element) { 
        if (!element) return; 
        element.style.display = ''; 
        element.removeAttribute('hidden'); 
    }, 
ECHO is on.
    hide(element) { 
        if (!element) return; 
        element.style.display = 'none'; 
        element.setAttribute('hidden', ''); 
    }, 
ECHO is on.
    toggle(element) { 
        if (!element) return; 
        if (element.style.display === 'none') { 
            this.show(element); 
        } else { 
            this.hide(element); 
        } 
    } 
}; 
```
