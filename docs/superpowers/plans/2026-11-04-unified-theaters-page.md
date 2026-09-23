# Unified "Rạp" Page & 5s Banner Slider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate the separate cinema list and ticket price pages into a unified "Rạp" page (`/theaters`) featuring an admin-managed 5-second auto-transitioning top banner slider, cinema selector, showtimes, and side-by-side pricing matrix and cinema details.

**Architecture:** Add a `placement` attribute to banners to allow targeting banners specifically for the theater page from `/admin/banners`. Update public APIs, consolidate `/prices` and `/theaters` routes with a 301 redirect, rewrite `users.theaters.index` blade view and `theaters.css`, and implement a modular, clean frontend controller in `theaters.js` managing the 5-second slider and data synchronization across the cinema selector, showtimes, and ticket pricing.

**Tech Stack:** Laravel 11 (PHP 8.2+), Blade, Vanilla JavaScript (ES6+), CSS3 / Flexbox / Grid, Vite, PHPUnit, Playwright.

**Spec:** [docs/superpowers/specs/2026-11-04-unified-theaters-page-design.md](file:///c:/xampp/htdocs/cinema/docs/superpowers/specs/2026-11-04-unified-theaters-page-design.md)

## Global Constraints

- **No code comments**: Do NOT write comments in CSS, JS, or Blade code files.
- **Strict Design Rule**: Follow project-wide `border-radius: 0.5rem` on interactive inputs and buttons.
- **Zero Regression on Logic**: Preserve all authentication, booking, and API endpoints.
- **XSS Protection**: All dynamic HTML must be sanitized using `escapeHtml()`.
- **Security & Syntax Gates**: Must pass `npm run test:frontend:syntax` and `npm run test:frontend:security`.

## Review Focus

1. Banners with `placement = 'all'` or `placement = 'theater'` both appear in `/theaters` carousel, while `placement = 'home'` banners do not leak into the theater page.
2. When switching between theaters via the dropdown, the ticket pricing table on the left immediately updates to reflect that specific theater's `pricing_profile`.
3. Auto-advancing slider advances every 5000ms, pauses when the user hovers or interacts, and resumes without interval drift.
4. Accessing `/prices` returns an HTTP 301 permanent redirect to `/theaters`.
5. Mobile viewport (< 768px) cleanly stacks the 2-column pricing/details section into a responsive single column without horizontal scroll.

---

### Task 1: Database Migration & Model for Banner Placement

**Files:**
- Create: `database/migrations/2026_11_04_000001_add_placement_to_banners_table.php`
- Modify: `app/Models/Banner.php:13-35`
- Test: `tests/Feature/BannerPlacementTest.php`

**Interfaces:**
- Consumes: `Banner` model, Laravel Schema Builder
- Produces: `Banner::$fillable` with `placement`, `Banner::scopeForPlacement($query, $placement)`

- [ ] **Step 1: Write the failing test in `tests/Feature/BannerPlacementTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerPlacementTest extends TestCase
{
    public function test_banner_has_placement_column_with_default_home(): void
    {
        $banner = Banner::create([
            'title' => 'Test Banner',
            'is_active' => true,
        ]);

        $this->assertEquals('home', $banner->fresh()->placement);
    }

    public function test_scope_for_placement_filters_correctly(): void
    {
        Banner::create(['title' => 'Home Only', 'placement' => 'home', 'is_active' => true]);
        Banner::create(['title' => 'Theater Only', 'placement' => 'theater', 'is_active' => true]);
        Banner::create(['title' => 'All Pages', 'placement' => 'all', 'is_active' => true]);

        $theaterBanners = Banner::active()->forPlacement('theater')->pluck('title')->all();

        $this->assertContains('Theater Only', $theaterBanners);
        $this->assertContains('All Pages', $theaterBanners);
        $this->assertNotContains('Home Only', $theaterBanners);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BannerPlacementTest.php`  
Expected: FAIL with missing column or method `forPlacement`.

- [ ] **Step 3: Implement migration and update `Banner` model**

Create migration `database/migrations/2026_11_04_000001_add_placement_to_banners_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('placement', 50)->default('home')->after('link_url');
            $table->index(['is_active', 'placement', 'created_at'], 'banners_active_placement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_active_placement_idx');
            $table->dropColumn('placement');
        });
    }
};
```
Run `php artisan migrate`.  
Update `app/Models/Banner.php`:
Add `'placement'` to `$fillable`.  
Add scope:
```php
public function scopeForPlacement($query, string $placement)
{
    return $query->whereIn('placement', [$placement, 'all']);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/BannerPlacementTest.php`  
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*add_placement_to_banners_table.php app/Models/Banner.php tests/Feature/BannerPlacementTest.php
git commit -m "feat(banner): add placement column and scopeForPlacement to banners"
```

---

### Task 2: Admin Banner Management & Public Banner API with Placement

**Files:**
- Modify: `app/Http/Controllers/Admin/BannerController.php:98-107, 175-185, 300-330`
- Modify: `app/Http/Resources/BannerResource.php:12-25`
- Modify: `app/Http/Controllers/ContentController.php:23-35`
- Modify: `resources/views/admin/banners/index.blade.php:85-115`
- Modify: `public/js/admin/pages/banners.js:40-120`
- Test: `tests/Feature/AdminBannerPlacementApiTest.php`

**Interfaces:**
- Consumes: `Banner::scopeForPlacement()`, `PublicFileStorageService`
- Produces: API response with `placement`, Admin UI with placement select

- [ ] **Step 1: Write the failing test in `tests/Feature/AdminBannerPlacementApiTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBannerPlacementApiTest extends TestCase
{
    public function test_public_banners_api_filters_by_placement(): void
    {
        Banner::create(['title' => 'Home Banner', 'placement' => 'home', 'is_active' => true]);
        Banner::create(['title' => 'Theater Banner', 'placement' => 'theater', 'is_active' => true]);

        $response = $this->getJson('/api/v1/banners?placement=theater');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Theater Banner', $titles);
        $this->assertNotContains('Home Banner', $titles);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AdminBannerPlacementApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement controller validation, API filter, resource, and admin modal**

- In `app/Http/Controllers/Admin/BannerController.php`:
  - Add `'placement' => ['nullable', 'string', Rule::in(['home', 'theater', 'all'])]` in `store` and `update` rules.
  - In `sanitizeBannerData`: preserve `placement` (defaulting to `'home'`).
- In `app/Http/Resources/BannerResource.php`:
  - Include `'placement' => $this->placement ?? 'home'` in `toArray()`.
- In `app/Http/Controllers/ContentController.php`:
  - In `banners(Request $request)`:
  ```php
  $query = Banner::query()->active()->with(['images' => fn ($q) => $q->orderBy('id')])->ordered();
  if ($request->filled('placement')) {
      $query->forPlacement($request->query('placement'));
  }
  ```
- In `resources/views/admin/banners/index.blade.php`:
  - Add `<div class="mb-3"><label class="form-label" for="bannerPlacement">Vị trí hiển thị</label><select id="bannerPlacement" class="form-select bg-dark text-white border-secondary"><option value="home">Trang chủ</option><option value="theater">Trang rạp</option><option value="all">Tất cả các trang</option></select></div>`.
- In `public/js/admin/pages/banners.js`:
  - Read `bannerPlacement.value` into `formData` on save; populate `bannerPlacement.value` on edit.
  - (No comments in code).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AdminBannerPlacementApiTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/BannerController.php app/Http/Resources/BannerResource.php app/Http/Controllers/ContentController.php resources/views/admin/banners/index.blade.php public/js/admin/pages/banners.js tests/Feature/AdminBannerPlacementApiTest.php
git commit -m "feat(banners): add placement selection to admin and filter in public api"
```

---

### Task 3: Routes & Navigation Consolidation

**Files:**
- Modify: `routes/web.php:43-45`
- Modify: `resources/views/partials/header.blade.php:18-24`
- Modify: `resources/views/partials/footer.blade.php:12-14`
- Test: `tests/Feature/UnifiedTheatersNavigationTest.php`

**Interfaces:**
- Consumes: Laravel Route facade, Blade layouts
- Produces: 301 redirect `/prices` -> `/theaters`, single "Rạp" navigation item

- [ ] **Step 1: Write the failing test in `tests/Feature/UnifiedTheatersNavigationTest.php`**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnifiedTheatersNavigationTest extends TestCase
{
    public function test_prices_route_permanently_redirects_to_theaters(): void
    {
        $response = $this->get('/prices');
        $response->assertStatus(301);
        $response->assertRedirect('/theaters');
    }

    public function test_theaters_route_is_accessible(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
    }

    public function test_header_contains_only_one_theater_link_without_prices_link(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
        $response->assertSee('Rạp');
        $response->assertDontSee('>Giá vé<', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/UnifiedTheatersNavigationTest.php`  
Expected: FAIL (`/prices` currently returns 200, and header has `Giá vé`).

- [ ] **Step 3: Update `routes/web.php` and partials**

- In `routes/web.php`:
  Replace:
  ```php
  Route::view('/theaters', 'users.theaters.index')->name('theaters.index');
  Route::get('/prices', [PricePageController::class, 'index'])->name('prices.index');
  ```
  With:
  ```php
  Route::view('/theaters', 'users.theaters.index')->name('theaters.index');
  Route::redirect('/prices', '/theaters', 301)->name('prices.index');
  ```
- In `resources/views/partials/header.blade.php`:
  Remove `<li class="nav-item"><a class="nav-link {{ request()->is('prices*') ? 'active' : '' }}" href="{{ route('prices.index') }}">Giá vé</a></li>`.
- In `resources/views/partials/footer.blade.php`:
  Replace separate links with unified `<li><a href="{{ route('theaters.index') }}">Hệ thống rạp & Bảng giá vé</a></li>`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/UnifiedTheatersNavigationTest.php`  
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add routes/web.php resources/views/partials/header.blade.php resources/views/partials/footer.blade.php tests/Feature/UnifiedTheatersNavigationTest.php
git commit -m "feat(navigation): consolidate theaters and prices into unified theaters route"
```

---

### Task 4: Public Unified Theaters View Template & CSS

**Files:**
- Modify: `resources/views/users/theaters/index.blade.php`
- Modify: `public/css/users/pages/theaters.css`
- Test: `tests/Feature/TheatersPageRenderTest.php`

**Interfaces:**
- Consumes: Blade directives, assets
- Produces: Unified page markup with banner slider, theater info bar, showtimes section, and two-column price/details section

- [ ] **Step 1: Write test for page structure in `tests/Feature/TheatersPageRenderTest.php`**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class TheatersPageRenderTest extends TestCase
{
    public function test_theaters_page_renders_all_required_sections(): void
    {
        $response = $this->get('/theaters');
        $response->assertOk();
        $response->assertSee('theaters-hero-slider', false);
        $response->assertSee('theaters-info-bar', false);
        $response->assertSee('theaters-showtimes-section', false);
        $response->assertSee('theaters-split-section', false);
        $response->assertSee('theaterPricingContainer', false);
        $response->assertSee('theaterDetailsContainer', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/TheatersPageRenderTest.php`  
Expected: FAIL.

- [ ] **Step 3: Rewrite `resources/views/users/theaters/index.blade.php` and `theaters.css`**

- Update `resources/views/users/theaters/index.blade.php`:
  - Include `.theaters-hero-slider` with `<div id="theatersHeroBackdrop">`, arrows `#theatersHeroPrev`, `#theatersHeroNext`, and dots container `#theatersHeroDots`.
  - Include `.theaters-info-bar` with `#currentTheaterName`, `#currentTheaterAddress`, `#currentTheaterPhone`, and dropdowns `#citySelect`, `#theaterSelect`.
  - Include `.theaters-showtimes-section` with date picker `#showtimeDateTabs` and grid `#theaterMoviesGrid`.
  - Include `.theaters-split-section` containing:
    - `#theaterPricingContainer` (title, tables container, note rules).
    - `#theaterDetailsContainer` (address, hotline, email, amenities tags, description).
  - Clean, no code comments.
- Update `public/css/users/pages/theaters.css`:
  - Dark theme `#111113`, cards `#1c1c1f`, accents `#ff1722`.
  - Slider container height 380px with smooth fade transitions.
  - Table styling for pricing using high-contrast borders and clean typography.
  - Clean responsive breakpoints: 2 columns on desktop (`>=992px`), 1 column on tablet/mobile (`<992px`).
  - No code comments.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/TheatersPageRenderTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/users/theaters/index.blade.php public/css/users/pages/theaters.css tests/Feature/TheatersPageRenderTest.php
git commit -m "feat(theaters): implement unified theaters blade template and responsive styles"
```

---

### Task 5: Frontend Modular JavaScript (`theaters.js`) & Final Verification

**Files:**
- Modify: `public/js/users/pages/theaters.js`
- Test: Syntax and security gates (`npm run test:frontend:syntax`, `npm run test:frontend:security`)
- Test: Playwright browser verification

**Interfaces:**
- Consumes: `/api/v1/banners?placement=theater`, `/api/v1/theaters`, `/api/v1/theaters/cities`, `/api/v1/prices`, `/api/v1/showtimes`
- Produces: 5-second auto-slider, interactive theater & city switching, dynamic price table rendering, date showtime selection

- [ ] **Step 1: Write modular `public/js/users/pages/theaters.js`**

Implement modular logic (strictly without code comments):
1. `initHeroSlider()`:
   - Fetches `/api/v1/banners?placement=theater`.
   - Handles 5000ms timer (`window.setInterval`), pauses on hover/focus, resumes on mouseleave.
   - Prev / Next button click handlers.
   - Dots indicator click handlers.
2. `initTheaterSelection()`:
   - Loads cities from `/api/v1/theaters/cities` and theaters from `/api/v1/theaters`.
   - Reads URL parameter `?theater={id}` if present; defaults to first active theater.
   - Populates `#citySelect` and `#theaterSelect`.
   - On change, updates URL state without page reload (`history.replaceState`), updates theater header info, triggers price table reload and showtimes reload.
3. `loadTheaterPricing(theaterId)`:
   - Fetches `/api/v1/prices`.
   - Renders format tables (2D, 3D, Standard, VIP/Couple surcharges) for the selected theater.
4. `loadTheaterShowtimes(theaterId, date)`:
   - Renders date tabs (today + 6 days).
   - Fetches and displays movies and time slots with booking links `/booking/{encryptedShowtimeId}`.
5. Strict XSS protection via `escapeHtml()` on all dynamic strings.

- [ ] **Step 2: Run frontend syntax and security gates**

Run: `npm run test:frontend:syntax`  
Expected: `Frontend syntax passed for 75 files.`  
Run: `npm run test:frontend:security`  
Expected: `Frontend security gate passed.`

- [ ] **Step 3: Run Vite asset build**

Run: `npm run build`  
Expected: `✓ built in ...` with zero errors.

- [ ] **Step 4: Verify live in browser using Playwright**

Run script to load `http://127.0.0.1:8000/theaters` with Edge headless, check that 5s slider, selectors, pricing tables, and details render without console errors. Take screenshot and inspect output.

- [ ] **Step 5: Commit**

```bash
git add public/js/users/pages/theaters.js public/build/*
git commit -m "feat(theaters): implement interactive 5s slider and synchronized theater pricing"
```

---

## Plan Review Checklist

- [x] Every task has exact file paths, tests, commands, and expected results.
- [x] No code comments in any deliverable code.
- [x] Follows project coding rules and `border-radius: 0.5rem`.
- [x] 301 Redirect for `/prices` included to prevent broken links.
- [x] Covers all 4 sections from user mockup and reference image.
