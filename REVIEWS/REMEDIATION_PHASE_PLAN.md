# Remediation Phase Plan

## Nguyên tắc

- Ưu tiên theo thứ tự: security/data integrity → correctness → performance → UX consistency → maintainability.
- Mỗi phase phải có regression tests và rollback plan.
- Không đổi business contract trong phase chỉ dành cho refactor.
- Generated/runtime artifacts không trộn với feature commit.

## Phase 1 — Correctness blockers

Mục tiêu: loại bỏ lỗi runtime đang ảnh hưởng người dùng.

- Sửa dữ liệu backdrop legacy làm `/api/v1/home` trả 500.
- Sửa nullable format trong order printing.
- Đóng toàn bộ PHPStan errors thay vì baseline/suppress.
- Chạy targeted tests cho home, POS, printing, RBAC.

Exit criteria:

- API home 200 và có dữ liệu.
- `composer analyse` pass.
- Browser home/printing flow không có visible error.

Trạng thái: **Hoàn thành**.

## Phase 2 — RBAC và identity hardening

Mục tiêu: mọi vai trò chỉ truy cập đúng capability và theater scope.

- Đăng ký explicit toàn bộ policy quan trọng.
- Kiểm tra delegated permission, customer boundary và theater isolation.
- Duy trì permission matrix theo endpoint/use case.
- Bổ sung regression test khi thêm permission hoặc role mới.

Exit criteria:

- RBAC targeted suite pass.
- Không endpoint management nào chỉ dựa vào UI visibility.
- Format/Sound policy được Gate resolve tường minh.

Trạng thái: **Hoàn thành trong phạm vi audit**.

## Phase 3 — Database và query performance

Mục tiêu: lookup/list/report giữ hiệu năng khi dữ liệu tăng.

- Thêm index cho `users.phone`.
- Capture `EXPLAIN` cho POS customer lookup và admin user search trên staging dataset.
- Chuẩn hóa phone format khi write; quyết định unique customer identity sau data cleanup.
- Thêm query budget cho dashboard/revenue/ticket/combo reports.

Exit criteria:

- Migration chạy thành công.
- Lookup phone dùng index.
- Không N+1 ở các API listing/report chính.

Trạng thái: **Đã code; cần chạy migration và staging explain**.

## Phase 4 — Frontend consistency và performance

Mục tiêu: không flicker/layout shift, component và chart theo cùng pattern.

- Sửa horizontal overflow trang posts.
- Chuẩn hóa DataRegion, skeleton, toast, modal, pagination và filter bar.
- Lazy-load scanner/chart dependencies theo route hoặc interaction.
- Kiểm tra Turbo lifecycle cleanup và request cancellation.
- Thêm visual regression desktop/tablet/mobile.

Exit criteria:

- Không horizontal overflow trên màn hình đại diện.
- Không visible skeleton sau ready state.
- Không modal backdrop kẹt khi Turbo navigation.
- Bundle budget được ghi nhận trong CI.

Trạng thái: **Một phần hoàn thành; component migration và bundle split còn mở**.

## Phase 5 — Repository hygiene và maintainability

Mục tiêu: diff sạch, module nhỏ, ownership rõ.

- Xóa debug scripts root.
- Ignore PID/runtime artifacts.
- Xóa compiled Blade views khỏi Git index trong commit riêng.
- Tách `PaymentService`, `OrderService`, `SeatService` theo use case/state transition.
- Chuẩn hóa Order status constants trong mọi service.

Exit criteria:

- Không runtime/generated file tracked.
- Không source service/controller vượt ngưỡng đã thống nhất nếu chưa có ADR ngoại lệ.
- Full tests không đổi behavior.

Trạng thái: **Debug/PID hoàn thành; tracked views và service split còn mở**.

## Phase 6 — Production operations

Mục tiêu: hệ thống vận hành ổn định và có khả năng phục hồi.

- Supervisor cho queue `emails,default`.
- Scheduler chạy mỗi phút; Reverb process nếu realtime bật.
- Health checks cho DB, cache, queue và mail.
- Sentry alerts, queue latency, failed job và payment webhook alert.
- Backup restore drill và incident runbook.

Exit criteria:

- Restart process không mất job.
- Có cảnh báo khi mail/payment/queue lỗi.
- Backup restore được diễn tập và ghi thời gian RPO/RTO.

Trạng thái: **Chưa triển khai hạ tầng**.

## Phase 7 — E2E, load và security verification

Mục tiêu: xác nhận production readiness bằng tải và luồng thật.

- E2E booking online: hold seat → product → voucher/points → zero/non-zero payment → ticket/invoice.
- E2E POS: guest/member, product-only, cash, PayOS QR, cancel/release hold, printing.
- E2E RBAC theo từng vai trò và theater scope.
- Load test concurrent seat hold/order/webhook.
- Accessibility scan và keyboard-only smoke test.

Exit criteria:

- Không double booking/double fulfillment.
- P95/P99 đạt SLO đã thống nhất.
- Không Critical/High accessibility hoặc security finding.

Trạng thái: **Targeted tests hoàn thành; full E2E/load còn mở**.
