# PROJECT STRICT REVIEW REPORT

Ngày review: 2026-08-03
Phạm vi: toàn bộ Laravel backend, Blade views, public/resources frontend, database migrations/seeders, tests, scripts, CI, security gates và build pipeline.

## 1. Executive summary

Dự án đã vượt mức prototype: có Laravel 12, JWT cookie auth, RBAC, audit logs, PayOS payment, idempotency, seat locking, health/metrics, Sentry, test suite lớn và CI quality gate. Các lỗi nghiêm trọng trong luồng đặt vé/thanh toán đã có regression tests.

Kết luận khắt khe: hệ thống đang ở mức **pre-production tốt**, nhưng chưa đạt chuẩn production hiện đại kiểu CGV/Galaxy nếu xét về frontend architecture, UI consistency, asset strategy, static analysis coverage và repository hygiene.

## 2. Điểm tổng quan

| Nhóm | Điểm | Nhận xét |
|---|---:|---|
| Backend correctness | 8.5/10 | Service layer rõ, test nhiều, payment/seat/order có idempotency và constraint. |
| Security | 8/10 | Có CSP, headers, auth throttling, audit, dependency audit sạch. Cần mở rộng least-privilege enforcement theo permission. |
| Data integrity | 8.5/10 | Có FK/index/idempotency/expiration tests. Cần operational runbook cho scheduler/queue. |
| Frontend UX/UI | 6.5/10 | Đã có shared components nhưng vẫn còn nhiều JS/CSS lớn, skeleton/toast/modal chưa hoàn toàn thống nhất. |
| Performance | 6.5/10 | Có pagination ở nhiều API, build pass. Cần code-splitting, lazy scanner, cache strategy và giảm DOM work. |
| Maintainability | 6.5/10 | File lớn, mixed asset pipeline, nhiều generated/cache files đang dirty. |
| CI/Testing | 8/10 | Test suite mạnh, lint/build pass. PHPStan scope còn quá hẹp. |

## 3. Quality gates đã chạy

- `composer test:structure`: Pass.
- `composer analyse`: Pass, nhưng chỉ phân tích 6 path trong `phpstan.neon`.
- `npm run lint`: Pass.
- `npm run test:frontend:syntax`: Pass, 64 files.
- `npm run test:frontend:security`: Pass.
- `npm run build`: Pass.
- `php artisan test`: Pass, 235 tests / 1949 assertions.
- `composer audit`: Pass, 0 vulnerabilities.
- `npm audit --audit-level=moderate`: Pass, 0 vulnerabilities.

## 4. Phát hiện theo mức độ ưu tiên

### Critical

Không phát hiện blocker Critical qua static/test/audit hiện tại. Không có secret được track trực tiếp: `.env` không nằm trong `git ls-files`; `.env.example` dùng `APP_DEBUG=false` và secret rỗng.

### High

1. **Static analysis chưa phủ toàn bộ backend**
   - Nguyên nhân: `phpstan.neon` chỉ scan một số command/middleware/service/API nhỏ, chưa scan toàn bộ `app`.
   - Ảnh hưởng: `composer analyse` xanh nhưng không đảm bảo controller/service lớn như order/payment/booking không có type issue.
   - Khắc phục: mở rộng PHPStan theo phase: đầu tiên `app/Models`, `app/Services`, `app/Http/Resources`; sau đó `app/Http/Controllers`; nâng dần baseline thay vì bật cả repo một lần.

2. **RBAC route-level còn coarse-grained**
   - Nguyên nhân: admin API đang dùng `auth:api` + `role:admin,super-admin` ở group lớn; quyền chi tiết chủ yếu dựa vào policy/controller.
   - Ảnh hưởng: nếu một endpoint quên gọi policy thì admin role có thể vượt quyền nghiệp vụ.
   - Khắc phục: gắn middleware `permission:*` cho từng route nhạy cảm, giữ policy làm lớp thứ hai; thêm route-permission integrity test.

3. **Frontend god files gây rủi ro hiệu năng và regression**
   - Nguyên nhân: file lớn: `public/js/users/pages/booking.js` 2364 dòng, `public/css/users/pages/booking.css` 4440 dòng, `public/js/admin/pages/showtimes.js` 1040 dòng.
   - Ảnh hưởng: khó review, khó test đơn vị, dễ flicker/NaN/loading race, thao tác tab/admin dễ khựng.
   - Khắc phục: tách theo feature module: state, API client, renderers, price calculator, seat hold, payment, result screen; áp dụng event bus nhỏ và render diff thay vì repaint toàn section.

4. **Repository hygiene đang rất bẩn**
   - Nguyên nhân: `.npm-cache`, `storage/framework/views`, `public/build` và nhiều file generated/cache xuất hiện trong `git status`.
   - Ảnh hưởng: commit dễ lẫn artifact, review khó, CI/local lệch nhau, nguy cơ merge conflict vô nghĩa.
   - Khắc phục: đưa `.npm-cache/`, `storage/framework/views/`, `public/build/` vào `.gitignore` nếu chưa track; xóa khỏi index nếu đã lỡ track; trước merge phải tách commit chức năng và commit generated nếu bắt buộc.

5. **Mixed asset strategy**
   - Nguyên nhân: vừa dùng Vite input `resources/*`, vừa load nhiều asset từ `public/css` và `public/js` bằng `asset()`/query version.
   - Ảnh hưởng: cache busting không thống nhất, bundle khó tối ưu, dependency/load order dễ lỗi kiểu `window.onAdminPageLoad`.
   - Khắc phục: chuẩn hóa: app shell qua Vite, page module dynamic import; public legacy chỉ là bridge tạm thời và có kế hoạch loại bỏ.

### Medium

6. **Encoding/mojibake còn tồn tại**
   - Nguyên nhân: một số file có chuỗi bị encode sai hoặc ký tự Vietnamese bị đọc/ghi không nhất quán.
   - Ảnh hưởng: UI có chữ lỗi, giảm độ chuyên nghiệp, có thể ảnh hưởng aria/title/toast.
   - Khắc phục: ép UTF-8 toàn repo, thêm script gate phát hiện `Ã|Ä|áº|á»|Æ`; sửa routes/views bị lỗi.

7. **Debug logs còn trong production frontend**
   - Nguyên nhân: nhiều `console.log` nằm trong `public/js/users/pages/booking.js`, `movie-detail.js`, `admin/pages/showtimes.js`.
   - Ảnh hưởng: noise console, lộ trạng thái nội bộ, ảnh hưởng performance nhỏ ở page nóng.
   - Khắc phục: thay bằng logger có `isDebug` theo env hoặc xóa hẳn log debug.

8. **Toast/modal/skeleton chưa hoàn toàn một design system**
   - Nguyên nhân: đã có shared components nhưng vẫn còn page-specific toast/skeleton CSS, đặc biệt booking/posts/admin pages.
   - Ảnh hưởng: trải nghiệm thiếu đồng nhất, trạng thái loading khác nhau, dễ layout shift.
   - Khắc phục: một contract chung: `DataRegion`, `ToastService`, `ModalShell`, `Pagination`, `TableState`; page chỉ truyền data/config.

9. **Admin performance cần lazy loading sâu hơn**
   - Nguyên nhân: admin bundle có chunk lớn như `admin-navigation` ~98KB gzip raw 98KB và scanner bootstrap ~130KB trước gzip.
   - Ảnh hưởng: chuyển tab/admin có thể khựng do parse/evaluate JS và DOM replace lớn.
   - Khắc phục: dynamic import theo route, lazy ticket scanner khi mở modal, cache HTML shell, skeleton chỉ vùng data child, giữ chrome/sidebar ổn định.

10. **Browser/UX/accessibility test chưa đủ khắt khe**
    - Nguyên nhân: có smoke/responsive scripts nhưng chưa thấy gate WCAG/keyboard/CLS/tab smooth bắt buộc cho mọi critical flow.
    - Ảnh hưởng: UI có thể pass build nhưng vẫn lệch, modal trap lỗi, focus state thiếu, mobile table khó dùng.
    - Khắc phục: thêm Playwright specs cho login, booking, payment result, admin CRUD, roles-permissions; tích hợp axe-core, screenshot diff, console error fail-fast.

### Low

11. **Comment/text trong routes/views còn lẫn ngôn ngữ và style**
    - Nguyên nhân: comment cũ và label tiếng Anh/Việt trộn lẫn.
    - Ảnh hưởng: giảm tính thống nhất khi bàn giao team.
    - Khắc phục: chuẩn hóa glossary tiếng Việt cho UI, tiếng Anh cho code identifiers.

12. **Một số Request/Controller vẫn trùng pattern validate-response**
    - Nguyên nhân: controller trả JSON thủ công nhiều nơi.
    - Ảnh hưởng: dễ lệch response envelope/pagination meta.
    - Khắc phục: dùng API response helper/resource collection chung.

## 5. Điểm mạnh đáng giữ

- Payment/booking có idempotency, order expiration, seat hold locking và regression tests.
- Security headers, CSP, throttling auth/payment/webhook có test.
- Audit log có redaction, immutable behavior và tests.
- RBAC catalog/seeders có tests, admin nhận đầy đủ permission.
- Public API có versioning `/api/v1`, health/ready/metrics và OpenAPI contract test.
- Dependency audit sạch ở thời điểm review.
- CI workflow chạy composer, npm, PHPStan, lint, frontend gates, build, tests, browser smoke và audit.

## 6. Lộ trình khắc phục đề xuất

### Phase A - Hardening bắt buộc trước production

1. Mở rộng PHPStan coverage toàn `app` theo baseline có kiểm soát.
2. Áp `permission:*` middleware cho admin routes trọng yếu.
3. Dọn repository hygiene và `.gitignore` generated/cache artifacts.
4. Xóa/gate toàn bộ production `console.log`.
5. Sửa mojibake còn sót và thêm encoding gate.

### Phase B - Frontend production UX

1. Refactor booking thành module nhỏ: seat state, hold API, product cart, pricing, payment, result renderer.
2. Chuẩn hóa toast/modal/skeleton/pagination/table thành design-system primitives.
3. Admin route dynamic import, lazy ticket scanner, tránh replace toàn `adminPageContent` bằng khung cũ.
4. Thêm Playwright e2e cho booking/payment/admin CRUD và screenshot diff.
5. Thêm accessibility checks: focus trap modal, keyboard nav, color contrast, touch target.

### Phase C - Scale/operations

1. Runbook production cho `queue:work`, `schedule:run`, Horizon/Redis nếu dùng queue nhiều.
2. DB query profiling cho dashboard/admin list; thêm cache TTL/tag theo module.
3. CDN/image optimization: WebP/AVIF, width/height, lazy loading, preload LCP image.
4. Monitoring dashboard: request latency, payment webhook failure, queue backlog, order pending over SLA.

## 7. Kết luận

Nếu chỉ xét bằng test/build/security gate, dự án đang ổn và không có blocker Critical. Nếu xét chuẩn production hiện đại, phần backend đã khá chắc, nhưng frontend/admin shell và quy trình repository cần refactor thêm. Ưu tiên tiếp theo nên là: mở rộng PHPStan, route permission middleware, dọn generated files, xóa console logs, rồi tách booking/admin JS lớn.
