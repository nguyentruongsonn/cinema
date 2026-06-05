# 🎬 Cinema Booking – Senior Standards

> Quy tắc và nguyên tắc xây dựng Frontend & Backend chuẩn Senior cho website đặt vé xem phim.
> Stack: Laravel 11, Bootstrap 5, Vanilla JS (Fetch API), JWT, RESTful API, Realtime.

---

## 1. KIẾN TRÚC TỔNG THỂ

### 1.1. Phân tách rõ ràng Server-rendered vs Client-rendered

| Loại trang | Chiến lược | Ví dụ |
|---|---|---|
| **Public/SEO** | Blade shell + Fetch API render | Home, Movie detail, Showtimes |
| **Admin** | Blade full server-render (hoặc Inertia) | Dashboard, CRUD |
| **Auth** | Blade server-render | Login, Register |

**Nguyên tắc:**
- Blade chỉ chịu trách nhiệm render **khung tĩnh** (`<html>`, header, footer, container rỗng)
- JS đảm nhận **nội dung động** qua Fetch API
- SEO-critical content ưu tiên server-render Blade truyền thống

### 1.2. Mô hình MVC thuần (Laravel)

```
Request → Route → Controller → Service → Model → Database
                                ↓
                          Response (JSON / View)
```

- **Controller**: Tiếp nhận request, gọi Service, trả response. **Controller phải THIN**.
- **Service**: Xử lý business logic, gọi Model, query DB. **Service chứa logic, Controller không chứa logic**.
- **Model**: Eloquent Model, định nghĩa relationships, scopes, accessors, mutators. **Không chứa business logic phức tạp**.
- **Response**: Luôn dùng `ApiResponse` trait để đồng nhất format JSON.

---

## 2. BACKEND STANDARDS (Laravel)

### 2.1. Controller Standards

```php
// ✅ GOOD – Thin Controller
class MovieController extends Controller
{
    public function index(MovieService $service): JsonResponse
    {
        $movies = $service->getNowShowing();

        return $this->successResponse($movies);
    }
}

// ❌ BAD – Fat Controller (chứa business logic)
public function index(): JsonResponse
{
    $movies = Movie::query()
        ->where('status', 'active')
        ->where('release_date', '<=', now())
        ->with(['categories', 'showtimes'])
        ->get()
        ->map(function ($movie) {
            // ... logic phức tạp
        });
    // ...
}
```

**Rules:**
- Controller methods: tối đa **5-10 dòng**
- Không query DB trực tiếp trong Controller
- Không xử lý business logic trong Controller
- Dùng **Form Request** để validate
- Dùng **Resource** để transform response (hoặc `transformMovie()` pattern)

### 2.2. Service Layer

```php
// ✅ GOOD
class MovieService
{
    public function __construct(
        private readonly Movie $model,
        private readonly CacheService $cache,
    ) {}
    
    public function getNowShowing(): Collection
    {
        return $this->cache->remember('movies.now_showing', 3600, function () {
            return $this->model
                ->active()
                ->nowShowing()
                ->with('categories:id,name')
                ->latest('release_date')
                ->limit(8)
                ->get();
        });
    }
}
```

**Rules:**
- Mỗi Model business-heavy nên có Service riêng
- Service inject dependencies qua constructor
- Service không extends gì cả (POJO)
- Service có thể gọi Service khác
- Cache handling ở Service layer, không ở Controller

### 2.3. API Response Format

Luôn dùng `ApiResponse` trait:

```json
{
    "success": true,
    "message": "Home data loaded successfully",
    "data": { ... },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "total": 50
    },
    "errors": null
}
```

**Rules:**
- `success`: boolean
- `message`: string (tiếng Việt hoặc tiếng Anh tùy dự án, nhất quán)
- `data`: object hoặc array
- `meta`: pagination, version, timestamp (khi cần)
- `errors`: validation errors hoặc null
- HTTP status code chuẩn (200, 201, 400, 401, 403, 404, 422, 500)

### 2.4. Validation

```php
// ✅ GOOD – Form Request
class StoreMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-movies') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('movies')->ignore($this->route('id'))],
            'slug' => ['nullable', 'alpha_dash', 'unique:movies,slug'],
            'duration' => ['required', 'integer', 'min:1', 'max:600'],
            'release_date' => ['required', 'date', 'after:today'],
            'poster_url' => ['nullable', 'url'],
            'age_rating' => ['nullable', Rule::in(['P', 'K', 'T13', 'T16', 'T18'])],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề phim không được để trống',
            'duration.min' => 'Thời lượng phải lớn hơn 0',
        ];
    }
}
```

**Rules:**
- KHÔNG dùng `$request->validate()` trong Controller
- Mỗi form/action tạo Form Request riêng
- Validation messages tiếng Việt (end-user)
- Dùng `Rule` class cho các rule phức tạp
- Phân quyền (`authorize()`) ngay trong Form Request

### 2.5. Model Standards

```php
class Movie extends Model
{
    // 1. Fillable / Guarded
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    // 2. Casts
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'is_hot' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
    
    // 3. Scopes
    public function scopeNowShowing(Builder $query): Builder
    {
        return $query->where('status', 'now_showing')
            ->where('release_date', '<=', now());
    }
    
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'upcoming')
            ->where('release_date', '>', now());
    }
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
    
    // 4. Relationships
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }
    
    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
    
    // 5. Accessors / Mutators
    public function getPosterUrlAttribute(?string $value): ?string
    {
        return $value ?: config('app.default_poster');
    }
    
    public function getDurationLabelAttribute(): string
    {
        return "{$this->duration} phút";
    }
}

// ❌ BAD – Model chứa business logic
public function getFeaturedMovie()
{
    return $this->where('is_hot', true)->first(); // ❌ Sai: logic ở Service
}
```

### 2.6. Route Standards

```php
// api.php – API Routes
Route::prefix('v1')->group(function () {
    // Public
    Route::get('home', [HomeController::class, 'data']);
    Route::apiResource('movies', MovieController::class)->only(['index', 'show']);
    
    // Auth required
    Route::middleware('auth:api')->group(function () {
        Route::get('orders/user/me', [OrderController::class, 'userOrders']);
        Route::apiResource('orders', OrderController::class)->only(['store', 'show']);
        Route::put('orders/{id}/cancel', [OrderController::class, 'cancel']);
    });
    
    // Admin
    Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
        Route::apiResource('movies', MovieController::class)->except(['index', 'show']);
        Route::apiResource('theaters', TheaterController::class)->except(['index', 'show']);
    });
});

// web.php – Web Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/movies', [MovieController::class, 'webIndex'])->name('movies.index');
Route::get('/movies/{slug}', [MovieController::class, 'webShow'])->name('movies.show');
```

**Rules:**
- API routes: `api.php` (có prefix `/api`)
- Web routes: `web.php`
- Admin routes: group `prefix('admin')` + middleware `role:admin`
- Resource routes: dùng `apiResource` cho API, `resource` cho web
- Route names: snake_case, `resource.action` (VD: `movies.store`)

### 2.7. JWT Authentication

```php
// ✅ Middleware
class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = Auth::guard('api')->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token đã hết hạn'], 401);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Token không hợp lệ'], 401);
        }
        
        return $next($request);
    }
}
```

**Rules:**
- Dùng `tymon/jwt-auth` (JWT cho Laravel)
- Token gửi qua header `Authorization: Bearer {token}`
- Refresh token tự động khi gọi `/api/auth/refresh`
- Blacklist token khi logout
- Thời hạn: access token 60 phút, refresh token 7 ngày

### 2.8. Database Naming Conventions

| Object | Convention | Example |
|---|---|---|
| Tables | snake_case, plural | `movies`, `showtimes`, `seat_holds` |
| Columns | snake_case | `release_date`, `poster_url` |
| PK | `id` | `id` |
| FK | `singular_id` | `movie_id`, `user_id` |
| Pivot | `singular1_singular2` | `category_movie` |
| Indexes | `table_column_index` | `movies_release_date_index` |
| Migrations | `YYYY_MM_DD_HHmmss_create_movies_table` | Laravel default |

### 2.9. Error Handling

```php
// app/Exceptions/Handler.php
public function register(): void
{
    $this->renderable(function (ValidationException $e, Request $request) {
        if ($request->expectsJson()) {
            return $this->errorResponse(
                'Dữ liệu không hợp lệ',
                422,
                $e->errors()
            );
        }
    });

    $this->renderable(function (ModelNotFoundException $e, Request $request) {
        if ($request->expectsJson()) {
            return $this->errorResponse('Không tìm thấy dữ liệu', 404);
        }
    });
}
```

**Rules:**
- Bắt exception global trong `Handler`
- API errors → JSON response
- Web errors → Error pages (404.blade.php, 500.blade.php)
- Log errors với context (user, action, input)

### 2.10. Security

- **SQL Injection**: Eloquent/Query Builder tự động escape (không RAW query nếu không cần)
- **XSS**: Blade auto-escape `{{ }}`, JS `textContent` (không `innerHTML` với user input)
- **CSRF**: `@csrf` trong tất cả form Blade
- **Auth**: JWT middleware, rate limiting (`throttle:60,1`)
- **CORS**: Cấu hình `config/cors.php` cho frontend domain

---

## 3. FRONTEND STANDARDS (JS + Bootstrap 5)

### 3.1. IIFE / Module Pattern

```javascript
// ✅ GOOD – Immediately Invoked Function Expression
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Configuration                                                      */
    /* ------------------------------------------------------------------ */
    const API_BASE = '/api';
    const SELECTORS = {
        hero: '#heroContent',
        grid: '#movie-grid',
    };

    /* ------------------------------------------------------------------ */
    /*  State                                                              */
    /* ------------------------------------------------------------------ */
    let state = {
        movies: [],
        isLoading: false,
    };

    /* ------------------------------------------------------------------ */
    /*  DOM References                                                     */
    /* ------------------------------------------------------------------ */
    let els = {};

    function cacheDoms() {
        els = {
            hero: document.querySelector(SELECTORS.hero),
            grid: document.querySelector(SELECTORS.grid),
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */
    function currency(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + '₫';
    }

    function htmlToElement(html) {
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        return div.firstElementChild;
    }

    /* ------------------------------------------------------------------ */
    /*  Render                                                             */
    /* ------------------------------------------------------------------ */
    function renderHero(movie) {
        if (!els.hero) return;
        els.hero.innerHTML = ''; // Clear loading
        els.hero.appendChild(htmlToElement(buildHeroHTML(movie)));
    }

    /* ------------------------------------------------------------------ */
    /*  API                                                                */
    /* ------------------------------------------------------------------ */
    async function fetchData(url, options = {}) {
        try {
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...options.headers,
                },
                ...options,
            });

            if (!res.ok) {
                const error = await res.json().catch(() => ({}));
                throw new Error(error.message || `HTTP ${res.status}`);
            }

            return await res.json();
        } catch (err) {
            console.error(`[API] ${url}`, err);
            throw err;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Bootstrap                                                          */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        cacheDoms();
        init();
    });
})();
```

**Rules:**
- Mỗi page một file JS riêng trong `public/js/pages/` (VD: `home.js`, `movie-detail.js`)
- Dùng IIFE để không pollute global scope
- Những hàm cần global (VD: `selectMovie`) gán explicit qua `window.selectMovie = fn`
- Cache DOM references một lần ở đầu → không query DOM lại

### 3.2. Fetch API Pattern

```javascript
// ✅ GOOD – Centralized fetch wrapper
async function apiGet(url, params = {}) {
    const query = new URLSearchParams(params).toString();
    const fullUrl = query ? `${url}?${query}` : url;

    const res = await fetch(fullUrl, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!res.ok) handleApiError(res);
    return res.json();
}

async function apiPost(url, data = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${getToken()}`,
        },
        body: JSON.stringify(data),
    });

    if (!res.ok) handleApiError(res);
    return res.json();
}
```

**Rules:**
- Một wrapper function cho GET, POST, PUT, DELETE
- Tất cả request đều có `Accept: application/json`
- POST/PUT có `Content-Type: application/json`
- Token từ localStorage hoặc cookie
- Error handler tập trung (show toast, redirect nếu 401)

### 3.3. Error Handling Frontend

```javascript
function handleApiError(res) {
    if (res.status === 401) {
        // Token hết hạn → redirect login
        localStorage.removeItem('token');
        window.location.href = '/login';
        return;
    }
    
    if (res.status === 422) {
        // Validation error → show field errors
        return res.json().then(json => {
            showValidationErrors(json.errors);
        });
    }
    
    // Generic error
    res.json().then(json => {
        showToast(json.message || 'Đã xảy ra lỗi, vui lòng thử lại', 'danger');
    });
}
```

**Rules:**
- Không try-catch ở từng page → **Error handler tập trung**
- 401 → refresh token hoặc redirect login
- 422 → show validation errors dưới form field
- 500 → show toast "Hệ thống đang bảo trì"
- Network error → show "Vui lòng kiểm tra kết nối"

### 3.4. DOM Manipulation Rules

```javascript
// ✅ GOOD – textContent (no XSS)
element.textContent = userInput;

// ✅ GOOD – href (safe)
link.href = url;

// ❌ BAD – innerHTML với user input (XSS risk)
element.innerHTML = userInput; // ❌

// ✅ GOOD – Nếu bắt buộc dùng innerHTML, phải sanitize
function sanitize(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
element.innerHTML = `<b>${sanitize(userInput)}</b>`;
```

**Rules:**
- **Ưu tiên** `textContent`, `createElement`, `appendChild`
- Chỉ dùng `innerHTML` với **template string đã biết trước** (không chứa raw user input)
- Nội dung từ API (VD: movie title) render qua `textContent`
- Dùng `classList.add/remove/toggle` thay vì `className`
- Không dùng jQuery (Vanilla JS)

### 3.5. CSS Naming Convention (BEM-like)

```css
/* Block: cinema-hero */
.cinema-hero {}
.cinema-hero-content {}
.cinema-hero-backdrop {}

/* Element: block__element */
.cinema-hero__title {}
.cinema-hero__desc {}

/* Modifier: block--modifier */
.cinema-hero--featured {}
.cinema-tab--active {}

/* Utility */
.cinema-text-muted {}
.cinema-bg-panel {}
```

**Rules:**
- Prefix `cinema-` cho tất cả class (namespace)
- Phân cách bằng `-` (kebab-case)
- Component-level: `.cinema-hero-title`, `.cinema-movie-card`
- Utility/mixin: `.cinema-btn-primary`, `.cinema-control`
- Mỗi file CSS/page theo component, không viết một file dài

### 3.6. Bootstrap 5 Usage

```html
<!-- ✅ GOOD – Bootstrap utility-first -->
<button class="btn btn-danger rounded-pill fw-bold px-4">
    Đặt Vé
</button>

<!-- ✅ GOOD – Grid responsive -->
<div class="row g-3">
    <div class="col-6 col-sm-4 col-lg-3">
        <div class="cinema-movie-card">...</div>
    </div>
</div>

<!-- ✅ GOOD – Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark">...</div>
    </div>
</div>
```

**Rules:**
- Tận dụng **Utility classes** của Bootstrap (spacing, typography, color, flex, grid)
- Chỉ viết CSS custom cho component không có sẵn
- Modal: dùng Bootstrap Modal (JS API không cần jQuery)
- Form: dùng `form-control`, `form-select`, `form-label`, `invalid-feedback`
- Toast: dùng Bootstrap Toast
- Tooltip/Popover: Bootstrap API

### 3.7. Responsive Design Principles

```css
/* ✅ Mobile-first */
.cinema-movie-card {
    /* Base: mobile */
    width: 100%;
}

/* Tablet */
@media (min-width: 576px) {
    .cinema-movie-card { ... }
}

/* Desktop */
@media (min-width: 992px) {
    .cinema-movie-card { ... }
}
```

**Breakpoints (Bootstrap 5):**
| Name | Min Width | Device |
|---|---|---|
| xs | <576px | Phone |
| sm | ≥576px | Phone landscape |
| md | ≥768px | Tablet |
| lg | ≥992px | Desktop |
| xl | ≥1200px | Large desktop |
| xxl | ≥1400px | Extra large |

**Rules:**
- Mobile-first: base style cho mobile, media query tăng dần
- `clamp()` cho font-size responsive: `font-size: clamp(1rem, 2.5vw, 2rem)`
- Grid Bootstrap: `col-12 col-md-6 col-lg-4`
- Touch targets tối thiểu 44×44px
- Test trên 320px, 375px, 768px, 1024px, 1440px

### 3.8. Performance Rules

```html
<!-- ✅ Lazy loading images -->
<img src="poster.jpg" loading="lazy" alt="Movie title">

<!-- ✅ Defer JS -->
<script src="/js/pages/home.js" defer></script>

<!-- ✅ Preload critical -->
<link rel="preload" href="/css/style.css" as="style">
```

**Rules:**
- Tất cả `<img>` dưới fold có `loading="lazy"`
- JS không block render: dùng `defer` hoặc load cuối `<body>`
- Giảm DOM reflow: cache DOM, batch updates
- Debounce scroll/resize events
- Không dùng CSS `@import` (blocking)
- CSS animation → dùng `transform` và `opacity` (GPU-accelerated)

### 3.9. Accessibility (a11y)

```html
<!-- ✅ Semantic HTML -->
<nav aria-label="Main navigation">
<ul>
    <li><a href="/movies" aria-current="page">Phim</a></li>
</ul>
</nav>

<!-- ✅ Form labels -->
<label for="movie" class="form-label">Chọn phim</label>
<select id="movie" class="form-select" aria-describedby="movieHelp">
    ...
</select>

<!-- ✅ Loading state -->
<div id="heroContent" role="status" aria-live="polite">
    <span class="spinner"></span>
    <span class="visually-hidden">Đang tải...</span>
</div>

<!-- ✅ Error messages -->
<div class="invalid-feedback" role="alert">
    Vui lòng chọn phim
</div>
```

**Rules:**
- HTML semantic: `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>`
- Form controls có `<label>` rõ ràng
- Images có `alt` text (mô tả ngắn gọn)
- ARIA labels cho interactive elements không có text
- Color contrast tối thiểu 4.5:1
- Keyboard navigation: tất cả interactive element có focus style
- Loading/error states: `aria-live="polite"` hoặc `role="status"`

---

## 4. TỔ CHỨC CODE & FILE STRUCTURE

```
cinema/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Thin controllers
│   │   │   ├── Admin/            # Admin controllers
│   │   │   ├── AuthController.php
│   │   │   ├── HomeController.php
│   │   │   ├── MovieController.php
│   │   │   └── ...
│   │   ├── Middleware/            # JWT, Role, Admin middleware
│   │   └── Requests/             # Form Request validation
│   ├── Models/                   # Eloquent Models + Scopes
│   ├── Services/                 # Business logic
│   └── Traits/                   # ApiResponse, ...
├── public/
│   ├── css/
│   │   └── style.css             # Custom CSS (cinema-*)
│   └── js/
│       └── pages/                # JS per page
│           ├── home.js
│           ├── movie-detail.js
│           └── ...
├── resources/
│   └── views/
│       └── users/                # Frontend views
│       └── layouts/              # Layouts
│       └── partials/             # Reusable partials
├── routes/
│   ├── api.php                   # API routes
│   └── web.php                   # Web routes
└── database/
    └── migrations/               # Migrations từ SQL
```

---

## 5. QUY TRÌNH PHÁT TRIỂN (GIT FLOW)

1. **Tạo issue/task** → xác định yêu cầu
2. **Branch**: `feature/{module}` từ `develop`
3. **Implement**:
   - Database: migration
   - Backend: Model → Service → Controller → Route → Test
   - Frontend: Blade shell → CSS → JS → Test
4. **Self-review**: check code standards, security, accessibility
5. **Pull request** → review → merge vào `develop`
6. **Test** trên staging
7. **Release** → merge `develop` vào `main`

---

## 6. CHECKLIST LINT/BUILD

**Trước khi commit:**

- [ ] Controller không chứa business logic
- [ ] Form Request validation đầy đủ
- [ ] API response format đúng (`success`, `message`, `data`, `errors`)
- [ ] JS không có `console.log` (debug code)
- [ ] CSS không có class không dùng
- [ ] Image có `loading="lazy"` (dưới fold)
- [ ] HTML semantic (header, nav, main, footer)
- [ ] Form có label
- [ ] Responsive test 320px, 768px, 1024px

---

> Tài liệu này được cập nhật liên tục trong quá trình phát triển dự án.
