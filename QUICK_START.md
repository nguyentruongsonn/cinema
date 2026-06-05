# 🚀 Cinema - Quick Start Guide

## ⚡ 5 Phút Để Chạy Dự Án

### Bước 1: Cài Đặt Dependencies
```bash
cd c:\xampp\htdocs\cinema
composer install
```

### Bước 2: Cấu Hình Environment
```bash
cp .env.example .env
php artisan key:generate
```

### Bước 3: Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### Bước 4: Khởi Động Server
```bash
php artisan serve --host=localhost --port=8000
```

### Bước 5: Truy Cập Website
- **Website**: http://localhost:8000
- **API**: http://localhost:8000/api

## 👤 Tài Khoản Test

### User Account
```
Email: test@example.com
Password: password
```

### Admin Account
```
Email: admin@example.com
Password: password
```

## 🎬 Dữ Liệu Mẫu

Seeder đã tạo sẵn:
- **6 Phim**: Avengers, Shawshank Redemption, The Dark Knight, Inception, Interstellar, Pulp Fiction
- **5 Rạp Chiếu**: CGV Hà Nội, Lotte Cinema, BHD Star, CGV TP.HCM, Lotte Cinema TP.HCM
- **45 Phòng Chiếu**: 8-12 phòng mỗi rạp
- **4,500 Ghế**: 100 ghế mỗi phòng (10x10 grid)
- **Suất Chiếu**: 3 suất mỗi ngày (9h, 14h, 19h)

## 🔌 API Endpoints

### Xem Danh Sách Phim
```bash
curl http://localhost:8000/api/movies
```

### Đăng Nhập
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

### Xem Suất Chiếu
```bash
curl http://localhost:8000/api/showtimes
```

## 📚 Tài Liệu Đầy Đủ

- **README.md** - Giới thiệu dự án
- **SETUP_GUIDE.md** - Hướng dẫn cài đặt chi tiết
- **ARCHITECTURE.md** - Kiến trúc hệ thống
- **DEVELOPMENT_GUIDE.md** - Hướng dẫn phát triển
- **PROJECT_SUMMARY.md** - Tóm tắt dự án

## 🐛 Troubleshooting

### Lỗi: "Class not found"
```bash
composer dump-autoload
```

### Lỗi: "SQLSTATE error"
- Kiểm tra MySQL đang chạy
- Kiểm tra .env có đúng database info

### Lỗi: "Port 8000 đang dùng"
```bash
php artisan serve --host=localhost --port=8001
```

## ✅ Kiểm Tra Cài Đặt

```bash
# Kiểm tra database
php artisan tinker
>>> App\Models\Movie::count()  # Nên trả về 6

# Kiểm tra users
>>> App\Models\User::count()   # Nên trả về 2

# Kiểm tra theaters
>>> App\Models\Theater::count() # Nên trả về 5
```

## 🎯 Tiếp Theo

1. Đăng nhập với tài khoản test
2. Xem danh sách phim
3. Chọn suất chiếu
4. Chọn ghế ngồi
5. Đặt vé & thanh toán

---

**Phiên bản**: 1.0.0  
**Cập nhật**: 2026-05-29
