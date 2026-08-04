# RBAC Implementation Plan

## 1. Mục Tiêu

Chuẩn hóa hệ thống phân quyền cho rạp chiếu phim theo mô hình RBAC rõ ràng, dễ kiểm soát và an toàn khi mở rộng.

Các vai trò nghiệp vụ cần hỗ trợ:

- Quản trị viên (`admin`)
- Quản lý rạp (`theater_manager`)
- Nhân viên bán vé (`ticket_seller`)
- Nhân viên soát vé (`ticket_checker`)
- Nhân viên quầy bắp nước (`concession_staff`)
- Khách hàng (`customer`)
- Khách hàng vãng lai (`guest`)

Nguyên tắc chính:

- `admin` có toàn bộ quyền.
- Các vai trò nhân sự chỉ có quyền đúng phạm vi công việc.
- Khách hàng chỉ được thao tác trên dữ liệu của chính mình.
- Khách vãng lai chỉ được xem dữ liệu công khai, trừ khi bật guest checkout.
- Không dùng role hard-code cho nghiệp vụ có thể kiểm soát bằng permission.

## 2. Hiện Trạng Cần Refactor

Hệ thống hiện có các role seed mặc định:

- `admin`
- `manager`
- `staff`
- `user`

Các permission hiện tại đang dùng dạng slug cũ, ví dụ:

- `view_movies`
- `create_movies`
- `view_all_orders`
- `book_tickets`
- `view_dashboard`

Vấn đề:

- Role `staff` đang quá chung, không tách được nhân viên bán vé, soát vé, bắp nước.
- Role `manager` chưa thể giới hạn theo rạp được phân công.
- Một số permission slug chưa theo chuẩn namespace.
- Một số controller/policy đang kiểm tra role trực tiếp, cần chuyển sang permission.
- Chưa có test đầy đủ cho từng vai trò.

## 3. Chuẩn Role

| Role | Slug | Mô tả |
| --- | --- | --- |
| Quản trị viên | `admin` | Toàn quyền hệ thống |
| Quản lý rạp | `theater_manager` | Quản lý vận hành một hoặc nhiều rạp |
| Nhân viên bán vé | `ticket_seller` | Tạo đơn vé, giữ ghế, xử lý thanh toán tại quầy |
| Nhân viên soát vé | `ticket_checker` | Quét vé, xác minh vé, đánh dấu vé đã dùng |
| Nhân viên quầy bắp nước | `concession_staff` | Xem, bán, giao sản phẩm/combo |
| Khách hàng | `customer` | Đặt vé, thanh toán, xem đơn của mình |
| Khách vãng lai | `guest` | Xem nội dung công khai, có thể checkout nếu bật |

## 4. Chuẩn Permission Slug

Nên chuyển dần sang dạng namespace:

```text
resource.action
```

Ví dụ:

- `movies.view`
- `movies.create`
- `orders.refund`
- `tickets.verify`
- `dashboard.view`

### 4.1. Nhóm Người Dùng Và Phân Quyền

- `users.view`
- `users.create`
- `users.update`
- `users.delete`
- `users.manage_roles`
- `roles.view`
- `roles.create`
- `roles.update`
- `roles.delete`
- `permissions.assign`

### 4.2. Nhóm Phim

- `movies.view`
- `movies.create`
- `movies.update`
- `movies.delete`
- `movies.publish`
- `movie_categories.manage`
- `formats.manage`
- `subtitles.manage`
- `sounds.manage`

### 4.3. Nhóm Rạp Và Phòng Chiếu

- `branches.view`
- `branches.create`
- `branches.update`
- `branches.delete`
- `theaters.view`
- `theaters.create`
- `theaters.update`
- `theaters.delete`
- `screens.view`
- `screens.create`
- `screens.update`
- `screens.delete`
- `screens.manage_seats`
- `seat_layouts.view`
- `seat_layouts.create`
- `seat_layouts.update`
- `seat_layouts.delete`

### 4.4. Nhóm Suất Chiếu

- `showtimes.view`
- `showtimes.create`
- `showtimes.update`
- `showtimes.delete`
- `showtimes.bulk_create`

### 4.5. Nhóm Đặt Vé Và Ghế

- `booking.hold_seats`
- `booking.release_seats`
- `booking.create_order`
- `booking.guest_checkout`
- `seats.view_status`

### 4.6. Nhóm Đơn Hàng Và Vé

- `orders.view_all`
- `orders.view_theater`
- `orders.view_own`
- `orders.create`
- `orders.cancel`
- `orders.refund`
- `orders.export`
- `tickets.view`
- `tickets.issue`
- `tickets.verify`
- `tickets.mark_used`
- `tickets.cancel`

### 4.7. Nhóm Thanh Toán

- `payments.process`
- `payments.view`
- `payments.verify`
- `payments.refund`
- `payments.webhook_manage`

### 4.8. Nhóm Sản Phẩm Và Combo

- `products.view`
- `products.create`
- `products.update`
- `products.delete`
- `products.toggle_status`
- `combos.view`
- `combos.create`
- `combos.update`
- `combos.delete`
- `combos.toggle_status`
- `concessions.fulfill`

### 4.9. Nhóm Khuyến Mãi Và Thành Viên

- `promotions.view`
- `promotions.create`
- `promotions.update`
- `promotions.delete`
- `promotions.apply`
- `promotions.reset_usage`
- `vouchers.claim`
- `loyalty.view`
- `loyalty.use_points`
- `loyalty.adjust_points`

### 4.10. Nhóm Nội Dung Website

- `posts.view`
- `posts.create`
- `posts.update`
- `posts.delete`
- `posts.publish`
- `banners.view`
- `banners.create`
- `banners.update`
- `banners.delete`
- `banners.toggle_status`

### 4.11. Nhóm Báo Cáo Và Hệ Thống

- `dashboard.view`
- `reports.view`
- `reports.export`
- `analytics.view`
- `pricing.view`
- `pricing.update`
- `audit_logs.view`
- `settings.manage`

## 5. Ma Trận Quyền Theo Vai Trò

### 5.1. Admin

`admin` được sync toàn bộ permission có trong bảng `permissions`.

### 5.2. Quản Lý Rạp

- `dashboard.view`
- `reports.view`
- `reports.export`
- `analytics.view`
- `movies.view`
- `branches.view`
- `theaters.view`
- `theaters.update`
- `screens.view`
- `screens.create`
- `screens.update`
- `screens.manage_seats`
- `seat_layouts.view`
- `seat_layouts.create`
- `seat_layouts.update`
- `showtimes.view`
- `showtimes.create`
- `showtimes.update`
- `showtimes.delete`
- `showtimes.bulk_create`
- `orders.view_theater`
- `orders.cancel`
- `orders.refund`
- `tickets.view`
- `payments.view`
- `payments.verify`
- `products.view`
- `combos.view`
- `concessions.fulfill`
- `users.view`
- `users.create`
- `users.update`

Giới hạn quan trọng:

- Chỉ xem/sửa dữ liệu thuộc rạp được phân công.
- Không được quản lý `admin`.
- Không được thay đổi role/permission cấp hệ thống.
- Không được xóa rạp/chi nhánh nếu không có quyền đặc biệt.

### 5.3. Nhân Viên Bán Vé

- `movies.view`
- `theaters.view`
- `screens.view`
- `showtimes.view`
- `seats.view_status`
- `booking.hold_seats`
- `booking.release_seats`
- `booking.create_order`
- `orders.create`
- `orders.view_theater`
- `orders.cancel`
- `tickets.issue`
- `payments.process`
- `payments.view`
- `promotions.apply`
- `products.view`
- `combos.view`

Giới hạn quan trọng:

- Chỉ thao tác đơn tại rạp/quầy được phân công.
- Không được hoàn tiền nếu chưa có quyền riêng.
- Không được sửa phim, lịch chiếu, giá vé, sản phẩm, combo.

### 5.4. Nhân Viên Soát Vé

- `movies.view`
- `showtimes.view`
- `tickets.view`
- `tickets.verify`
- `tickets.mark_used`
- `orders.view_theater`

Giới hạn quan trọng:

- Chỉ xác minh vé trong rạp được phân công.
- Không được tạo đơn, hủy đơn, hoàn tiền.
- Không được xem dữ liệu thanh toán nhạy cảm.

### 5.5. Nhân Viên Quầy Bắp Nước

- `products.view`
- `combos.view`
- `orders.view_theater`
- `concessions.fulfill`
- `payments.process`

Nếu hỗ trợ bán bắp nước độc lập:

- `orders.create`

Giới hạn quan trọng:

- Không được giữ ghế, tạo vé, xác minh vé.
- Không được sửa sản phẩm/combo.
- Không được hoàn tiền.

### 5.6. Khách Hàng

- `movies.view`
- `theaters.view`
- `showtimes.view`
- `seats.view_status`
- `booking.hold_seats`
- `booking.release_seats`
- `booking.create_order`
- `orders.view_own`
- `orders.create`
- `orders.cancel`
- `payments.process`
- `promotions.apply`
- `vouchers.claim`
- `loyalty.view`
- `loyalty.use_points`

Giới hạn quan trọng:

- Chỉ xem và thao tác đơn của chính mình.
- Không được xem dashboard/admin.
- Không được áp voucher chưa nằm trong kho voucher của mình.

### 5.7. Khách Hàng Vãng Lai

Khách vãng lai không cần record role trong DB nếu chỉ xem nội dung công khai.

Quyền public:

- Xem trang chủ.
- Xem phim.
- Xem rạp.
- Xem suất chiếu.
- Xem giá vé.
- Xem tin tức.
- Xem ưu đãi công khai.

Nếu bật guest checkout:

- `booking.guest_checkout`
- `seats.view_status`
- `booking.hold_seats`
- `booking.release_seats`
- `orders.create`
- `payments.process`

Giới hạn quan trọng:

- Không có kho voucher cá nhân.
- Không tích điểm thành viên.
- Không xem lịch sử đơn dài hạn nếu chưa xác thực qua email/số điện thoại.

## 6. Lộ Trình Triển Khai

### Phase 1: Audit Và Thiết Kế Nền

Việc cần làm:

- Kiểm tra toàn bộ role/permission hiện có trong seed và DB.
- Liệt kê toàn bộ policy đang dùng.
- Liệt kê route admin/API cần bảo vệ.
- Tìm các đoạn hard-code role: `admin`, `manager`, `staff`, `user`.
- Tìm các permission slug cũ.
- Xác định dữ liệu nào cần giới hạn theo rạp.

Kết quả bàn giao:

- Danh sách role/permission hiện có.
- Danh sách route/policy cần đổi.
- Danh sách hard-code cần refactor.

### Phase 2: Tạo Permission Catalog

Việc cần làm:

- Tạo file catalog quyền, ví dụ `config/rbac.php`.
- Định nghĩa permission theo nhóm.
- Định nghĩa mapping role với permission.
- Giữ alias cho permission cũ trong giai đoạn chuyển tiếp.

Kết quả bàn giao:

- `config/rbac.php`
- Permission catalog có thể seed lại nhiều lần.
- Admin luôn nhận toàn bộ permission.

### Phase 3: Migration Role Và Permission

Việc cần làm:

- Tạo migration/command chuyển role cũ sang role mới.
- Map dữ liệu:
  - `manager` -> `theater_manager`
  - `staff` -> `ticket_seller` tạm thời
  - `user` -> `customer`
- Tạo role mới:
  - `ticket_checker`
  - `concession_staff`
  - `customer`
- Không xóa role cũ ngay nếu còn dữ liệu phụ thuộc.

Kết quả bàn giao:

- Migration an toàn, có thể chạy trên dữ liệu thật.
- Seeder idempotent, chạy nhiều lần không duplicate.

### Phase 4: Refactor Seeder

Việc cần làm:

- Cập nhật `RoleSeeder`.
- Cập nhật `PermissionSeeder`.
- Tách mapping role-permission ra hàm rõ ràng.
- Dùng transaction khi seed role-permission.
- Không truncate dữ liệu production.

Kết quả bàn giao:

- Seeder tạo đúng role mới.
- Seeder sync đúng quyền từng vai trò.
- Admin có tất cả quyền.

### Phase 5: Refactor Policy Và Middleware

Việc cần làm:

- Chuyển kiểm tra role sang permission ở policy.
- Giữ `admin` bypass ở policy bằng `before`.
- Thêm scope theo rạp cho `theater_manager`, `ticket_seller`, `ticket_checker`, `concession_staff`.
- Chuẩn hóa response `403` cho API.

Kết quả bàn giao:

- Policy không phụ thuộc role chung chung.
- Route admin/API chặn đúng vai trò.
- Nhân viên chỉ thấy dữ liệu thuộc phạm vi được phân công.

### Phase 6: Admin UI Quản Lý Role Và Permission

Việc cần làm:

- Cập nhật form tạo/sửa user để chọn role mới.
- Hiển thị tên role tiếng Việt.
- Nhóm checkbox quyền theo resource.
- Ẩn quyền hệ thống khỏi manager/staff.
- Thêm bộ lọc user theo role.
- Nếu có phân công rạp, thêm trường `theater_id` hoặc quan hệ user-theater.

Kết quả bàn giao:

- Admin quản lý role dễ hiểu.
- UI không còn role `staff` chung chung.
- Có thể lọc nhân viên theo vai trò.

### Phase 7: Customer Và Guest Flow

Việc cần làm:

- Xác định có bật guest checkout hay không.
- Nếu không bật, guest chỉ xem nội dung public.
- Nếu bật, guest checkout phải yêu cầu email/số điện thoại.
- Không cho guest dùng voucher cá nhân hoặc điểm thành viên.
- Đơn guest cần có trạng thái và thông tin liên hệ đủ để tra cứu.

Kết quả bàn giao:

- Luồng customer/guest rõ ràng.
- Không lẫn quyền guest với customer đã đăng nhập.

### Phase 8: Test Bảo Mật Và Regression

Việc cần làm:

- Test admin có toàn bộ quyền.
- Test theater manager bị giới hạn theo rạp.
- Test ticket seller tạo đơn nhưng không sửa lịch chiếu.
- Test ticket checker xác minh vé nhưng không hoàn tiền.
- Test concession staff giao combo nhưng không giữ ghế.
- Test customer chỉ xem đơn của mình.
- Test guest không vào được route cần đăng nhập.
- Test API trả `403` đúng khi thiếu quyền.

Kết quả bàn giao:

- Feature tests cho RBAC.
- Policy tests cho từng model quan trọng.
- Browser smoke cho admin/customer.

### Phase 9: Rollout Và Kiểm Tra Production

Việc cần làm:

- Backup DB trước khi migration.
- Chạy migration trên staging.
- Chạy seeder role/permission.
- Kiểm tra account admin.
- Kiểm tra tài khoản mẫu từng vai trò.
- Kiểm tra log `403` bất thường.
- Sau khi ổn mới deploy production.

Kết quả bàn giao:

- Checklist rollout.
- Tài khoản test từng role.
- Báo cáo quyền sau migration.

## 7. File Dự Kiến Thay Đổi

Backend:

- `config/rbac.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/UserSeeder.php`
- `database/migrations/*_add_rbac_role_slugs.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `app/Models/Permission.php`
- `app/Http/Middleware/PermissionMiddleware.php`
- `app/Policies/*Policy.php`
- `routes/api/admin.php`
- `routes/api/customer.php`

Frontend/Admin:

- `resources/views/admin/users/index.blade.php`
- `public/js/admin/pages/users.js`
- `public/js/admin/pages/roles.js` nếu có hoặc sẽ tạo mới
- `public/css/admin/pages/users.css`

Tests:

- `tests/Feature/Auth/RbacAccessTest.php`
- `tests/Feature/Admin/AdminRolePermissionTest.php`
- `tests/Feature/Admin/TheaterScopedAccessTest.php`
- `tests/Unit/Policies/*PolicyTest.php`

Docs:

- `RBAC_IMPLEMENTATION_PLAN.md`

## 8. Rủi Ro Và Biện Pháp Giảm Thiểu

| Rủi ro | Ảnh hưởng | Giảm thiểu |
| --- | --- | --- |
| Đổi slug permission làm hỏng policy cũ | User bị chặn nhầm | Giữ alias permission cũ trong giai đoạn chuyển tiếp |
| Role `staff` cũ không biết map sang vai trò nào | Sai quyền nhân viên | Map tạm sang `ticket_seller`, sau đó admin phân loại lại |
| Manager xem dữ liệu ngoài rạp | Lộ dữ liệu vận hành | Thêm theater scope ở query và policy |
| Guest checkout gây lạm dụng giữ ghế | Ghế bị giữ ảo | Throttle, thời hạn hold ngắn, yêu cầu email/số điện thoại |
| Seeder truncate production | Mất mapping quyền thật | Seeder dùng updateOrCreate/sync, không truncate production |

## 9. Definition Of Done

Hoàn tất khi:

- Có đủ 7 vai trò nghiệp vụ.
- Admin có toàn bộ quyền.
- Từng vai trò có permission đúng ma trận.
- Không còn phụ thuộc role `staff` chung chung.
- Policy chặn đúng quyền ở backend.
- Admin UI hiển thị và lọc đúng role mới.
- Customer chỉ xem được dữ liệu của mình.
- Guest không truy cập được route riêng tư.
- Test RBAC pass.
- Browser smoke pass cho admin, nhân viên, customer.

## 10. Thứ Tự Ưu Tiên Thực Hiện

1. Tạo `config/rbac.php`.
2. Refactor `RoleSeeder` và `PermissionSeeder`.
3. Tạo migration map role cũ sang role mới.
4. Cập nhật policy/middleware theo permission.
5. Thêm scope theo rạp cho nhân viên/quản lý.
6. Cập nhật admin UI quản lý user/role.
7. Thêm test RBAC.
8. Chạy full validation.

