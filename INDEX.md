# 📑 Cinema - Danh Mục Tài Liệu

## 🎯 Bắt Đầu Nhanh

1. **[QUICK_START.md](./QUICK_START.md)** ⭐ **BẮT ĐẦU TỪ ĐÂY**
   - 5 phút để chạy dự án
   - Tài khoản test
   - Troubleshooting cơ bản

2. **[README.md](./README.md)**
   - Giới thiệu dự án
   - Tính năng chính
   - Stack công nghệ
   - API endpoints overview

## 📚 Tài Liệu Chi Tiết

3. **[SETUP_GUIDE.md](./SETUP_GUIDE.md)**
   - Yêu cầu hệ thống
   - Cài đặt chi tiết từng bước
   - Cấu hình environment
   - Chạy migrations & seeding
   - Tài khoản test
   - Dữ liệu mẫu
   - API endpoints đầy đủ
   - Troubleshooting

4. **[ARCHITECTURE.md](./ARCHITECTURE.md)**
   - Tổng quan kiến trúc
   - Nguyên tắc thiết kế
   - Các thành phần chính
   - Luồng dữ liệu
   - Database schema
   - Bảo mật
   - Performance optimization

5. **[DEVELOPMENT_GUIDE.md](./DEVELOPMENT_GUIDE.md)**
   - Quy trình phát triển feature mới
   - Database layer
   - Model layer
   - Controller layer
   - Routes
   - Testing
   - Code standards
   - Debugging tips
   - Performance tips

6. **[PROJECT_SUMMARY.md](./PROJECT_SUMMARY.md)**
   - Hoàn thành
   - Thống kê dự án
   - Cách chạy dự án
   - Tính năng chính
   - API endpoints
   - Cấu trúc thư mục
   - Bước tiếp theo

## 🗂️ Cấu Trúc Dự Án

```
cinema/
├── 📄 Tài Liệu
│   ├── README.md                 # Giới thiệu
│   ├── QUICK_START.md            # Quick start ⭐
│   ├── SETUP_GUIDE.md            # Cài đặt chi tiết
│   ├── ARCHITECTURE.md           # Kiến trúc
│   ├── DEVELOPMENT_GUIDE.md      # Phát triển
│   ├── PROJECT_SUMMARY.md        # Tóm tắt
│   └── INDEX.md                  # File này
│
├── 📁 app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── MovieController.php
│   │   │   ├── TheaterController.php
│   │   │   ├── ScreenController.php
│   │   │   ├── SeatController.php
│   │   │   ├── ShowtimeController.php
│   │   │   ├── OrderController.php
│   │   │   └── PaymentController.php
│   │   └── Middleware/
│   │       ├── JwtMiddleware.php
│   │       └── AdminMiddleware.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Movie.php
│   │   ├── Theater.php
│   │   ├── Screen.php
│   │   ├── Seat.php
│   │   ├── Showtime.php
│   │   ├── Order.php
│   │   └── Payment.php
│   └── Traits/
│       └── ApiResponse.php
│
├── 📁 database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_05_29_131540_create_movies_table.php
│   │   ├── 2026_05_29_131541_create_theaters_table.php
│   │   ├── 2026_05_29_131542_create_screens_table.php
│   │   ├── 2026_05_29_131543_create_seats_table.php
│   │   ├── 2026_05_29_131543_create_showtimes_table.php
│   │   ├── 2026_05_29_131544_create_orders_table.php
│   │   └── 2026_05_29_131545_create_payments_table.php
│   └── seeders/
│       └── DatabaseSeeder.php
│
├── 📁 public/
│   ├── index.html
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
│
├── 📁 routes/
│   ├── api.php
│   └── web.php
│
├── 📁 config/
│   └── auth.php
│
└── 📁 storage/
    └── logs/
        └── laravel.log
```

## 🚀 Quy Trình Sử Dụng

### Lần Đầu Tiên
1. Đọc **QUICK_START.md** (5 phút)
2. Chạy các lệnh cài đặt
3. Truy cập http://localhost:8000

### Phát Triển Thêm
1. Đọc **DEVELOPMENT_GUIDE.md**
2. Tạo migration mới
3. Tạo model & controller
4. Thêm routes
5. Test API

### Hiểu Kiến Trúc
1. Đọc **ARCHITECTURE.md**
2. Xem database schema
3. Hiểu luồng dữ liệu
4. Tìm hiểu security

## 📊 Thống Kê

| Thành Phần | Số Lượng |
|-----------|---------|
| Controllers | 8 |
| Models | 8 |
| Migrations | 10 |
| Middleware | 2 |
| Traits | 1 |
| API Routes | 30+ |
| Database Tables | 8 |
| Seeded Records | 10,000+ |
| Documentation Files | 6 |

## 🔌 API Endpoints

### Authentication (5)
- POST /api/auth/register
- POST /api/auth/login
- POST /api/auth/logout
- GET /api/auth/me
- POST /api/auth/refresh

### Movies (5)
- GET /api/movies
- GET /api/movies/{id}
- POST /api/admin/movies
- PUT /api/admin/movies/{id}
- DELETE /api/admin/movies/{id}

### Theaters (5)
- GET /api/theaters
- GET /api/theaters/{id}
- POST /api/admin/theaters
- PUT /api/admin/theaters/{id}
- DELETE /api/admin/theaters/{id}

### Showtimes (5)
- GET /api/showtimes
- GET /api/showtimes/{id}
- POST /api/admin/showtimes
- PUT /api/admin/showtimes/{id}
- DELETE /api/admin/showtimes/{id}

### Seats (3)
- GET /api/seats/showtime/{showtimeId}
- POST /api/seats/lock
- DELETE /api/seats/unlock/{holdId}

### Orders (4)
- POST /api/orders
- GET /api/orders/{id}
- GET /api/orders/user/me
- PUT /api/orders/{id}/cancel

### Payments (3)
- POST /api/payments
- GET /api/payments/{id}
- POST /api/payments/{id}/verify

## 👤 Tài Khoản Test

### User
- Email: test@example.com
- Password: password

### Admin
- Email: admin@example.com
- Password: password

## 🎬 Dữ Liệu Mẫu

- 6 Phim
- 5 Rạp Chiếu
- 45 Phòng Chiếu
- 4,500 Ghế
- 3 Suất Chiếu/Ngày

## 🔗 Liên Kết Nhanh

| Tài Liệu | Mục Đích | Thời Gian |
|---------|---------|----------|
| QUICK_START.md | Chạy nhanh | 5 phút |
| README.md | Giới thiệu | 10 phút |
| SETUP_GUIDE.md | Cài đặt chi tiết | 20 phút |
| ARCHITECTURE.md | Hiểu kiến trúc | 30 phút |
| DEVELOPMENT_GUIDE.md | Phát triển | 45 phút |
| PROJECT_SUMMARY.md | Tóm tắt | 15 phút |

## 📞 Hỗ Trợ

### Lệnh Hữu Ích
```bash
# Xem logs
tail -f storage/logs/laravel.log

# Tinker shell
php artisan tinker

# Clear cache
php artisan cache:clear

# Refresh database
php artisan migrate:refresh --seed
```

### Tài Liệu Bên Ngoài
- Laravel: https://laravel.com/docs/11.x
- JWT: https://github.com/tymondesigns/jwt-auth
- Bootstrap: https://getbootstrap.com/docs/5.0/

## ✅ Checklist

- [x] Backend API (Laravel 11)
- [x] Database Schema
- [x] Frontend (HTML/CSS/JS)
- [x] JWT Authentication
- [x] Admin Middleware
- [x] Database Seeding
- [x] API Documentation
- [x] Setup Guide
- [x] Architecture Documentation
- [x] Development Guide
- [x] Quick Start Guide

## 🎉 Kết Luận

Cinema là một ứng dụng web đặt vé xem phim hoàn chỉnh, sẵn sàng cho production.

**Bắt đầu từ [QUICK_START.md](./QUICK_START.md) ngay bây giờ!** ⭐

---

**Phiên bản**: 1.0.0  
**Cập nhật**: 2026-05-29  
**Trạng thái**: ✅ Production Ready
