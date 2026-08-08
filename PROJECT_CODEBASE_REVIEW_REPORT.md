# Báo cáo Review Toàn Bộ Codebase

**Ngày audit:** 08/08/2026  
**Phạm vi:** Laravel API/SSR, RBAC, POS, booking, frontend JavaScript/CSS, CI, dependency, responsive public pages.  
**Phương pháp:** đọc code và cấu hình; kiểm tra route/migration; chạy quality gates; kiểm tra browser không ghi dữ liệu. Không thay đổi mã ứng dụng trong đợt audit này.

## Tóm tắt điều hành

Hệ thống là một modular monolith Laravel có nền tảng tốt: phân tách route theo domain, policy cho phần lớn tài nguyên, transaction/idempotency ở các luồng đặt vé-thanh toán, rate limit, audit log, 235 routes và migration đang đồng bộ. Toàn bộ suite backend hiện tại qua **249 tests / 2.040 assertions**.

Tuy nhiên, **chưa nên xem là production-ready** trước khi xử lý các mục High: một nhóm endpoint quản trị Format bỏ qua policy, dữ liệu quản trị có thể đi vào DOM HTML chưa escape, và dependency audit báo 4 advisory High của `league/commonmark` cùng 5 High trong toolchain Vite/PostCSS. Ngoài ra có nợ kỹ thuật UI/CSS, kiểm tra tĩnh chưa bao phủ toàn bộ codebase và một lỗi tràn ngang thực tế ở `/posts` trên tablet.

## Cập nhật triển khai remediation — 08/08/2026

Đợt remediation sau audit đã hoàn tất các lỗi có thể xử lý an toàn trong codebase hiện tại:

- **H-01:** thêm `FormatPolicy`, bắt buộc authorization cho Format CRUD, bổ sung relation `Format::screens()` và regression test cho admin/theater manager. `SoundPolicy` cũng được chuyển sang permission chính thức `sounds.manage` để tránh cùng lỗi phân quyền toàn cục.
- **H-02:** thay các sink đã xác nhận ở theater public và POS bằng DOM API/text node; không còn nội suy dữ liệu rạp vào `innerHTML`/`insertAdjacentHTML` tại các vị trí này.
- **H-03:** cập nhật `laravel/framework` lên 12.65.0 và `league/commonmark` lên 2.9.0. `composer audit` hiện không còn advisory.
- **M-01/M-02/M-03/M-06/M-08/L-01/L-02:** API đã nhận `nosniff` và referrer policy, POS không trả raw exception, log rạp không còn ghi PII payload, `/posts` hết tràn ngang ở 768px, permission menu dùng cache theo model request, nút mật khẩu có accessible name và README đã trỏ tới tài liệu hiện hữu.

Kết quả xác minh sau thay đổi: **254 tests / 2.055 assertions pass**, PHPStan scope hiện có pass, Pint scope hiện có pass, ESLint/build/frontend syntax-security gates pass, `composer audit` pass, `npm audit` pass, và browser xác nhận `/posts` không overflow tại 768×1024.

Hardening CSP đã chuyển toàn bộ inline script Blade sang nonce và bỏ `'unsafe-inline'` khỏi `script-src`; `style-src-attr 'unsafe-inline'` vẫn được giữ riêng để không làm hỏng style attribute do legacy UI/runtime tạo ra. Full PHPStan scan ở level 5 hiện phát hiện **580 lỗi type/relation có sẵn** trên toàn `app/`; gate toàn project không được bật hoặc suppress bằng baseline vì cần sửa theo từng module. Quy hoạch CSS ownership và tách controller/service lớn cũng là refactor có phạm vi rộng, chưa thể coi là hoàn tất chỉ bằng thay đổi cấu hình.

| Mức độ | Số lượng | Ý nghĩa |
|---|---:|---|
| Critical | 0 | Không phát hiện trong phạm vi đã kiểm chứng |
| High | 3 | Cần xử lý trước release production |
| Medium | 7 | Đưa vào sprint hardening kế tiếp |
| Low | 2 | Sửa cùng đợt chuẩn hóa UI/DX |

## Phạm vi và bằng chứng

### Đã kiểm tra

- 235 routes, gồm 191 API, 118 admin API và 11 POS API; toàn bộ migration đang ở trạng thái `Ran`.
- `composer test:structure`: pass; `composer analyse`: pass; `npm run lint`: pass; frontend syntax/security gates: pass; `npm run build`: pass.
- `php artisan test --compact`: **249 passed, 2.040 assertions, 45,28 giây**.
- `composer audit` và `npm audit --audit-level=moderate`.
- Browser tại 390×844, 768×1024 và 1440×900 cho `/`, `/movies`, `/posts`, `/theaters`, `/prices`.

### Giới hạn

- Không dùng tài khoản thật hoặc thực hiện giao dịch/ghi dữ liệu trong browser. Các route admin/POS được review bằng source, route metadata và test hiện có.
- `npm run test:browser:smoke` không hoàn thành trong môi trường local trong hơn ba phút và đã được dừng; CI có chạy script này, nhưng không có artifact/timeout diagnostic để tái lập lỗi local.
- Không thực hiện pentest, quét hạ tầng, email/payment gateway thật hoặc review secret lịch sử Git.

## Findings

### H-01 — Endpoint quản trị Format bỏ qua authorization chi tiết

**Mức độ: High**

- **Bằng chứng:** `routes/api/admin.php` đặt `POST|PUT|DELETE /api/v1/admin/formats` trong group `role:admin,super-admin,theater_manager`. Ba method `storeFormat`, `updateFormat`, `destroyFormat` ở `app/Http/Controllers/Admin/ScreenController.php` không gọi `authorize()`. Hai Form Request Format đều trả về `true` trong `authorize()`.
- **Nguyên nhân:** Format CRUD được gộp vào `ScreenController`; khác với Sound CRUD ngay bên dưới có `SoundPolicy` và gọi `$this->authorize(...)`.
- **Ảnh hưởng:** `theater_manager` có thể tạo/sửa/xóa danh mục Format dùng toàn hệ thống dù role matrix không cấp `formats.manage`; có thể làm sai giá/phụ thu và cấu hình phòng chiếu liên quan.
- **Khắc phục:** tạo `FormatPolicy` với `formats.manage`, áp dụng policy cho cả 3 action (hoặc gắn `permission:formats.manage` ở route); chỉ thêm permission này cho admin mặc định; bổ sung test 403 cho theater manager và test 201/200/204 cho admin.
- **Xác minh hoàn tất:** `php artisan test --filter=FormatAuthorization` với matrix admin/theater_manager/ticket_seller.

### H-02 — Dynamic HTML chưa escape tạo đường dẫn stored DOM XSS

**Mức độ: High**

- **Bằng chứng:** `public/js/users/pages/theaters.js` chèn `theater.phone` và `theater.email` vào `innerHTML`; `public/js/pos/pos-app.js` chèn `theater.name` vào `insertAdjacentHTML`. Các trường này là chuỗi quản trị được chấp nhận bởi `StoreTheaterRequest`/`UpdateTheaterRequest` và admin tương ứng.
- **Nguyên nhân:** frontend có nhiều cách render HTML động (audit tìm thấy 303 sink `innerHTML`/`insertAdjacentHTML`) nhưng không có boundary bắt buộc để escape hoặc render text node.
- **Ảnh hưởng:** dữ liệu đã lưu có thể thực thi markup/script trong trang công khai hoặc POS khi được hiển thị; CSP hiện tại không đủ giảm thiểu vì vẫn cho phép `'unsafe-inline'`.
- **Khắc phục:** thay phần text bằng `textContent`/`document.createElement`; với HTML bắt buộc dùng một helper escape/sanitizer duy nhất; áp dụng rule lint/test cấm nội suy dữ liệu không escape vào sink HTML; kiểm tra toàn bộ 303 sink theo nguồn dữ liệu.
- **Xác minh hoàn tất:** test DOM với payload HTML cho tên rạp/điện thoại/email và assertion không sinh element/script ngoài ý muốn.

### H-03 — Dependency audit đang có advisory High chưa được xử lý

**Mức độ: High**

- **Bằng chứng:** `composer audit` báo 6 advisory trên `league/commonmark` 2.8.3 (4 High, 2 Medium; phạm vi ảnh hưởng kết thúc trước 2.9.0). Đây là dependency bắc cầu của `laravel/framework` 12.64.0. `npm audit --audit-level=moderate` báo 5 High qua `nanoid` 3.3.16 → `postcss` → `vite` trong dev toolchain.
- **Nguyên nhân:** lockfile đang giữ phiên bản đã có advisory mới công bố.
- **Ảnh hưởng:** các advisory CommonMark là DoS và bypass filter khi có input Markdown; chưa tìm thấy sử dụng Markdown trực tiếp trong `app/`, `resources/`, `routes/`, nên exploitability của sản phẩm cần xác minh riêng. Toolchain build/CI vẫn không đạt ngưỡng audit hiện tại.
- **Khắc phục:** cập nhật Laravel/framework và lockfile để resolve `league/commonmark >= 2.9.0`; cập nhật Vite/PostCSS theo advisory hoặc chấp nhận có ghi rõ ngoại lệ có thời hạn nếu upstream chưa có bản vá; chạy lại audit, build, PHPUnit.
- **Xác minh hoàn tất:** `composer audit` và `npm audit --audit-level=moderate` exit 0, hoặc chỉ còn advisory đã được risk-accept có owner/hạn xử lý.

### M-01 — CSP yếu và API không nhận security headers cơ bản

**Mức độ: Medium**

- **Bằng chứng:** `app/Http/Middleware/SecurityHeaders.php` trả response sớm cho mọi `/api/*`; kiểm tra runtime `GET /api/v1/movies` chỉ có request ID và `Access-Control-Allow-Origin: *`, không có `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` hay CSP. HTML có các header này nhưng CSP dùng `'unsafe-inline'` cho script và style.
- **Ảnh hưởng:** giảm lớp phòng vệ khi một XSS hoặc MIME sniffing xuất hiện; inline script làm CSP không chặn được payload inline.
- **Khắc phục:** externalize inline script/style, dùng nonce/hash cho trường hợp buộc phải inline; áp dụng `nosniff` và referrer policy cho API; review CORS theo origin thực tế thay vì wildcard nếu API sẽ được gọi cross-origin.

### M-02 — Một số POS API trả nguyên exception message cho client

**Mức độ: Medium**

- **Bằng chứng:** `app/Http/Controllers/Pos/PosController.php` trả `$e->getMessage()` tại create order (RuntimeException), sync hold và cancel order. Các action khác trong cùng controller trả thông điệp tổng quát.
- **Ảnh hưởng:** lỗi service/DB được wrap thành RuntimeException có thể lộ schema, trạng thái nội bộ hoặc chi tiết nhà cung cấp; UX cũng không nhất quán.
- **Khắc phục:** quy chuẩn exception domain có mã lỗi public đã duyệt; map exception sang thông điệp tiếng Việt an toàn, log chi tiết kèm request ID ở server; chỉ trả `request_id` cho lỗi bất ngờ.

### M-03 — PII được ghi nguyên validation payload vào log

**Mức độ: Medium**

- **Bằng chứng:** catch tạo/cập nhật rạp trong `app/Http/Controllers/TheaterController.php` ghi `data => $request->validated()`. Payload có địa chỉ, số điện thoại và email.
- **Ảnh hưởng:** PII đi vào file log/Sentry, tăng phạm vi truy cập dữ liệu và nghĩa vụ retention/redaction.
- **Khắc phục:** log ID, field names, request ID và hash/mask dữ liệu; tạo log context allowlist; cấu hình retention/access audit cho Sentry và log store.

### M-04 — Quality gates xanh nhưng static analysis không bao phủ toàn bộ mã

**Mức độ: Medium**

- **Bằng chứng:** `phpstan.neon` chỉ quét 6 vùng nhỏ (commands, 1 controller, 2 middleware, observability, OpenAPI) ở level 5. `eslint.config.js` tắt toàn cục `no-undef` và `no-unused-vars`. `composer test:modern-format` chỉ Pint một danh sách file hẹp và hiện fail `routes/api/public.php` với `ordered_imports`.
- **Ảnh hưởng:** CI có thể pass trong khi lỗi type/undefined/dead code ở phần lớn `app/` và `public/js/` không bị chặn; format gate hiện cũng không xanh.
- **Khắc phục:** mở rộng PHPStan theo module rồi toàn `app/`; bật dần ESLint rules với globals chính xác; thay format script bằng `pint --test` toàn project hoặc baseline có expiry; sửa import order đang fail.

### M-05 — Test browser và RBAC chưa kiểm chứng các luồng có xác thực trong CI

**Mức độ: Medium**

- **Bằng chứng:** CI chỉ chạy `npm run test:browser:smoke` cho public routes. `test:browser:roles` tồn tại nhưng không nằm trong workflow và cần `BROWSER_TEST_SESSIONS`. Test Format hiện chỉ kiểm tra admin/sound, không có test theater manager bị từ chối.
- **Ảnh hưởng:** regression quyền và giao diện POS/admin khó bị phát hiện trước merge.
- **Khắc phục:** tạo dữ liệu/session test cô lập trong CI; đưa role browser smoke vào workflow; thêm API authorization matrix cho từng action nhạy cảm; lưu screenshot/trace artifact khi browser test fail.

### M-06 — Trang tin tức tràn ngang ở breakpoint tablet

**Mức độ: Medium**

- **Bằng chứng runtime:** browser tại 768×1024 cho `/posts` có `documentElement.scrollWidth > clientWidth`; phần tử tràn là `.posts-main-section .row.g-5`, `col-lg-8` và `aside.col-lg-4` từ -12px đến 772px. Không có overflow tại 390×844 hoặc 1440×900.
- **Nguyên nhân:** reset row chỉ tồn tại ở `max-width: 767.98px` trong `public/css/users/pages/posts.css`, trong khi `posts-index.css` thay gutter cho tới `991.98px`.
- **Ảnh hưởng:** kéo ngang không chủ đích ở tablet, làm card/sidebar lệch và giảm khả năng đọc.
- **Khắc phục:** chuẩn hóa gutter/container cho khoảng 768–991.98px theo pattern Bootstrap chung; thêm Playwright visual/overflow assertion tại 768px.

### M-07 — CSS/admin source ownership chưa hoàn tất, làm tăng rủi ro UI không nhất quán

**Mức độ: Medium**

- **Bằng chứng:** `resources/css/admin.css` chỉ import ngược `public/css/admin/admin.css`; entry này import token/component mới đồng thời `admin-common.css`, `admin-modals.css`, `style.css` như “temporary compatibility layer”, và tự liệt kê page CSS “will be refactored later”.
- **Ảnh hưởng:** cascade order khó dự báo, tăng bundle CSS admin (build gzip khoảng 20,36 kB), dễ tái xuất hiện lỗi spacing/modal/skeleton không đồng nhất.
- **Khắc phục:** chọn `resources/css` làm source duy nhất; chuyển page styles theo từng module, loại compatibility import sau visual regression; dùng CSS layers/tokens và ownership rõ ràng.

### M-08 — Check permission có nguy cơ phát sinh nhiều query khi render sidebar

**Mức độ: Medium**

- **Bằng chứng:** closure `$canAny` trong `resources/views/layouts/admin.blade.php` gọi `User::hasPermission()` cho từng permission/menu; method này thực hiện `role->permissions()->whereIn(...)->exists()` mỗi lần. Menu được lọc động theo nhóm và child.
- **Ảnh hưởng:** với role không phải admin, SSR admin sidebar có thể phát nhiều query `exists`, làm tăng độ trễ trang quản trị theo số mục menu.
- **Khắc phục:** eager-load/cache permission slugs theo request hoặc đưa `canAny` vào authorization service trả set đã tính; benchmark bằng Telescope/DB query listener và đặt budget query cho admin shell.

### M-09 — Controller/service tập trung quá nhiều trách nhiệm và có trùng lặp public/admin

**Mức độ: Medium**

- **Bằng chứng:** `PaymentService` 692 lines, `OrderService` 619, `SeatService` 533; `Admin/ScreenController` 415 lines quản lý screen, seat state, format và sound. Đồng thời tồn tại cặp `TheaterController`, `ProductController`, `ScreenController` ở root và `Admin/` với logic/query khác nhau.
- **Ảnh hưởng:** dễ lệch policy/validation như H-01, khó test theo use case, tăng regression khi sửa POS/booking/admin song song.
- **Khắc phục:** không big-bang rewrite; tách theo use case (FormatCatalog, SoundCatalog, SeatState), dùng shared application service/resource/query object, sau đó xóa controller logic trùng khi có characterization tests.

### L-01 — Hai nút hiện/ẩn mật khẩu không có accessible name

**Mức độ: Low**

- **Bằng chứng runtime/source:** browser tìm thấy 2 unlabeled button trên mọi public page; tại `resources/views/partials/auth-modal.blade.php`, nút `.cinema-auth-toggle-password` chỉ chứa icon, không có `aria-label`/text.
- **Ảnh hưởng:** screen reader không hiểu hành động; khó kiểm thử bằng role/name.
- **Khắc phục:** thêm `aria-label` động “Hiển thị mật khẩu”/“Ẩn mật khẩu”, `aria-pressed`, và cập nhật nhãn trong `public/js/users/auth.js` khi toggle.

### L-02 — Tài liệu cấu trúc và trạng thái remediation đã lạc hậu

**Mức độ: Low**

- **Bằng chứng:** `README.md` tham chiếu `REVIEWS/SYSTEM_STRUCTURE_REVIEW.md` và tracker trong `REVIEWS/` nhưng thư mục không hiện diện. `FRONTEND_STRUCTURE.md` vẫn ghi Phase 1/2 hoàn tất và “Next: Phase 3”, không phản ánh Vite/Turbo/POS hiện tại.
- **Ảnh hưởng:** onboarding và quyết định refactor dựa trên tài liệu sai.
- **Khắc phục:** đưa report/tracker vào một thư mục docs tồn tại, gắn owner/date/expiry, và CI link checker đơn giản cho Markdown nội bộ.

## Điểm đã xác nhận tốt

- Migration local đồng bộ; nhiều migration gần đây bổ sung unique/foreign key/index cho booking, payment, listing và POS.
- API có request ID và error envelope tập trung ở `bootstrap/app.php` cho exception framework.
- Route auth, booking, payment, POS và webhook có rate limiter; webhook PayOS được xác thực trong `PaymentService::handleWebhook()` qua gateway.
- Public pages kiểm tra ở mobile và desktop không có console error, không ảnh thiếu `alt`, không tràn ngang; trừ `/posts` ở 768px.
- Vite build thành công; public JS assets được chia entry, dù một số page file vẫn lớn và cần budget sau này.

## Lộ trình remediation đề xuất

### P0 — Chặn release (0–2 ngày)

1. Sửa H-01, thêm policy/route middleware và authorization tests cho Format.
2. Sửa H-02 tại các sink đã xác nhận; tạo helper render an toàn và test payload.
3. Cập nhật dependency cho H-03, chạy audit/build/test trên lockfile mới.
4. Chạy lại full regression booking/POS/payment sau các thay đổi trên.

### P1 — Hardening (3–7 ngày)

1. Chuẩn hóa public error mapping, bỏ raw exception response và PII logging.
2. Tighten CSP/header; xác nhận CORS theo domain triển khai.
3. Sửa `/posts` 768px và thêm viewport regression 390/768/1440.
4. Thêm role API/browser test matrix, artifact khi test fail.

### P2 — Maintainability & performance (1–3 sprint)

1. Mở rộng PHPStan/ESLint/Pint toàn project theo baseline có deadline.
2. Đo và cache permission set của admin sidebar.
3. Chuyển CSS admin sang ownership trong `resources/css`, giảm compatibility layer theo visual regression.
4. Tách Screen/Format/Sound và các service lớn theo use case; tránh thay đổi logic booking/payment khi chưa có characterization test.
5. Cập nhật tài liệu kiến trúc và tracker sau mỗi PR remediation.

## Tiêu chí đóng audit

- Không còn High/Critical từ `composer audit`/`npm audit` hoặc có risk acceptance được phê duyệt, có hạn xử lý.
- Authorization matrix pass cho mọi endpoint thay đổi cấu hình hoặc tiền/đơn/vé.
- Không còn interpolation không escape vào HTML sink theo lint/test rule.
- Browser suite chạy được trong CI cho public + authenticated roles, với 390/768/1440 và không layout overflow.
- PHPStan, ESLint, Pint toàn project xanh; report coverage/quality trend được lưu trong CI.

## Cập nhật remediation cuối — 08/08/2026

### Đã hoàn tất

- PHPStan level 5 đã mở rộng ra toàn bộ `app/` (219 file) và không còn lỗi.
- Full backend regression đạt 254 test, 2057 assertion; structure gate và modern-format gate đều đạt.
- ESLint, frontend syntax gate, frontend security gate và Vite production build đều đạt.
- `composer audit` và `npm audit --audit-level=moderate` không phát hiện advisory.
- Chuẩn hóa kiểu quan hệ Eloquent, API Resource, paginator transformer và các kiểu trả về controller/service.
- Sửa các sai lệch field promotion/order/showtime/payment, xử lý stock combo thiếu dữ liệu và JWT invalidation.
- Loại bỏ việc đưa trực tiếp thông báo lỗi POS vào `innerHTML`; bổ sung shared `renderEmptyState()` dùng `textContent`.
- Security gate hiện chặn lỗi mới khi nội suy trực tiếp `error.message` hoặc `err.message` vào HTML sink.
- CSP script không cho phép inline script. Chính sách CSP nghiêm ngặt `style-src-attr 'none'` được bật ở chế độ Report-Only để đo vi phạm trước khi chuyển sang enforced.

### Ngoại lệ được kiểm soát

- Enforced CSP vẫn tạm giữ `style-src-attr 'unsafe-inline'` vì giao diện hiện còn các style động phục vụ modal, skeleton, progress và responsive state. Xóa ngay sẽ làm hỏng tương tác. Report-Only đã được bổ sung để migration theo số liệu thay vì thay đổi phá vỡ giao diện.
- Phiên kiểm thử Codex In-app Browser chưa chạy được do Browser webview không attach sau nhiều lần thử. Các gate tự động, build và test backend/frontend vẫn đã chạy đầy đủ; authenticated visual regression cần chạy lại khi webview khả dụng.
