# 📅 Kế Hoạch Toàn Bộ Dự Án Cinema Booking System

**Ngày Lập Kế Hoạch:** 10/6/2026  
**Mục Tiêu:** Sửa các lỗi, triển khai production  
**Thời Gian:** 3-4 tuần  
**Đội:** 2-3 developers

---

## 🎯 Mục Tiêu Chính

### Giai Đoạn 1: Sửa Lỗi Critical (Tuần 1)
- ✅ Sửa lỗi 401 Authentication
- ✅ Sửa lỗi 404 Showtimes
- ✅ Implement token refresh mechanism
- ✅ Seed dữ liệu production

### Giai Đoạn 2: Kiểm Thử Tự Động (Tuần 1-2)
- ✅ Viết 50+ unit tests
- ✅ Viết 30+ feature tests
- ✅ Đạt 70%+ coverage
- ✅ Cấu hình CI/CD

### Giai Đoạn 3: Infrastructure (Tuần 2-3)
- ✅ Docker & Docker Compose
- ✅ SSL/TLS certificates
- ✅ Automated backups
- ✅ Monitoring & alerting

### Giai Đoạn 4: Production Ready (Tuần 3-4)
- ✅ Load testing
- ✅ Performance optimization
- ✅ Deployment automation
- ✅ Go live

---

## 📊 TUẦN 1 - SỬA LỖI VÀ TESTING CƠ BẢN

### Thứ 2 - Ngày 1
**Sửa Lỗi 401 Authentication**

Công việc:
```
[ ] Kiểm tra auth.js line 274
[ ] Xác minh token được gửi với "Bearer " prefix
[ ] Kiểm tra localStorage lưu token đúng
[ ] Test login -> /auth/me flow
[ ] Implement token refresh logic
```

Deliverable:
- ✅ 401 error được sửa
- ✅ Token lưu/gửi đúng
- ✅ Refresh token hoạt động

### Thứ 3 - Ngày 2
**Sửa Lỗi 404 Showtimes**

Công việc:
```
[ ] Kiểm tra routes definition (/api/v1/showtimes)
[ ] Verify ShowtimeController có method
[ ] Seed dữ liệu showtimes
[ ] Test API endpoint
[ ] Verify frontend hiển thị đúng
```

Deliverable:
- ✅ 404 error được sửa
- ✅ Showtimes hiển thị trong list
- ✅ Data seeding hoạt động

### Thứ 4-5 - Ngày 3-4
**Viết Unit Tests (50 tests)**

Công việc:
```
[ ] Setup PHPUnit test structure
[ ] Write AuthService tests (10)
[ ] Write MovieService tests (10)
[ ] Write OrderService tests (10)
[ ] Write PaymentService tests (10)
[ ] Write Model tests (10)
[ ] Target: 60%+ coverage
```

Test cần:
- AuthService register/login/refresh
- MovieService fetch/search
- OrderService create/cancel
- Payment validation
- Model relationships

### Thứ 6 - Ngày 5
**Feature Tests & Setup CI/CD**

Công việc:
```
[ ] Write feature tests (30)
[ ] Setup GitHub Actions workflow
[ ] Configure code coverage reporting
[ ] Run full test suite
[ ] Target: 70%+ coverage
```

Feature tests:
- Login flow
- Movie booking flow
- Payment flow
- Admin operations
- Error handling

---

## 📊 TUẦN 2 - INFRASTRUCTURE & DEPLOYMENT

### Thứ 2-3 - Ngày 6-7
**Docker Setup**

Công việc:
```
[ ] Create Dockerfile (PHP + Laravel)
[ ] Create docker-compose.yml
[ ] Setup MySQL in container
[ ] Setup Redis in container
[ ] Setup Nginx proxy
[ ] Test local development
[ ] Document docker setup
```

Files:
- Dockerfile
- docker-compose.yml
- .dockerignore
- docker-entrypoint.sh

### Thứ 4 - Ngày 8
**SSL/TLS & Security**

Công việc:
```
[ ] Generate SSL certificates
[ ] Configure Nginx SSL
[ ] Setup security headers middleware
[ ] Implement global rate limiting
[ ] Configure CORS headers
[ ] Test HTTPS locally
```

Middleware:
- Security headers (HSTS, X-Frame-Options)
- CORS configuration
- Rate limiting on auth

### Thứ 5 - Ngày 9
**Backup & Monitoring Setup**

Công việc:
```
[ ] Configure automated database backup
[ ] Setup backup restoration testing
[ ] Add error tracking (Sentry)
[ ] Configure application monitoring
[ ] Setup log aggregation
[ ] Create monitoring dashboard
```

Tools:
- Database backup scripts
- Sentry error tracking
- Laravel Telescope
- ELK Stack (optional)

### Thứ 6 - Ngày 10
**Load Testing**

Công việc:
```
[ ] Setup Apache JMeter
[ ] Create load test scenarios
[ ] Test 100 concurrent users
[ ] Test 500 concurrent users
[ ] Identify bottlenecks
[ ] Document capacity limits
[ ] Performance report
```

Scenarios:
- Homepage load (100 users)
- Search movies (100 users)
- Booking flow (50 users)
- Payment processing (25 users)

---

## 📊 TUẦN 3 - OPTIMIZATION & PRODUCTION PREP

### Thứ 2-3 - Ngày 11-12
**Performance Optimization**

Công việc:
```
[ ] Implement Redis caching
[ ] Optimize database queries
[ ] Add query result caching
[ ] Implement HTTP caching
[ ] Optimize images
[ ] Minify CSS/JS
[ ] Run benchmarks
```

Caching:
- Movie list cache (1 hour)
- Showtime cache (30 minutes)
- User data cache (personal)
- API response cache

### Thứ 4 - Ngày 13
**Deployment Automation**

Công việc:
```
[ ] Create deployment script
[ ] Setup zero-downtime deployment
[ ] Create rollback procedures
[ ] Setup health check endpoints
[ ] Document deployment steps
[ ] Create runbooks
[ ] Test deployment process
```

Scripts:
- deploy.sh (main deployment)
- rollback.sh (rollback)
- health-check.php (endpoint)

### Thứ 5 - Ngày 14
**Final Testing & Documentation**

Công việc:
```
[ ] UAT testing
[ ] Security review
[ ] Performance verification
[ ] Backup verification
[ ] Disaster recovery test
[ ] Write deployment guide
[ ] Create operations manual
[ ] Team training
```

Documentation:
- Deployment guide
- Operations manual
- Troubleshooting guide
- On-call runbook

### Thứ 6 - Ngày 15
**Production Deployment**

Công việc:
```
[ ] Final pre-deployment checks
[ ] Deploy to production
[ ] Monitor for errors
[ ] Performance tracking
[ ] User feedback monitoring
[ ] Issue resolution if needed
```

---

## 📋 DANH SÁCH CHI TIẾT CÔNG VIỆC

### Phase 1: Bug Fixes (Tuần 1, Ngày 1-2)

#### Lỗi #1: 401 Authentication
**Time:** 4 giờ

```
1. Kiểm tra auth.js (30 phút)
   - Xem fetchAPI function
   - Xem cách gửi Authorization header
   
2. Kiểm tra localStorage (30 phút)
   - Đảm bảo token được lưu
   - Kiểm tra login response
   
3. Kiểm tra backend (1 giờ)
   - Routes có middleware 'jwt'?
   - AuthController method 'me' tồn tại?
   - JwtMiddleware hoạt động?
   
4. Implement refresh token (1.5 giờ)
   - Thêm refresh logic trong fetchAPI
   - Test flow: Login -> 1h -> Auto refresh
   
5. Testing (1 giờ)
   - Manual test login
   - Manual test /auth/me
   - Manual test expired token
```

#### Lỗi #2: 404 Showtimes
**Time:** 3 giờ

```
1. Kiểm tra routes (30 phút)
   - php artisan route:list | grep showtime
   - Verify endpoints tồn tại
   
2. Kiểm tra controller (1 giờ)
   - ShowtimeController methods
   - Query logic
   
3. Seed data (1 giờ)
   - Run ShowtimeSeeder
   - Verify data trong database
   - Test API endpoint
   
4. Frontend verification (30 phút)
   - Verify showtimes hiển thị
   - Check no console errors
```

### Phase 2: Testing (Tuần 1, Ngày 3-5)

#### Unit Tests (50 tests)
**Time:** 2 days

**AuthService (10 tests)**
```php
- testRegisterValidation()
- testLoginSuccess()
- testLoginInvalidCredentials()
- testRefreshToken()
- testLogout()
- testCheckAuthStatus()
- testPasswordReset()
- testPasswordChange()
- testEmailVerification()
- testOAuthLogin()
```

**MovieService (10 tests)**
```php
- testGetAllMovies()
- testSearchMovies()
- testFilterByCategory()
- testGetMovieDetail()
- testCreateMovie() [admin]
- testUpdateMovie() [admin]
- testDeleteMovie() [admin]
- testGetRelatedMovies()
- testTrendingMovies()
- testUpcomingMovies()
```

**OrderService (10 tests)**
- testCreateOrder()
- testValidateSeatAvailability()
- testApplyPromotion()
- testCalculateTotals()
- testCancelOrder()
- testExpireOldOrders()
- testGetUserOrders()
- testUpdateOrderStatus()
- testRefundOrder()
- testDuplicateDetection()

**PaymentService (10 tests)**
- testCreatePayment()
- testPayOSVerification()
- testPaymentStatusUpdate()
- testRefundProcessing()
- testWebhookValidation()
- testIdempotencyKey()
- testAmountValidation()
- testFailedPaymentHandling()
- testPaymentRetry()
- testTransactionRollback()

**Models (10 tests)**
- User relationships
- Movie relationships
- Order with items
- Payment with order
- SeatHold expiration
- RefreshToken validity
- Permission checking
- Role assignment
- Slug generation
- Soft deletes

#### Feature Tests (30 tests)
**Time:** 1 day

**Auth Flow (5 tests)**
```
- Register new user
- Login with email/password
- Refresh token expiry
- Logout and token invalidation
- Password reset flow
```

**Booking Flow (10 tests)**
```
- Browse movies
- Select showtimes
- Lock seats
- Create order
- Add products
- Apply promotions
- Validate total
- Cancel order
- Refund process
- View order history
```

**Payment Flow (8 tests)**
```
- Initiate PayOS payment
- Webhook notification
- Payment success
- Payment failure
- Payment cancellation
- Idempotency check
- Amount verification
- Order confirmation
```

**Admin Operations (7 tests)**
```
- Create movie
- Update movie
- Delete movie
- Manage showtimes
- View all orders
- Generate reports
- User management
```

### Phase 3: Infrastructure (Tuần 2-3)

#### Docker Setup (1 day)
```dockerfile
# Dockerfile
- PHP 8.2 image
- Laravel dependencies
- Composer install
- Artisan commands
```

```yaml
# docker-compose.yml
- PHP-FPM service
- MySQL service
- Redis service
- Nginx service
- PhpMyAdmin (dev)
```

#### SSL/TLS (4 hours)
```
- Generate self-signed or Let's Encrypt cert
- Configure Nginx SSL
- Test HTTPS
- Security headers
```

#### Monitoring (6 hours)
```
- Sentry error tracking
- Laravel Telescope (dev)
- Database monitoring
- API performance tracking
- Uptime monitoring
```

#### Backups (4 hours)
```
- Daily database backups
- 30-day retention
- Backup restoration test
- Disaster recovery plan
```

#### Load Testing (8 hours)
```
Apache JMeter scenarios:
- 100 concurrent users
- 500 concurrent users
- 1000 concurrent users (peak)
- Ramp-up over 10 minutes
- Duration: 30 minutes
```

### Phase 4: Production (Tuần 3-4)

#### Performance Optimization (2 days)
```
Redis caching:
- Movie listings
- Showtime data
- User sessions
- API responses

Database optimization:
- Index analysis
- Query optimization
- Connection pooling
- Read replicas

Frontend optimization:
- Minify CSS/JS
- Image optimization
- CDN integration
- Browser caching
```

#### Deployment Automation (1 day)
```
Scripts:
- deploy.sh
- rollback.sh
- health-check.php

GitHub Actions:
- Run tests
- Build Docker image
- Push to registry
- Deploy to production
```

#### Documentation (1 day)
```
- Deployment guide
- Operations manual
- Troubleshooting guide
- Runbook for on-call
- SLA documentation
```

---

## 👥 PHÂN CÔNG

### Developer 1: Backend/Testing
- Tuần 1: Sửa auth bugs + unit tests
- Tuần 2: Feature tests + infrastructure
- Tuần 3: Performance tuning
- Tuần 4: Production deployment

### Developer 2: DevOps/Infrastructure
- Tuần 1: Setup test environment
- Tuần 2: Docker + monitoring setup
- Tuần 3: Load testing + optimization
- Tuần 4: Deployment automation

### Developer 3: QA/Testing (Optional)
- Tuần 1-2: Test planning + UAT
- Tuần 3: Load testing analysis
- Tuần 4: Production monitoring

---

## 📈 CRITICAL PATH

```
Week 1:
├─ Day 1-2: Fix bugs (4-5 hours)
├─ Day 3-5: Write tests (16 hours)
└─ Daily: Run tests, fix failures

Week 2:
├─ Day 6-7: Docker setup (8 hours)
├─ Day 8-9: Monitoring (8 hours)
├─ Day 10: Load testing (8 hours)
└─ Daily: Monitor, optimize

Week 3:
├─ Day 11-12: Performance tuning (8 hours)
├─ Day 13: Deployment automation (8 hours)
├─ Day 14: Final testing (8 hours)
└─ Daily: Documentation

Week 4:
├─ Day 15: Go-live prep (4 hours)
├─ Deployment (2 hours)
└─ Post-deployment monitoring (ongoing)
```

---

## ✅ SUCCESS METRICS

### Tuần 1
- ✅ All critical bugs fixed
- ✅ 70%+ test coverage
- ✅ CI/CD pipeline running
- ✅ All tests passing

### Tuần 2
- ✅ Docker working locally
- ✅ Monitoring configured
- ✅ Load tests completed
- ✅ Bottlenecks identified

### Tuần 3
- ✅ Performance targets met
- ✅ Deployment automated
- ✅ Full documentation
- ✅ Team trained

### Tuần 4
- ✅ Live in production
- ✅ Zero critical errors
- ✅ <100ms response time
- ✅ 99.5%+ uptime

---

## 🚨 RISKS & MITIGATION

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Tests fail to run | Medium | High | Buffer day for debugging |
| Docker issues | Medium | High | Use existing images |
| Performance below target | Medium | High | Identify bottlenecks early |
| Deployment fails | Low | Critical | Practice on staging first |
| Data loss during migration | Low | Critical | Full backup before migration |

---

**Kế Hoạch Được Phê Duyệt:** 10/6/2026  
**Người Lập:** Development Team  
**Next Review:** Sau tuần 1