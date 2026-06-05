# 🎬 CINEMA BOOKING SYSTEM - MASTER PLAN
## Kế hoạch Tổng Thể Xây Dựng Website Đặt Vé Xem Phim

> **Executive Summary**: Tài liệu tổng hợp toàn bộ quy trình, quy tắc, skills và lộ trình phát triển hệ thống đặt vé xem phim online production-ready.

---

## 📊 TỔNG QUAN DỰ ÁN

### Thông Tin Cơ Bản
- **Tên dự án**: Cinema Ticket Booking System
- **Mục tiêu**: Hệ thống đặt vé xem phim online hoàn chỉnh, chuyên nghiệp, production-ready
- **Thời gian ước tính**: 12-14 tuần (3-3.5 tháng)
- **Phương pháp**: Agile/Scrum với 2-week sprints

### Tech Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Backend** | Laravel | 11.x | API & Server-side logic |
| **Database** | MySQL | 8.0+ | Data persistence |
| **Authentication** | JWT | tymon/jwt-auth | Token-based auth |
| **Frontend** | Bootstrap | 5.3 | UI framework |
| **JavaScript** | Vanilla JS | ES6+ | Client-side logic |
| **API Pattern** | RESTful API | - | Backend-Frontend communication |
| **HTTP Client** | Fetch API | Native | AJAX requests |
| **Caching** | Redis | 7.x | Performance optimization |
| **Real-time** | WebSocket/Pusher | - | Live seat updates |
| **Payment** | VNPay/MoMo | - | Payment gateway |

---

## 🎯 SKILLS CẦN THIẾT

### 1. Backend Developer Skills

#### Core Skills (Bắt buộc)
- ✅ **PHP 8.2+**: OOP, Traits, Interfaces, Type declarations
- ✅ **Laravel 11**: 
  - MVC architecture
  - Eloquent ORM (relationships, scopes, accessors)
  - Migrations & Seeders
  - Form Requests (validation)
  - Middleware
  - Service Container & Dependency Injection
- ✅ **Database**:
  - MySQL query optimization
  - Indexing strategies
  - Transactions
  - Relationships (1-1, 1-N, N-N)
- ✅ **JWT Authentication**:
  - Token generation & validation
  - Refresh token flow
  - Token blacklisting
- ✅ **RESTful API Design**:
  - HTTP methods (GET, POST, PUT, DELETE)
  - Status codes (200, 201, 400, 401, 403, 404, 422, 500)
  - API versioning
  - Response standardization

#### Advanced Skills (Nên có)
- ⭐ **Redis**: Caching strategies, seat locking
- ⭐ **Queue & Jobs**: Async processing, email queue
- ⭐ **Payment Integration**: VNPay, MoMo sandbox testing
- ⭐ **Real-time**: WebSocket, Pusher for live updates
- ⭐ **Testing**: PHPUnit, Feature tests, Unit tests

### 2. Frontend Developer Skills

#### Core Skills (Bắt buộc)
- ✅ **HTML5**: Semantic markup, accessibility (a11y)
- ✅ **CSS3**: 
  - Flexbox & Grid
  - Responsive design (mobile-first)
  - CSS animations
  - BEM-like naming convention
- ✅ **Bootstrap 5**:
  - Grid system
  - Utility classes
  - Components (Modal, Toast, Dropdown)
  - Responsive utilities
- ✅ **JavaScript ES6+**:
  - Async/Await
  - Fetch API
  - DOM manipulation
  - Event handling
  - Module pattern (IIFE)
- ✅ **Blade Templates**: Laravel templating engine

#### Advanced Skills (Nên có)
- ⭐ **Performance optimization**: Lazy loading, code splitting
- ⭐ **Accessibility**: WCAG 2.1 compliance, ARIA labels
- ⭐ **Progressive Enhancement**: Works without JS
- ⭐ **Browser DevTools**: Debugging, profiling

### 3. DevOps & Tools Skills

#### Bắt buộc
- ✅ **Git**: Branching, merging, pull requests
- ✅ **Composer**: PHP dependency management
- ✅ **npm/Yarn**: Node package management
- ✅ **Command Line**: Basic terminal commands

#### Nên có
- ⭐ **Docker**: Containerization
- ⭐ **CI/CD**: GitHub Actions, GitLab CI
- ⭐ **Server management**: Nginx, SSL, firewall
- ⭐ **Monitoring**: Laravel Telescope, Sentry

### 4. Soft Skills

- 📝 **Documentation**: Clear technical writing
- 🔍 **Code review**: Constructive feedback
- 🐛 **Debugging**: Problem-solving mindset
- 📊 **Time management**: Task estimation, deadline commitment
- 🤝 **Communication**: Team collaboration
- 🎯 **Attention to detail**: Quality-focused

---

## 📋 QUY TRÌNH PHÁT TRIỂN (DEVELOPMENT PROCESS)

### 1. Git Workflow

```
main (production)
  ↑
  merge via PR
  ↑
develop (staging)
  ↑
  merge via PR
  ↑
feature/{module-name} (development)
```

#### Quy tắc Git
1. **Branch naming**:
   - `feature/auth-system` - New features
   - `bugfix/seat-locking-issue` - Bug fixes
   - `hotfix/payment-critical` - Production hotfixes

2. **Commit messages** (Conventional Commits):
   ```
   feat: add JWT refresh token endpoint
   fix: resolve seat double booking race condition
   refactor: extract movie service logic from controller
   docs: update API documentation
   test: add unit tests for booking flow
   ```

3. **Pull Request Process**:
   - Create PR từ `feature/*` → `develop`
   - Đặt title mô tả rõ ràng
   - Liệt kê changes trong description
   - Self-review code trước khi request review
   - Chờ approval (tối thiểu 1 reviewer)
   - Merge sau khi CI/CD pass

### 2. Development Cycle (Mỗi Feature)

```
1. Planning
   ↓
2. Database Design (Migration)
   ↓
3. Backend (Model → Service → Controller → Route)
   ↓
4. Testing Backend (Unit + Integration tests)
   ↓
5. Frontend (Blade → CSS → JS)
   ↓
6. Testing Frontend (Manual + E2E)
   ↓
7. Code Review
   ↓
8. Merge & Deploy to Staging
```

### 3. Daily Workflow

**Morning Standup** (9:00 AM - 15 phút):
- What I did yesterday?
- What I'll do today?
- Any blockers?

**Development** (9:15 AM - 12:00 PM):
- Focus time - implement tasks
- Code với standards (xem section 4)

**Lunch Break** (12:00 PM - 1:00 PM)

**Development** (1:00 PM - 5:00 PM):
- Continue implementation
- Code review others' PRs
- Testing

**End of Day** (5:00 PM - 5:30 PM):
- Commit work
- Update task status (Trello/Jira)
- Document blockers

### 4. Sprint Planning (2 tuần/sprint)

**Sprint Start** (Monday Week 1):
- Sprint planning meeting (2h)
- Chọn tasks từ backlog
- Estimate story points
- Assign tasks

**Sprint Progress** (Daily):
- Daily standup
- Update task board
- Pair programming khi cần

**Sprint End** (Friday Week 2):
- Sprint review (demo) - 1h
- Sprint retrospective - 1h
- Deploy to staging
- Plan next sprint

---

## 📏 QUY TẮC & STANDARDS

### 1. Backend Coding Standards

#### Controller Rules
```php
// ✅ GOOD - Thin Controller
class MovieController extends Controller
{
    public function index(MovieService $service): JsonResponse
    {
        $movies = $service->getNowShowing();
        return $this->successResponse($movies);
    }
}

// ❌ BAD - Fat Controller
public function index(): JsonResponse
{
    $movies = Movie::where(...)->with(...)->get();
    // Logic trực tiếp trong controller
}
```

**Quy tắc bắt buộc:**
- Controller methods ≤ 10 dòng
- Không query DB trực tiếp
- Không chứa business logic
- Sử dụng Form Request cho validation
- Sử dụng Service layer

#### API Response Format

```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... },
    "meta": { "page": 1, "total": 100 },
    "errors": null
}
```

#### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| **Tables** | snake_case, plural | `movies`, `showtimes` |
| **Columns** | snake_case | `release_date`, `poster_url` |
| **Models** | PascalCase, singular | `Movie`, `Showtime` |
| **Controllers** | PascalCase + Controller | `MovieController` |
| **Services** | PascalCase + Service | `MovieService` |
| **Routes** | kebab-case | `/api/now-showing` |
| **Variables** | camelCase | `$nowShowing` |
| **Constants** | UPPER_SNAKE_CASE | `MAX_SEATS` |

### 2. Frontend Coding Standards

#### JavaScript Pattern
```javascript
// ✅ GOOD - IIFE Module Pattern
(function () {
    'use strict';
    
    const API_BASE = '/api';
    
    async function fetchMovies() {
        const res = await fetch(`${API_BASE}/movies`);
        return res.json();
    }
    
    function init() {
        // Initialize page
    }
    
    document.addEventListener('DOMContentLoaded', init);
})();
```

#### CSS Naming (BEM-like)
```css
/* Block */
.cinema-movie-card { }

/* Element */
.cinema-movie-card__title { }
.cinema-movie-card__poster { }

/* Modifier */
.cinema-movie-card--featured { }
```

#### Responsive Breakpoints
- **Mobile**: < 576px
- **Tablet**: 576px - 991px
- **Desktop**: ≥ 992px
- **Large Desktop**: ≥ 1200px

### 3. Security Standards

| Risk | Prevention |
|------|-----------|
| **SQL Injection** | Eloquent ORM, prepared statements |
| **XSS** | Blade `{{ }}`, JS `textContent` |
| **CSRF** | `@csrf` token in forms |
| **Authentication** | JWT with refresh tokens |
| **Authorization** | Middleware + RBAC |
| **Rate Limiting** | `throttle:60,1` middleware |
| **Password** | `bcrypt`, min 8 chars |
| **HTTPS** | SSL certificate (Let's Encrypt) |

### 4. Performance Standards

**Backend:**
- API response time < 200ms (cached)
- API response time < 500ms (uncached)
- Database queries < 50ms
- Use eager loading (`with()`) to prevent N+1
- Cache frequently accessed data (Redis)

**Frontend:**
- First Contentful Paint (FCP) < 1.5s
- Largest Contentful Paint (LCP) < 2.5s
- Images lazy-loaded below fold
- JS deferred or async
- CSS minified
- Lighthouse score > 90

---

## ✅ COMPLETED SETUP TASKS (6/2/2026)

### Database & Authentication Fixes
- ✅ Import database từ sql.sql
- ✅ Fix users table structure (thêm username, avatar_url, last_login_at, last_login_ip)
- ✅ Fix roles table (thêm slug column + seed 4 default roles)
- ✅ Create refresh_tokens table migration
- ✅ Fix email validation (remove DNS check cho development)
- ✅ Fix Carbon date type casting (config values → int)
- ✅ **Test registration API thành công** ✓ HTTP 201

**Status**: Authentication system hoàn toàn hoạt động. Có thể tiếp tục phase 2.

---

## 🗓️ LỘTRÌNH PHÁT TRIỂN (11 GIAI ĐOẠN)

### Phase 1: Foundation ✅ (HOÀN THÀNH)
**Thời gian**: 2-3 ngày  
**Trạng thái**: DONE

- [x] Laravel project setup
- [x] Database schema & migrations
- [x] JWT authentication
- [x] Models with relationships
- [x] Basic controllers & routes
- [x] Bootstrap 5 setup
- [x] Base documentation

---

### Phase 2: Core Features 🔄 (ĐANG THỰC HIỆN)
**Thời gian**: 9 ngày  
**Trạng thái**: IN PROGRESS

#### 2.1 Authentication (2 ngày)
- [ ] Email verification
- [ ] Password reset
- [ ] User profile management
- [ ] Role-based access control (RBAC)

#### 2.2 Movie Management (3 ngày)
- [ ] Movie CRUD (Admin)
- [ ] Movie listing (Public)
- [ ] Movie detail page
- [ ] Search & filter
- [ ] Image upload

#### 2.3 Theater & Screen (2 ngày)
- [ ] Theater CRUD
- [ ] Screen CRUD
- [ ] Seat layout management

#### 2.4 Showtime Management (2 ngày)
- [ ] Showtime CRUD
- [ ] Dynamic pricing
- [ ] Calendar view

---

### Phase 3: Booking System ⏳ (QUAN TRỌNG NHẤT)
**Thời gian**: 11 ngày

#### 3.1 Seat Selection (4 ngày)
- [ ] Interactive seat map
- [ ] Seat locking mechanism (Redis)
- [ ] Real-time seat status updates
- [ ] Auto-release expired locks
- [ ] Countdown timer

#### 3.2 Order Processing (3 ngày)
- [ ] Create order
- [ ] Apply promotions/discounts
- [ ] Order validation
- [ ] Order history
- [ ] Cancel order

#### 3.3 Payment Integration (4 ngày)
- [ ] VNPay integration
- [ ] MoMo integration (optional)
- [ ] Payment verification
- [ ] Refund logic
- [ ] Receipt generation

---

### Phase 4: Admin Dashboard ⏳
**Thời gian**: 10 ngày

- [ ] Admin authentication
- [ ] Dashboard with statistics
- [ ] Movies management
- [ ] Theaters & Screens management
- [ ] Showtimes management
- [ ] Orders & Payments management
- [ ] Users management
- [ ] Promotions management
- [ ] Reports & Analytics

---

### Phase 5: UI/UX Enhancement ⏳
**Thời gian**: 5 ngày

- [ ] Responsive refinement (mobile/tablet/desktop)
- [ ] Loading states & skeletons
- [ ] Error & empty states
- [ ] Animations & transitions
- [ ] Form validation improvements
- [ ] Accessibility (WCAG 2.1)

---

### Phase 6: Notifications ⏳
**Thời gian**: 7 ngày

- [ ] Email system (booking confirmation, payment receipt)
- [ ] In-app notifications
- [ ] SMS notifications (optional)
- [ ] Real-time notifications (WebSocket)

---

### Phase 7: Performance & Optimization ⏳
**Thời gian**: 7 ngày

- [ ] Database optimization (indexes, query optimization)
- [ ] Redis caching strategy
- [ ] API rate limiting
- [ ] Frontend asset optimization
- [ ] Code splitting & lazy loading
- [ ] Monitoring & logging setup

---

### Phase 8: Testing & QA ⏳
**Thời gian**: 11 ngày

#### Backend Testing
- [ ] Unit tests (Models, Services)
- [ ] Integration tests (API endpoints)
- [ ] Feature tests (Complete flows)
- [ ] Test coverage > 80%

#### Frontend Testing
- [ ] Component tests
- [ ] E2E tests (Cypress/Playwright)
- [ ] Cross-browser testing
- [ ] Mobile device testing

#### Security Testing
- [ ] SQL injection tests
- [ ] XSS tests
- [ ] CSRF protection tests
- [ ] Authentication bypass tests

---

### Phase 9: Deployment & DevOps ⏳
**Thời gian**: 6 ngày

- [ ] Server setup (Ubuntu/CentOS)
- [ ] Nginx/Apache configuration
- [ ] SSL certificate (Let's Encrypt)
- [ ] CI/CD pipeline (GitHub Actions)
- [ ] Database migration to production
- [ ] Environment variables setup
- [ ] Backup strategy
- [ ] Monitoring setup

---

### Phase 10: Documentation ⏳
**Thời gian**: 6 ngày

- [ ] API documentation (Swagger/Postman)
- [ ] Database schema documentation
- [ ] Deployment guide
- [ ] User manual
- [ ] Admin training guide
- [ ] FAQ page

---

### Phase 11: Launch ⏳
**Thời gian**: 1+ tuần

- [ ] Beta testing
- [ ] Bug fixes
- [ ] Official launch
- [ ] Marketing campaign
- [ ] Post-launch support

---

## 🎯 MILESTONES

### Milestone 1: MVP (Minimum Viable Product)
**Timeline**: 4 tuần  
**Features**:
- Authentication (login/register)
- Movie browsing
- Seat selection
- Basic booking
- Basic payment
- User dashboard

**Success Criteria**:
- User can register and login
- User can browse movies
- User can select seats
- User can complete booking
- User can make payment

---

### Milestone 2: Full Features
**Timeline**: 8 tuần (từ start)  
**Features**:
- All MVP features
- Admin dashboard
- Advanced booking features
- Multiple payment gateways
- Email notifications
- Reports & analytics

**Success Criteria**:
- Admin can manage all resources
- Multiple payment options work
- Email notifications sent correctly
- Reports generate accurately

---

### Milestone 3: Production Ready
**Timeline**: 12 tuần (từ start)  
**Features**:
- All features complete
- Performance optimized
- Fully tested
- Documented
- Deployed to production

**Success Criteria**:
- All tests pass (>80% coverage)
- Performance benchmarks met
- Security audit passed
- Documentation complete
- Production deployment successful

---

## 📐 ARCHITECTURE OVERVIEW

### System Architecture

```
┌──────────────┐
│   Browser    │
│  (HTML/CSS/  │
│     JS)      │
└──────┬───────┘
       │ HTTP/HTTPS
       │
┌──────▼───────┐
│   Nginx/     │
│   Apache     │
└──────┬───────┘
       │
┌──────▼───────┐
│   Laravel    │
│  Application │
│              │
│ ┌──────────┐ │
│ │Controller│ │
│ └────┬─────┘ │
│      │       │
│ ┌────▼─────┐ │
│ │ Service  │ │
│ └────┬─────┘ │
│      │       │
│ ┌────▼─────┐ │
│ │  Model   │ │
│ └────┬─────┘ │
└──────┼───────┘
       │
   ┌───▼────┐  ┌────────┐
   │ MySQL  │  │ Redis  │
   └────────┘  └────────┘
```

### Database Schema (Key Tables)

```
users ─────┐
           │
movies ────┼─── showtimes ─── seats ─── seat_holds
           │        │                        │
categories ┘        │                        │
                    │                        │
orders ─────────────┴────────────────────────┘
  │
  └─── order_items
  │
  └─── payments
```

### API Architecture

```
Frontend (Fetch API)
       │
       │ JSON Request
       ▼
    Middleware
    │  ├─ CORS
    │  ├─ JWT Auth
    │  ├─ Rate Limit
    │  └─ Logging
       │
       ▼
    Controller (Thin)
       │
       ▼
    Service (Business Logic)
       │
       ▼
    Model (Eloquent ORM)
       │
       ▼
    Database (MySQL)
       │
       ▼
    JSON Response
       │
       ▼
    Frontend (Render)
```

---

## 🔧 ENVIRONMENT SETUP

### Development Environment

```bash
# System Requirements
- PHP 8.2+
- MySQL 8.0+
- Redis 7.x
- Node.js 18+
- Composer 2.x
- Git 2.x

# Installation Steps
1. Clone repository
   git clone <repo-url>
   cd cinema

2. Install dependencies
   composer install
   npm install

3. Environment setup
   cp .env.example .env
   php artisan key:generate
   php artisan jwt:secret

4. Database setup
   php artisan migrate
   php artisan db:seed

5. Start servers
   php artisan serve
   npm run dev

6. Access application
   http://localhost:8000
```

---

## ✅ QUALITY CHECKLIST

### Before Committing Code

**Backend:**
- [ ] Controller thin (≤10 lines per method)
- [ ] Business logic in Service layer
- [ ] Form Request validation used
- [ ] API response format correct
- [ ] No N+1 queries
- [ ] Security checks passed
- [ ] Unit tests written

**Frontend:**
- [ ] No `console.log()` left
- [ ] XSS prevention (`textContent` used)
- [ ] Responsive on mobile/tablet/desktop
- [ ] Images have `alt` text
- [ ] Forms have labels
- [ ] Loading states implemented
- [ ] Error handling implemented

**General:**
- [ ] Code follows standards
- [ ] Comments for complex logic
- [ ] No hardcoded credentials
- [ ] Git commit message clear
- [ ] Self-reviewed code

---

## 📚 TÀI LIỆU THAM KHẢO

### Internal Documentation
- `DEVELOPMENT_ROADMAP.md` - Chi tiết 11 giai đoạn phát triển
- `FRONTEND_BACKEND_STANDARDS.md` - Standards và best practices chi tiết
- `ARCHITECTURE.md` - Kiến trúc hệ thống
- `QUICK_START.md` - Hướng dẫn bắt đầu nhanh
- `PROJECT_SUMMARY.md` - Tổng quan dự án

### External Resources
- [Laravel Documentation](https://laravel.com/docs)
- [Bootstrap Documentation](https://getbootstrap.com/docs)
- [JWT Authentication](https://jwt.io/)
- [VNPay Sandbox](https://sandbox.vnpayment.vn/apis/)
- [MDN Web Docs](https://developer.mozilla.org/)
- [PHP Standards (PSR-12)](https://www.php-fig.org/psr/psr-12/)

---

## 🎓 LEARNING PATH

### For Junior Developers

**Week 1-2**: Foundation
- Laravel basics (routing, controllers, models)
- Blade templating
- Database migrations
- Git basics

**Week 3-4**: Intermediate
- Eloquent relationships
- Form validation
- API development
- JWT authentication

**Week 5-6**: Advanced
- Service layer pattern
- Redis caching
- Real-time features
- Payment integration

**Week 7+**: Expert
- Testing (PHPUnit, E2E)
- Performance optimization
- Security best practices
- Deployment & DevOps

---

## 💡 BEST PRACTICES SUMMARY

### DO's ✅
- Write thin controllers
- Use dependency injection
- Cache frequently accessed data
- Write tests for critical features
- Use meaningful variable names
- Comment complex logic
- Commit often with clear messages
- Review code before PR
- Mobile-first responsive design
- Optimize images (lazy loading)

### DON'Ts ❌
- Don't put business logic in controllers
- Don't query DB directly in controllers
- Don't use raw SQL (use Eloquent)
- Don't commit sensitive data
- Don't use `innerHTML` with user input
- Don't skip validation
- Don't ignore errors silently
- Don't deploy without testing
- Don't skip code review
- Don't leave `console.log()` in production

---

## 🚀 QUICK START

1. **Read this document** (MASTER_PLAN.md) để hiểu tổng quan
2. **Setup environment** theo phần Environment Setup
3. **Read DEVELOPMENT_ROADMAP.md** để biết phase hiện tại
4. **Read FRONTEND_BACKEND_STANDARDS.md** trước khi code
5. **Pick a task** từ current phase
6. **Create feature branch** và bắt đầu develop
7. **Follow standards** khi implement
8. **Test thoroughly** trước khi commit
9. **Create PR** và request review
10. **Address feedback** và merge

---

## 📞 SUPPORT

**Questions về:**
- Architecture → Check `ARCHITECTURE.md`
- Coding standards → Check `FRONTEND_BACKEND_STANDARDS.md`
- Current tasks → Check `DEVELOPMENT_ROADMAP.md`
- Quick start → Check `QUICK_START.md`

**Still stuck?**
- Ask team lead
- Search Stack Overflow
- Check Laravel documentation
- Review similar implemented features

---

## 🎯 SUCCESS METRICS

### Technical Metrics
- Code coverage > 80%
- API response time < 500ms
- Page load time < 2s
- Lighthouse score > 90
- Zero critical security vulnerabilities
- Uptime > 99.9%

### Business Metrics
- User registration completion rate > 80%
- Booking completion rate > 70%
- Payment success rate > 95%
- Mobile traffic > 60%
- User satisfaction score > 4.5/5

---

## 📝 VERSION HISTORY

- **v1.0.0** (2026-06-02): Initial master plan
- Future updates will be tracked here

---

**Ghi chú cuối**: Đây là living document. Update thường xuyên khi có thay đổi trong quy trình, công nghệ, hoặc yêu cầu dự án.

**Status**: ✅ Foundation hoàn thành | 🔄 Core Features đang phát triển | 🎯 Target: Production ready trong 12 tuần
