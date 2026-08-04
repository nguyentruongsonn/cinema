# Role Permission And Audit Log Implementation Plan

## 1. Mục Tiêu

Hoàn thiện module để admin quản lý quyền động theo từng vai trò và theo dõi lịch sử thao tác quan trọng trong hệ thống.

Module cần đáp ứng:

- Admin xem danh sách vai trò chuẩn.
- Admin xem toàn bộ quyền theo nhóm.
- Admin tick/bỏ tick quyền cho từng vai trò.
- Admin không vô tình tự khóa quyền quản trị.
- Mỗi lần đổi quyền phải ghi audit log.
- Admin xem audit log có phân trang, lọc và xem chi tiết before/after.

## 2. Phạm Vi

### Backend

- API danh sách role.
- API danh sách permission theo nhóm.
- API xem quyền của một role.
- API cập nhật quyền của một role.
- API danh sách audit log.
- API xem chi tiết audit log.
- Authorization bằng permission:
  - `roles.view`
  - `roles.update`
  - `permissions.assign`
  - `audit_logs.view`

### Admin UI

- Trang `admin/roles-permissions`.
- Trang `admin/audit-logs`.
- Menu trong nhóm Tài khoản.
- Loading state, empty state, error state.
- Modal xem chi tiết audit log.
- Confirm trước khi lưu phân quyền.

## 3. Quy Tắc Bảo Mật

- `admin` có toàn bộ quyền và không được chỉnh trực tiếp trong UI thường.
- Chỉ `super-admin` mới được sửa quyền `admin` nếu sau này bật chế độ đó.
- Không cho user tự gỡ quyền `permissions.assign`, `roles.update`, `audit_logs.view` khỏi role hiện tại nếu đó là quyền cuối cùng giúp họ quản trị.
- Seeder vẫn là source mặc định, nhưng UI cho phép override quyền runtime.
- Audit log bất biến: không update, không delete.
- Audit log không trả `ip_address` và `user_agent` mặc định nếu không cần thiết.

## 4. API Thiết Kế

### Role Permission

- `GET /api/v1/admin/roles-permissions/roles`
  - Trả role chuẩn, số permission mỗi role.
- `GET /api/v1/admin/roles-permissions/permissions`
  - Trả permission theo nhóm.
- `GET /api/v1/admin/roles-permissions/roles/{role}`
  - Trả role và danh sách permission slug đang có.
- `PUT /api/v1/admin/roles-permissions/roles/{role}`
  - Body: `{ "permissions": ["movies.view", "orders.view_own"] }`
  - Ghi audit action `role.permissions.updated`.

### Audit Log

- `GET /api/v1/admin/audit-logs`
  - Filters: `search`, `action`, `auditable_type`, `user_id`, `date_from`, `date_to`, `page`, `per_page`.
- `GET /api/v1/admin/audit-logs/{auditLog}`
  - Trả chi tiết `old_values`, `new_values`, `request_id`.

## 5. Admin UI

### Trang Phân Quyền

- Cột trái: danh sách vai trò.
- Cột phải: nhóm quyền với checkbox.
- Header hiển thị số quyền đang chọn.
- Nút lưu chỉ bật khi có thay đổi.
- Role `admin` hiển thị readonly.

### Trang Audit Log

- Bộ lọc: search, action, model, user, thời gian.
- Bảng: thời gian, actor, action, model, request id.
- Nút xem chi tiết mở modal.
- Phân trang server-side.

## 6. Test Bắt Buộc

- Admin xem được role/permission catalog.
- Customer không truy cập được API phân quyền.
- Admin cập nhật quyền role và ghi audit log.
- Không cho cập nhật role `admin` qua API thường.
- Admin xem audit log có phân trang.
- Audit log detail trả dữ liệu before/after.

## 7. Definition Of Done

- API role permission hoạt động.
- API audit log hoạt động.
- Menu admin có 2 trang mới.
- UI phân quyền lưu được runtime permission.
- UI audit log xem được lịch sử.
- PHPUnit pass.
- PHPStan pass.

