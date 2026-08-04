# Project Production Readiness Audit

Ngày audit: 02/08/2026  
Phạm vi: Laravel backend, REST API, Blade/Vite frontend, security, accessibility, responsive, performance, CI và browser smoke.

## Executive Summary

**Kết luận: Conditionally Ready — chưa nên phát hành production trước khi hoàn thành các P1 còn mở.**

- Backend có nền tảng tốt: policy, rate limit, transaction, locking, idempotency, audit log và test concurrency/payment tương đối đầy đủ.
- Sau remediation, toàn bộ 223 test với 1.884 assertions đạt; PHPStan, ESLint, build, structure/security gates và public browser smoke đều đạt.
- Các blocker còn lại tập trung ở CSP, browser E2E của admin/booking/payment, accessibility touch target và migration component admin.

## Issues Đã Khắc Phục

### AUD-SEC-001 — High — Raw order gateway payload bị lộ

- **Bằng chứng:** `app/Http/Resources/OrderResource.php` từng trả trực tiếp `payload`; security test thất bại vì có thể chứa `access_token` và `card_number`.
- **Nguyên nhân:** Dùng payload nội bộ làm API contract cho màn hình chi tiết.
- **Ảnh hưởng:** Rò rỉ dữ liệu gateway và metadata thanh toán cho client/admin.
- **Khắc phục:** Thay bằng `invoice` allowlist; áp dụng cả `OrderSummaryResource`; frontend admin đọc snapshot an toàn.
- **Test:** `ApiResourceSecurityTest`, `AdminOrderControllerTest`.
- **Trạng thái:** **Verified**.

### AUD-SEC-002 — High — Seeder tạo admin không có role và mật khẩu mặc định

- **Bằng chứng:** `database/seeders/UserSeeder.php` tạo `admin@example.com/password` nhưng không gán `role_id`.
- **Nguyên nhân:** `RoleSeeder` và `UserSeeder` không có contract gán role; không chặn production.
- **Ảnh hưởng:** Fresh install không đăng nhập được admin; chạy seeder nhầm trên production tạo credential đoán được.
- **Khắc phục:** Gán role tường minh, kiểm tra thứ tự seeder và từ chối chạy `UserSeeder` ở production.
- **Test:** `UserSeederSecurityTest`.
- **Trạng thái:** **Verified**.

### AUD-CI-001 — High — CI tham chiếu quality scripts đã bị xóa

- **Bằng chứng:** `package.json`, `composer.json`, README và workflow tham chiếu `scripts/*`; commit `2100de1` đã xóa toàn bộ thư mục.
- **Ảnh hưởng:** CI thất bại trước khi kiểm tra code; trạng thái release không đáng tin cậy.
- **Khắc phục:** Khôi phục route integrity, repository hygiene, frontend syntax/security và browser smoke gates; workflow cài Chromium.
- **Trạng thái:** **Verified**.

### AUD-DEP-001 — High — Dependency advisories

- **Bằng chứng:** npm có 4 high advisories; Composer có 3 medium advisories trong Guzzle `<7.15.1`.
- **Khắc phục:** `npm audit fix`; nâng Guzzle lên 7.15.2 và PSR-7 lên 2.13.0.
- **Trạng thái:** **Verified — npm/composer audit sạch**.

### AUD-RESP-001 — Medium — Bảng giá tràn ngang mobile

- **Bằng chứng:** `/prices` ở 375px có document width 464px; `.galaxy-table` vượt viewport.
- **Khắc phục:** Thêm `.pricing-table-scroll`, giữ bảng tối thiểu 680px nhưng scroll trong container.
- **Trạng thái:** **Verified — document width còn 375px**.

### AUD-RESP-002 — Medium — Trang posts tràn ngang tablet

- **Bằng chứng:** `/posts` ở 768px có document width 780px do Bootstrap `.row.g-5`.
- **Khắc phục:** Giảm gutter của row ở breakpoint `<=991.98px`.
- **Trạng thái:** **Verified — document width còn 768px**.

### AUD-A11Y-001 — Medium — Filter thiếu accessible label

- **Bằng chứng:** Browser audit phát hiện input tìm rạp ở home và hai filter theaters không có accessible name.
- **Khắc phục:** Thêm `aria-label`, `for/id` và `type="button"`.
- **Trạng thái:** **Verified — 0 unlabeled controls trên hai route**.

### AUD-FE-001 — Medium — Inline event handlers

- **Bằng chứng:** `onclick`/`onerror` xuất hiện trong home, posts, media thumb, products, combos và orders.
- **Ảnh hưởng:** Cản trở CSP nghiêm ngặt, tăng XSS surface và phân tán behavior.
- **Khắc phục:** Event listener/delegation chung và `data-admin-image-fallback`.
- **Trạng thái:** **Verified — inline handler count bằng 0**.

### AUD-TEST-001 — Medium — Homepage smoke test không khởi tạo database

- **Bằng chứng:** Full suite lỗi `no such table: posts` tại `tests/Feature/ExampleTest.php`.
- **Khắc phục:** Dùng `RefreshDatabase`.
- **Trạng thái:** **Verified**.

### AUD-DEV-001 — Medium — Reverb bật nhưng development command không chạy server

- **Bằng chứng:** Browser báo WebSocket `localhost:8080` refused trong khi `.env` bật Reverb.
- **Khắc phục:** `composer dev` khởi động thêm `php artisan reverb:start`.
- **Trạng thái:** **Fixed; cần kiểm chứng khi chạy full development stack**.

## Issues Còn Mở

### AUD-SEC-003 — High — CSP vẫn cho phép `unsafe-inline`

- **File:** `app/Http/Middleware/SecurityHeaders.php`.
- **Nguyên nhân:** Layout vẫn có inline bootstrap/config script và phụ thuộc CDN.
- **Ảnh hưởng:** CSP giảm đáng kể hiệu quả chống XSS.
- **Khuyến nghị:** Chuyển config sang JSON script/application endpoint, bundle Echo/Bootstrap qua Vite và dùng nonce/hash; bỏ `unsafe-inline` khỏi `script-src` trước.
- **Trạng thái:** **Open**.

### AUD-E2E-001 — High — Chưa có browser E2E đầy đủ cho booking/payment/admin

- **Bằng chứng:** Public smoke đạt; admin browser redirect vì database hiện tại chứa admin seed cũ chưa có role. Payment gateway cần sandbox fixture có kiểm soát.
- **Ảnh hưởng:** Chưa chứng minh được hành vi UI end-to-end cho giữ ghế, redirect, callback, webhook và CRUD admin.
- **Khuyến nghị:** Tạo database E2E riêng, seed role mới, mock PayOS/Reverb và chạy Chrome matrix cho các luồng bắt buộc.
- **Trạng thái:** **Blocked by controlled E2E environment**.

### AUD-A11Y-002 — Medium — Touch targets dưới 44×44px

- **Bằng chứng:** Browser audit ghi nhận 11–36 target nhỏ tùy route/viewport.
- **Ảnh hưởng:** Khó thao tác trên mobile và chưa đạt WCAG 2.2 target-size best practice.
- **Khuyến nghị:** Chuẩn hóa icon button/pill/link bằng token `--touch-target: 44px`; audit ngoại lệ inline-link riêng.
- **Trạng thái:** **Open**.

### AUD-A11Y-003 — Medium — Button thiếu `type`

- **Bằng chứng:** Static scan còn 46 button không khai báo `type`.
- **Ảnh hưởng:** Có nguy cơ submit form ngoài ý muốn khi markup thay đổi; contract component không rõ.
- **Khuyến nghị:** Mặc định `type="button"`; chỉ submit button dùng `type="submit"`.
- **Trạng thái:** **Open**.

### AUD-UI-001 — Medium — Component admin chưa migrate hoàn toàn

- **Bằng chứng:** Còn 17 modal viết tay, 72 runtime `.style.*`; chi tiết nằm trong `UI_COMPONENT_REFACTOR_AUDIT.md`.
- **Ảnh hưởng:** Behavior modal/loading chưa đồng nhất, khó bảo trì và tăng nguy cơ SPA lifecycle leak.
- **Trạng thái:** **Open**.

### AUD-PERF-001 — Medium — Payload trang đầu còn lớn

- **Bằng chứng:** Browser audit cold-load ghi nhận home/posts có thể vượt khoảng 1,6–1,8MB tùy cache và ảnh.
- **Khuyến nghị:** AVIF/WebP responsive image, `srcset/sizes`, preload duy nhất LCP image, immutable cache và giới hạn hero/post thumbnail dimensions.
- **Trạng thái:** **Open — cần Lighthouse/WebPageTest trong môi trường production-like**.

### AUD-QA-001 — Low — Full Pint gate chưa sạch

- **Bằng chứng:** `vendor/bin/pint --test` báo style drift trên nhiều file legacy.
- **Ảnh hưởng:** Noise trong review và khó bật formatting gate toàn repository.
- **Khuyến nghị:** Format theo module, không tạo một diff toàn dự án; giữ `test:modern-format` cho file production-critical trong thời gian chuyển tiếp.
- **Trạng thái:** **Open**.

## Browser Matrix

- Public routes `/`, `/movies`, `/posts`, `/theaters`, `/prices`: HTTP 200, không page error/console error nghiệp vụ.
- Viewports đã đo: 375×812, 768×1024 và 1440×1000.
- Không còn horizontal document overflow sau remediation.
- Images không có `alt`: 0 trên các route đã đo.
- Admin/booking/payment browser E2E: chưa xác nhận, không được tính là passed.

## Quality Gates

- PHPUnit: **223 passed, 1.884 assertions**.
- PHPStan: **0 errors**.
- ESLint: **passed**.
- Vite production build: **passed**.
- Route/controller integrity: **passed**.
- Repository hygiene: **passed**.
- Frontend syntax/security gates: **passed**.
- Public browser smoke: **passed**.
- npm audit: **0 vulnerabilities**.
- Composer audit: **0 advisories**.
- Full Pint: **failed due legacy formatting drift**.

## Remediation Plan

1. **P1:** Siết CSP và loại inline scripts/config.
2. **P1:** Dựng controlled E2E environment cho admin, booking và PayOS.
3. **P1:** Backfill role cho dữ liệu admin cũ trước deploy.
4. **P2:** Chuẩn hóa touch target và button type.
5. **P2:** Migrate 17 modal và data-state/filter-bar còn lại.
6. **P2:** Tối ưu image pipeline và đo Core Web Vitals production-like.
7. **P3:** Format legacy theo module và bật full Pint gate.

## Definition of Done Production

- Không còn `unsafe-inline` trong `script-src`.
- Booking/payment/admin E2E đạt trên mobile/tablet/desktop với network slow/error cases.
- Admin hiện hữu có role hợp lệ sau data preflight/backfill.
- Không còn Critical/High issue mở.
- Touch targets và keyboard/focus đạt WCAG 2.2 AA trên luồng chính.
- Full CI chạy xanh từ clean checkout, gồm browser smoke và dependency audits.
