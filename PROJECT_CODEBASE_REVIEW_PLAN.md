# Kế hoạch review toàn bộ codebase

## 1. Mục tiêu

Thực hiện audit toàn bộ hệ thống Cinema theo góc nhìn Senior/Staff Engineer, tập trung vào:

- correctness và tính toàn vẹn nghiệp vụ;
- architecture và ranh giới trách nhiệm;
- consistency giữa các module tương tự;
- maintainability và khả năng mở rộng;
- performance, concurrency và scalability;
- security, authentication và authorization;
- UI/design consistency, accessibility và responsive;
- testing, dependency health, CI/CD và developer experience.

Đây là giai đoạn **audit-only**. Không sửa code, không thay đổi schema, không format hàng loạt và không cập nhật dependency trong lúc review.

## 2. Nguyên tắc bắt buộc

1. Đọc đủ rộng trước khi kết luận.
2. Mỗi finding phải có bằng chứng cụ thể từ code, cấu hình, test hoặc hành vi runtime.
3. So sánh tối thiểu ba implementation tương tự trước khi kết luận một module lệch pattern.
4. Pattern phổ biến và có chủ đích trong project là nguồn tham chiếu chính.
5. Phân biệt rõ bug, security risk, inconsistency, technical debt và suggestion.
6. Không coi file dài là lỗi nếu responsibility vẫn rõ và cohesion tốt.
7. Không đề xuất framework, package hoặc architecture mới nếu chưa chứng minh vấn đề thực tế.
8. Finding cũ phải được xác minh lại trên code hiện tại; không sao chép kết luận từ report cũ.
9. Không đọc hoặc đưa giá trị secret từ `.env` vào báo cáo.
10. Các quality gate chỉ cung cấp bằng chứng; test pass không đồng nghĩa hệ thống không còn lỗi.

## 3. Phạm vi hiện tại

### Backend

- `app/Http/Controllers`, Form Requests, Middleware và Resources.
- `app/Services`, Models, Policies, Events, Jobs, Mail và Console Commands.
- Routes web/API cho public, auth, customer, admin, POS và staff.
- Database migrations, factories, seeders, indexes và constraints.
- Config authentication, JWT, session, cache, queue, CORS, CSP, filesystems, logging và observability.

### Frontend

- Blade layouts và views của customer, admin, staff và POS.
- Vite entry points trong `resources/js` và `resources/css`.
- Page-scoped JavaScript/CSS trong `public/js` và `public/css`.
- Turbo lifecycle, API client, authentication runtime, toast, modal, skeleton, table và pagination.
- Booking, payment result, profile/tickets, posts, admin management, scanner và POS workflows.

### Quality và vận hành

- PHPUnit feature/unit tests.
- Playwright/browser smoke scripts.
- ESLint, frontend syntax/security gates, Larastan và Pint.
- Composer/NPM dependencies và GitHub Actions.
- README, architecture, standards, setup và operational documentation.

### Quy mô baseline đã ghi nhận

- `app`: khoảng 218 file.
- `database`: khoảng 103 file.
- `resources`: khoảng 62 file.
- `public/js`: khoảng 61 file.
- `public/css`: khoảng 59 file.
- `tests`: khoảng 55 file.
- Các hotspot lớn gồm booking, profile, showtime admin, order admin, payment, seat locking và POS.

## 4. Deliverables

### Báo cáo chính

Tạo `PROJECT_CODEBASE_REVIEW_REPORT.md` với cấu trúc:

1. Executive Summary.
2. Architecture Assessment.
3. Critical/High Findings.
4. Code Quality.
5. Consistency Issues.
6. UI/Design Consistency.
7. Performance and Scalability.
8. Security.
9. Testing and Quality Gates.
10. Technical Debt.
11. Quick Wins.
12. Recommended Refactoring.
13. Priority Roadmap P0–P3.
14. Overall Assessment.

### Phụ lục bằng chứng

- Inventory các module và entry point.
- Ma trận route → middleware → request → policy → controller → service → test.
- Ma trận UI pattern giữa customer/admin/staff/POS.
- Danh sách quality gate đã chạy và kết quả thực tế.
- Danh sách finding cũ: còn đúng, đã khắc phục hoặc không đủ bằng chứng.

## 5. Quy trình thực hiện

### Phase 0 — Khóa phạm vi và lập baseline

Mục tiêu: bảo đảm review có thể lặp lại và không nhầm thay đổi đang phát triển với lỗi hệ thống.

Thực hiện:

- ghi nhận branch, commit hiện tại và trạng thái worktree nhưng không sửa hoặc reset file;
- lập danh sách file theo module, loại file và kích thước;
- xác định generated artifacts, screenshots, build output và file không thuộc source review;
- đọc README, architecture, standards, package manifests, CI và `.env.example`;
- ghi nhận framework, runtime, package manager và hạ tầng dự kiến;
- đánh dấu tài liệu có khả năng stale so với code hiện tại.

Đầu ra:

- repository inventory;
- danh sách entry point;
- danh sách tài liệu chuẩn dùng để đối chiếu;
- danh sách vùng cần review sâu.

Điều kiện hoàn thành:

- mọi thư mục source chính đều được phân loại;
- không có kết luận kỹ thuật nào được đưa ra chỉ từ documentation.

### Phase 1 — Lập bản đồ architecture và dependency flow

Mục tiêu: xác định architecture thực tế thay vì architecture được mô tả.

Thực hiện:

- map web/API request flow từ route tới response;
- map domain flow cho booking, seat hold, order, payment, fulfillment, expiration và ticket;
- map admin/staff/POS authorization flow;
- map browser flow giữa Blade shell, Vite bundle, public page script và Turbo lifecycle;
- xác định layer ownership của validation, authorization, transformation và business logic;
- tìm controller/service/model/view đang vượt trách nhiệm;
- tìm dependency ngược, service locator, static/global coupling và duplicated orchestration;
- kiểm tra kiến trúc tài liệu có khớp implementation hiện tại hay không.

Các domain bắt buộc trace end-to-end:

1. Đặt vé online và giữ ghế.
2. Thanh toán PayOS, callback/webhook và fulfillment.
3. Đơn 0 đồng.
4. POS tiền mặt, POS QR và đơn chỉ có bắp nước.
5. Hủy/hết hạn đơn và hoàn trả tài nguyên.
6. Ticket issuance, email và check-in QR.
7. RBAC, theater scope và audit log.
8. Admin Turbo navigation và page lifecycle.

Điều kiện hoàn thành:

- mỗi domain có sơ đồ control flow và source-of-truth;
- các boundary bất nhất đều có ít nhất hai điểm đối chiếu.

### Phase 2 — Backend correctness và maintainability

Mục tiêu: tìm lỗi logic, layer violation và duplication có ảnh hưởng thực tế.

Thực hiện:

- review controller mỏng/dày, Form Request coverage và response consistency;
- review service cohesion, transaction boundaries và exception semantics;
- review model casts, state helpers, relationships, mass assignment và hidden fields;
- review Resource/serializer để tránh lộ payload nội bộ;
- review enum/status mapping giữa database, model, API và frontend;
- review idempotency, retry behavior và side-effect ordering;
- review mail/job/event dispatch trước và sau transaction commit;
- kiểm tra analytics dùng chung định nghĩa doanh thu, discount, refund và timezone;
- so sánh CRUD admin tương tự để tìm implementation đi lệch.

Mẫu so sánh bắt buộc:

- movie/product/combo/promotion CRUD;
- theater/screen/showtime/seat layout;
- customer order/admin order/POS order;
- revenue/ticket/food/combo analytics;
- banner/post content management.

### Phase 3 — Database, concurrency và financial integrity

Mục tiêu: xác minh các invariant quan trọng được bảo vệ ở cả application và database.

Thực hiện:

- kiểm tra foreign key, unique constraint, index và kiểu dữ liệu tiền;
- kiểm tra migration forward/backward safety và dữ liệu legacy;
- kiểm tra N+1, eager loading, pagination và query theo scope rạp;
- kiểm tra race condition khi giữ/bỏ ghế, trừ tồn kho, dùng voucher/điểm;
- kiểm tra duplicate payment callback, webhook replay và concurrent fulfillment;
- kiểm tra expiration/cancellation hoàn lại đầy đủ ghế, stock, điểm và voucher;
- kiểm tra order/payment/ticket state transition hợp lệ;
- kiểm tra transaction có gọi network, mail hoặc broadcast trong lock quá lâu không;
- kiểm tra cleanup jobs, scheduler overlap và retry safety.

Invariant bắt buộc xác minh:

- một ghế không thể bán cho hai đơn;
- một payment chỉ fulfill một lần;
- tổng order item, discount và final amount khớp;
- hủy/hết hạn không để tài nguyên bị giữ vĩnh viễn;
- ticket không được issue trước khi payment được xác nhận;
- check-in không thể dùng lại ticket đã sử dụng.

### Phase 4 — API, authentication, authorization và security

Mục tiêu: tìm security flaw có khả năng khai thác và inconsistency trong boundary API.

Thực hiện:

- lập ma trận toàn bộ route và middleware;
- kiểm tra public/protected route, permission alias và policy coverage;
- kiểm tra IDOR theo user, role và theater scope;
- kiểm tra JWT cookie, refresh rotation, CSRF, logout và token storage;
- kiểm tra rate limit cho auth, seat, order, payment, ticket và webhook;
- kiểm tra validation cho upload, rich HTML, redirect và callback URL;
- kiểm tra XSS sinks như `innerHTML`, unsafe template và rich text rendering;
- kiểm tra SQL/raw query, command execution và path traversal;
- kiểm tra sensitive logging, exception detail và API payload;
- kiểm tra CORS, CSP, security headers, cookie flags và production defaults;
- chạy dependency audit và xác minh advisory có reachable hay không;
- kiểm tra OpenAPI contract có phản ánh route, auth và error schema hiện tại.

Quy tắc severity:

- Critical chỉ dùng khi có đường khai thác rõ, ảnh hưởng lớn và ít điều kiện.
- High dùng cho bypass authorization, financial integrity hoặc data exposure đáng kể.
- Không nâng severity chỉ dựa trên pattern đáng ngờ nếu chưa có execution path.

### Phase 5 — Frontend architecture, state và API consistency

Mục tiêu: xác định các runtime/pattern song song gây stale state, duplicate request và khó bảo trì.

Thực hiện:

- map Vite entries, Blade script includes và public legacy scripts;
- kiểm tra global namespace, duplicate initialization và event listener cleanup;
- kiểm tra Turbo load/cleanup hooks, abort request và modal teardown;
- so sánh API client/auth fetch/direct fetch giữa các page;
- kiểm tra normalization khi API trả array, paginated object hoặc wrapped resource;
- kiểm tra race condition, stale response, polling và interval cleanup;
- kiểm tra derived state, duplicate state và state reset giữa các bước;
- review booking/POS/profile/admin hotspot theo responsibility;
- tìm duplicated formatting, error handling, toast, modal, table, skeleton và pagination;
- xác minh fallback khi network lỗi, 401, 403, 422, 429 và 500.

Hotspot ưu tiên:

- `public/js/users/pages/booking.js`;
- `public/js/users/pages/profile.js`;
- admin showtimes/orders/users/combos;
- POS app/payment/cart/seat/customer;
- admin navigation và API request utilities.

### Phase 6 — UI, responsive và accessibility

Mục tiêu: đánh giá implementation theo design system đang tồn tại, không tạo style mới.

Thực hiện:

- inventory design tokens và shared component classes;
- so sánh ít nhất ba page cho button, form, card, filter, table, modal và pagination;
- tìm CSS ownership chồng chéo giữa base/component/common/page/legacy;
- kiểm tra hard-coded color/spacing/radius khi đã có token;
- kiểm tra loading/empty/error/success/disabled/hover/focus/active;
- kiểm tra skeleton chỉ bao phủ vùng dữ liệu, khớp layout thật và không gây CLS;
- kiểm tra modal focus trap, ESC, backdrop, body scroll và cleanup qua Turbo;
- kiểm tra semantic HTML, label, ARIA, keyboard flow và touch target;
- kiểm tra contrast và reduced motion;
- kiểm tra desktop, tablet và mobile cho các workflow quan trọng.

Browser matrix tối thiểu:

| Khu vực | Desktop | Tablet | Mobile |
|---|---:|---:|---:|
| Customer home/movies/posts | Có | Có | Có |
| Booking/payment/result | Có | Có | Có |
| Profile/tickets/orders | Có | Có | Có |
| Admin dashboard/list/form/modal | Có | Có | Có |
| POS | Có | Có | N/A hoặc kiểm tra graceful fallback |
| Staff scanner/concession | Có | Có | Có |

### Phase 7 — Performance và scalability

Mục tiêu: chỉ ghi nhận vấn đề performance khi có bằng chứng định lượng hoặc execution path rõ.

Thực hiện:

- đo route/query count cho dashboard, listings, booking và POS;
- kiểm tra N+1 và duplicate API requests;
- kiểm tra cache key, invalidation, user/theater scope và cache stampede;
- đo bundle/asset size và script tải theo từng page;
- kiểm tra ảnh poster/banner/post, lazy loading, dimensions và fallback;
- kiểm tra DOM/list rendering lớn, pagination và client filtering;
- kiểm tra polling interval, realtime fallback và hidden-tab behavior;
- kiểm tra transaction/lock duration trong payment và seat flow;
- kiểm tra queue separation, retries, failed jobs và scheduler health;
- phân biệt bottleneck production với giới hạn riêng của XAMPP/dev mode.

Mọi performance finding phải kèm ít nhất một trong các bằng chứng:

- query log hoặc test query count;
- network waterfall/request trace;
- bundle/file size;
- algorithmic path rõ ràng;
- reproducible timing comparison.

### Phase 8 — Testing, dependencies, CI/CD và developer experience

Mục tiêu: đánh giá khả năng phát hiện regression và mức độ dễ vận hành dự án.

Thực hiện:

- map test hiện có tới các business-critical flow;
- xác định test gap theo risk, không chỉ theo coverage percentage;
- kiểm tra test isolation, database assumptions, clock/timezone và external gateway mocking;
- kiểm tra browser smoke có assertion meaningful và không phụ thuộc state ngẫu nhiên;
- kiểm tra flaky source: timeout, fixed sleep, date hiện tại, shared database và network;
- chạy structure, static analysis, lint, syntax/security gate, build và PHPUnit;
- chạy browser smoke trong môi trường kiểm soát khi baseline backend đã ổn;
- chạy Composer/NPM audit và phân loại direct/transitive/reachable;
- kiểm tra CI local parity, cache, timeout và artifact handling;
- kiểm tra setup docs, environment defaults, queue/realtime requirements và troubleshooting;
- kiểm tra dead code, generated files, debug logs và TODO/FIXME.

### Phase 9 — Xác minh chéo và triage findings

Mục tiêu: loại false positive và chuẩn hóa mức độ ưu tiên.

Với từng candidate finding:

1. Xác minh file/line trên source hiện tại.
2. Xác minh execution path hoặc caller.
3. Tìm test hoặc guard hiện có có thể vô hiệu hóa rủi ro.
4. So sánh với pattern phổ biến trong project.
5. Ghi evidence tối thiểu đủ tái hiện.
6. Gán severity và confidence.
7. Tách quick fix khỏi refactor dài hạn.
8. Ghi dependency giữa các remediation để tránh sửa sai thứ tự.

Finding không đủ bằng chứng sẽ được đưa vào “Needs verification”, không xếp Critical/High.

### Phase 10 — Viết report và roadmap

Mục tiêu: tạo báo cáo có thể chuyển thẳng thành remediation backlog.

Mỗi finding dùng format:

```text
### [Severity] Finding title

Location: path/to/file:line
Category: Architecture | Correctness | Security | Performance | UI | Testing | DX
Confidence: High | Medium | Low

Problem:
Mô tả cụ thể.

Why it matters:
Ảnh hưởng thực tế.

Evidence:
Code path, pattern so sánh, test hoặc runtime evidence.

Recommendation:
Cách xử lý tối thiểu và phù hợp architecture hiện tại.

Risk if unresolved:
Rủi ro nếu giữ nguyên.

Verification:
Cách chứng minh remediation hoàn tất.
```

Roadmap cuối cùng:

- P0: correctness/security/financial integrity cần xử lý trước release.
- P1: vấn đề gây regression cao hoặc cản production operation.
- P2: maintainability, consistency và performance có evidence.
- P3: polish và suggestion không ảnh hưởng correctness.

## 6. Quality gates dự kiến

Chạy theo thứ tự từ rẻ đến tốn thời gian:

```powershell
composer test:structure
composer analyse
composer test:modern-format
npm run lint
npm run test:frontend:syntax
npm run test:frontend:security
npm run build
php artisan test --compact
npm run test:browser:smoke
npm run test:browser:roles
composer audit
npm audit --audit-level=moderate
```

Lưu ý:

- audit dependency có thể cần network;
- browser test cần server, database seed và account test ổn định;
- không sửa code để làm quality gate pass trong giai đoạn review;
- failure được ghi thành evidence và phân loại nguyên nhân trước khi tạo finding.

## 7. Chiến lược sampling và coverage

Review toàn bộ repository không đồng nghĩa đọc mọi file với cùng độ sâu.

### Review 100%

- routes, middleware, policies và auth config;
- payment/order/seat/ticket/POS services;
- migration liên quan financial/state integrity;
- Vite entries, API/auth client và Turbo lifecycle;
- CI workflow và quality scripts;
- các file chứa secret handling, upload, HTML rendering hoặc raw query.

### Review theo representative set và đối chiếu pattern

- CRUD controllers và admin pages;
- Blade forms/modals/tables;
- CSS page-specific;
- factories/seeders ít rủi ro;
- static content pages.

Nếu representative set phát hiện inconsistency có hệ thống, phạm vi sẽ mở rộng sang toàn bộ nhóm đó.

## 8. Phân công theo workstream

Nếu dùng sub-agent, chia theo vùng độc lập để tránh duplicate:

1. Backend architecture và data integrity.
2. Security, auth, RBAC và API contract.
3. Frontend architecture, Turbo lifecycle và state.
4. UI consistency, responsive và accessibility.
5. Testing, performance, dependencies và DX.

Main reviewer chịu trách nhiệm:

- đọc và thống nhất tiêu chí;
- xác minh lại mọi Critical/High finding;
- loại finding trùng;
- chuẩn hóa severity;
- tổng hợp architecture assessment và roadmap.

## 9. Definition of Done

Review chỉ được coi là hoàn tất khi:

- toàn bộ module chính đã có inventory và mức coverage;
- tám workflow trọng yếu đã được trace end-to-end;
- toàn bộ route protected đã được kiểm tra middleware/policy/scope;
- các invariant payment, seat, stock, voucher, point và ticket đã được xác minh;
- frontend customer/admin/staff/POS đã được so sánh pattern;
- quality gate và browser matrix có kết quả hoặc blocker rõ ràng;
- mọi Critical/High finding được main reviewer xác minh lại;
- mỗi finding có location, evidence, recommendation, risk và verification;
- finding cũ được đánh dấu còn đúng/đã sửa/không xác minh được;
- report không chứa secret, dữ liệu cá nhân hoặc kết luận thiếu bằng chứng;
- roadmap P0–P3 có dependency và thứ tự triển khai hợp lý.

## 10. Thứ tự thực thi đề xuất

1. Baseline và architecture map.
2. Payment/order/seat/ticket financial integrity.
3. Authentication, RBAC, API và security.
4. POS và customer booking workflows.
5. Admin/staff modules và analytics.
6. Frontend lifecycle, UI consistency và accessibility.
7. Performance, testing, dependencies và DX.
8. Browser verification.
9. Cross-validation findings.
10. Final report và remediation roadmap.

Thứ tự này ưu tiên correctness và security trước visual polish, đồng thời giảm nguy cơ báo cáo UI/performance dựa trên một backend flow chưa ổn định.
