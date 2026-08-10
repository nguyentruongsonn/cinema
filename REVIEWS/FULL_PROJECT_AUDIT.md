# Full Project Audit

Ngày audit: 10/08/2026  
Phạm vi: Laravel API, Blade/Vanilla JS, Turbo admin shell, POS, RBAC, booking/payment, email/queue, database, tests, build và browser smoke test.

## 1. Executive summary

Hệ thống đã có nền tảng production tương đối tốt: API versioned, FormRequest, policy/RBAC, theater scope, idempotency, transaction, queue, CSP nonce, security headers, pagination và test suite theo domain. Các quality gate chính hiện chạy thành công sau remediation trong đợt audit này.

Đánh giá tổng quan: **B+ / production candidate có điều kiện**.

- Không phát hiện Critical đang mở.
- Các lỗi High phát hiện trong audit đã được sửa: API trang chủ 500 do dữ liệu JSON legacy, lỗi hóa đơn in khi format nullable và 27 lỗi PHPStan.
- Hai High phát hiện trong vòng review bổ sung đã được remediation ở code và xác minh trên local; production vẫn cần process manager để bảo đảm worker không dừng âm thầm.
- Các rủi ro còn lại chủ yếu là vận hành queue, cấu hình runtime, accessibility, maintainability, CSP style attribute, kiểm thử E2E đa thiết bị và component adoption chưa đồng đều.

## 2. Inventory

| Hạng mục | Số lượng |
|---|---:|
| Routes | 239 |
| Controllers | 44 |
| Form Requests | 47 |
| Models | 40 |
| Services | 34 |
| Policies | 16 |
| Jobs | 4 |
| Migrations | 72 |
| Blade views | 53 |
| Public JavaScript files | 64 |
| Feature tests | 45 |
| Unit tests | 6 |

## 3. Quality gates

| Gate | Kết quả |
|---|---|
| `composer audit --locked` | Pass, không có advisory |
| `npm audit --omit=dev` | Pass, 0 vulnerability |
| `composer analyse` | Pass, 0 PHPStan error |
| `npm run lint` | Pass |
| `npm run build` | Pass |
| `npm run test:frontend:syntax` | Pass |
| `npm run test:frontend:security` | Pass |
| Route/controller integrity | Pass |
| RBAC/security targeted tests | Pass |
| POS/printing targeted tests | Pass |
| Full Laravel test suite | Pass, 298 tests / 2269 assertions |
| Browser smoke test | Pass trên các màn hình đại diện |

## 4. Findings và remediation

### Critical

Không có finding Critical đang mở.

### High

#### H-01 — API trang chủ trả 500 với dữ liệu backdrop legacy — Đã sửa

- Nguyên nhân: một số bản ghi `movies.backdrops` bị double-encode; Eloquent JSON cast trả về chuỗi thay vì mảng.
- Ảnh hưởng: banner/phim đang chiếu biến mất, client hiển thị toast lỗi.
- Khắc phục: đọc raw value, decode tối đa hai lớp, trả fallback an toàn và log dữ liệu hỏng.
- File: `app/Http/Controllers/HomeController.php`.
- Xác minh: `/api/v1/home` trả 200, 8 phim, 3 banner; browser hiển thị 8 movie cards và không còn toast lỗi.

#### H-02 — In hóa đơn có thể 500 khi suất chiếu chưa gán format — Đã sửa

- Nguyên nhân: dereference quan hệ `format` nullable.
- Ảnh hưởng: lookup/in hóa đơn thất bại dù đơn hợp lệ.
- Khắc phục: serialize format nullable bằng truy cập an toàn theo dữ liệu thực.
- File: `app/Services/OrderPrintService.php`.
- Xác minh: `OrderPrintingTest` pass 3/3.

#### H-03 — Static analysis có 27 lỗi — Đã sửa

- Nguyên nhân: dead code, nullability không khớp schema, collection callback type, aggregate dynamic attributes và optional relation handling.
- Ảnh hưởng: tăng nguy cơ runtime error, khó refactor an toàn.
- Khắc phục: xóa dead booking verifier, chuẩn hóa relation nullable, chuyển aggregate query sang query builder, sửa callback và dùng constants/guards phù hợp.
- Files chính: `TicketController.php`, `HomeController.php`, `OrderController.php`, `SeatHold.php`, `OrderPrintService.php`, `PosOrderService.php`, `UserService.php`.
- Xác minh: `composer analyse` pass 0 error.

#### H-04 — Broadcast queue không được xử lý nhưng health vẫn báo healthy — Đã sửa code/local

- Bằng chứng runtime: queue `broadcasts` có 37 job tồn, job cũ nhất khoảng 6 giờ; gồm `SeatStatusUpdated` và `OrderPaid`.
- `queue:monitor-health` vẫn trả healthy vì chỉ kiểm tra depth tối đa 100, không kiểm tra tuổi job; readiness `/api/v1/health/ready` chỉ kiểm tra database/cache.
- Ảnh hưởng: ghế và trạng thái thanh toán không cập nhật realtime, POS/client phải reload, lỗi khó được phát hiện sớm.
- Khắc phục: thêm `QueueHealthService` kiểm tra depth, tuổi ready job và expired reservation cho database/Redis; readiness trả 503 khi queue stale; queue monitor log critical và exit 1.
- Runtime local: xử lý thành công 37 event cũ, queue `broadcasts` về 0, Reverb và worker riêng đang chạy; readiness trở lại 200.
- Còn lại trước production: cấu hình supervisor/systemd theo README để tự restart Reverb/worker.

#### H-05 — Booking có thể im lặng khi trạng thái đăng nhập SSR và API lệch nhau — Đã sửa

- Browser tái hiện: header SSR vẫn hiển thị người dùng đã đăng nhập nhưng seat polling chuyển từ `user_id=1` sang `user_id=null`; click ghế không đổi trạng thái, không gọi hold API và không có feedback nhìn thấy được.
- Nguyên nhân khả dĩ: access/refresh state của API hết hạn trong khi Blade shell còn trạng thái cũ; click ghế chặn sớm bằng `authManager.isAuthenticated()`.
- Ảnh hưởng: người dùng không thể tiếp tục đặt vé và không biết cần đăng nhập lại hay tải lại trang.
- Khắc phục: refresh failure tạo lỗi `SESSION_EXPIRED`, đồng bộ UI auth, phát sự kiện `cinema:session-expired`, mở lại modal an toàn và giữ nguyên lựa chọn ghế thay vì xóa state cục bộ.
- Xác minh browser: booking tải 209 ghế, click ghế đổi ngay sang `seat-selected`, không visible alert/overflow; bundle production đã rebuild.

### Medium

#### M-01 — Truy vấn khách hàng theo số điện thoại chưa có index — Đã sửa

- Nguyên nhân: `users.phone` được lookup tại POS nhưng schema không có index.
- Ảnh hưởng: lookup chậm dần theo số lượng tài khoản.
- Khắc phục: thêm non-unique index `users_phone_lookup_idx` để không phá dữ liệu staff/customer đang trùng số.
- File: `database/migrations/2026_08_10_180000_add_users_phone_lookup_index.php`.
- Lưu ý: chiến lược unique phone cho customer cần quyết định nghiệp vụ riêng vì dữ liệu hiện có trùng giữa các vai trò.

#### M-02 — Format/Sound policy phụ thuộc auto-discovery — Đã sửa

- Nguyên nhân: policy tồn tại nhưng chưa đăng ký tường minh cùng các policy còn lại.
- Ảnh hưởng: RBAC có thể thay đổi hành vi khi convention/file namespace thay đổi.
- Khắc phục: đăng ký explicit trong `AppServiceProvider` và thêm regression test.

#### M-03 — Trang tin tức tràn ngang 12px ở desktop — Đã sửa

- Nguyên nhân: global container max-width 1320px kết hợp Bootstrap row gutter âm khi viewport nhỏ hơn max-width.
- Ảnh hưởng: horizontal scroll và layout thiếu ổn định.
- Khắc phục: giới hạn container của trang tin tức bằng gutter hai bên.
- File: `public/css/users/pages/posts-index.css`.
- Xác minh browser: `scrollWidth === clientWidth`, 10 cards, không skeleton tồn dư.

#### M-04 — Compiled Blade views đang được Git theo dõi — Đã xử lý trong working tree

- Bằng chứng: 62 file dưới `storage/framework/views` đang tracked.
- Ảnh hưởng: diff nhiễu, merge conflict, có thể commit nội dung runtime không đồng nhất.
- Khắc phục: ignore `/storage/framework/views/*.php` và clear compiled views; các deletion cần được giữ trong commit hygiene để Git ngừng theo dõi chúng.

#### M-05 — CSP vẫn cho phép inline style attributes — Còn mở

- Bằng chứng: `style-src-attr 'unsafe-inline'`; phiên bản strict mới ở report-only.
- Ảnh hưởng: giảm mức bảo vệ XSS qua style injection.
- Khắc phục đề xuất: di chuyển dynamic style còn lại sang class/CSS variable hợp lệ, theo dõi report-only rồi chuyển `style-src-attr` sang `'none'`.

#### M-06 — Service/controller quá lớn — Còn mở

- Các điểm nóng: `PaymentService` khoảng 855 dòng, `OrderService` 760, `SeatService` 698, `ScreenController` 502, `SeatLayoutTemplateController` 436.
- Ảnh hưởng: tăng coupling, review khó, test setup lớn.
- Khắc phục đề xuất: tách orchestration, gateway adapter, state transition và query object theo bounded context; không đổi public contract trong cùng một phase.

#### M-07 — Shared component adoption chưa đồng đều — Còn mở

- Hiện có shared admin modal/filter/skeleton/data-state và user toast/modal/pagination/data-region.
- `x-admin.filter-bar` mới được dùng ở ít màn hình; một số trang vẫn tự dựng filter/layout và JS lifecycle riêng.
- Ảnh hưởng: UI drift, duplicate logic, khó sửa đồng loạt.
- Khắc phục đề xuất: migrate theo nhóm trang tương tự, bắt đầu từ listing CRUD rồi statistics.

#### M-08 — Bundle quản trị và scanner còn lớn — Còn mở

- `admin-ticket-scanner-bootstrap` khoảng 130.5KB, `main` 108KB, `admin-navigation` 98.5KB trước gzip.
- Ảnh hưởng: parse/execute cost trên máy POS cấu hình thấp.
- Khắc phục đề xuất: lazy-load scanner khi mở modal, tách chart/scanner dependencies theo route, giữ cache immutable cho production assets.

#### M-09 — Runtime URL/branding chưa đúng môi trường — Còn mở

- Runtime local đang dùng `APP_URL=http://localhost` trong khi ứng dụng chạy tại `http://127.0.0.1:8000`; `APP_NAME=Laravel`.
- Ảnh hưởng: link reset mật khẩu được tạo từ queue/CLI có thể sai host/port; health/email hiển thị tên mặc định thay vì CINEMA.
- Khắc phục: bắt buộc kiểm tra `APP_URL`, `APP_NAME`, HTTPS và trusted proxy trong release checklist; thêm smoke test URL tuyệt đối của email reset.

#### M-10 — POS còn các trạng thái tương tác không rõ ràng — Còn mở

- Luồng chỉ mua bắp nước hoạt động và không bắt buộc suất chiếu/ghế.
- Khi đã chọn sản phẩm nhưng chưa chọn rạp phục vụ, nút tiếp tục không chuyển bước và không có validation nhìn thấy được.
- `.pos-ui-hidden` được khai báo trước các rule footer nên nút `Quay lại` vẫn có thể được coi là visible; modal hủy tạo hai action cùng accessible name.
- Khắc phục: validate rạp ngay tại field và focus vào lỗi; tăng specificity hoặc chuyển utility hidden xuống cuối stylesheet; chuẩn hóa accessible name của modal actions.

#### M-11 — Accessibility/client semantics chưa đồng nhất — Còn mở

- Storefront render `lang="en"` dù nội dung tiếng Việt; một checkbox permission chưa có accessible label theo browser audit.
- Icon action ở header 16px và action card tin tức 32px nhỏ hơn touch target khuyến nghị; nhiều footer/topic link vẫn dùng `href="#"`.
- Bootstrap modal POS chưa liên kết `aria-labelledby` với tiêu đề; trang phim còn trộn nhãn English/Vietnamese.
- Khắc phục: đặt locale/`lang="vi"`, bổ sung label/ARIA, tăng hit area tối thiểu 44px, thay placeholder link bằng route thật hoặc button disabled rõ nghĩa.

#### M-12 — Tooltip biểu đồ line dùng vị trí cố định — Còn mở

- Dashboard, revenue, ticket và combo trend đặt tooltip `fixed: topRight`, `followCursor: false` bên trong card/chart có giới hạn kích thước.
- Ảnh hưởng: tooltip dễ bị khuất/cắt và tạo cảm giác hover không hoạt động, nhất là card hẹp hoặc viewport nhỏ.
- Khắc phục: bỏ fixed tooltip hoặc dùng shared/follow-cursor phù hợp; thêm browser test hover điểm đầu/cuối và kiểm tra tooltip không vượt card.

#### M-13 — Dữ liệu lịch sử chưa có dấu gửi hóa đơn — Còn mở theo nghiệp vụ

- Có 28 paid order có user email nhưng `ticket_email_sent_at` null; không có đơn mới ngày audit và queue `emails` hiện rỗng/không failed.
- Phần lớn là đơn tạo trước khi bổ sung email invoice, nên không phải lỗi queue hiện tại nhưng cần quyết định có backfill hay không.
- Khắc phục: chạy dry-run/backfill có idempotency nếu nghiệp vụ yêu cầu gửi lại; tránh tự động gửi hàng loạt khi chưa được phê duyệt.

### Low

#### L-01 — Debug scripts ở project root — Đã sửa

- Đã xóa `test.php`, `test2.php`, `test_lock.php`; logic tương ứng đã có automated tests.

#### L-02 — Runtime PID file xuất hiện trong working tree — Đã sửa

- Đã ignore `/storage/framework/*.pid`.

#### L-03 — TODO chưa được đóng — Còn mở

- Google OAuth chưa triển khai.
- Quick-booking home chưa load showtime theo selection.
- Seat reservation architecture còn TODO phase tiếp theo trong `OrderService`.

## 5. Architecture và backend

- Phân lớp Controller → FormRequest/Policy → Service → Model tương đối rõ.
- Payment, order fulfillment, seat hold và expiration dùng transaction/locking/idempotency; targeted tests xác minh race-sensitive paths.
- API chuẩn hóa dưới `/api/v1`; protected routes dùng `auth:api`, management gate và theater scope.
- Một số authorization nằm ở policy/controller thay vì route middleware; hành vi hiện được test nhưng nên duy trì matrix endpoint-permission trong tài liệu.
- Order lifecycle có constants trong model; một số service còn duplicate numeric status và nên quy về constants trong phase maintainability.

## 6. Database và data integrity

- Orders, tickets, payments, idempotency keys và normalized seat-hold items có unique/index quan trọng.
- Ticket uniqueness theo `(showtime_id, seat_id)` và seat-hold active lock giúp giảm double booking.
- Orders hỗ trợ POS product-only bằng nullable `showtime_id` và `theater_id` riêng.
- Phone lookup index đã bổ sung; chưa áp unique vì cần migration dữ liệu và rule identity rõ ràng.
- EXPLAIN local xác nhận POS phone lookup dùng `users_phone_lookup_idx`; danh sách order mới nhất dùng `orders_admin_created_id_idx`; filter paid dùng `orders_admin_payment_created_id_idx`.
- Phone prefix kết hợp sort theo `created_at` vẫn có `Using filesort`; chấp nhận được với tập dữ liệu hiện tại nhưng cần query-budget/staging EXPLAIN khi dữ liệu lớn.
- 31 targeted tests cho seat locking, payment idempotency, checkout replay, POS 0đ và POS authorization đều pass.

## 7. Security và RBAC

- Không phát hiện secret đang tracked; `.env` không được commit.
- Password reset dùng generic response, token one-time và hết hạn 10 phút.
- CSP dùng nonce cho script, security headers và no-store cho API nhạy cảm đã có.
- PayOS webhook được verify ở gateway service trước khi transition order.
- RBAC hỗ trợ delegated permissions, theater scoping và chặn customer truy cập management API.
- Các regression tests đã xác minh role view không thể update role, staff không xem dữ liệu rạp khác và POS dựa trên capability thay vì hard-code role.

## 8. Frontend, UX và accessibility

- Admin dùng Turbo shell, lifecycle cleanup, AbortController, shared API cache và modal cleanup.
- Browser audit các trang dashboard, revenue, tickets, combo stats, orders, roles-permissions, POS, posts và theaters: không chart overflow, không modal kẹt, không visible error state sau load.
- Dashboard chart có semantic application/tooltip nodes; cards và charts giữ đúng parent width ở desktop 1280x720.
- Client home không horizontal overflow, ảnh load thành công sau fix API.
- Cần tiếp tục E2E responsive thực trên mobile/tablet cho booking, POS và admin table; CSS đã có breakpoint nhưng chưa có visual regression tự động.
- Touch target, focus trap, keyboard scan flow và reduced-motion nên được đưa vào automated accessibility checks.
- Browser xác nhận POS hỗ trợ product-only: chọn bắp nước, chọn rạp, chuyển payment, đổi Cash/PayOS và mở modal hủy mà không tạo order.
- Browser cũng ghi nhận validation chọn rạp còn im lặng, client `lang` sai và một số touch target/placeholder link chưa đạt chuẩn production.

## 9. Testing, observability và operations

- Test suite đã bao phủ auth identity, API security, RBAC delegation, POS authorization, zero-amount checkout, payment fulfillment, printing và seat locking.
- Queue failed jobs hiện rỗng; invoice email dùng queue riêng và không có email job pending.
- PDF đính kèm email và hóa đơn in cùng dùng `orders.partials.invoice-slip`; email được dispatch sau commit và đánh dấu `ticket_email_sent_at` sau khi SMTP send thành công.
- 11 tests payment fulfillment/printing pass; 15 tests operational health/auth identity/security headers pass.
- Sentry, slow-query logging, request ID và structured logs đã có cấu hình.
- Runtime local chưa cấu hình Sentry, metrics token và slow-query logging; đây là chấp nhận được khi develop nhưng phải fail release checklist nếu production.
- Đã có Supervisor template cho business queue, `broadcasts`, scheduler và Reverb; cần deploy và test auto-restart trên staging.
- Queue depth/age, expired reservation, failed jobs, overdue payment và unsent invoice email đã có monitor; webhook alert có cooldown cần được cấu hình bằng endpoint incident thật.
- Đã có MySQL backup/restore drill cô lập và runbook, nhưng drill chưa được chạy trên staging clone.

## 10. Browser evidence

- `/`: 8 movie cards, 3 banners từ API, 0 broken image, không overflow.
- `/admin/dashboard`: 2 charts đúng parent width, không visible skeleton sau load.
- `/admin/revenue`, `/admin/tickets`, `/admin/combos/stats`: chart width không vượt container.
- `/admin/orders`: 10 rows, không skeleton tồn dư.
- `/pos`: không visible error/dialog/skeleton sau load.
- `/posts`: 10 cards, không horizontal overflow sau remediation.
- `/theaters`: region ở state `ready`, 4 theater groups/cards, không visible error.
- Public staging smoke: 5 route x 3 viewport pass; release smoke Windows thoát server sạch, không còn treo.
- Home media: URL upload đã chuyển sang same-origin relative path, ảnh mẫu load thành công và không còn CSP error.
- Booking movie detail → showtime → seat map: sau remediation trang tải 209 ghế, click ghế cập nhật `seat-selected` ngay và không visible error/overflow.
- POS product-only: chọn combo, chọn rạp, đến bước payment và đổi Cash/PayOS thành công; không tạo order trong audit.
- Audit DOM 8 màn hình: không horizontal overflow, duplicate ID hoặc ảnh visible thiếu alt; còn các issue touch target, `lang`, label và placeholder link nêu tại M-11.

## 11. Production readiness decision

Có thể triển khai staging/ UAT ngay sau khi chạy migration phone index và full regression. Production go-live nên yêu cầu thêm:

1. Dọn tracked compiled views trong commit riêng.
2. Deploy Supervisor template, cấu hình alert webhook và chạy backup restore drill trên staging.
3. Chạy E2E đa viewport cho booking/payment/POS/RBAC.
4. Load test seat hold, order creation, PayOS webhook và dashboard reporting.
5. Hoàn tất CSP style migration hoặc chấp nhận rủi ro có thời hạn, có owner.
6. Deploy queue-age/operations monitor và broadcasts worker dưới process manager; xác minh auto-restart trên staging.
7. Chạy auth-expiry full booking E2E và concurrency probe trên staging; cấu hình đúng `APP_URL` trước khi thử email reset mật khẩu.
