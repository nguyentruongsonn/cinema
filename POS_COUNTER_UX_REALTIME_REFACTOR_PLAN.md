# Kế hoạch refactor POS tại quầy và đồng bộ ghế realtime

## 1. Mục tiêu

Hoàn thiện luồng bán vé tại quầy theo hướng nhanh, rõ ràng, an toàn và phù hợp với nghiệp vụ rạp chiếu phim:

- Không bắt buộc khách vãng lai cung cấp số điện thoại.
- Chỉ tra cứu số điện thoại khi khách muốn nhận diện tài khoản, tích điểm hoặc dùng ưu đãi cá nhân.
- Phân loại đối tượng trên từng vé, không áp dụng một loại khách cho toàn bộ đơn.
- Vé sinh viên chỉ được hưởng giá ưu đãi sau khi nhân viên xác nhận đã xem thẻ sinh viên.
- Vé trẻ em và người cao tuổi do nhân viên xác nhận theo chính sách tại quầy, không bắt buộc lưu giấy tờ.
- Cho phép bỏ từng vé ngay trong cột thanh toán.
- Nhả ghế và cập nhật màu ghế ngay lập tức khi quay lại hoặc hủy giao dịch.
- Đồng bộ trạng thái ghế giữa nhiều máy POS và kênh đặt vé online theo thời gian thực.
- Refactor giao diện POS theo design pattern hiện có, ưu tiên màn hình quầy bán vé và thao tác cảm ứng.

## 2. Kết quả rà soát hiện trạng

### 2.1 Khách vãng lai chưa hoạt động đúng

- Giao diện ghi số điện thoại là tùy chọn, nhưng `PosController::createOrder()` vẫn trả `422` nếu không tìm thấy một `User` khách hàng.
- `PosOrderService::createPosOrder()` bắt buộc nhận `User $customer` vì `PaymentService`, đơn hàng và vé hiện đang phụ thuộc `user_id`.
- Không được dùng tài khoản nhân viên làm chủ sở hữu đơn; nhân viên chỉ là người thực hiện giao dịch.

### 2.2 Phân loại khách đang sai cấp nghiệp vụ

- Request hiện chỉ có `customer_type` ở cấp đơn.
- Frontend dùng một công tắc sinh viên và áp dụng giảm giá cho tất cả ghế.
- Một đơn có nhiều người khác nhau chưa thể định giá đúng từng vé.
- Việc xác nhận đã xem thẻ sinh viên chưa được kiểm tra ở backend và chưa có audit log.

### 2.3 Hủy giữ ghế chưa đồng bộ UI

- Backend đã phát `SeatStatusUpdated` khi giữ, nhả và hết hạn ghế.
- Trang booking online đã subscribe kênh `showtime.{id}`, nhưng POS chưa subscribe.
- API nhả ghế chỉ trả `unlocked_count`; frontend không nhận danh sách ghế authoritative để cập nhật ngay.
- `pos-app.js` xóa `currentHoldId` và hiện toast nhưng chưa xóa/reconcile đầy đủ state trong `PosSeat` và `PosCart`.

### 2.4 Cột thanh toán chưa quản lý từng vé

- `pos-payment-right` chỉ render dòng ghế tĩnh.
- Chưa có loại đối tượng, trạng thái xác minh, giá vé cuối cùng và nút xóa trên từng dòng.
- Tổng tiền sinh viên hiện được suy ra từ một cờ toàn đơn nên không phù hợp với đơn hỗn hợp.

## 3. Quyết định kiến trúc

### 3.1 Khách vãng lai không cần số điện thoại

Chọn phương án **tài khoản hệ thống “Khách vãng lai” theo rạp** để tương thích an toàn với các khóa ngoại `orders.user_id`, `tickets.user_id` và luồng `PaymentService` hiện tại.

- Mỗi rạp có đúng một system customer dành cho giao dịch ẩn danh.
- Thêm khóa nhận diện bất biến, ví dụ `users.system_key = pos_guest:{theater_id}`, unique và nullable.
- Tài khoản này không có email, số điện thoại hoặc mật khẩu đăng nhập.
- Không được đăng nhập, tích điểm, dùng điểm, nhận voucher cá nhân hoặc xuất hiện như tài khoản khách thông thường.
- `served_by_user_id` tiếp tục lưu nhân viên bán vé; tuyệt đối không dùng nhân viên làm `user_id` của đơn.
- Đơn vẫn lưu snapshot `customer_mode`, tên hiển thị và thông tin thành viên nếu có trong payload/audit.

Không chọn nullable `orders.user_id` trong phase này vì sẽ kéo theo thay đổi lớn ở payment, ticket, email, policy và báo cáo. Có thể xem xét ở một migration domain lớn sau khi POS ổn định.

### 3.2 Hợp đồng dữ liệu vé mới

Thay `seat_ids + customer_type` bằng danh sách vé có cấu trúc:

```json
{
  "tickets": [
    {
      "seat_id": 101,
      "audience_type": "adult",
      "student_card_verified": false
    },
    {
      "seat_id": 102,
      "audience_type": "student",
      "student_card_verified": true
    }
  ]
}
```

Các loại hỗ trợ ban đầu:

- `adult`: người lớn.
- `student`: học sinh/sinh viên, bắt buộc nhân viên xác nhận đã xem thẻ hợp lệ.
- `child`: trẻ em, nhân viên phân loại theo chính sách độ tuổi/chiều cao của rạp.
- `senior`: người cao tuổi, nhân viên xác nhận trực tiếp tại quầy.

Backend không tin giá do frontend gửi. Giá từng vé phải được tính lại bằng `TicketPricingService`/`PricingService` theo suất chiếu, loại ghế, ngày giờ, định dạng phim và đối tượng.

### 3.3 Xác minh đối tượng ưu đãi

- Sinh viên: UI bắt buộc chọn “Đã kiểm tra thẻ sinh viên” trước khi cho thanh toán.
- Backend trả `422` nếu vé `student` thiếu `student_card_verified=true`.
- Chỉ lưu `verified_by_user_id`, `verified_at`, loại đối tượng và policy version trong metadata/audit.
- Không chụp, tải lên hoặc lưu số thẻ sinh viên để giảm rủi ro dữ liệu cá nhân.
- Trẻ em/người cao tuổi: không yêu cầu giấy tờ; lưu nhân viên đã phân loại và thời điểm phân loại.
- Nội dung hướng dẫn chiều cao/độ tuổi lấy từ cấu hình chính sách, không hard-code vào JavaScript.

### 3.4 Đồng bộ giữ ghế

Tận dụng `SeatStatusUpdated` hiện có và bổ sung response authoritative:

- Mỗi thay đổi giữ ghế trả `showtime_id`, `hold_id`, `seat_ids`, `released_seat_ids`, `held_until` và `state_version`.
- Event broadcast bổ sung `state_version`, `hold_id` hoặc `owner_key` không chứa PII.
- POS subscribe `showtime.{showtimeId}` khi mở sơ đồ ghế và unsubscribe khi đổi suất, reset hoặc rời trang.
- Response HTTP là nguồn cập nhật tức thời cho chính máy đang thao tác; broadcast dùng để đồng bộ các máy khác.
- Nếu WebSocket mất kết nối, fallback polling 3–5 giây chỉ chạy khi panel ghế đang hiển thị và tab trình duyệt đang active.

## 4. Luồng UX đích

### 4.1 Chọn khách hàng

Mặc định giao dịch ở chế độ **Khách vãng lai**:

- Không hiện validation bắt nhập số điện thoại.
- Có hai lựa chọn rõ ràng: `Khách vãng lai` và `Thành viên`.
- Chỉ khi chọn `Thành viên` mới hiện ô số điện thoại, nút tra cứu và thông tin điểm.
- Nếu không tìm thấy thành viên, nhân viên có thể tạo khách chưa kích hoạt khi khách đồng ý cung cấp thông tin.
- Loyalty, dùng điểm và ưu đãi cá nhân bị khóa ở chế độ khách vãng lai.

### 4.2 Chọn và phân loại từng vé

Sau khi chọn ghế, cột giỏ hàng hiển thị một dòng cho mỗi vé:

- Mã ghế và loại ghế.
- Dropdown/segmented control loại đối tượng.
- Giá vé sau khi server quote.
- Trạng thái xác minh sinh viên nếu áp dụng.
- Nút `X` có accessible label, ví dụ “Bỏ vé ghế D8”.

Khi chọn `student`:

- Hiện checkbox hoặc nút xác nhận “Đã kiểm tra thẻ sinh viên”.
- Dòng vé có trạng thái “Chưa xác minh” cho tới khi nhân viên xác nhận.
- Nút tiếp tục/thanh toán bị vô hiệu hóa nếu còn vé sinh viên chưa xác minh.

### 4.3 Bỏ từng vé

- Trước khi giữ ghế: nút `X` chỉ xóa ghế khỏi local selection và tính lại quote.
- Sau khi đã giữ ghế: nút `X` gọi endpoint sync hold để nhả đúng ghế đó, giữ nguyên các ghế còn lại.
- UI đặt dòng vé ở trạng thái pending, chống double-click và rollback nếu API thất bại.
- Khi xóa vé cuối cùng, quay về trạng thái chưa chọn ghế và vô hiệu hóa bước tiếp theo.

### 4.4 Quay lại từ thanh toán

Trình tự bắt buộc:

1. Khóa nút quay lại để ngăn thao tác lặp.
2. Nếu đã tạo order pending thì hủy order trước; nếu mới chỉ có hold thì nhả hold.
3. Nhận `released_seat_ids` từ server.
4. Xóa `currentHoldId` và state ghế tương ứng trong `PosSeat`.
5. Đồng bộ `PosCart`, classification map, tổng tiền và trạng thái nút.
6. Áp dụng delta ghế ngay, sau đó fetch seat map authoritative để reconcile.
7. Chỉ hiện toast thành công sau khi state local đã cập nhật.
8. Nếu release thất bại, giữ nguyên state, báo lỗi rõ ràng và cho phép thử lại.

Không chuyển panel trước rồi mới nhả ghế vì sẽ gây nhấp nháy và hiển thị dữ liệu cũ.

## 5. Thiết kế trạng thái và màu ghế

Khai báo bằng design token trong CSS, không dùng màu inline:

- `available`: nền trung tính, có thể chọn.
- `selected`: màu đỏ chủ đạo của hệ thống.
- `held-by-me`: màu hổ phách `#F59E0B`, kèm biểu tượng khóa nhỏ.
- `held-by-other`: màu xanh tím trầm `#6366F1`, không cho chọn.
- `booked`: xám đậm, dấu gạch chéo hoặc `×`.
- `maintenance`: xám nhạt/pattern riêng.

Yêu cầu accessibility:

- Không chỉ phân biệt bằng màu; dùng icon/pattern và `aria-label`.
- Contrast tối thiểu WCAG AA cho nhãn ghế.
- Vùng bấm tối thiểu 44×44 px trên màn hình cảm ứng.
- Legend dùng đúng cùng token và tên trạng thái với seat map.
- Transition chỉ áp dụng `background-color`, `border-color`, `transform` trong 120–180 ms; tôn trọng `prefers-reduced-motion`.

## 6. API và backend cần thay đổi

### Phase 1 — Guest checkout chuẩn hóa

1. Thêm migration `users.system_key` unique nullable hoặc bảng mapping system customer theo rạp.
2. Tạo `PosGuestCustomerResolver` dùng `firstOrCreate` an toàn theo transaction.
3. `PosController::createOrder()` chọn thành viên nếu có `customer_id` hợp lệ; nếu không, resolve system guest theo rạp.
4. Request dùng `customer_mode: guest|member`; không suy luận chỉ từ số điện thoại.
5. Backend từ chối loyalty/voucher cá nhân nếu `customer_mode=guest`.
6. Policy và serializer không cho system guest xuất hiện trong danh sách tài khoản quản trị hoặc endpoint profile.

### Phase 2 — Ticket classification và quote

1. Thêm rules cho `tickets.*.seat_id`, `tickets.*.audience_type`, `tickets.*.student_card_verified`.
2. Kiểm tra seat unique, thuộc đúng phòng và khớp hoàn toàn với hold hiện tại.
3. Bổ sung endpoint `POST /api/v1/pos/quotes` để tính giá authoritative trước khi tạo đơn.
4. Mở rộng `PaymentService`/pricing input để nhận audience type theo từng seat item.
5. Lưu metadata trên từng `OrderItem` vé:
   - `audience_type`;
   - `base_price`;
   - `audience_discount`;
   - `final_price`;
   - `classification_verified_by`;
   - `classification_verified_at`;
   - `student_card_verified` nếu là sinh viên;
   - `pricing_policy_version`.
6. Bỏ phụ thuộc `orders.customer_type` cho logic mới; chỉ giữ fallback đọc dữ liệu cũ trong giai đoạn chuyển tiếp.
7. Audit sự kiện `pos.ticket_audience_classified` và `pos.student_card_verified`.

### Phase 3 — Đồng bộ một phần seat hold

Ưu tiên tái sử dụng logic replace hold đã có trong `SeatService::lock()` thay vì tạo cơ chế lock thứ hai:

- Bổ sung endpoint POS `PUT /api/v1/pos/seat-holds/{holdId}` với danh sách ghế còn giữ.
- Transaction khóa hold, hold items, showtime và seats liên quan.
- Xác minh owner là nhân viên hiện tại và đúng theater scope.
- Nhả các ghế bị xóa, giữ/refresh các ghế còn lại, thêm ghế mới nếu hợp lệ.
- Trả cả `seat_ids` và `released_seat_ids`.
- Request có `expected_state_version` để phát hiện update cũ; conflict trả `409` cùng seat state mới nhất.
- Nếu danh sách rỗng, giải phóng toàn bộ hold theo cùng contract.

### Phase 4 — Realtime và reconciliation

1. Mở rộng `SeatStatusUpdated` với payload ổn định và version tăng đơn điệu theo showtime.
2. Viết module `pos-seat-sync.js` quản lý subscribe, reconnect, polling fallback và cleanup.
3. Event nhận được chỉ patch ghế tương ứng, không render lại toàn bộ sơ đồ nếu không cần.
4. Nếu version bị nhảy hoặc thấp hơn state hiện tại, gọi fetch reconcile.
5. Dùng `AbortController` hủy request seat map cũ khi đổi suất.
6. Khi reconnect, luôn fetch snapshot mới trước khi nhận thao tác tiếp theo.

## 7. Refactor frontend POS

### 7.1 State thống nhất

Tạo một store/controller nhỏ thay vì để state phân tán giữa nhiều file:

```text
transaction
├── showtime
├── customerMode
├── customer
├── selectedSeats
├── ticketClassifications
├── hold
├── products
├── quote
├── paymentMethod
└── status
```

- State thay đổi qua action rõ ràng: `selectSeat`, `removeTicket`, `classifyTicket`, `holdSynced`, `releaseCompleted`, `quoteUpdated`, `resetTransaction`.
- Component render từ store; không tự giữ bản sao state riêng gây lệch dữ liệu.
- Mọi request có pending/error state và chống response cũ ghi đè response mới.

### 7.2 Bố cục màn hình

- Giữ shell, typography, radius và màu nền theo hệ thống hiện có.
- Step 1: suất chiếu và bộ lọc gọn, skeleton chỉ ở vùng dữ liệu.
- Step 2: sơ đồ ghế là nội dung chính; sidebar hiển thị phim, legend và vé đã chọn.
- Step 3: bắp nước; giỏ hàng cố định nhưng không che nội dung.
- Step 4: bên trái là chế độ khách và phương thức thanh toán; bên phải là hóa đơn/vé chi tiết.
- `pos-payment-right` phải scroll độc lập khi nhiều vé, footer tổng tiền luôn nhìn thấy.
- Modal QR, tiền mặt và thành công dùng chung modal pattern của dự án, không dùng màu Bootstrap rời rạc.
- Toast dùng shared toast service, không tạo implementation riêng cho POS.

### 7.3 Component vé trong cột thanh toán

Mỗi `PosTicketRow` gồm:

- Nhãn ghế nổi bật.
- Loại ghế và suất chiếu ở mức secondary text.
- Bộ chọn đối tượng với nhãn tiếng Việt.
- Khu vực xác minh chỉ render khi cần.
- Giá authoritative và trạng thái đang tính lại.
- Nút xóa ở mép phải, không làm thay đổi layout khi hover.
- Error inline ngay trên dòng vé nếu quote/verification không hợp lệ.

## 8. File dự kiến tác động

### Backend

- `app/Http/Controllers/Pos/PosController.php`
- `app/Http/Requests/Pos/PosCreateOrderRequest.php`
- `app/Http/Requests/Pos/PosQuoteRequest.php`
- `app/Http/Requests/Pos/PosSyncSeatHoldRequest.php`
- `app/Services/PosOrderService.php`
- `app/Services/PosGuestCustomerResolver.php`
- `app/Services/SeatService.php`
- `app/Services/PaymentService.php`
- `app/Services/TicketPricingService.php`
- `app/Events/SeatStatusUpdated.php`
- `app/Models/OrderItem.php`
- `app/Models/User.php`
- `routes/api/pos.php`
- migration cho system customer/state version nếu cần.

### Frontend

- `resources/views/pos/index.blade.php`
- `public/js/pos/pos-app.js`
- `public/js/pos/pos-customer.js`
- `public/js/pos/pos-seat.js`
- `public/js/pos/pos-seat-sync.js`
- `public/js/pos/pos-cart.js`
- `public/js/pos/pos-payment.js`
- `public/js/pos/pos-store.js`
- `public/css/pos/pos.css`

### Test

- `tests/Feature/PosGuestCheckoutTest.php`
- `tests/Feature/PosTicketClassificationTest.php`
- `tests/Feature/PosSeatHoldSyncTest.php`
- `tests/Feature/Seat/SeatServiceLockingTest.php`
- browser E2E POS hai context để kiểm tra realtime.

## 9. Test bắt buộc

### 9.1 Guest và thành viên

- Tạo đơn khách vãng lai không có phone/name thành công.
- Đơn guest thuộc system customer đúng rạp và `served_by_user_id` đúng nhân viên.
- Guest không được dùng điểm hoặc voucher cá nhân.
- Thành viên được tra cứu, tích/dùng điểm đúng chính sách.
- System guest không thể đăng nhập và không xuất hiện trong quản lý tài khoản.

### 9.2 Phân loại vé

- Một đơn có adult + student + child + senior tính đúng từng vé.
- Student thiếu xác minh bị trả `422` và không tạo order.
- Student đã xác minh lưu đúng actor/time, không lưu dữ liệu thẻ.
- Child/senior không bị bắt giấy tờ.
- Frontend thay loại vé gọi quote lại, không tự tính giá cuối.
- Payload sửa giá từ client không làm thay đổi giá server.

### 9.3 Hold và realtime

- Bỏ một vé nhả đúng một ghế, các ghế khác vẫn được giữ.
- Bỏ vé cuối giải phóng hold.
- Quay lại từ payment đổi màu ghế ngay, không reload trang.
- Hai máy POS nhìn thấy lock/unlock của nhau.
- Booking online và POS nhìn thấy cùng trạng thái ghế.
- Hold hết hạn cập nhật thành available.
- WebSocket mất kết nối chuyển sang polling và reconcile khi reconnect.
- Hai nhân viên giữ cùng ghế: một bên thành công, bên còn lại nhận `409` và UI cập nhật đúng.

### 9.4 UI và accessibility

- Tab/Enter/Space thao tác được seat, ticket type, xác minh và nút xóa.
- Focus visible, touch target tối thiểu 44×44 px.
- Không có layout shift lớn khi quote lại hoặc nhận realtime event.
- Không có toast trùng, modal kẹt backdrop hoặc skeleton phủ toàn khung.
- Kiểm tra desktop quầy, laptop và tablet ngang.

## 10. Thứ tự triển khai

1. Viết feature tests đỏ cho guest, per-ticket classification và partial release.
2. Triển khai system guest và contract `customer_mode`.
3. Triển khai quote + pricing theo từng ticket và metadata/audit.
4. Triển khai API sync hold và response authoritative.
5. Tạo POS store, chuyển seat/cart/customer/payment sang state chung.
6. Thêm ticket row, xác minh sinh viên và nút xóa từng vé.
7. Thêm realtime subscriber, fallback polling và reconcile khi quay lại.
8. Chuẩn hóa màu ghế, layout, toast, modal và accessibility.
9. Chạy test PHP/JS, browser E2E một máy và hai máy POS.
10. Pilot tại một rạp, theo dõi conflict, thời gian quote, lỗi broadcast rồi mới mở rộng.

## 11. Chỉ số nghiệm thu

- Chọn khách vãng lai tới bước thanh toán: không quá 1 thao tác bổ sung.
- Nhả ghế phản hồi trên máy hiện tại trong mục tiêu dưới 300 ms sau API response.
- Máy POS khác nhận thay đổi ghế mục tiêu dưới 1 giây khi WebSocket hoạt động.
- Quote lại sau đổi loại vé mục tiêu dưới 500 ms ở p95 trong mạng nội bộ.
- Không có đơn POS thiếu `served_by_user_id`, theater scope hoặc ticket classification metadata.
- Không có vé sinh viên được bán với giá ưu đãi khi thiếu xác minh của nhân viên.
- Không cần refresh trang để thấy ghế vừa nhả, vừa giữ, hết hạn hoặc đã bán.

## 12. Definition of Done

- Khách vãng lai mua vé/bắp nước không cần số điện thoại.
- Từng vé có loại đối tượng, giá và audit độc lập.
- Student verification được enforce cả frontend và backend.
- Nút `X` bỏ từng vé hoạt động trước và sau khi giữ ghế.
- Quay lại/hủy giao dịch cập nhật ghế ngay lập tức.
- POS subscribe realtime và có fallback khi broadcast lỗi.
- Màu ghế mới rõ ràng, nhất quán và accessible.
- UI POS thống nhất với pattern hệ thống, không inline CSS và không tạo toast/modal riêng.
- Tất cả test nghiệp vụ, concurrency, security và browser E2E đạt.
