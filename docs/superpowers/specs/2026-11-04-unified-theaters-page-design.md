# Design Document: Unified "Rạp" (Cinemas & Ticket Prices) Page

**Date:** 2026-11-04  
**Author:** Antigravity  
**Status:** In Review  
**Topic:** Consolidate Cinema and Ticket Price Pages with Admin-Managed 5s Auto-Slider  

---

## 1. Executive Summary

Currently, the user-facing application maintains two separate pages:
- `/theaters` (`theaters.index`): Lists theaters grouped by branch/city with card layouts and search filters.
- `/prices` (`prices.index`): Displays pricing matrix tables for each theater and movie format (2D, 3D, weekday, weekend, holiday, student, child).

This design consolidates both pages into a single, comprehensive **"Rạp"** page located at `/theaters` (inspired by leading cinema platforms like Galaxy Cinema). The new page delivers a unified experience:
1. **Top Banner Slider**: A 5-second auto-transitioning gallery showcasing cinema halls/amenities, with prev/next buttons and pagination dots. Banners are customizable in Admin via `/admin/banners`.
2. **Theater Information & Selector Bar**: Displays selected theater name, address, and hotline, alongside City/Branch and Theater dropdowns for seamless switching.
3. **Showtimes & Screening Movies**: Date tabs allowing customers to view movies and available showtimes at the selected cinema.
4. **Two-Column Details & Pricing Section**:
   - **Left Column (Giá vé)**: Dynamic ticket price table automatically reflecting the selected theater's pricing profile.
   - **Right Column (Thông tin chi tiết)**: Detailed theater information, contact hotline, amenities, and facility overview.
5. **Clean Navigation**: The header navbar and footer navigation are streamlined to a single "Rạp" entry, with `/prices` issuing a `301 Permanent Redirect` to `/theaters`.

---

## 2. Architecture & Data Flow

```
+-----------------------------------------------------------------------------------------+
|                                    User Navigation                                      |
|                                                                                         |
|   Header Nav: [ Phim ]  [ Rạp ] (Active)  [ Tin tức & Ưu đãi ]                          |
|   Legacy Link: /prices -------> (301 Redirect) -------> /theaters                       |
+-----------------------------------------------------------------------------------------+
                                             |
                                             v
+-----------------------------------------------------------------------------------------+
|                            Unified Theaters Page (/theaters)                            |
|                                                                                         |
|  [ 1. Top Banner Carousel ] (Auto-switch 5s; GET /api/v1/banners?placement=theater)     |
|                                                                                         |
|  [ 2. Theater Info & Selector Bar ] (GET /api/v1/theaters, GET /api/v1/theaters/cities) |
|       - Tên rạp, Địa chỉ, Hotline                                                       |
|       - Dropdown: [ Tỉnh/Thành phố ] [ Chọn rạp khác ]                                  |
|                                                                                         |
|  [ 3. Movies & Showtimes at this Cinema ] (GET /api/v1/showtimes?theater_id=&date=)     |
|       - Tabs: Hôm nay, Ngày mai, Ngày kế tiếp...                                        |
|       - Movie cards with time slots linking to booking flow                             |
|                                                                                         |
|  [ 4. Split Section (2 Columns) ]                                                       |
|       +-----------------------------------+------------------------------------------+  |
|       | Left: BẢNG GIÁ VÉ                 | Right: THÔNG TIN CHI TIẾT                |  |
|       | (GET /api/v1/prices)              | - Địa chỉ, Hotline, Email                |  |
|       | - 2D, 3D, Happy Day, Cuối tuần    | - Giới thiệu cơ sở vật chất, công nghệ   |  |
|       | - Phụ thu ghế VIP / Couple        | - Tags: Dolby Atmos, Laser, v.v.         |  |
|       +-----------------------------------+------------------------------------------+  |
+-----------------------------------------------------------------------------------------+
```

---

## 3. Database & Admin Configuration

### 3.1 Migration: Banner Placement
To allow managing banners specifically for the "Rạp" page through `/admin/banners`:
- Add column `placement` to `banners` table:
  - Type: `string(50)`
  - Default: `'home'`
  - Allowed values: `'home'` (Trang chủ), `'theater'` (Trang rạp), `'all'` (Tất cả).
  - Index: `['is_active', 'placement', 'created_at']`.

### 3.2 Admin Banners Management (`/admin/banners`)
- **Banner Modal Form**:
  - Add select input `Vị trí hiển thị` (`placement`):
    - `Trang chủ` (`home`)
    - `Trang rạp` (`theater`)
    - `Tất cả các trang` (`all`)
- **Admin Controller & Validation**:
  - Update `BannerController` `store()` and `update()` to validate `placement` (`Rule::in(['home', 'theater', 'all'])`).
  - Sanitize and persist `placement`.
- **Public API Filter**:
  - In `ContentController::banners(Request $request)`:
    - Accept `placement` query parameter (e.g. `?placement=theater`).
    - Filter banners matching the requested placement or `'all'`.
    - Return `placement` in `BannerResource`.

---

## 4. Frontend Implementation

### 4.1 Blade View: `resources/views/users/theaters/index.blade.php`
The blade template is restructured into modular components:
1. **Hero Banner Section (`.theaters-hero-slider`)**:
   - Large aspect-ratio gallery container.
   - Prev/Next chevron buttons.
   - Dot pagination indicators.
   - Background gradient overlay for contrast.
2. **Theater Bar (`.theater-info-bar`)**:
   - Current theater details block.
   - Dropdown selectors: City/Branch (`#branchSelect`) and Theater (`#theaterSelect`).
3. **Showtimes Section (`.theater-showtimes-section`)**:
   - Horizontal date scroll / tabs (`#showtimeDateTabs`).
   - Movie grid with poster, title, age rating, and showtime pill buttons leading to `/booking/{id}`.
   - Loading skeleton and empty states.
4. **Two-Column Section (`.theater-details-pricing-section`)**:
   - Left Column: `#theaterPricingContainer` rendering format-based pricing tables and rules.
   - Right Column: `#theaterDetailsContainer` rendering address, hotline, facility badges, and theater description.

### 4.2 Stylesheet: `public/css/users/pages/theaters.css`
- Dark aesthetic matching `--cinema-danger` (`#ff1722`), background `#111113`, cards `#1c1c1f`.
- Responsive layout: 2-column on desktop (`lg`), cleanly stacking into a single column on tablet/mobile (`<992px`).
- Smooth CSS fade transitions for the 5-second banner slider.
- High-contrast, legible typography conforming to the cinema design rules (`border-radius: 0.5rem`).

### 4.3 JavaScript: `public/js/users/pages/theaters.js`
- Modular, clean, modern vanilla JS (ES6+), without code comments:
  - `BannerSlider`: handles 5000ms timer, manual navigation, touch swipe, pause-on-hover.
  - `TheaterManager`: fetches branches and theaters, manages URL query state (`?theater={id}`), synchronizes dropdowns.
  - `PricingRenderer`: renders ticket price tables for the currently selected theater using `/api/v1/prices`.
  - `ShowtimesRenderer`: loads and renders movies and showtimes for the selected date and theater.
  - Full XSS protection using `escapeHtml()`.

---

## 5. Routing & Backward Compatibility

1. **Route Updates (`routes/web.php`)**:
   - `Route::view('/theaters', 'users.theaters.index')->name('theaters.index');` (Main entry).
   - `Route::redirect('/prices', '/theaters', 301)->name('prices.index');` (Permanent redirect for SEO & bookmarks).
2. **Navigation Updates**:
   - `resources/views/partials/header.blade.php`: Remove separate "Giá vé" link; only keep "Rạp" with active state matching `request()->is('theaters*')`.
   - `resources/views/partials/footer.blade.php`: Replace separate links with unified "Hệ thống rạp & Bảng giá vé".
3. **Asset Build**:
   - Run `npm run build` to update Vite assets.
   - Run frontend syntax and security gates to ensure zero regressions.

---

## 6. Verification & Test Plan

1. **Banner 5s Slider**:
   - Verify banners auto-advance every 5000ms.
   - Verify clicking prev/next or dots changes slide and resets 5s timer.
   - Verify admin can create a banner with placement `theater` and it immediately appears on the page.
2. **Theater & Price Synchronization**:
   - Verify changing the theater dropdown updates:
     - Header info (name, address, hotline).
     - Pricing tables (matching that theater's base price and format surcharges).
     - Showtimes and movies for that theater.
     - Facility description.
3. **Redirect & Navigation**:
   - Access `http://127.0.0.1:8000/prices` -> verify automatic 301 redirect to `/theaters`.
   - Verify header only displays "Phim", "Rạp", "Tin tức & Ưu đãi".
4. **Automated Quality Gates**:
   - `npm run test:frontend:syntax`: Pass.
   - `npm run test:frontend:security`: Pass.
   - Browser rendering verified with Playwright in headless Edge.
