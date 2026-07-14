# 📋 Kế hoạch Code Review Toàn diện - Cinema Booking System

**Review Date:** 13/07/2026  
**Reviewer:** Senior Software Engineer (10+ years experience)  
**Scope:** Backend codebase - Controllers, Services, Requests, Middleware, Traits  
**Format:** Production PR Review Standard

---

## 🎯 Mục tiêu Review

1. Tìm bug tiềm ẩn (logic, edge case, race condition, security)
2. Đánh giá Clean Code và SOLID principles
3. Kiểm tra Laravel/PHP conventions
4. Phát hiện code smell, magic numbers, duplicated code
5. Đánh giá maintainability và readability
6. Kiểm tra performance (N+1, eager loading, memory)
7. Kiểm tra security (authorization, validation, injection)
8. Kiểm tra response standardization và exception handling
9. Kiểm tra business logic placement
10. Đề xuất refactoring

---

## 📊 Codebase Inventory

### Controllers (28 files)
**Main Controllers (17):**
- AuthController, BookingController, HomeController
- MovieController, OrderController, PaymentController
- PricePageController, PricingController, ProductController
- ProfileController, PromotionController, ScreenController
- SeatController ✅ (Đã review), ShowtimeController
- TheaterController, UserController

**Admin Controllers (11):**
- BannerController, BranchController, ComboController
- ComboStatController, DashboardController, FoodStatController
- PostController, ProductController, PromotionController
- RevenueController, ScreenController, SeatLayoutTemplateController
- TheaterController, TicketStatController

### Services (21 files)
- AuthService, ComboAnalyticsService, DashboardService
- FoodAnalyticsService, MovieService, OrderExpirationService
- OrderFulfillmentService, OrderService, PaymentService
- PayOSGateway, PricingService, ProductService
- PromotionService, RevenueService, ScreenService
- SeatService, ShowtimeService, TheaterService
- TicketAnalyticsService, TicketPricingService, UserService

### Requests (17+ files)
- Auth: Login, Register, ForgotPassword, ResetPassword, VerifyEmail
- User: ChangePassword, UpdateProfile
- Orders: StoreOrder, CancelOrder
- Payments: CreatePayment, StorePayment, VerifyPayment
- Seats: LockSeat
- Movies: StoreMovie, UpdateMovie
- Theaters: StoreTheater, UpdateTheater
- Admin/* (subfolder - cần explore)

### Middleware (8 files)
- AdminMiddleware, AuthenticateFromCookie, CookieToBearerToken
- JwtMiddleware, PermissionMiddleware, RoleMiddleware
- SecurityHeaders, VerifyPayOSWebhookSignature

### Traits (1 file)
- ApiResponse ✅ (Đã phân tích)

**Tổng: ~75+ files**

---

## 🚀 Phase 1: Critical Components (Priority: 🔴 CRITICAL)

**Mục tiêu:** Review các components quan trọng nhất, liên quan trực tiếp đến bảo mật và tiền bạc

### 1.1 Authentication & Authorization
- [ ] AuthController
- [ ] AuthService
- [ ] JwtMiddleware
- [ ] AdminMiddleware
- [ ] RoleMiddleware
- [ ] PermissionMiddleware

**Focus Areas:**
- Token security, session handling
- Password hashing, brute force protection
- Authorization logic, role checks
- CSRF, XSS vulnerabilities

### 1.2 Payment Flow
- [ ] PaymentController
- [ ] User/PaymentController
- [ ] PaymentService
- [ ] PayOSGateway
- [ ] VerifyPayOSWebhookSignature
- [ ] CreatePaymentRequest
- [ ] VerifyPaymentRequest

**Focus Areas:**
- Payment integrity, race conditions
- Webhook signature verification
- Double-payment prevention
- Refund logic
- Money calculation accuracy

### 1.3 Booking & Seats
- [ ] BookingController
- [ ] SeatController ✅ (Done)
- [ ] OrderController
- [ ] SeatService
- [ ] OrderService
- [ ] OrderExpirationService
- [ ] LockSeatRequest
- [ ] StoreOrderRequest

**Focus Areas:**
- Seat locking mechanism
- Race conditions in booking
- Order expiration logic
- Inventory consistency

**Estimated Time:** 2-3 days

---

## 🔧 Phase 2: Core Business Logic (Priority: 🟡 HIGH)

**Mục tiêu:** Review logic nghiệp vụ chính

### 2.1 Movie & Showtime Management
- [ ] MovieController
- [ ] ShowtimeController
- [ ] ScreenController
- [ ] TheaterController
- [ ] MovieService
- [ ] ShowtimeService
- [ ] ScreenService
- [ ] TheaterService

### 2.2 Pricing & Promotions
- [ ] PricingController
- [ ] PricePageController
- [ ] PromotionController
- [ ] PricingService
- [ ] TicketPricingService
- [ ] PromotionService

### 2.3 Products & Combos
- [ ] ProductController
- [ ] ProductService
- [ ] Admin/ProductController
- [ ] Admin/ComboController

**Estimated Time:** 2-3 days

---

## 📈 Phase 3: Admin & Analytics (Priority: 🟢 MEDIUM)

**Mục tiêu:** Review admin features và reporting

### 3.1 Admin Dashboard
- [ ] Admin/DashboardController
- [ ] DashboardService
- [ ] Admin/RevenueController
- [ ] RevenueService

### 3.2 Admin Analytics
- [ ] Admin/TicketStatController
- [ ] Admin/ComboStatController
- [ ] Admin/FoodStatController
- [ ] TicketAnalyticsService
- [ ] ComboAnalyticsService
- [ ] FoodAnalyticsService

### 3.3 Admin CRUD
- [ ] Admin/BannerController
- [ ] Admin/BranchController
- [ ] Admin/PostController
- [ ] Admin/PromotionController
- [ ] Admin/SeatLayoutTemplateController

**Estimated Time:** 2 days

---

## 🛠️ Phase 4: Supporting Components (Priority: 🟢 LOW)

**Mục tiêu:** Review các components hỗ trợ

### 4.1 User Management
- [ ] UserController
- [ ] ProfileController
- [ ] UserService
- [ ] UpdateProfileRequest
- [ ] ChangePasswordRequest

### 4.2 Miscellaneous
- [ ] HomeController
- [ ] OrderFulfillmentService

### 4.3 Middleware & Security
- [ ] AuthenticateFromCookie
- [ ] CookieToBearerToken
- [ ] SecurityHeaders

**Estimated Time:** 1 day

---

## 📝 Review Format Template

Mỗi vấn đề phải trình bày theo format:

```markdown
### Issue #X: [Tên vấn đề ngắn gọn]

**Severity:** Critical | High | Medium | Low
**Location:** File:Line
**Category:** Bug | Security | Performance | Code Quality | Maintainability

**Problem:**
[Mô tả vấn đề]

**Why This Matters:**
[Giải thích tại sao đây là vấn đề, hậu quả nếu không sửa]

**How to Fix:**
[Hướng dẫn cách sửa]

**Example Code:**
```php
// ❌ Trước
[Code hiện tại]

// ✅ Sau
[Code đề xuất]
```

**Impact:** Breaking | Non-breaking
**Estimated Fix Time:** [X minutes/hours]
```

---

## 📊 Priority Matrix

| Priority | Components | Est. Time | Start Date |
|----------|------------|-----------|------------|
| 🔴 CRITICAL | Auth, Payment, Booking | 2-3 days | Day 1 |
| 🟡 HIGH | Core Business Logic | 2-3 days | Day 4 |
| 🟢 MEDIUM | Admin & Analytics | 2 days | Day 7 |
| 🟢 LOW | Supporting Components | 1 day | Day 9 |

**Total Estimated Time:** 7-10 business days

---

## 🎯 Review Checklist (Per File)

### Code Quality
- [ ] Clean Code principles
- [ ] SOLID principles
- [ ] DRY (Don't Repeat Yourself)
- [ ] KISS (Keep It Simple, Stupid)
- [ ] Meaningful names
- [ ] Function/method size (<50 lines)
- [ ] Class size (<300 lines)
- [ ] Cyclomatic complexity

### Laravel Conventions
- [ ] Route Model Binding
- [ ] Form Requests
- [ ] API Resources
- [ ] Eloquent relationships
- [ ] Query scopes
- [ ] Accessors/Mutators
- [ ] Service Container
- [ ] Facades usage

### Performance
- [ ] N+1 queries (use eager loading)
- [ ] Unnecessary queries
- [ ] Missing indexes
- [ ] Memory usage
- [ ] Cache opportunities
- [ ] Database transactions
- [ ] Bulk operations

### Security
- [ ] SQL Injection
- [ ] XSS vulnerabilities
- [ ] CSRF protection
- [ ] Authentication checks
- [ ] Authorization checks
- [ ] Input validation
- [ ] Output sanitization
- [ ] Information leakage
- [ ] Rate limiting
- [ ] Mass assignment protection

### Business Logic
- [ ] Correct layer (Controller/Service/Model)
- [ ] Edge cases handled
- [ ] Error handling
- [ ] Transaction boundaries
- [ ] Race conditions
- [ ] Idempotency
- [ ] Data consistency

### API Design
- [ ] Response standardization
- [ ] HTTP status codes
- [ ] Error messages
- [ ] Pagination
- [ ] Filtering/Sorting
- [ ] API versioning

---

## 📈 Scoring System

Mỗi file sẽ được chấm điểm:

| Criteria | Weight | Score /10 |
|----------|--------|-----------|
| Code Quality | 15% | ? |
| Readability | 10% | ? |
| Maintainability | 15% | ? |
| Security | 30% | ? |
| Performance | 15% | ? |
| Laravel Best Practice | 15% | ? |

**Overall Score:** (Weighted Average) /10

**Approval Decision:**
- **8.0-10.0:** ✅ Approve
- **6.0-7.9:** ⚠️ Approve with minor comments
- **4.0-5.9:** ⛔ Request changes (non-blocking)
- **0-3.9:** 🔴 Request changes (BLOCKING)

---

## 🚦 Next Steps

### Immediate Actions:
1. ✅ **Complete** - SeatController review done
2. **START Phase 1** - Auth & Payment components
3. Create tracking spreadsheet for all files
4. Set up automated code analysis (PHPStan, Psalm)

### Tools to Use:
- PHPStan (level 8)
- Psalm
- Laravel Pint (code style)
- PHP_CodeSniffer
- PHPMD (PHP Mess Detector)

### Expected Deliverables:
- Individual review report per file (following template)
- Summary report per phase
- Consolidated findings document
- Refactoring priority list
- Estimated fix timeline

---

**Review Plan Created:** 13/07/2026  
**Status:** Ready to execute  
**First Phase Start:** Pending approval
