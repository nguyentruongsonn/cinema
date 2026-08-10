# Remediation Execution Tracker

Cập nhật: 10/08/2026

## Tổng quan

| Phase | Trạng thái | Tiến độ |
|---|---|---:|
| 1. Correctness blockers | Completed | 100% |
| 2. RBAC & identity hardening | Completed | 100% |
| 3. Database & query performance | In progress | 90% |
| 4. Frontend consistency & performance | In progress | 80% |
| 5. Repository hygiene & maintainability | In progress | 35% |
| 6. Production operations | In progress | 80% |
| 7. E2E/load/security verification | In progress | 85% |

## Completed

- [x] Inventory toàn bộ route/controller/request/model/service/policy/job/migration/view/test.
- [x] Dependency audit cho Composer và npm.
- [x] PHPStan pass 0 error.
- [x] ESLint, frontend syntax, frontend security gate và Vite build pass.
- [x] Sửa home API 500 do `backdrops` double-encoded.
- [x] Sửa order printing 500 khi showtime format nullable.
- [x] Xóa dead booking verifier trong ticket check-in.
- [x] Sửa aggregate user stats type safety.
- [x] Sửa SeatHold collection callback/type handling.
- [x] Đăng ký explicit `FormatPolicy` và `SoundPolicy`.
- [x] Thêm regression test policy registration.
- [x] Thêm migration index cho `users.phone`.
- [x] Loại event property assignment bị frontend security gate cảnh báo.
- [x] Sửa horizontal overflow trang posts.
- [x] Xóa root debug scripts.
- [x] Ignore runtime PID file.
- [x] Ignore và clear compiled Blade views khỏi working tree.
- [x] Browser smoke: home, dashboard, revenue, ticket stats, combo stats, orders, roles-permissions, POS, posts, theaters.
- [x] Full Laravel suite pass: 298 tests, 2269 assertions.
- [x] POS/printing/user/order targeted suites pass trong phạm vi đã chạy.
- [x] Browser xác minh luồng POS chỉ mua bắp nước, chọn rạp, payment method và modal hủy.
- [x] EXPLAIN local xác nhận index cho POS phone lookup và admin order listing.
- [x] 31 tests locking/idempotency/POS 0đ/authorization pass.
- [x] 11 tests fulfillment/email/printing pass.
- [x] 15 tests health/auth identity/security headers pass.
- [x] Audit DOM 8 màn hình cho overflow, duplicate ID, alt text, label và touch target.
- [x] Queue monitor kiểm tra depth, tuổi pending job và expired reservation.
- [x] Readiness trả 503 khi monitored queue stale và trở lại 200 sau recovery.
- [x] Xử lý backlog 37 broadcast event; local Reverb/broadcast worker hoạt động và queue về 0.
- [x] Booking phát hiện `SESSION_EXPIRED`, mở auth modal và không xóa lựa chọn ghế.
- [x] Thêm Supervisor template cho business queue, broadcasts, scheduler và Reverb.
- [x] Thêm `operations:monitor-health` cho queue, payment quá hạn và email hóa đơn chưa gửi.
- [x] Thêm external webhook alert có cooldown và regression test chống alert storm.
- [x] Thêm staging browser smoke cho 5 public route trên mobile/tablet/desktop.
- [x] Thêm browser auth-expiry smoke; event chỉ phát một lần và modal đăng nhập được mở.
- [x] Thêm booking concurrency probe hai tài khoản, tự release hold sau test.
- [x] Thêm MySQL backup/restore drill script và production operations runbook.
- [x] Chuẩn hóa media upload URL về same-origin relative path; browser xác nhận ảnh load thành công.

## In progress

- [x] Chạy migration `2026_08_10_180000_add_users_phone_lookup_index` trên local.
- [ ] Chạy migration phone index trên staging/production theo release process.
- [x] Capture execution plan cho phone lookup và order listing trên local MySQL.
- [ ] Capture execution plan cho report queries trên staging dataset.
- [ ] Migrate các filter/listing còn lại sang shared `x-admin.filter-bar`.
- [ ] Chuẩn hóa modal/list/data-state ở các admin page chưa dùng shared component.
- [ ] Lazy-load scanner và route-specific chart code.
- [ ] Đặt bundle budget trong CI.
- [ ] Bổ sung E2E mobile/tablet/desktop và accessibility automation.
- [x] Sửa booking auth-expiry để không thất bại im lặng.
- [ ] Sửa POS validation rạp, hidden utility và modal labelling.
- [ ] Chuẩn hóa client `lang`, touch target và placeholder link.

## Pending

- [ ] Commit các deletion compiled Blade views trong commit hygiene riêng để hoàn tất untrack trên remote.
- [ ] Chuyển CSP `style-src-attr` từ `unsafe-inline` sang `none` sau khi xử lý dynamic styles.
- [ ] Tách các service/controller quá lớn theo bounded context.
- [ ] Chuẩn hóa Order lifecycle constants trong mọi service.
- [ ] Deploy Supervisor template cho queue, scheduler và Reverb trên staging/production; xác minh auto-restart.
- [ ] Cấu hình webhook alert thật, chạy backup/restore drill và PayOS reconciliation test trên staging.
- [x] Chạy worker `broadcasts`, xử lý backlog local và alert theo tuổi job.
- [x] Mở rộng readiness/queue monitor để phát hiện queue starvation.
- [ ] Chuẩn hóa `APP_URL`, `APP_NAME`, Sentry, metrics token và slow-query logging theo môi trường.
- [ ] Quyết định backfill 28 paid order lịch sử chưa có `ticket_email_sent_at`.
- [ ] Chạy booking concurrency probe và full load test seat hold/order/payment fulfillment trên staging test data.
- [ ] Hoàn tất Google OAuth hoặc xóa entry chưa hỗ trợ khỏi UI/docs.
- [ ] Hoàn tất quick-booking showtime cascade ở trang chủ.

## Verification commands

```powershell
composer audit --locked
npm audit --omit=dev
composer analyse
npm run lint
npm run build
npm run test:frontend:syntax
npm run test:frontend:security
php artisan test
php artisan migrate --force
```

## Exit checklist trước production

- [ ] Full test suite pass trên clean database.
- [ ] Migration/rollback được chạy thử trên staging clone.
- [ ] Queue worker và scheduler được process manager giám sát.
- [ ] Mail thật được xác minh bằng paid online và paid POS order.
- [ ] PayOS webhook signature/duplicate/retry được test trên staging.
- [ ] Browser E2E pass cho booking, POS, printing, reset password và RBAC.
- [ ] Browser E2E mô phỏng access token hết hạn giữa bước booking.
- [x] Browser auth-expiry component smoke pass trên Chromium/Edge headless.
- [x] Realtime seat/order event được xử lý trên local, queue `broadcasts` không có job quá hạn.
- [ ] Không runtime/generated artifacts trong Git diff.
- [ ] Có rollback release và database backup mới nhất.
