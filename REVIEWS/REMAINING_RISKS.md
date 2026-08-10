# Remaining Risks

## Risk register

| ID | Priority | Risk | Impact | Recommended owner | Status |
|---|---|---|---|---|---|
| R-01 | High | Đã có Supervisor template nhưng chưa deploy/xác minh auto-restart ở production | Email, expiration, retry và async fulfillment có thể dừng âm thầm | DevOps/Backend | Production action required |
| R-02 | High | Đã có concurrency probe nhưng chưa chạy full load test seat hold/order/webhook trên staging | Double booking, lock contention hoặc latency cao khi mở bán | Backend/QA | Mitigated; staging action required |
| R-03 | Medium | 62 compiled Blade views đang tracked | Diff nhiễu, conflict và runtime artifact lọt vào release | Tech Lead | Open |
| R-04 | Medium | CSP cho phép inline style attributes | Giảm mức phòng vệ XSS | Frontend/Security | Open |
| R-05 | Medium | Phone chưa unique theo customer identity | Có thể lookup nhầm nếu rule vai trò thay đổi hoặc dữ liệu nhập không chuẩn | Product/Backend | Accepted temporarily |
| R-06 | Medium | Service/controller lớn và nhiều orchestration responsibility | Regression risk, review khó và onboarding chậm | Backend | Open |
| R-07 | Medium | Shared admin components chưa được áp dụng toàn bộ | UI drift, duplicate CSS/logic | Frontend | Open |
| R-08 | Medium | Public smoke đã chạy 3 viewport nhưng chưa có visual regression cho booking/POS/admin | Responsive/UI lỗi có thể quay lại mà unit test không phát hiện | QA/Frontend | Partially mitigated |
| R-09 | Medium | Scanner/chart bundles tải sớm và khá lớn | Khựng trên kiosk/POS cấu hình thấp | Frontend | Open |
| R-10 | Medium | Đã có operations monitor và webhook alert nhưng chưa cấu hình endpoint incident thật | Phát hiện sự cố tích hợp chậm | DevOps | Production action required |
| R-11 | Low | Google OAuth và quick-booking còn TODO | Chức năng dang dở gây kỳ vọng sai | Product/Frontend | Open |
| R-12 | Low | Một số order status constants còn duplicate trong service | State transition drift khi thay đổi enum | Backend | Open |
| R-13 | High | `broadcasts` worker có thể dừng nếu production thiếu process manager | Seat/payment realtime không cập nhật; monitor mới sẽ fail readiness nhưng không tự restart worker | DevOps/Backend | Mitigated locally; production action required |
| R-14 | High | SSR header và API auth có thể lệch sau khi token hết hạn | Booking click ghế thất bại im lặng | Frontend/Auth | Resolved |
| R-15 | Medium | `APP_URL`/`APP_NAME` runtime dùng giá trị mặc định | Reset-password link sai host/port và branding email sai | DevOps | Open |
| R-16 | Medium | Client `lang`, touch target, modal labels và placeholder links chưa đạt chuẩn | WCAG/keyboard/mobile usability giảm | Frontend/UX | Open |
| R-17 | Medium | 28 paid order lịch sử chưa có dấu gửi hóa đơn | Khách cũ có thể chưa nhận invoice; backfill có nguy cơ gửi trùng | Product/Backend | Decision required |
| R-18 | Medium | POS validation rạp im lặng và hidden utility bị override | Nhân viên không hiểu vì sao không chuyển bước, action trùng trong modal | Frontend/POS | Open |
| R-19 | Medium | Tooltip chart line cố định top-right | Tooltip dễ bị che/cắt và hover khó hiểu | Frontend/UX | Open |

## Risk acceptance bắt buộc nếu release sớm

Nếu phát hành trước khi đóng toàn bộ risk, cần ghi nhận owner và deadline cho tối thiểu R-01, R-02, R-04, R-08, R-10 và R-13. Không nên production go-live nếu chưa deploy process manager, chạy backup restore drill, booking race/load probe và auth-expiry full-flow E2E trên staging.

## Rollback notes

- Migration phone index có thể rollback độc lập bằng `php artisan migrate:rollback --step=1` nếu đây là migration mới nhất.
- Home backdrop remediation chỉ thay đổi serialization; rollback không cần data migration.
- Policy registration là additive; rollback chỉ cần revert provider/test nếu phát hiện convention conflict.
- CSS posts fix chỉ giới hạn container trang posts và có thể revert độc lập.
