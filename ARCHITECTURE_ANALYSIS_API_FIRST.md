# Architecture Analysis: API-First vs Blade MVC

**Document Version:** 1.0  
**Created:** 2026-06-11  
**Analyst:** Senior Solution Architect & Senior Laravel Developer  
**Status:** Analysis Complete  
**Priority:** CRITICAL - Architecture Foundation  

---

## Executive Summary

Sau khi review toàn bộ codebase, hệ thống cinema đang chạy theo **HYBRID ARCHITECTURE**:

- **Blade làm Shell Pages**: render HTML containers + inject config
- **JavaScript SPA-like**: fetch data qua API, handle UI/UX
- **Dual Authentication**: JWT (localStorage) + Session (cookie)
- **API-First cho business logic**: booking, payment, profile data

**KHÔNG phải pure Laravel Blade SSR như nhiều project Laravel truyền thống.**

### Key Finding

**Implementation Plan hiện tại (PAYMENT_FLOW_REFACTOR_PLAN.md) có giả định sai:**

❌ Phase 5 đề xuất ProfileController query data và pass vào View  
❌ Phase 5 đề xuất Blade render ticket list directly  
❌ Tư duy Blade MVC: Controller → Query → View → Render  

✅ Thực tế: Controller → return empty View → JS → API → render  
✅ Frontend đã SPA-like, backend đã API-first  
✅ Cần đồng bộ architecture pattern toàn project  

---

## 1. Mô Hình Kiến Trúc Thực Tế

### 1.1. Evidence từ Codebase

#### A. Web Routes (routes/web.php)

```php
// Shell pages - NO data queries
Route::view('/movies', 'users.movies.index')->name('movies.index');
Route::view('/movies/{idOrSlug}', 'users.movies.show')->name('movies.show');

Route::get('/booking/{encryptedShowtimeId}', [BookingController::class, 'show']);
Route::get('/profile', [ProfileController::class, 'index']);
Route::get('/my-tickets', [ProfileController::class, 'tickets']);
```

**Comment trong code:**
```php
// Login route - redirect to home (auth handled by frontend modal)
// Profile routes - auth handled by SSR (@guest/@auth directives in views)
// AuthenticateFromCookie middleware auto-authenticates from JWT cookie
```

**Phân tích:**
- Routes chỉ return views
- Auth handled by **frontend modal**
- Controller không query data
- Middleware chỉ authenticate để Blade có thể dùng @auth

---

#### B. Blade Layout (resources/views/layouts/app.blade.php)

```php
<script>
    window.APP_CONFIG = {
        appName: @json(config('app.name', 'Cinema')),
        apiUrl: @json(url('/api/v1')),
        csrfToken: @json(csrf_token()),
        auth: {
            checked: true,
            authenticated: @json(Auth::guard('web')->check()),
            user: @json(Auth::guard('web')->user()),
        },
    };
</script>

{{-- Auth Module --}}
<script src="{{ asset('js/auth.js') }}"></script>

@stack('scripts')
```

**Phân tích:**
- Blade **inject config** vào `window.APP_CONFIG`
- Blade **inject initial auth state** (để tránh flicker)
- Blade **load JavaScript modules**
- JavaScript sẽ gọi API để load data thực

**Pattern:** Blade là shell, JavaScript là engine

---

#### C. ProfileController Thực Tế

```php
public function index(): View
{
    return view('users.profile.index');
}

public function tickets(): View
{
    return view('users.tickets.index');
}
```

**Không có:**
- ❌ Query user data
- ❌ Query orders/tickets
- ❌ Eager loading
- ❌ Pagination
- ❌ Pass data to view

**Chỉ có:**
- ✅ Return empty shell view

---

#### D. Frontend JavaScript (public/js/pages/profile.js)

```js
async loadProfile() {
    // Check auth from authManager (frontend)
    if (!window.authManager?.isAuthenticated()) {
        this.showAuthRequired();
        return;
    }

    // CALL API to load data
    const response = await this.apiRequest('/auth/profile');
    this.user = response.data?.user || response.data || response.user || null;

    // Render with JS
    this.renderProfile();
    this.populateForms();
    this.showContent();
}
```

**Flow thực tế:**
```
1. Blade render shell page
2. JS check auth (from localStorage JWT)
3. JS call API: GET /api/v1/auth/profile
4. API return JSON
5. JS render UI
```

**KHÔNG có server-side data binding.**

---

#### E. Booking Flow (public/js/pages/booking.js)

```js
// Load seats via API
const response = await this.fetchAPI(`/seats?showtime_id=${this.config.showtimeId}`);

// Lock seats via API
const response = await this.fetchAPI('/seats/lock', {
    method: 'POST',
    body: JSON.stringify({ showtime_id, seat_ids })
});

// Create payment via API
const response = await this.fetchAPI('/payments', {
    method: 'POST',
    ...
});

// Redirect to PayOS
window.location.href = response.data.checkout_url;
```

**100% API-driven. Zero server-side rendering.**

---

### 1.2. Kết Luận Mô Hình

Hệ thống đang chạy theo **HYBRID: Blade Shell + JavaScript SPA**

| Component | Role | Tech |
|-----------|------|------|
| **Backend - Web Routes** | Return shell pages | Laravel Blade |
| **Backend - API Routes** | Business logic, data | Laravel API + JSON |
| **Frontend - JavaScript** | Load data, render UI, handle UX | Vanilla JS (SPA-like) |
| **Auth** | Dual: JWT (API) + Session (Blade @auth) | JWT + Laravel Session |
| **Realtime** | Optional Laravel Echo + Reverb | WebSocket |

**Không phải:**
- ❌ Pure Blade SSR (server render data)
- ❌ Pure SPA (React/Vue with no Blade)
- ❌ Traditional Laravel MVC

**Mà là:**
- ✅ Blade shell + API backend + JS frontend
- ✅ Progressive enhancement mindset
- ✅ SEO-friendly shell, interactive via JS
- ✅ Mobile app ready (same API)

---

## 2. Review Implementation Plan

File: `PAYMENT_FLOW_REFACTOR_PLAN.md`

### 2.1. Phase 1-4: Đúng Hướng ✅

**Phase 1: Payment Validation**
- ✅ Validate SeatHold trong PaymentService
- ✅ Backend API security
- ✅ Transaction safety
- ✅ API-first thinking

**Phase 2: Payment Ledger**
- ✅ Payment model/table
- ✅ Audit trail
- ✅ Reconciliation
- ✅ API response include payment info

**Phase 3: Order Fulfillment**
- ✅ Ticket model/table
- ✅ Release holds
- ✅ Stock management
- ✅ Atomic operations

**Phase 4: Unify Flows**
- ✅ Deprecate /orders
- ✅ Single payment flow
- ✅ Clear API contracts

**Những phase này đều API-centric, đúng với architecture thực tế.**

---

### 2.2. Phase 5: SAI - Mang Tư Duy Blade MVC ❌

#### Problem 1: ProfileController Query Data

**Trong Plan:**
```php
// Phase 5.1: Complete ProfileController@tickets
public function tickets(Request $request): View
{
    $tickets = Ticket::query()
        ->where('user_id', Auth::id())
        ->with([
            'order',
            'showtime.movie',
            'showtime.screen.cinema',
            'seat.seatType',
        ])
        ->when($request->query('status'), fn($q, $status) => 
            $q->where('status', $status)
        )
        ->latest()
        ->paginate(15);

    return view('users.tickets.index', compact('tickets'));
}
```

**Vấn đề:**
1. ❌ Controller query data directly
2. ❌ Pass data to Blade view
3. ❌ Blade render list server-side
4. ❌ Không có API endpoint
5. ❌ Mobile app không dùng được
6. ❌ Frontend JS phải reload page để filter/paginate
7. ❌ Không consistent với booking flow (API-first)

**Thực tế hiện tại:**
```php
public function tickets(): View
{
    return view('users.tickets.index'); // Empty shell
}
```

Frontend JS sẽ gọi API để load tickets.

---

#### Problem 2: Blade Render Tickets

**Trong Plan:**
```
Phase 5.2: Update Tickets Blade View

Display:
- Ticket list
- Movie poster
- Showtime info
- Seat info
- QR code
- Status badge
```

**Vấn đề:**
1. ❌ Blade loop through tickets server-side
2. ❌ No pagination/filter API
3. ❌ No mobile support
4. ❌ Hard to add realtime updates
5. ❌ Inconsistent with profile page (JS-rendered)

---

### 2.3. Giả Định Sai Trong Plan

| Giả Định | Thực Tế | Impact |
|----------|---------|--------|
| ProfileController nên query data | Controller chỉ return shell | HIGH |
| Blade render tickets server-side | JS gọi API, render client-side | HIGH |
| Traditional MVC pattern | API-first hybrid | CRITICAL |
| Single platform (web only) | Mobile app ready architecture | MEDIUM |
| Server-side pagination | Client-side via API | MEDIUM |

---

### 2.4. Root Cause Analysis

**Tại sao có giả định sai?**

1. **Laravel tradition**: Nhiều Laravel project dùng Blade MVC
2. **Phase 1-4 focus backend**: Chưa rõ frontend pattern
3. **Thiếu analysis frontend**: Chưa đọc profile.js, booking.js
4. **Assumption over evidence**: Giả định thay vì verify code thực tế

**Bài học:**
- ✅ Phải đọc frontend code trước khi đề xuất backend
- ✅ Phải verify architecture pattern toàn hệ thống
- ✅ Không assume Laravel = Blade MVC
- ✅ Follow existing patterns, không invent mới

---

## 3. API-First Architecture Best Practice 2026

### 3.1. Principles

**1. Backend API-First**
- All business logic exposed via REST API
- Controllers return JSON for data endpoints
- Web controllers return shell views only
- Consistent API contracts

**2. Frontend SPA-like**
- JavaScript fetch data via API
- Client-side rendering
- Progressive enhancement
- SEO-friendly shells

**3. Mobile Ready**
- Same API for web + mobile
- JWT authentication
- Stateless backend
- Versioned APIs

**4. Separation of Concerns**
- Backend: business logic, security, data
- Frontend: UI/UX, interaction, rendering
- Clear boundaries
- Independent deployment (future)

---

### 3.2. Architecture Layers

```
┌─────────────────────────────────────────┐
│         Frontend (Browser/Mobile)        │
│  - JavaScript (Vanilla/React/Vue)        │
│  - Fetch API data                        │
│  - Render UI                             │
│  - Handle events                         │
└──────────────┬──────────────────────────┘
               │ HTTP JSON
               │ JWT Token
┌──────────────▼──────────────────────────┐
│         Laravel API Backend              │
│  ┌────────────────────────────────────┐ │
│  │   API Routes (/api/v1/*)           │ │
│  │   - Payment, Booking, Profile       │ │
│  │   - Return JSON                     │ │
│  │   - JWT Auth                        │ │
│  └────────────────────────────────────┘ │
│  ┌────────────────────────────────────┐ │
│  │   Web Routes (/, /profile, etc)    │ │
│  │   - Return Blade shell views        │ │
│  │   - Inject config                   │ │
│  │   - SEO metadata                    │ │
│  └────────────────────────────────────┘ │
│  ┌────────────────────────────────────┐ │
│  │   Services (Business Logic)        │ │
│  │   - PaymentService                  │ │
│  │   - OrderFulfillmentService         │ │
│  │   - Shared by API controllers       │ │
│  └────────────────────────────────────┘ │
└─────────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│          Database (MySQL)                │
└─────────────────────────────────────────┘
```

---

### 3.3. Controller Patterns

#### Pattern A: API Controller (JSON Response)

**Purpose:** Provide data/actions for frontend

```php
namespace App\Http\Controllers\Api\V1;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $tickets = Ticket::query()
            ->where('user_id', $user->id)
            ->with(['order', 'showtime.movie', 'seat'])
            ->when($request->query('status'), fn($q, $status) => 
                $q->where('status', $status)
            )
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }
}
```

**Route:**
```php
Route::get('/tickets', [TicketController::class, 'index'])
    ->middleware('auth:sanctum');
```

---

#### Pattern B: Web Controller (Shell View)

**Purpose:** Return HTML shell for SEO + initial load

```php
namespace App\Http\Controllers;

class ProfileController extends Controller
{
    public function tickets(): View
    {
        // NO data queries
        // NO eager loading
        // ONLY return shell
        
        return view('users.tickets.index', [
            'meta' => [
                'title' => 'My Tickets - Cinema',
                'description' => 'View your movie tickets',
            ],
        ]);
    }
}
```

**Route:**
```php
Route::get('/my-tickets', [ProfileController::class, 'tickets'])
    ->name('tickets.index');
```

---

#### Pattern C: Hybrid Controller (Rare Cases)

**Purpose:** SSR for SEO-critical pages (home, movie list)

```php
class HomeController extends Controller
{
    public function index(): View
    {
        // ONLY for SEO/initial load
        // Keep queries simple
        
        $featuredMovies = Cache::remember('home.featured', 3600, function () {
            return Movie::featured()->limit(6)->get();
        });

        return view('home', [
            'initialMovies' => $featuredMovies, // For SSR
            'apiUrl' => url('/api/v1'), // For JS hydration
        ]);
    }
}
```

**Frontend JS can:**
- Use initial data if available
- Or fetch fresh data via API
- Progressive enhancement

---

### 3.4. Frontend Pattern Summary

JavaScript modules follow SPA-like architecture:
- Class-based structure with init/render lifecycle
- API calls with JWT Bearer token authentication
- Client-side rendering using template literals
- Progressive enhancement approach

---

## 4. Phase 5 Implementation (REVISED)

**Old Approach (WRONG):**
- ProfileController queries database
- Pass data to Blade view
- Blade renders server-side

**New Approach (CORRECT):**
- ProfileController returns empty shell
- JavaScript fetches data from /api/v1/tickets
- JavaScript renders client-side

**Files to Create:**
1. app/Http/Controllers/Api/V1/TicketController.php
2. public/js/pages/tickets.js
3. Update routes/api.php

**Estimated Time:** 9 hours

---

## 5. Best Practices Summary

| Module | Status | Recommendation |
|--------|--------|---------------|
| Booking | ✅ API-first | Keep as-is |
| Payment | 🔄 Phase 1-4 correct | Follow PAYMENT_FLOW_REFACTOR_PLAN.md |
| Tickets | ❌ Missing API | Implement Phase 5 REVISED |
| Admin | ⏳ Future | Use Filament framework |

---

## 6. Migration Priority

**CRITICAL (Now):** Phase 1-4 payment security fixes
**HIGH (Week 2):** Tickets API + frontend  
**MEDIUM (Month 2):** Admin dashboard
**LOW (Future):** Native mobile app

---

## 7. Architecture Comparison

| Aspect | Blade MVC | API-First | Winner |
|--------|-----------|-----------|--------|
| Mobile Support | ❌ | ✅ | API-First |
| Maintenance | ❌ Coupled | ✅ Separated | API-First |
| Scalability | ❌ Limited | ✅ High | API-First |
| Realtime | ❌ Hard | ✅ Easy | API-First |

---

## 8. Final Recommendations

**✅ ADOPT: API-First Hybrid Architecture**

Key principles:
- Blade for shell pages only (SEO)
- All data via JSON API endpoints
- JavaScript handles rendering
- Dual authentication (JWT + Session)
- Mobile-ready by design

**Implementation Order:**
1. CRITICAL: Payment security (Phase 1-4)
2. HIGH: Tickets API endpoint
3. MEDIUM: Admin dashboard
4. LOW: Mobile app

**Success Metrics:**
- All business logic accessible via API
- Zero Blade data binding (except SEO)
- API test coverage > 80%
- Mobile app can consume APIs

---

## Appendix

**Key Files:**
- routes/api.php - API endpoints
- routes/web.php - Shell pages
- app/Services/* - Business logic
- public/js/pages/* - Frontend modules

**Related Documents:**
- PAYMENT_FLOW_REFACTOR_PLAN.md (Phase 1-4 ✅, Phase 5 ❌ superseded)
- ARCHITECTURE.md - Original architecture
- PROJECT_COMPREHENSIVE_REVIEW_2026.md - Full review

---

**END OF ANALYSIS**

**Document Status:** Complete  
**Review Required:** Yes - Architecture decision  
**Next Steps:** Update Phase 5 plan and begin implementation

*This analysis supersedes Phase 5 of PAYMENT_FLOW_REFACTOR_PLAN.md and establishes API-first as the standard pattern.*

