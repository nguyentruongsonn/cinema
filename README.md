# Cinema — Nền Tảng Quản Trị & Đặt Vé Xem Phim Trực Tuyến

[![CI Quality](https://github.com/nguyentruongsonn/cinema/actions/workflows/quality.yml/badge.svg)](https://github.com/nguyentruongsonn/cinema/actions/workflows/quality.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Redis](https://img.shields.io/badge/Redis-7.x-DC382D?logo=redis&logoColor=white)](https://redis.io)
[![Turbo](https://img.shields.io/badge/Hotwire-Turbo_8-000000?logo=hotwire&logoColor=white)](https://turbo.hotwired.dev)
[![TailwindCSS](https://img.shields.io/badge/Tailwind-4.x-38B2AC?logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Tests](https://img.shields.io/badge/Tests-301%20Passed-brightgreen)](tests/)
[![Static Analysis](https://img.shields.io/badge/Larastan-Level_5-blue)](phpstan.neon)

Cinema là nền tảng quản trị và đặt vé xem phim trực tuyến đạt chuẩn production, được xây dựng theo kiến trúc **Modular Monolith** với **Laravel 12**, **MySQL 8**, **Redis** và **Hotwire Turbo 8**. Hệ thống tích hợp quy trình tuần tự hóa đặt vé (booking serialization), xử lý thanh toán cổng PayOS, cập nhật trạng thái ghế theo thời gian thực (real-time) qua WebSockets, kiểm soát vé nguyên tử qua mã QR (atomic QR verification), cùng các cơ chế bảo mật và giám sát hệ thống chuẩn doanh nghiệp.

---

## 🏛️ Sơ Đồ Kiến Trúc Hệ Thống

```mermaid
flowchart TD
    subgraph Clients["Tầng Giao Diện (Clients & Interfaces)"]
        Storefront["Cổng Khách Hàng<br/>(Blade + Vite + CSS)"]
        AdminSPA["Trang Quản Trị Admin<br/>(Hotwire Turbo 8)"]
        POSDesk["Điểm Bán Vé POS & Soát Vé<br/>(QR Scanner / In Hóa Đơn Nhiệt)"]
    end

    subgraph Gateway["Tầng HTTP & API (/api/v1)"]
        Routing["Điều Hướng Route<br/>(Rate Limiting & RequestId)"]
        AuthMiddleware["Bảo Mật & Xác Thực<br/>(HttpOnly JWT / RBAC Policies)"]
        FormRequests["Form Request Validation<br/>& Chuẩn Hóa Dữ Liệu"]
    end

    subgraph Domain["Tầng Dịch Vụ Cốt Lõi (Domain Services)"]
        SeatSvc["SeatService<br/>(Distributed Lock & Giữ Ghế)"]
        OrderSvc["OrderService<br/>(Idempotent Checkout)"]
        PaymentSvc["PaymentService<br/>(Cổng PayOS & Webhook)"]
        FulfillSvc["OrderFulfillmentService<br/>(Xuất Vé Exactly-Once)"]
        ExpireSvc["OrderExpirationService<br/>(Tự Động Hoàn Tồn Kho)"]
    end

    subgraph Storage["Lưu Trữ & Hạ Tầng (Storage & Infrastructure)"]
        MySQL[("MySQL 8<br/>Row-Level Locks & Constraints")]
        RedisDB[("Redis 7<br/>Cache / Queues / Locks")]
        Reverb["Laravel Reverb<br/>(Sự Kiện WebSocket Realtime)"]
        Sentry["Sentry & Prometheus<br/>(Telemetry & Metrics)"]
    end

    Clients --> Gateway
    Gateway --> Domain
    Domain --> MySQL
    Domain --> RedisDB
    Domain --> Reverb
    Domain --> Sentry
```

---

## 💡 Điểm Sáng Kỹ Thuật (Key Engineering Highlights)

### 1. Cơ Chế Khóa Ghế & Xử Lý Đồng Thời Cao (High-Concurrency Seat Locking)
- **Kiểm soát đồng thời phân tán:** Kết hợp Redis distributed locks, MySQL row-level locks (`SELECT ... FOR UPDATE`) và compound unique indexes ở tầng cơ sở dữ liệu để đảm bảo triệt tiêu hoàn toàn race condition, chống đặt trùng ghế (double-booking) khi nhiều người cùng chọn ghế đồng thời.
- **Snapshot sơ đồ phòng chiếu bất biến:** Lưu trữ ảnh chụp layout ghế tại thời điểm tạo suất chiếu, bảo vệ các đơn hàng đang hoạt động không bị ảnh hưởng nếu rạp có điều chỉnh sơ đồ ghế sau đó.
- **Tự động hoàn trả ghế & khuyến mại:** Background worker tự động giải phóng các ghế giữ chỗ hết hạn (seat hold expiration) và hoàn lại lượt sử dụng mã ưu đãi / điểm thành viên.

### 2. Thanh Toán Chống Trùng Lặp & Webhook Replay-Safe (PayOS Gateway)
- **Idempotency Keys:** Bắt buộc áp dụng token định danh duy nhất cho mỗi lượt checkout, ngăn chặn trừ tiền 2 lần khi mạng gặp sự cố hoặc người dùng click đúp.
- **Xác thực chữ ký số Webhook:** Toàn bộ dữ liệu webhook từ PayOS được kiểm tra chữ ký mã hóa HMAC SHA-256 và lưu vết idempotent để đảm bảo **Exactly-Once Fulfillment** (xử lý đơn hàng đúng một lần duy nhất).
- **Xuất vé & Bắp nước nguyên tử:** Cập nhật trạng thái thanh toán, xuất mã vé và trừ kho combo bắp nước diễn ra trọn vẹn trong một Database Transaction duy nhất.

### 3. Tự Sinh Hợp Đồng OpenAPI 3.1 & Kiểm Thử Chống Sai Lệch (Zero-Drift)
- **Sinh OpenAPI tự động tại Runtime:** Bóc tách metadata từ route đăng ký và FormRequests trong Laravel để xuất tài liệu đặc tả OpenAPI 3.1 qua `OpenApiService`.
- **Ngăn ngừa lệch tài liệu (Drift Prevention):** Bộ test tự động (`OpenApiContractTest`) xác nhận 100% route và phương thức HTTP trong code luôn khớp với tài liệu API đã công bố.

### 4. Sơ Đồ Ghế Thời Gian Thực với Laravel Reverb
- **WebSocket Broadcasts:** Phát tín hiệu khóa ghế, nhả ghế và cập nhật trạng thái đơn hàng theo thời gian thực đến tất cả khách hàng đang xem sơ đồ cùng phòng chiếu, loại bỏ hoàn toàn việc phải liên tục gửi request thăm dò (HTTP polling).

### 5. Phân Quyền Vai Trò (RBAC) & Nhật Ký Kiểm Toán (Audit Trail)
- **Phân quyền chi tiết theo Policy:** Thiết lập ma trận quyền hạn chặt chẽ giữa các vai trò Admin, Quản lý chi nhánh, Nhân viên quầy vé (POS/Staff) và Khách hàng.
- **Audit Log bất biến:** Ghi lại toàn bộ lịch sử thao tác của quản trị viên và các thay đổi trạng thái tài chính để phục vụ kiểm toán và truy vết.

---

## 🚀 Hướng Dẫn Cài Đặt & Chạy Môi Trường Local

### Yêu cầu môi trường
- PHP 8.2+ (kèm các extension: `pdo_mysql`, `redis`, `gd`, `bcmath`)
- Node.js 20+ & npm
- MySQL 8.0+
- Redis 7.0+ (hoặc dùng driver array/sync cho môi trường dev)

### Các bước cài đặt

```powershell
# 1. Clone mã nguồn
git clone https://github.com/nguyentruongsonn/cinema.git
cd cinema

# 2. Cài đặt các gói phụ thuộc Backend & Frontend
composer install
npm ci

# 3. Cấu hình môi trường
Copy-Item .env.example .env
php artisan key:generate

# 4. Migrate database và tạo dữ liệu mẫu
php artisan migrate --seed

# 5. Build assets giao diện
npm run build

# 6. Khởi chạy máy chủ Laravel
php artisan serve
```

### Môi trường phát triển (Dev Server)

```powershell
composer dev
```
*Lệnh này khởi chạy đồng thời Laravel server, queue listener, log tailing và Vite dev server.*

---

## 🧪 Kiểm Thử Chất Lượng & Quality Gates

Dự án áp dụng quy trình kiểm soát chất lượng nghiêm ngặt được thực thi tự động cả ở máy local và GitHub Actions CI:

```powershell
# Kiểm tra tính toàn vẹn cấu trúc & route
composer test:structure

# Phân tích tĩnh mã nguồn (Larastan Level 5)
composer analyse

# Kiểm tra quy chuẩn định dạng code (Laravel Pint)
composer test:modern-format

# Kiểm tra cú pháp và bảo mật frontend
npm run lint
npm run test:frontend:syntax
npm run test:frontend:security

# Kiểm tra build production
npm run build

# Bộ kiểm thử Unit & Feature (301 tests)
php artisan test

# Kiểm thử giao diện tự động với Playwright (Headless Browser Smoke Tests)
npm run test:browser:smoke
npm run test:browser:admin-pos
npm run test:browser:auth-expiry
```

---

## 📡 Vận Hành & Giám Sát Hệ Thống (Operations)

- **Liveness Probe:** `GET /api/v1/health/live`
- **Readiness Probe:** `GET /api/v1/health/ready` *(kiểm tra kết nối database, cache read/write, độ trễ và độ dài hàng đợi)*
- **OpenAPI 3.1 Spec:** `GET /api/v1/docs/openapi.json`
- **Prometheus Metrics:** `GET /api/v1/internal/metrics` *(Yêu cầu `Authorization: Bearer <METRICS_TOKEN>`)*
- **Giám sát hàng đợi:** `php artisan queue:monitor-health --json`
- **Giám sát vận hành:** `php artisan operations:monitor-health --json`

---

## 📄 Giấy Phép (License)

Mã nguồn dự án được phát hành theo giấy phép [MIT License](LICENSE).
