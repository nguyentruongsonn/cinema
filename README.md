# 🎬 Cinema - Website Đặt Vé Xem Phim

Một ứng dụng web hiện đại để đặt vé xem phim trực tuyến, được xây dựng với **Laravel**, **Bootstrap 5**, **JWT Authentication**, và **RESTful API**.

## ✨ Tính Năng Chính

### 👥 Người Dùng
- ✅ Đăng ký & Đăng nhập với JWT
- ✅ Xem danh sách phim & rạp chiếu
- ✅ Chọn suất chiếu & ghế ngồi
- ✅ Đặt vé & thanh toán
- ✅ Quản lý đơn hàng
- ✅ Hủy vé

### 🛠️ Admin
- ✅ Quản lý phim (CRUD)
- ✅ Quản lý rạp chiếu (CRUD)
- ✅ Quản lý suất chiếu (CRUD)
- ✅ Xem báo cáo doanh thu
- ✅ Quản lý người dùng

## 🏗️ Stack Công Nghệ

| Lớp | Công Nghệ |
|-----|-----------|
| **Backend** | Laravel 11, PHP 8.2 |
| **Database** | MySQL 5.7+ |
| **Authentication** | JWT (tymon/jwt-auth) |
| **API** | RESTful API |
| **Frontend** | HTML5, CSS3, JavaScript |
| **UI Framework** | Bootstrap 5 |
| **HTTP Client** | Fetch API |
| **Architecture** | MVC Pattern |

## 📁 Cấu Trúc Thư Mục

```
cinema/
├── app/
│   ├── Http/Controllers/        # API Controllers
│   ├── Http/Middleware/         # JWT & Admin Middleware
│   ├── Models/                  # Eloquent Models
│   └── Traits/                  # Reusable Traits
├── database/
│   ├── migrations/              # Database Schemas
│   └── seeders/                 # Sample Data
├── public/
│   ├── index.html               # Frontend Entry
│   ├── css/style.css            # Styles
│   └── js/app.js                # Frontend Logic
├── routes/
│   ├── api.php                  # API Routes
│   └── web.php                  # Web Routes
├── config/
│   └── auth.php                 # JWT Config
└── SETUP_GUIDE.md               # Installation Guide
```

## 🚀 Bắt Đầu Nhanh

### 1. Cài Đặt
```bash
cd c:\xampp\htdocs\cinema
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### 2. Chạy Server
```bash
php artisan serve --host=localhost --port=8000
```

### 3. Truy Cập
- **Website**: http://localhost:8000
- **API**: http://localhost:8000/api

### 4. Đăng Nhập Test
- Email: `test@example.com`
- Password: `password`

## 📚 Tài Liệu

- [SETUP_GUIDE.md](./SETUP_GUIDE.md) - Hướng dẫn cài đặt chi tiết
- [ARCHITECTURE.md](./ARCHITECTURE.md) - Kiến trúc hệ thống
- [API_DOCS.md](./API_DOCS.md) - Tài liệu API

## 🔌 API Endpoints

### Authentication
```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
POST   /api/auth/refresh
```

### Movies
```
GET    /api/movies
GET    /api/movies/{id}
POST   /api/admin/movies
PUT    /api/admin/movies/{id}
DELETE /api/admin/movies/{id}
```

### Theaters
```
GET    /api/theaters
GET    /api/theaters/{id}
POST   /api/admin/theaters
PUT    /api/admin/theaters/{id}
DELETE /api/admin/theaters/{id}
```

### Showtimes
```
GET    /api/showtimes
GET    /api/showtimes/{id}
POST   /api/admin/showtimes
PUT    /api/admin/showtimes/{id}
DELETE /api/admin/showtimes/{id}
```

### Orders & Payments
```
POST   /api/orders
GET    /api/orders/{id}
GET    /api/orders/user/me
PUT    /api/orders/{id}/cancel
POST   /api/payments
GET    /api/payments/{id}
POST   /api/payments/{id}/verify
```

## 🔐 Bảo Mật

- ✅ JWT Token Authentication
- ✅ Password Hashing (Bcrypt)
- ✅ Role-based Authorization
- ✅ Input Validation
- ✅ CORS Protection
- ✅ SQL Injection Prevention

## 📊 Dữ Liệu Mẫu

Seeder tạo sẵn:
- 6 phim phổ biến
- 5 rạp chiếu (Hà Nội & TP.HCM)
- 45 phòng chiếu
- 4,500 ghế ngồi
- 3 suất chiếu mỗi ngày

## 🎯 Quy Trình Phát Triển

### Code Standards
- ✅ PSR-12 PHP Coding Standard
- ✅ Clean Code Principles
- ✅ SOLID Principles
- ✅ MVC Architecture
- ✅ RESTful API Design

### Best Practices
- ✅ Eloquent ORM
- ✅ Route Model Binding
- ✅ Request Validation
- ✅ Exception Handling
- ✅ Middleware Pattern

## 🧪 Testing

### Test Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

### Test Get Movies
```bash
curl http://localhost:8000/api/movies
```

## 📝 Ghi Chú

- Tất cả responses là JSON
- JWT token lưu trong localStorage
- Seat locking có timeout
- Payment status tracking
- Order history management

## 🐛 Troubleshooting

| Vấn Đề | Giải Pháp |
|--------|----------|
| Database connection error | Kiểm tra MySQL & .env |
| JWT token invalid | Chạy `php artisan key:generate` |
| CORS error | Kiểm tra config/cors.php |
| Port 8000 đang dùng | Dùng port khác: `--port=8001` |

## 📞 Liên Hệ & Hỗ Trợ

- 📧 Email: support@cinema.local
- 🐛 Issues: GitHub Issues
- 📖 Docs: Xem SETUP_GUIDE.md

## 📄 License

MIT License - Tự do sử dụng cho mục đích cá nhân & thương mại

## 👨‍💻 Tác Giả

**Cinema Development Team**  
Phiên bản: 1.0.0  
Cập nhật: 2026-05-29

---

**Hãy bắt đầu xây dựng ứng dụng đặt vé xem phim của bạn ngay hôm nay! 🎬**
