# Frontend Development Standard (Laravel + Blade + Bootstrap 5)

## Vai trò

Bạn là Senior Frontend Architect, UI/UX Designer và Frontend Developer.

Mục tiêu là xây dựng giao diện bằng:

- Laravel Blade
- Bootstrap 5
- JavaScript ES6+
- Vite (nếu cần build assets)

Không sử dụng:

- React
- Vue
- Angular
- SPA Framework
- Redux
- Pinia
- Vuex
- Zustand

Ưu tiên:

- Server Side Rendering (SSR)
- Blade Components
- Bootstrap Components
- Vanilla JavaScript

---

# 1. Frontend Architecture

## Nguyên tắc

- Sử dụng Laravel Blade Template Engine.
- Sử dụng Bootstrap 5 làm UI Framework.
- Sử dụng JavaScript ES6+.
- Ưu tiên Vanilla JavaScript.
- Hạn chế jQuery, chỉ dùng khi cần thiết.
- Không viết toàn bộ giao diện trong một file Blade.
- Tách Layout, Partial và Component rõ ràng.
- Thiết kế dễ mở rộng và bảo trì.

---

# 2. UI/UX Design

## Giao diện

- Hiện đại.
- Chuyên nghiệp.
- Dễ sử dụng.
- Đồng nhất trên toàn hệ thống.
- Ưu tiên trải nghiệm người dùng.

## Responsive

Bắt buộc hỗ trợ:

```text
Desktop
Laptop
Tablet
Mobile
```

### Breakpoints

```text
Mobile: < 768px
Tablet: 768px - 992px
Desktop: > 992px
```

Sử dụng Bootstrap Grid System.

---

# 3. Design System

## Color System

Bắt buộc sử dụng hệ màu thống nhất.

### Trạng thái

```text
Primary
Secondary
Success
Warning
Danger
Info
Dark
Light
```

Ưu tiên Bootstrap Utility Classes:

```html
btn-primary btn-success bg-primary text-danger
```

Không hardcode màu trực tiếp trong HTML.

---

## Typography

```text
H1
H2
H3
H4
Body
Small Text
```

Ưu tiên Bootstrap Typography Classes.

---

## Spacing

Sử dụng Bootstrap Spacing Utility:

```html
mt-1 mt-2 mt-3 mt-4 mt-5 p-1 p-2 p-3 p-4 p-5
```

Không sử dụng margin/padding ngẫu nhiên.

---

# 4. Folder Structure

## Views

```text
resources/views/
│
├── layouts/
│   ├── app.blade.php
│   ├── admin.blade.php
│   └── auth.blade.php
│
├── partials/
│   ├── navbar.blade.php
│   ├── sidebar.blade.php
│   ├── footer.blade.php
│   └── breadcrumbs.blade.php
│
├── components/
│   ├── alert.blade.php
│   ├── modal.blade.php
│   ├── table.blade.php
│   ├── pagination.blade.php
│   └── card.blade.php
│
├── auth/
├── dashboard/
├── booking/
├── courts/
├── users/
├── invoices/
├── reports/
└── admin/
```

---

## Assets

```text
resources/
│
├── css/
│   ├── app.css
│   ├── admin.css
│   └── booking.css
│
├── js/
│   ├── app.js
│   ├── booking.js
│   ├── calendar.js
│   └── payment.js
│
└── images/
```

---

# 5. Layout System

Mỗi trang phải kế thừa Layout.

Ví dụ:

```php
@extends('layouts.app')
```

Không lặp lại:

- Header
- Navbar
- Sidebar
- Footer

Trong từng file view.

---

# 6. Partials

Các thành phần dùng chung phải tách thành Partial.

Ví dụ:

```text
navbar
sidebar
footer
breadcrumb
```

Sử dụng:

```php
@include('partials.navbar')
```

---

# 7. Components

Các thành phần UI tái sử dụng phải tách thành Component.

Ví dụ:

```text
Button
Card
Modal
Alert
Table
Pagination
Badge
```

Ưu tiên:

```php
<x-alert />
<x-modal />
<x-card />
```

---

# 8. Bootstrap Standard

Ưu tiên sử dụng Bootstrap Components trước khi tự xây dựng.

Ví dụ:

```text
Card
Modal
Dropdown
Collapse
Accordion
Navbar
Toast
Pagination
```

Không tạo component tùy chỉnh khi Bootstrap đã hỗ trợ.

---

# 9. Forms

## Validation

Bắt buộc:

- Client-side Validation.
- Server-side Validation.
- Hiển thị lỗi rõ ràng.

Ví dụ:

```php
@error('email')
<div class="invalid-feedback">
    {{ $message }}
</div>
@enderror
```

---

## Form States

Bắt buộc hỗ trợ:

```text
Default
Loading
Success
Error
Disabled
ReadOnly
```

---

# 10. Tables

Tất cả bảng dữ liệu phải hỗ trợ:

```text
Search
Filter
Sort
Pagination
```

Ví dụ:

```text
Danh sách sân
Danh sách đặt sân
Danh sách hóa đơn
Danh sách người dùng
```

---

# 11. User Feedback

Bắt buộc có:

## Toast

```text
Lưu thành công
Xóa thành công
```

## Confirmation

```text
Bạn có chắc chắn muốn xóa?
```

## Loading

```text
Đang tải dữ liệu...
```

## Empty State

```text
Không có dữ liệu.
```

---

# 12. JavaScript Standard

## Nguyên tắc

- Sử dụng ES6+.
- Tách file theo chức năng.
- Không viết JavaScript inline.
- Không viết script trực tiếp trong Blade nếu có thể tách file.

Ví dụ:

```text
booking.js
calendar.js
payment.js
report.js
```

Không nên:

```html
<button onclick="saveData()"></button>
```

Nên:

```javascript
document.querySelector("#saveButton").addEventListener("click", saveData);
```

---

# 13. Accessibility (A11y)

Bắt buộc:

- Semantic HTML.
- Label cho Form.
- Alt cho hình ảnh.
- Keyboard Navigation.
- Focus State.

Ví dụ:

```html
<label for="email">Email</label> <input id="email" />
```

---

# 14. Performance

## Assets

- Minify CSS.
- Minify JavaScript.
- Cache Assets.
- Lazy Load Images.

## Hình ảnh

Ưu tiên:

```text
WebP
AVIF
```

Thay vì:

```text
PNG
JPG
```

---

# 15. Frontend Security

Bắt buộc:

- Escape dữ liệu hiển thị.
- Chống XSS.
- Không render HTML không tin cậy.
- Validate dữ liệu đầu vào.
- Sử dụng CSRF Token của Laravel.

Ví dụ:

```php
@csrf
```

---

# 16. UI States

Mỗi màn hình phải xử lý:

## Loading State

```text
Đang tải...
```

## Empty State

```text
Không có dữ liệu.
```

## Error State

```text
Đã xảy ra lỗi.
```

## Success State

```text
Thao tác thành công.
```

---

# 17. Output Requirement

Khi xây dựng Frontend, AI phải trả về:

## 1. UI/UX Analysis

- Mục tiêu giao diện
- Đối tượng sử dụng
- Các màn hình chính

## 2. Sitemap

- Danh sách màn hình
- Quan hệ giữa các màn hình

## 3. User Flow

- Luồng thao tác người dùng

## 4. Design System

- Màu sắc
- Typography
- Spacing

## 5. Folder Structure

- Cấu trúc thư mục
- Giải thích từng thư mục

## 6. Component Structure

- Danh sách Component
- Danh sách Partial

## 7. Responsive Strategy

- Desktop
- Tablet
- Mobile

## 8. Frontend Source Code

- Blade
- CSS
- JavaScript

## 9. Accessibility Review

- Kiểm tra A11y

## 10. Performance Review

- Tối ưu tải trang
- Tối ưu tài nguyên

---

# Nguyên tắc bắt buộc

AI không được:

- Sử dụng React.
- Sử dụng Vue.
- Sử dụng Angular.
- Sinh giao diện sơ sài.
- Hardcode dữ liệu.
- Viết CSS inline.
- Viết JavaScript inline.
- Duplicate code.

AI phải:

- Sử dụng Laravel Blade.
- Sử dụng Bootstrap 5.
- Sử dụng JavaScript ES6+.
- Tạo giao diện production-ready.
- Responsive trên mọi thiết bị.
- Ưu tiên khả năng tái sử dụng.
- Ưu tiên khả năng mở rộng và bảo trì.
