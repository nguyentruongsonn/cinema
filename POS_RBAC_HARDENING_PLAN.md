# POS And Multi-Role RBAC Hardening Plan

## 1. Mục tiêu

Hoàn thiện POS thành luồng bán hàng tại quầy an toàn, nhất quán và có thể vận hành thực tế; sau đó đồng bộ giao diện, quyền, API và phạm vi dữ liệu cho toàn bộ vai trò trong hệ thống.

Thứ tự bắt buộc:

1. Đóng lỗ hổng truy cập và IDOR của POS.
2. Hoàn thiện giữ ghế, tạo đơn, thanh toán và phát hành vé tại quầy.
3. Chuẩn hóa khách vãng lai, tích điểm, giảm giá và tồn kho.
4. Đồng bộ role, permission, trang đích, menu và API.
5. Hoàn thiện UI theo từng vai trò.
6. Bổ sung test, quan sát hệ thống và quy trình rollout.

## 1.1. Trạng thái triển khai

Đã hoàn thành trong đợt triển khai hiện tại:

- Đã khóa POS bằng `auth:api`, role, permission, theater scope và rate limit.
- Đã kiểm tra policy trên đơn POS cho xem, hủy và xác nhận tiền mặt.
- Đã tách `actor` (nhân viên) khỏi `customer` (chủ đơn, voucher, điểm và vé).
- Đã lưu `theater_id`, `served_by_user_id`, `source` và `payment_method` trên `orders`.
- Đã đồng bộ giữ ghế khi hoàn tất, hủy, hết hạn hoặc gateway lỗi; có hỗ trợ `seat_hold_user_id`.
- Đã hoàn kho cả sản phẩm lẻ và thành phần combo khi checkout thất bại/hết hạn.
- Đã loại bỏ việc tạo PayOS link trùng trong POS.
- Đã bổ sung regression test cho POS authorization và product-only order.
- Đã thêm shell/landing và API riêng cho `ticket_checker` và `concession_staff`.
- Scanner ticket đã giới hạn theo rạp được phân công và dùng endpoint staff riêng.
- Đã triển khai fulfillment concession: trạng thái dòng hàng, nhân viên xử lý, thời điểm giao, phân trang và chống xử lý lặp.
- Đã kiểm tra browser trực tiếp bằng Edge với các trang public: `/`, `/movies`, `/posts`, `/theaters`, `/prices` đều trả HTTP 200 ở mobile viewport.
- Đã thêm `npm run test:browser:roles`, nhận session test qua `BROWSER_TEST_SESSIONS` để không lưu credential trong mã nguồn.

Kiểm tra đã đạt: `244 tests passed`, build frontend, security gate, syntax gate và route/controller integrity.

Các hạng mục còn lại: refactor toàn bộ inline style POS theo design system, browser E2E có session đăng nhập thật và rollout production.

## 2. Nguyên tắc kiến trúc

- POS dùng chung `PricingService`, `SeatService`, `PaymentService`, `OrderFulfillmentService` và `OrderExpirationService` với luồng online.
- Không sao chép logic giá, giữ ghế, tồn kho hoặc hoàn tất đơn sang controller POS.
- Phân biệt rõ:
  - `actor`: nhân viên đang thao tác.
  - `customer`: người sở hữu đơn, vé và điểm thành viên.
  - `theater`: phạm vi vận hành của giao dịch.
- Route middleware chỉ là lớp bảo vệ đầu tiên. Controller, policy và query vẫn phải xác minh quyền trên từng tài nguyên.
- Không tin `theater_id`, giá, giảm giá, điểm, tổng tiền hoặc trạng thái do frontend gửi lên.
- Mọi thao tác tài chính, giữ ghế, tồn kho, điểm và voucher phải chạy trong transaction và có row lock phù hợp.
- Tạo đơn, xác nhận tiền mặt, hủy đơn, webhook và phát hành vé phải idempotent.
- UI chỉ hiển thị chức năng có quyền; API vẫn phải từ chối nếu gọi trực tiếp.
- Không inline CSS, inline event handler hoặc tạo thêm pattern toast/modal/loading riêng.

## 3. Trạng thái hiện tại cần xử lý

### Critical

- API `/api/v1/pos/*` mới yêu cầu `auth:api` và `theater.scope`, chưa bắt buộc role/permission POS.
- `getOrder`, `cancelOrder`, `checkPaymentStatus` chưa kiểm tra đơn thuộc rạp của nhân viên.
- `confirmCash` có thể bỏ qua phạm vi rạp với đơn chỉ có sản phẩm.
- POS chọn ghế cục bộ nhưng không tạo seat hold; `PaymentService` lại bắt buộc seat hold.
- `PaymentService` đang nhận nhân viên làm user trước khi đổi chủ đơn sang khách; điểm và voucher có nguy cơ áp dụng sai tài khoản.

### High

- Có hai bộ POS controller/request cùng tồn tại.
- Permission web, API admin và `adminLandingRouteName()` chưa đồng bộ cho `ticket_checker` và `concession_staff`.
- API admin đang khóa theo role rộng thay vì bảo vệ theo capability/permission của endpoint.
- Metadata POS như `staff_id`, `served_by_user_id`, phương thức thanh toán và loại khách chủ yếu nằm trong JSON payload.
- Combo chưa được trình bày đầy đủ trong chi tiết đơn POS.

### Medium

- POS có lifecycle và UI component riêng, chưa theo pattern toast/modal/loading của hệ thống.
- Frontend security gate còn phát hiện inline event handler.
- Một số thông báo tiếng Việt bị hỏng encoding.
- Chưa có browser E2E đầy đủ cho giao dịch POS.

## 4. Kiến trúc đích

```text
POS UI
  -> POS API middleware
      -> auth:api
      -> role/permission
      -> theater.scope
      -> throttle
  -> POS Controller mỏng
  -> PosOrderService
      -> SeatService
      -> PricingService
      -> PaymentService
      -> OrderFulfillmentService
  -> MySQL transaction + row locks
  -> Audit log + mail + broadcast
```

Luồng dữ liệu chuẩn:

```text
actor(ticket_seller) + customer + theater + cart
  -> validate scope
  -> reserve seat/product/voucher/points
  -> create pending order
  -> cash confirmation OR PayOS QR
  -> fulfill exactly once
  -> issue tickets + loyalty + audit + email
```

## 5. Phase 0 — Đóng băng hợp đồng và dọn cấu trúc POS

### Công việc

- Chọn một implementation duy nhất:
  - `App\Http\Controllers\Pos\PosController`
  - `App\Http\Requests\Pos\*`
  - `App\Services\Pos*`
- Xóa sau khi xác minh không còn tham chiếu:
  - `App\Http\Controllers\Api\PosController`
  - `App\Http\Requests\PosCreateOrderRequest`
  - `App\Http\Requests\PosLookupCustomerRequest`
- Chuẩn hóa route và response contract của POS.
- Thêm API Resource cho:
  - showtime POS;
  - seat map POS;
  - customer lookup;
  - cart quotation;
  - order detail;
  - payment status.
- Thống nhất error shape với API chung: `success`, `message`, `errors`, `request_id`.

### Kết quả

- Một controller/request tree duy nhất.
- Không còn class chết hoặc hợp đồng API trùng lặp.
- OpenAPI phản ánh đầy đủ endpoint POS.

## 6. Phase 1 — Hardening truy cập POS

### 6.1 Route middleware

Áp dụng cho toàn bộ group POS:

- `auth:api`
- `role:ticket_seller,admin,super-admin`
- permission cụ thể theo endpoint
- `theater.scope`
- rate limiter `pos`

Permission đề xuất:

| Endpoint | Permission |
|---|---|
| Xem suất chiếu | `showtimes.view` |
| Xem sơ đồ ghế | `seats.view_status` |
| Giữ/nhả ghế | `booking.hold_seats`, `booking.release_seats` |
| Tra cứu khách | `customers.lookup` |
| Tạo khách vãng lai | `customers.create_walk_in` |
| Báo giá | `pricing.quote_pos` |
| Tạo đơn | `orders.create` và `booking.create_order` |
| Xác nhận tiền mặt | `payments.process_cash` |
| Tạo PayOS QR | `payments.process` |
| Hủy đơn | `orders.cancel` |
| Xem đơn | `orders.view_theater` |

### 6.2 Resource authorization

- Tạo `PosOrderPolicy` hoặc mở rộng `OrderPolicy` với:
  - `viewAtPos`;
  - `cancelAtPos`;
  - `confirmCash`;
  - `checkPaymentStatus`.
- Mọi method nhận order phải kiểm tra:
  - đơn có `source = pos`;
  - actor có permission;
  - đơn thuộc một rạp actor được phân công;
  - trạng thái hiện tại cho phép thao tác;
  - actor không tự ý thao tác đơn của rạp khác.
- Đơn chỉ có bắp nước vẫn phải có `theater_id`, không được suy ra duy nhất từ showtime.
- Không trả model thô; chỉ trả resource đã giới hạn field.

### 6.3 Theater scope

- Không chỉ gắn `actor_theater_ids` vào request; bắt buộc service/query sử dụng scope đó.
- Tạo reusable query scope như `Order::forTheaters($ids)` và `Showtime::forTheaters($ids)`.
- Admin/super-admin có thể bypass scope nhưng vẫn phải có permission endpoint.
- Ghi audit khi truy cập bị từ chối do sai phạm vi rạp.

### Test bắt buộc

- Customer không gọi được bất kỳ API POS nào.
- Ticket checker và concession staff không gọi được API bán vé.
- Ticket seller không xem/hủy/xác nhận đơn rạp khác.
- Đơn chỉ có sản phẩm vẫn bị scope theo rạp.
- ID tuần tự không thể dùng để dò đơn.

## 7. Phase 2 — Hoàn thiện giữ ghế POS

### Quyết định UX

- Khi nhân viên bấm ghế: chỉ chọn cục bộ để UI phản hồi ngay.
- Khi bấm `Tiếp tục`: gửi một request giữ toàn bộ danh sách ghế trong transaction.
- Không gọi giữ ghế cho từng lần click.
- Nếu quay lại sửa ghế: thay thế hold cũ bằng hold mới theo một thao tác atomic.
- Hiển thị countdown và trạng thái mất hold rõ ràng.

### Backend

- Tạo endpoint POS hold/release hoặc tái sử dụng endpoint ghế hiện tại với policy POS.
- `SeatService::lock()` phải nhận actor và showtime, kiểm tra actor thuộc rạp của showtime.
- Lưu `seat_hold_id` vào state frontend và gửi khi tạo đơn.
- `PosOrderService` không tự bỏ qua seat hold.
- Thời gian giữ ghế POS cấu hình riêng nếu cần, ví dụ 10 phút.
- Khi POS đóng tab, đổi showtime, hủy giao dịch hoặc hết hạn: release hold an toàn.
- Khi request timeout: frontend truy vấn trạng thái hold trước khi thử lại.

### Chống cạnh tranh

- Lock seat hold, seat hold item và order item theo thứ tự cố định.
- Unique constraint ngăn hai active hold trên cùng showtime/seat.
- Tạo đơn phải xác minh danh sách ghế đúng hoàn toàn với hold.
- Fulfillment chỉ consume đúng hold gắn với đơn.

### Test bắt buộc

- Hai quầy chọn cùng ghế: chỉ một quầy giữ thành công.
- Retry request giữ ghế không tạo hold trùng.
- Hold hết hạn không tạo đơn được.
- Đổi danh sách ghế thay thế hold đúng cách.
- Đơn thành công giải phóng hold nhưng giữ trạng thái booked.

## 8. Phase 3 — Tách actor và customer trong checkout

### Thay đổi domain

- Refactor payment initiation để nhận một checkout context:
  - `actor`;
  - `customer`;
  - `source`;
  - `theater_id`;
  - `payment_method`;
  - `idempotency_key`.
- `actor` dùng cho authorization và audit.
- `customer` dùng cho:
  - `orders.user_id`;
  - voucher;
  - loyalty point;
  - ticket ownership;
  - email nhận vé.
- Không tạo order bằng staff rồi đổi `user_id` sau khi đã reserve điểm/voucher.

### Database đề xuất

- Thêm `orders.theater_id` bắt buộc cho đơn POS.
- Thêm `orders.served_by_user_id` nullable, foreign key tới users.
- Thêm `orders.source` dạng string/enum: `web`, `pos`, `guest`.
- Thêm hoặc chuẩn hóa `orders.payment_method` thay vì chỉ lưu trong payload.
- Giữ payload cho snapshot không quan trọng tới truy vấn nghiệp vụ.
- Index đề xuất:
  - `(theater_id, created_at)`;
  - `(served_by_user_id, created_at)`;
  - `(source, status, created_at)`;
  - `(payment_status, expires_at)`.

### Migration an toàn

- Backfill `theater_id` từ `showtime -> screen -> theater`.
- Backfill `source` từ prefix code/payload.
- Chỉ chuyển cột thành non-null sau khi kiểm tra dữ liệu cũ.
- Down migration không được làm mất dữ liệu nếu bản ghi guest/POS có credential nullable.

## 9. Phase 4 — Báo giá và tạo đơn POS

### Quote endpoint

- Thêm `POST /api/v1/pos/quotes`.
- Server trả về:
  - tiền ghế;
  - tiền sản phẩm/combo;
  - phụ thu;
  - giảm giá sinh viên/trẻ em/người cao tuổi nếu hợp lệ;
  - voucher discount;
  - point discount;
  - tổng tiền;
  - điểm dự kiến nhận;
  - cảnh báo tồn kho/hold.
- Quote có thời hạn ngắn và fingerprint để phát hiện cart đã thay đổi.
- Server tính lại toàn bộ khi tạo đơn; không dùng tổng tiền frontend.

### Validation nghiệp vụ

- Có showtime thì phải có ít nhất một ghế, trừ khi nghiệp vụ cho phép bán bắp nước kèm suất mà không có vé.
- Đơn concession-only phải có `theater_id` rõ ràng.
- Student/child/senior discount phải có rule và bằng chứng theo chính sách vận hành; không chỉ tin dropdown.
- Combo phải reserve tồn kho theo từng sản phẩm con.
- Số lượng tối đa trên mỗi dòng và toàn giỏ phải có giới hạn.

### Idempotency

- Frontend tạo UUID cho mỗi lần bấm thanh toán.
- Cùng key + cùng payload trả lại cùng kết quả.
- Cùng key + payload khác trả 409.
- Double-click không tạo hai đơn, hai payment hoặc hai lần trừ tồn kho.

## 10. Phase 5 — Thanh toán tiền mặt, PayOS QR và đơn 0 đồng

### Tiền mặt

- Tạo đơn pending trước khi mở modal nhận tiền.
- Xác nhận tiền mặt là endpoint riêng, idempotent.
- Chỉ actor có `payments.process_cash` và đúng rạp được xác nhận.
- Lưu số tiền khách đưa và tiền thối nếu nghiệp vụ yêu cầu.
- Không cho sửa giỏ sau khi đơn pending đã được tạo.

### PayOS QR

- Tạo payment link một lần sau khi order commit.
- Polling có backoff, timeout và dừng khi rời trang.
- Webhook là nguồn xác nhận chính; polling chỉ reconcile trạng thái.
- Không tự đánh dấu paid từ dữ liệu frontend.
- Hủy QR phải phân biệt hủy link và hủy đơn.

### Đơn 0 đồng

- Không tạo PayOS link.
- Payment method là `zero_amount`.
- Fulfillment chạy ngay, đúng một lần.
- UI chuyển thẳng đến màn hình thành công.

### Hoàn tất đơn

- `OrderFulfillmentService` phải đảm bảo:
  - payment success;
  - order confirmed;
  - ticket phát hành một lần;
  - tồn kho trừ một lần;
  - voucher dùng một lần;
  - điểm trừ/cộng một lần;
  - seat hold consume một lần;
  - email và broadcast phát sau commit.

## 11. Phase 6 — Khách vãng lai và loyalty

### Khách vãng lai

- Cho phép giao dịch không gắn customer account nếu không có số điện thoại.
- Không dùng tài khoản staff làm chủ đơn fallback.
- Nếu có số điện thoại:
  - tra cứu customer đang hoạt động;
  - tạo `unclaimed` customer nếu chưa tồn tại và có đủ thông tin tối thiểu;
  - không tạo email/password giả.
- Khi đăng ký online bằng cùng số điện thoại, claim tài khoản qua OTP hoặc quy trình xác minh, không chỉ dựa vào phone trùng.

### Loyalty

- Reserve điểm trên customer, không phải actor.
- Không cho tài khoản unclaimed dùng điểm trước khi xác minh nếu chính sách yêu cầu.
- Lịch sử điểm có unique/idempotency reference theo order + type.
- Khi đơn fail/expire/cancel phải hoàn điểm đã reserve đúng một lần.
- Không cộng điểm cho đơn của staff fallback hoặc khách không đăng ký.

### Voucher

- Kiểm tra voucher thuộc customer và còn hiệu lực.
- Reserve usage trong transaction.
- Release reservation khi đơn hết hạn/hủy.
- Không áp dụng voucher customer cho đơn guest không xác minh.

## 12. Phase 7 — UI POS production-ready

### Cấu trúc màn hình

1. Chọn suất chiếu.
2. Chọn ghế.
3. Chọn khách hàng.
4. Chọn sản phẩm/combo.
5. Xem lại và thanh toán.
6. Kết quả, in vé hoặc tạo giao dịch mới.

### Pattern bắt buộc

- Dùng chung toast, modal, confirm dialog và data-state của hệ thống.
- Skeleton chỉ áp dụng cho vùng dữ liệu con, giữ nguyên shell và stepper.
- Modal tiền mặt/QR dùng chung component modal admin.
- Không inline handler; dùng event delegation hoặc listener có cleanup.
- Abort request cũ khi đổi ngày/suất chiếu.
- Không render raw error message bằng `innerHTML`.
- Nút thanh toán disabled trong khi request đang chạy.
- Hiển thị rõ:
  - loading;
  - empty;
  - stale quote;
  - hold expired;
  - payment pending;
  - payment failed;
  - success.

### Hiệu năng

- Cache danh mục sản phẩm/combo ngắn hạn theo rạp.
- Prefetch seat map khi hover/focus suất chiếu nếu phù hợp.
- Render seat map theo fragment, không dựng lại toàn bộ SVG khi đổi một ghế.
- Không tạo gradient ID ngẫu nhiên trên mỗi render.
- Chỉ poll payment khi modal QR đang mở.
- Cleanup timer, listener và modal khi Turbo/pagehide.

### Accessibility

- Ghế là button có accessible name và trạng thái `aria-pressed`/`aria-disabled`.
- Stepper có trạng thái hiện tại bằng ARIA.
- Modal quản lý focus đúng chuẩn.
- Touch target tối thiểu 44px.
- Có thể hoàn thành giao dịch bằng bàn phím.

## 13. Phase 8 — Đồng bộ role, permission, route và API

### Ma trận giao diện đích

| Vai trò | Trang đích | Giao diện chính |
|---|---|---|
| `super-admin` | `/admin/dashboard` | Toàn hệ thống, gồm phân quyền |
| `admin` | `/admin/dashboard` | Toàn hệ thống theo permission được phép |
| `theater_manager` | `/admin/dashboard` | Admin scoped theo rạp |
| `ticket_seller` | `/pos` | POS bán vé và thanh toán |
| `ticket_checker` | `/staff/ticket-check` | Quét/xác minh vé |
| `concession_staff` | `/staff/concessions` | Giao bắp nước và bán concession nếu được phép |
| `customer` | `/` hoặc `/profile` | Storefront và vé của tôi |
| guest | `/` | Storefront, không có management UI |

### Permission-first routing

- Không dùng một API admin group khóa toàn bộ theo ba role.
- Tách route theo capability:
  - analytics;
  - catalog management;
  - theater operations;
  - user/RBAC management;
  - POS;
  - ticket control;
  - concession fulfillment.
- Mỗi endpoint có permission middleware và policy tương ứng.
- Role chỉ cung cấp permission mặc định; database permission là nguồn quyết định runtime.
- `admin`/`super-admin` có wildcard theo chính sách đã thống nhất.

### Trang đích

- Thay `adminLandingRouteName()` bằng resolver kiểm tra cả:
  - role shell;
  - permission;
  - route tồn tại;
  - page middleware tương ứng.
- Không được redirect tới chính trang vừa bị từ chối.
- Có fallback an toàn về storefront và thông báo một lần.
- Viết test chống redirect loop.

### Sidebar và header

- Header chỉ hiển thị `Giao diện quản lý` nếu có management destination hợp lệ.
- Ticket seller hiển thị `Mở POS`.
- Ticket checker hiển thị `Kiểm soát vé`.
- Concession staff hiển thị `Quầy bắp nước`.
- Sidebar admin chỉ dành cho admin/manager; staff dùng shell tối giản theo tác vụ.
- Tất cả shell đều có profile, đổi mật khẩu và đăng xuất.

## 14. Phase 9 — Giao diện theo từng vai trò

### Admin và super-admin

- Quản lý đầy đủ hệ thống.
- Super-admin quản lý quyền admin và tài khoản đặc quyền.
- Audit mọi thay đổi role/permission.

### Theater manager

- Dashboard, doanh thu, đơn, lịch chiếu, phòng, nhân viên theo rạp được gán.
- Không xem dữ liệu rạp khác qua filter, export hoặc ID trực tiếp.
- Chỉ tạo/cập nhật staff thuộc các role được phép.
- Không nâng quyền thành admin/manager ngoài phạm vi.

### Ticket seller

- Chỉ thấy POS, lịch sử giao dịch của quầy/rạp nếu có permission.
- Không thấy admin sidebar.
- Không truy cập API catalog management.

### Ticket checker

- Màn hình scanner riêng.
- Chỉ nhập `ticket_code` hoặc scan barcode/QR.
- Hiển thị thông tin tối thiểu cần thiết.
- Mark used atomic, chống scan đồng thời và có âm thanh success/failure.

### Concession staff

- Danh sách order cần giao theo rạp.
- Xem dòng sản phẩm/combo và trạng thái fulfillment.
- Xác nhận giao hàng idempotent.
- Không xem dữ liệu vé/thanh toán không cần thiết.

### Customer

- Storefront, hồ sơ, voucher, điểm, đơn và vé của chính mình.
- Không xuất hiện management link.

### Guest

- Chỉ storefront và flow được công khai.
- Nếu hỗ trợ guest checkout, dùng signed lookup token/OTP; không mở endpoint order theo ID.

## 15. Phase 10 — Audit log và observability

### Audit bắt buộc

- POS order created/cancelled.
- Cash confirmed.
- PayOS QR created/cancelled/reconciled.
- Seat hold created/replaced/released/expired.
- Loyalty redeemed/earned/restored.
- Voucher reserved/used/restored.
- Ticket issued/verified/used.
- Concession fulfilled.
- Role, permission và theater assignment thay đổi.

Audit context gồm:

- `request_id`;
- actor ID;
- customer ID nếu có;
- theater ID;
- order/payment/ticket ID;
- action;
- old/new snapshot đã redacted;
- IP và user agent theo chính sách bảo mật.

### Metrics đề xuất

- POS order success/failure rate.
- Thời gian từ chọn thanh toán đến hoàn tất.
- Seat hold conflict/expiry rate.
- Cash confirmation rate.
- PayOS webhook latency và reconcile count.
- Fulfillment retry count.
- Authorization denial theo role/endpoint.

## 16. Test strategy

### Unit

- Permission resolver.
- Theater scope query.
- POS pricing context.
- Loyalty/voucher reservation.
- Order state transitions.
- POS resource serialization.

### Feature

- Toàn bộ endpoint theo role.
- Cross-theater IDOR.
- Seat hold concurrency.
- Cash, QR và zero-amount checkout.
- Product-only và combo-only order.
- Tồn kho combo.
- Customer claim và loyalty.
- Cancel/expire restore resources.
- Fulfillment idempotency.
- Audit log redaction.

### Browser E2E

- Ticket seller đăng nhập và vào đúng POS.
- Chọn suất, ghế, khách, combo, thanh toán tiền mặt.
- PayOS QR pending -> success.
- Ghế bị quầy khác giữ.
- Hold hết hạn.
- Double-click thanh toán.
- Ticket checker scan thành công/thất bại.
- Concession staff xác nhận giao hàng.
- Manager không thấy dữ liệu rạp khác.
- Customer không thấy management link.

### Quality gates

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
```

## 17. Rollout

### Giai đoạn 1 — Shadow mode

- Deploy schema và backend mới nhưng chưa mở POS cho nhân viên.
- Backfill và kiểm tra theater/source/served-by.
- So sánh báo giá POS mới với pricing engine hiện tại.

### Giai đoạn 2 — Pilot một rạp

- Bật feature flag cho một rạp và một số ticket seller.
- Theo dõi lỗi giữ ghế, thanh toán, tồn kho và audit.
- Có runbook xử lý order pending, webhook chậm và máy quét/in vé.

### Giai đoạn 3 — Mở rộng

- Mở theo từng rạp.
- Không mở concession/ticket-checker UI trước khi permission và scope test đạt.
- Giữ khả năng tắt POS bằng feature flag mà không ảnh hưởng storefront.

## 18. Thứ tự file dự kiến thay đổi

### Backend nền tảng

- `config/rbac.php`
- `bootstrap/app.php`
- `routes/api/pos.php`
- `routes/api/admin.php`
- `routes/web.php`
- `app/Models/User.php`
- `app/Models/Order.php`
- `app/Policies/OrderPolicy.php`
- `app/Http/Middleware/TheaterScopeMiddleware.php`
- `app/Http/Middleware/PosAccessMiddleware.php`

### POS domain

- `app/Http/Controllers/Pos/PosController.php`
- `app/Http/Requests/Pos/*`
- `app/Http/Resources/Pos/*`
- `app/Services/PosOrderService.php`
- `app/Services/PosCustomerService.php`
- `app/Services/PaymentService.php`
- `app/Services/PricingService.php`
- `app/Services/SeatService.php`
- `app/Services/OrderFulfillmentService.php`
- `app/Services/OrderExpirationService.php`

### Database

- Migration thêm `theater_id`, `served_by_user_id`, `source`, `payment_method` cho orders.
- Migration/index cho loyalty idempotency nếu cần.
- Seeder đồng bộ role/permission.

### Frontend

- `resources/views/pos/index.blade.php`
- `public/js/pos/*`
- `public/css/pos/pos.css`
- Shared toast/modal/data-state component.
- Staff shell và trang ticket-check/concessions mới.

### Test

- `tests/Feature/Pos/*`
- `tests/Feature/Rbac/*`
- `tests/Feature/Payment/*`
- Browser regression scripts.

## 19. Definition of Done

POS chỉ được xem là hoàn thành khi:

- Không endpoint POS nào truy cập được bằng customer hoặc role không hợp lệ.
- Không thể xem hoặc thao tác đơn/rạp khác bằng cách đổi ID.
- Chọn ghế và tạo đơn POS dùng seat hold đúng chuẩn.
- Actor và customer không bị nhầm trong order, voucher hoặc loyalty.
- Cash, PayOS QR và đơn 0 đồng đều idempotent.
- Combo và sản phẩm reserve/restore tồn kho chính xác.
- Ticket chỉ phát hành một lần và gửi đúng email customer.
- Mọi role đi tới đúng giao diện, không redirect loop và không gặp API 403 do cấu hình lệch.
- Sidebar/menu/action phản ánh permission nhưng API vẫn bảo vệ độc lập.
- Audit log đủ actor, customer, theater và resource.
- Không còn implementation POS trùng lặp.
- Frontend security, syntax, lint, build, PHP test và browser E2E đều đạt.
- Có pilot production và runbook vận hành trước khi mở toàn hệ thống.

## 20. Thứ tự triển khai đề xuất

1. Phase 0 và Phase 1: dọn cấu trúc, khóa route và IDOR.
2. Phase 2: seat hold POS.
3. Phase 3: actor/customer và migration order.
4. Phase 4 và Phase 5: quote, checkout, cash/QR/zero amount.
5. Phase 6: guest customer, loyalty và voucher.
6. Phase 7: hoàn thiện UI POS.
7. Phase 8 và Phase 9: đồng bộ RBAC và UI theo vai trò.
8. Phase 10: audit, metrics, full test và rollout.
