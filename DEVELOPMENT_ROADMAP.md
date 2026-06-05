# KẾ HOẠCH XÂY DỰNG WEBSITE ĐẶT VÉ XEM PHIM - QUY TRÌNH CHUẨN

## 📋 TỔNG QUAN DỰ ÁN

**Tên dự án**: Cinema Ticket Booking System  
**Stack công nghệ**: Laravel 11 + Bootstrap 5 + JWT + RESTful API + MySQL  
**Mục tiêu**: Xây dựng hệ thống đặt vé xem phim online hoàn chỉnh, production-ready

---

## 🎯 GIAI ĐOẠN 1: FOUNDATION (ĐÃ HOÀN THÀNH ✅)

### 1.1 Setup & Infrastructure
- [x] Khởi tạo Laravel project
- [x] Cấu hình database MySQL
- [x] Setup JWT authentication
- [x] Tạo migrations từ database schema
- [x] Tạo seeders với dữ liệu mẫu
- [x] Setup Git repository
- [x] Tạo documentation cơ bản

### 1.2 Backend Core
- [x] Tạo Models với relationships
- [x] Xây dựng Controllers cơ bản (Auth, Movie, Theater, Screen, Seat, Showtime, Order, Payment)
- [x] Thiết lập RESTful API routes
- [x] Implement JWT middleware
- [x] Implement Admin middleware
- [x] Tạo ApiResponse trait

### 1.3 Frontend Skeleton
- [x] Setup Bootstrap 5
- [x] Tạo HTML structure cơ bản
- [x] Setup CSS với responsive design
- [x] Setup JavaScript với FetchAPI

**Thời gian**: 2-3 ngày  
**Trạng thái**: ✅ HOÀN THÀNH

---

## 🚀 GIAI ĐOẠN 2: CORE FEATURES (TIẾP THEO)

### 2.1 Authentication & Authorization (2 ngày)

#### Backend
- [ ] Hoàn thiện AuthController
  - [ ] Email verification
  - [ ] Password reset
  - [ ] Remember me functionality
  - [ ] Social login (Google, Facebook) - optional
- [ ] Implement Role-based Access Control (RBAC)
  - [ ] User roles: Guest, Member, Admin, Super Admin
  - [ ] Permission system
  - [ ] Middleware cho từng role
- [ ] API endpoints:
  - [ ] `POST /api/auth/verify-email`
  - [ ] `POST /api/auth/forgot-password`
  - [ ] `POST /api/auth/reset-password`
  - [ ] `GET /api/auth/user/profile`
  - [ ] `PUT /api/auth/user/profile`

#### Frontend
- [ ] Trang đăng ký (Register)
- [ ] Trang đăng nhập (Login)
- [ ] Trang quên mật khẩu
- [ ] Trang profile người dùng
- [ ] Form validation
- [ ] Error handling & user feedback

#### Testing
- [ ] Unit tests cho AuthController
- [ ] Integration tests cho auth flow
- [ ] Test JWT token expiration & refresh

---

### 2.2 Movie Management (3 ngày)

#### Backend
- [ ] Hoàn thiện MovieController
  - [ ] CRUD operations
  - [ ] Search & filter (theo thể loại, năm, rating)
  - [ ] Pagination
  - [ ] Sort (mới nhất, phổ biến nhất, rating cao nhất)
- [ ] Upload & quản lý hình ảnh phim
  - [ ] Poster upload
  - [ ] Trailer URL
  - [ ] Gallery images
- [ ] API endpoints:
  - [ ] `GET /api/movies?page=1&limit=12&category=action&sort=latest`
  - [ ] `GET /api/movies/{id}`
  - [ ] `GET /api/movies/now-showing`
  - [ ] `GET /api/movies/coming-soon`
  - [ ] `GET /api/movies/search?q=keyword`
  - [ ] Admin: `POST /api/admin/movies`
  - [ ] Admin: `PUT /api/admin/movies/{id}`
  - [ ] Admin: `DELETE /api/admin/movies/{id}`

#### Frontend
- [ ] Trang chủ (Homepage)
  - [ ] Hero slider với phim nổi bật
  - [ ] Danh sách phim đang chiếu
  - [ ] Danh sách phim sắp chiếu
  - [ ] Search bar
- [ ] Trang danh sách phim (Movie List)
  - [ ] Grid layout responsive
  - [ ] Filter sidebar (thể loại, năm, rating)
  - [ ] Pagination
  - [ ] Sort options
- [ ] Trang chi tiết phim (Movie Detail)
  - [ ] Thông tin phim đầy đủ
  - [ ] Trailer player
  - [ ] Gallery
  - [ ] Danh sách suất chiếu
  - [ ] Reviews & ratings
  - [ ] Nút "Đặt vé ngay"

#### Testing
- [ ] Unit tests cho MovieController
- [ ] Test upload images
- [ ] Test search & filter logic

---

### 2.3 Theater & Screen Management (2 ngày)

#### Backend
- [ ] Hoàn thiện TheaterController
  - [ ] CRUD operations
  - [ ] Get theaters by location/city
  - [ ] Get screens by theater
- [ ] Hoàn thiện ScreenController
  - [ ] CRUD operations
  - [ ] Seat layout management
- [ ] API endpoints:
  - [ ] `GET /api/theaters?city=hanoi`
  - [ ] `GET /api/theaters/{id}`
  - [ ] `GET /api/theaters/{id}/screens`
  - [ ] Admin: CRUD endpoints

#### Frontend
- [ ] Trang danh sách rạp
  - [ ] Filter theo thành phố
  - [ ] Map integration (Google Maps) - optional
  - [ ] Thông tin rạp (địa chỉ, hotline, facilities)
- [ ] Trang chi tiết rạp
  - [ ] Danh sách phòng chiếu
  - [ ] Lịch chiếu theo rạp

#### Testing
- [ ] Unit tests cho Theater & Screen controllers
- [ ] Test seat layout generation

---

### 2.4 Showtime Management (2 ngày)

#### Backend
- [ ] Hoàn thiện ShowtimeController
  - [ ] CRUD operations
  - [ ] Get showtimes by movie
  - [ ] Get showtimes by theater
  - [ ] Get showtimes by date range
  - [ ] Dynamic pricing logic
- [ ] API endpoints:
  - [ ] `GET /api/showtimes?movie_id=1&date=2026-05-30`
  - [ ] `GET /api/showtimes?theater_id=1&date=2026-05-30`
  - [ ] `GET /api/showtimes/{id}`
  - [ ] Admin: CRUD endpoints

#### Frontend
- [ ] Trang lịch chiếu
  - [ ] Calendar view
  - [ ] Filter theo phim/rạp/ngày
  - [ ] Grid layout hiển thị suất chiếu
- [ ] Component chọn suất chiếu
  - [ ] Date picker
  - [ ] Time slots
  - [ ] Theater selection
  - [ ] Format & subtitle info

#### Testing
- [ ] Unit tests cho ShowtimeController
- [ ] Test pricing logic
- [ ] Test date/time filtering

---

## 🎫 GIAI ĐOẠN 3: BOOKING SYSTEM (QUAN TRỌNG NHẤT)

### 3.1 Seat Selection & Locking (4 ngày)

#### Backend
- [ ] Hoàn thiện SeatController
  - [ ] Get available seats by showtime
  - [ ] Lock seats (temporary hold)
  - [ ] Unlock seats (release hold)
  - [ ] Auto-release expired locks (cron job)
- [ ] Implement Seat Locking Logic
  - [ ] Redis cache cho seat status
  - [ ] Lock timeout (10-15 phút)
  - [ ] Concurrent booking handling
  - [ ] Race condition prevention
- [ ] API endpoints:
  - [ ] `GET /api/seats/showtime/{showtimeId}`
  - [ ] `POST /api/seats/lock` (body: seat_ids, showtime_id)
  - [ ] `DELETE /api/seats/unlock/{holdId}`
  - [ ] `GET /api/seats/status/{showtimeId}` (realtime status)

#### Frontend
- [ ] Trang chọn ghế (Seat Selection)
  - [ ] Interactive seat map
  - [ ] Color coding (available, selected, booked, locked by others)
  - [ ] Seat type indicators (Standard, VIP, Couple)
  - [ ] Screen position indicator
  - [ ] Real-time seat status updates
  - [ ] Price calculation
  - [ ] Countdown timer (thời gian giữ ghế)
- [ ] Realtime Updates
  - [ ] WebSocket/Pusher integration
  - [ ] Auto-refresh seat status
  - [ ] Notification khi ghế bị người khác chọn

#### Testing
- [ ] Unit tests cho SeatController
- [ ] Integration tests cho seat locking
- [ ] Load testing cho concurrent bookings
- [ ] Test auto-release mechanism

---

### 3.2 Order Processing (3 ngày)

#### Backend
- [ ] Hoàn thiện OrderController
  - [ ] Create order
  - [ ] Calculate total price (seats + promotions)
  - [ ] Apply discount codes
  - [ ] Order validation
  - [ ] Get user orders
  - [ ] Cancel order
- [ ] Implement Order State Machine
  - [ ] States: pending, confirmed, cancelled, expired
  - [ ] State transitions
  - [ ] Auto-cancel expired orders
- [ ] API endpoints:
  - [ ] `POST /api/orders` (create order)
  - [ ] `GET /api/orders/{id}`
  - [ ] `GET /api/orders/user/me`
  - [ ] `PUT /api/orders/{id}/cancel`
  - [ ] `POST /api/orders/apply-promotion`

#### Frontend
- [ ] Trang xác nhận đơn hàng (Order Confirmation)
  - [ ] Order summary
  - [ ] Seat details
  - [ ] Price breakdown
  - [ ] Promotion code input
  - [ ] Terms & conditions checkbox
  - [ ] Nút "Tiếp tục thanh toán"
- [ ] Trang lịch sử đơn hàng (Order History)
  - [ ] Danh sách đơn hàng
  - [ ] Filter & search
  - [ ] Order status
  - [ ] Download ticket/invoice

#### Testing
- [ ] Unit tests cho OrderController
- [ ] Test order validation
- [ ] Test promotion logic
- [ ] Test order state transitions

---

### 3.3 Payment Integration (4 ngày)

#### Backend
- [ ] Hoàn thiện PaymentController
  - [ ] Create payment
  - [ ] Verify payment
  - [ ] Handle payment callback
  - [ ] Refund logic
- [ ] Integrate Payment Gateways
  - [ ] VNPay integration
  - [ ] MoMo integration (optional)
  - [ ] ZaloPay integration (optional)
  - [ ] COD (Cash on Delivery) - optional
- [ ] Payment Security
  - [ ] Signature verification
  - [ ] Idempotency keys
  - [ ] Transaction logging
- [ ] API endpoints:
  - [ ] `POST /api/payments` (initiate payment)
  - [ ] `GET /api/payments/{id}`
  - [ ] `POST /api/payments/{id}/verify`
  - [ ] `POST /api/payments/callback/vnpay`
  - [ ] `POST /api/payments/callback/momo`

#### Frontend
- [ ] Trang thanh toán (Payment)
  - [ ] Payment method selection
  - [ ] Payment gateway redirect
  - [ ] Loading states
  - [ ] Error handling
- [ ] Trang kết quả thanh toán (Payment Result)
  - [ ] Success page
  - [ ] Failure page
  - [ ] Ticket display
  - [ ] Download/Print ticket
  - [ ] QR code for ticket verification

#### Testing
- [ ] Unit tests cho PaymentController
- [ ] Integration tests với payment gateways (sandbox)
- [ ] Test payment callback handling
- [ ] Test refund logic

---

## 📱 GIAI ĐOẠN 4: ADMIN DASHBOARD

### 4.1 Admin Authentication & Layout (2 ngày)

#### Backend
- [ ] Admin authentication
- [ ] Admin authorization middleware
- [ ] Admin API endpoints protection

#### Frontend
- [ ] Admin login page
- [ ] Admin layout template
  - [ ] Sidebar navigation
  - [ ] Top navbar
  - [ ] Breadcrumbs
  - [ ] Footer
- [ ] Dashboard homepage
  - [ ] Statistics cards
  - [ ] Charts (revenue, bookings, users)
  - [ ] Recent activities

---

### 4.2 Admin CRUD Pages (5 ngày)

#### Movies Management
- [ ] Danh sách phim
- [ ] Thêm phim mới
- [ ] Sửa phim
- [ ] Xóa phim
- [ ] Upload images

#### Theaters & Screens Management
- [ ] Danh sách rạp
- [ ] CRUD rạp
- [ ] Danh sách phòng chiếu
- [ ] CRUD phòng chiếu
- [ ] Seat layout editor

#### Showtimes Management
- [ ] Danh sách suất chiếu
- [ ] Tạo suất chiếu
- [ ] Bulk create showtimes
- [ ] Sửa/xóa suất chiếu
- [ ] Calendar view

#### Orders & Payments Management
- [ ] Danh sách đơn hàng
- [ ] Chi tiết đơn hàng
- [ ] Order status management
- [ ] Payment history
- [ ] Refund processing

#### Users Management
- [ ] Danh sách users
- [ ] User details
- [ ] Role assignment
- [ ] Ban/unban users

#### Promotions Management
- [ ] Danh sách khuyến mãi
- [ ] CRUD promotions
- [ ] Promotion usage tracking

---

### 4.3 Admin Reports & Analytics (3 ngày)

- [ ] Revenue reports
  - [ ] Daily/weekly/monthly revenue
  - [ ] Revenue by theater
  - [ ] Revenue by movie
- [ ] Booking reports
  - [ ] Booking statistics
  - [ ] Popular movies
  - [ ] Popular time slots
  - [ ] Seat occupancy rate
- [ ] User reports
  - [ ] User growth
  - [ ] Active users
  - [ ] User behavior analytics
- [ ] Export reports (PDF, Excel)

---

## 🎨 GIAI ĐOẠN 5: UI/UX ENHANCEMENT

### 5.1 UI Polish (3 ngày)

- [ ] Responsive design refinement
  - [ ] Mobile optimization
  - [ ] Tablet optimization
  - [ ] Desktop optimization
- [ ] Loading states & skeletons
- [ ] Empty states
- [ ] Error states
- [ ] Success messages
- [ ] Animations & transitions
- [ ] Dark mode (optional)

### 5.2 User Experience (2 ngày)

- [ ] Form validation improvements
- [ ] Better error messages
- [ ] Tooltips & help text
- [ ] Keyboard navigation
- [ ] Accessibility (WCAG compliance)
- [ ] Performance optimization
  - [ ] Image lazy loading
  - [ ] Code splitting
  - [ ] Minification

---

## 🔔 GIAI ĐOẠN 6: NOTIFICATIONS & COMMUNICATION

### 6.1 Email System (3 ngày)

#### Backend
- [ ] Setup email service (SMTP/SendGrid/Mailgun)
- [ ] Email templates
  - [ ] Welcome email
  - [ ] Email verification
  - [ ] Password reset
  - [ ] Booking confirmation
  - [ ] Payment receipt
  - [ ] Booking reminder (1 day before)
  - [ ] Cancellation confirmation
- [ ] Email queue system
- [ ] Email logging

#### Frontend
- [ ] Email preferences page
- [ ] Unsubscribe functionality

---

### 6.2 In-App Notifications (2 ngày)

#### Backend
- [ ] Notification system
- [ ] Notification types
  - [ ] Booking confirmation
  - [ ] Payment status
  - [ ] Promotion alerts
  - [ ] System announcements
- [ ] API endpoints:
  - [ ] `GET /api/notifications`
  - [ ] `PUT /api/notifications/{id}/read`
  - [ ] `DELETE /api/notifications/{id}`

#### Frontend
- [ ] Notification bell icon
- [ ] Notification dropdown
- [ ] Notification page
- [ ] Mark as read functionality
- [ ] Real-time notifications (WebSocket)

---

### 6.3 SMS Notifications (Optional - 2 ngày)

- [ ] SMS gateway integration (Twilio/Nexmo)
- [ ] SMS templates
- [ ] SMS for booking confirmation
- [ ] SMS for payment verification

---

## ⚡ GIAI ĐOẠN 7: PERFORMANCE & OPTIMIZATION

### 7.1 Backend Optimization (3 ngày)

- [ ] Database optimization
  - [ ] Query optimization
  - [ ] Index optimization
  - [ ] N+1 query prevention
- [ ] Caching strategy
  - [ ] Redis cache
  - [ ] Cache movies list
  - [ ] Cache showtimes
  - [ ] Cache seat availability
- [ ] API rate limiting
- [ ] Response compression
- [ ] Database connection pooling

---

### 7.2 Frontend Optimization (2 ngày)

- [ ] Asset optimization
  - [ ] Image compression
  - [ ] CSS minification
  - [ ] JS minification
- [ ] Lazy loading
- [ ] Code splitting
- [ ] Service Worker (PWA) - optional
- [ ] CDN integration

---

### 7.3 Monitoring & Logging (2 ngày)

- [ ] Application monitoring
  - [ ] Laravel Telescope
  - [ ] Error tracking (Sentry)
  - [ ] Performance monitoring
- [ ] Logging strategy
  - [ ] Application logs
  - [ ] Error logs
  - [ ] Audit logs
  - [ ] Payment logs
- [ ] Health check endpoints

---

## 🧪 GIAI ĐOẠN 8: TESTING & QUALITY ASSURANCE

### 8.1 Automated Testing (5 ngày)

#### Backend Tests
- [ ] Unit tests
  - [ ] Models
  - [ ] Controllers
  - [ ] Services
  - [ ] Helpers
- [ ] Integration tests
  - [ ] API endpoints
  - [ ] Authentication flow
  - [ ] Booking flow
  - [ ] Payment flow
- [ ] Feature tests
  - [ ] Complete user journeys
- [ ] Test coverage > 80%

#### Frontend Tests
- [ ] Unit tests (Jest)
- [ ] Component tests
- [ ] E2E tests (Cypress/Playwright)
  - [ ] User registration & login
  - [ ] Movie browsing
  - [ ] Seat selection
  - [ ] Booking flow
  - [ ] Payment flow

---

### 8.2 Manual Testing (3 ngày)

- [ ] Functional testing
  - [ ] All features work as expected
  - [ ] Cross-browser testing
  - [ ] Mobile device testing
- [ ] Usability testing
  - [ ] User flow testing
  - [ ] UI/UX testing
- [ ] Security testing
  - [ ] SQL injection
  - [ ] XSS attacks
  - [ ] CSRF protection
  - [ ] Authentication bypass
- [ ] Performance testing
  - [ ] Load testing
  - [ ] Stress testing
  - [ ] Concurrent user testing

---

### 8.3 Bug Fixing & Refinement (3 ngày)

- [ ] Fix critical bugs
- [ ] Fix major bugs
- [ ] Fix minor bugs
- [ ] Code refactoring
- [ ] Documentation updates

---

## 🚀 GIAI ĐOẠN 9: DEPLOYMENT & DEVOPS

### 9.1 Server Setup (2 ngày)

- [ ] Choose hosting provider (AWS/DigitalOcean/Vultr)
- [ ] Server configuration
  - [ ] Ubuntu/CentOS setup
  - [ ] Nginx/Apache setup
  - [ ] PHP-FPM configuration
  - [ ] MySQL setup
  - [ ] Redis setup
- [ ] SSL certificate (Let's Encrypt)
- [ ] Domain configuration
- [ ] Firewall setup

---

### 9.2 CI/CD Pipeline (2 ngày)

- [ ] Git workflow
  - [ ] Branch strategy (main, develop, feature branches)
  - [ ] Pull request process
- [ ] CI/CD setup (GitHub Actions/GitLab CI)
  - [ ] Automated testing
  - [ ] Code quality checks
  - [ ] Automated deployment
- [ ] Environment management
  - [ ] Development
  - [ ] Staging
  - [ ] Production

---

### 9.3 Production Deployment (2 ngày)

- [ ] Database migration
- [ ] Environment variables setup
- [ ] File storage setup (S3/local)
- [ ] Queue worker setup
- [ ] Cron jobs setup
- [ ] Backup strategy
  - [ ] Database backups
  - [ ] File backups
  - [ ] Backup restoration testing
- [ ] Monitoring setup
  - [ ] Uptime monitoring
  - [ ] Error monitoring
  - [ ] Performance monitoring

---

## 📚 GIAI ĐOẠN 10: DOCUMENTATION & TRAINING

### 10.1 Technical Documentation (3 ngày)

- [ ] API documentation (Swagger/Postman)
- [ ] Database schema documentation
- [ ] Architecture documentation
- [ ] Deployment guide
- [ ] Troubleshooting guide
- [ ] Code comments & docblocks

---

### 10.2 User Documentation (2 ngày)

- [ ] User manual
- [ ] FAQ page
- [ ] Video tutorials
- [ ] Help center

---

### 10.3 Admin Training (1 ngày)

- [ ] Admin user guide
- [ ] Training sessions
- [ ] Support documentation

---

## 🎯 GIAI ĐOẠN 11: LAUNCH & POST-LAUNCH

### 11.1 Soft Launch (1 tuần)

- [ ] Beta testing với limited users
- [ ] Gather feedback
- [ ] Fix issues
- [ ] Performance monitoring
- [ ] Security audit

---

### 11.2 Official Launch (1 ngày)

- [ ] Marketing campaign
- [ ] Social media announcement
- [ ] Press release
- [ ] Email to registered users

---

### 11.3 Post-Launch Support (Ongoing)

- [ ] Monitor system health
- [ ] User support
- [ ] Bug fixes
- [ ] Feature requests tracking
- [ ] Regular updates

---

## 📊 TIMELINE TỔNG QUAN

| Giai đoạn | Thời gian ước tính | Trạng thái |
|-----------|-------------------|-----------|
| 1. Foundation | 2-3 ngày | ✅ HOÀN THÀNH |
| 2. Core Features | 9 ngày | 🔄 TIẾP THEO |
| 3. Booking System | 11 ngày | ⏳ CHỜ |
| 4. Admin Dashboard | 10 ngày | ⏳ CHỜ |
| 5. UI/UX Enhancement | 5 ngày | ⏳ CHỜ |
| 6. Notifications | 7 ngày | ⏳ CHỜ |
| 7. Performance & Optimization | 7 ngày | ⏳ CHỜ |
| 8. Testing & QA | 11 ngày | ⏳ CHỜ |
| 9. Deployment & DevOps | 6 ngày | ⏳ CHỜ |
| 10. Documentation & Training | 6 ngày | ⏳ CHỜ |
| 11. Launch & Post-Launch | 1 tuần + | ⏳ CHỜ |
| **TỔNG CỘNG** | **~12-14 tuần** | |

---

## 🎯 PRIORITIES & MILESTONES

### Milestone 1: MVP (Minimum Viable Product) - 4 tuần
- Authentication
- Movie browsing
- Seat selection
- Basic booking
- Basic payment
- User dashboard

### Milestone 2: Full Features - 8 tuần
- Admin dashboard
- Advanced booking features
- Multiple payment gateways
- Notifications
- Reports

### Milestone 3: Production Ready - 12 tuần
- Performance optimization
- Complete testing
- Documentation
- Deployment
- Launch

---

## 📝 NOTES & BEST PRACTICES

### Development Best Practices
1. **Code Quality**
   - Follow PSR-12 coding standards
   - Use meaningful variable/function names
   - Write clean, readable code
   - Comment complex logic

2. **Git Workflow**
   - Commit often with clear messages
   - Use feature branches
   - Code review before merge
   - Keep main branch stable

3. **Security**
   - Never commit sensitive data
   - Use environment variables
   - Validate all inputs
   - Sanitize outputs
   - Use prepared statements
   - Implement CSRF protection

4. **Performance**
   - Optimize database queries
   - Use caching strategically
   - Lazy load images
   - Minimize HTTP requests

5. **Testing**
   - Write tests as you code
   - Aim for high test coverage
   - Test edge cases
   - Automate testing

---

## 🔄 AGILE METHODOLOGY

### Sprint Planning (2 tuần/sprint)
- Sprint 1-2: Foundation + Core Features
- Sprint 3-4: Booking System
- Sprint 5-6: Admin Dashboard + UI Enhancement
- Sprint 7-8: Notifications + Optimization
- Sprint 9-10: Testing + Deployment
- Sprint 11-12: Documentation + Launch

### Daily Standup Questions
1. What did I accomplish yesterday?
2. What will I work on today?
3. Are there any blockers?

### Sprint Review & Retrospective
- Demo completed features
- Gather feedback
- Discuss what went well
- Discuss what can be improved
- Plan next sprint

---

## 📞 SUPPORT & RESOURCES

### Documentation
- Laravel: https://laravel.com/docs
- Bootstrap: https://getbootstrap.com/docs
- JWT: https://jwt.io/
- VNPay: https://sandbox.vnpayment.vn/apis/

### Tools
- Postman: API testing
- Laravel Telescope: Debugging
- Redis: Caching
- Git: Version control
- VS Code: IDE

---

**Lưu ý**: Đây là kế hoạch tổng quan. Thời gian thực tế có thể thay đổi tùy thuộc vào:
- Kỹ năng của team
- Độ phức tạp của requirements
- Các vấn đề phát sinh
- Thay đổi scope

**Khuyến nghị**: Bắt đầu với MVP, sau đó iteratively thêm features dựa trên user feedback.
