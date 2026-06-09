# LỘ TRÌNH SỬA & REFACTOR - PHASE 3 TỐI ƯU
## Cinema Booking System | 2 Tuần | 50-60 Giờ
**Mục tiêu**: Từ 8.0/10 → 8.5/10 (Production-Grade)

---

## TỔNG QUAN GIAI ĐOẠN 3

| Tuần | Ngày | Task | Giờ | Priority |
|------|------|------|-----|----------|
| Tuần 4 | T1-T2 | Caching Layer (Redis) | 8 | 🟡 MEDIUM |
| | T2-T3 | Structured Logging | 6 | 🟡 MEDIUM |
| | T3-T4 | Database Query Analysis | 8 | 🟡 MEDIUM |
| | T4 | Soft Deletes & Audit Trail | 4 | 🟡 MEDIUM |
| Tuần 5 | T5-T6 | API Documentation (OpenAPI) | 8 | 🟡 MEDIUM |
| | T6-T7 | Repository Pattern (Optional) | 10 | 🟢 LOW |
| | T7 | Monitoring Setup (APM) | 6 | 🟡 MEDIUM |
| | T7 | Performance Tuning | 4 | 🟡 MEDIUM |
| | | **TOTAL** | **54 giờ** | |

---

## TASK 1: CACHING LAYER - REDIS (T1-T2, 8 Giờ)

### 1.1: Setup Redis
```bash
# .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 1.2: Cache Static Data
```php
// app/Services/MovieService.php
public function getActiveMovies() {
    return Cache::remember(
        'movies:active',
        3600,  // 1 hour
        fn() => Movie::where('is_active', true)->get()
    );
}

// app/Services/TheaterService.php
public function getTheaterLayout($theaterId) {
    return Cache::remember(
        "theater:{$theaterId}:layout",
        86400,  // 24 hours
        fn() => Theater::with('screens.seats')->find($theaterId)
    );
}
```

### 1.3: Cache User Permissions
```php
// app/Models/User.php
public function getPermissionsAttribute() {
    return Cache::remember(
        "user:{$this->id}:permissions",
        3600,
        fn() => $this->permissions()->pluck('slug')->all()
    );
}
```

### 1.4: Invalidate Cache on Updates
```php
// app/Models/Movie.php
protected static function boot() {
    parent::boot();
    
    static::updated(function ($movie) {
        Cache::forget('movies:active');
        Cache::forget("movie:{$movie->id}");
    });
}
```

### 1.5: Cache Seat Status
```php
// app/Services/SeatService.php
public function getAvailableSeats($showtimeId) {
    return Cache::remember(
        "showtime:{$showtimeId}:available_seats",
        300,  // 5 minutes - update frequently
        fn() => Seat::availableFor($showtimeId)->get()
    );
}
```

---

## TASK 2: STRUCTURED LOGGING (T2-T3, 6 Giờ)

### 2.1: Create Custom Logger
```php
// app/Logging/CustomFormatter.php
class CustomFormatter {
    public function format(LogRecord $record): string {
        return json_encode([
            'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
            'level' => $record->level->name,
            'message' => $record->message,
            'context' => $record->context,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ]) . PHP_EOL;
    }
}
```

### 2.2: Log Important Events
```php
// In Controllers/Services

// Authentication
\Log::info('User login', ['user_id' => $user->id, 'ip' => request()->ip()]);

// Order creation
\Log::info('Order created', [
    'order_id' => $order->id,
    'user_id' => $order->user_id,
    'amount' => $order->total_amount,
]);

// Payment webhook
\Log::info('Payment webhook received', [
    'order_code' => $webhookData['orderCode'],
    'status' => $webhookData['status'],
]);

// Errors
\Log::error('Payment failed', [
    'exception' => $exception->getMessage(),
    'order_id' => $order->id,
]);
```

### 2.3: Protect PII in Logs
```php
// Don't log:
\Log::info('User data', ['email' => $user->email]);  // ❌

// Instead:
\Log::info('User action', ['user_id' => $user->id]);  // ✅
```

### 2.4: Centralized Logging (Optional)
```bash
# Install Sentry
composer require sentry/sentry-laravel

# Configure in config/sentry.php
SENTRY_DSN=https://...
```

---

## TASK 3: DATABASE QUERY ANALYSIS (T3-T4, 8 Giờ)

### 3.1: Identify Slow Queries
```php
// In AppServiceProvider boot()
DB::listen(function ($query) {
    if ($query->time > 100) {
        \Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time . 'ms',
            'bindings' => $query->getBindings(),
        ]);
    }
});
```

### 3.2: Common N+1 Fixes
```php
// PROBLEM: Each order loads user
foreach ($orders as $order) {
    echo $order->user->name;  // N+1
}

// SOLUTION: Eager load
$orders = Order::with('user', 'orderItems', 'showtime')->get();
foreach ($orders as $order) {
    echo $order->user->name;  // 1 query
}
```

### 3.3: Add Query Caching
```php
// Cache complex queries
$bookingStats = Cache::remember('booking:stats', 3600, function () {
    return Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->get();
});
```

### 3.4: Use Database Indexes Properly
```php
// Verify indexes used
EXPLAIN SELECT * FROM orders WHERE user_id = ? AND status = ?;
// Should show index usage in "key" column
```

---

## TASK 4: SOFT DELETES & AUDIT TRAIL (T4, 4 Giờ)

### 4.1: Add Soft Deletes
```php
// app/Models/Order.php
use SoftDeletes;

class Order extends Model {
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
}
```

### 4.2: Create Migration
```bash
php artisan make:migration add_soft_deletes_to_orders
```

```php
Schema::table('orders', function (Blueprint $table) {
    $table->softDeletes();
});
```

### 4.3: Audit Logging
```php
// app/Models/AuditLog.php (if not exists)
class AuditLog extends Model {
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'action',
        'changes',
        'ip_address',
    ];
}
```

### 4.4: Log State Changes
```php
// In Model boot()
static::updated(function ($model) {
    AuditLog::create([
        'user_id' => auth()->id(),
        'model_type' => self::class,
        'model_id' => $model->id,
        'action' => 'updated',
        'changes' => $model->getChanges(),
        'ip_address' => request()->ip(),
    ]);
});
```

---

## TASK 5: API DOCUMENTATION (T5-T6, 8 Giờ)

### 5.1: Install OpenAPI Generator
```bash
composer require --dev darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### 5.2: Add API Annotations
```php
// app/Http/Controllers/OrderController.php

/**
 * @OA\Get(
 *     path="/api/orders",
 *     summary="List user orders",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Orders list"
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized"
 *     )
 * )
 */
public function index() { ... }
```

### 5.3: Generate Documentation
```bash
php artisan l5-swagger:generate
```

### 5.4: Document Models
```php
/**
 * @OA\Schema(
 *     schema="Order",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="total_amount", type="number"),
 * )
 */
class Order extends Model { ... }
```

---

## TASK 6: REPOSITORY PATTERN (T6-T7, 10 Giờ) [OPTIONAL]

### 6.1: Create Repository Interface
```php
// app/Contracts/OrderRepository.php
interface OrderRepository {
    public function getById($id);
    public function getByCode($code);
    public function getByUser($userId);
    public function create(array $data);
    public function update($id, array $data);
}
```

### 6.2: Implement Repository
```php
// app/Repositories/EloquentOrderRepository.php
class EloquentOrderRepository implements OrderRepository {
    public function getById($id) {
        return Order::with('user', 'items')->find($id);
    }
    
    public function create(array $data) {
        return Order::create($data);
    }
}
```

### 6.3: Register in Service Provider
```php
// config/app.php or AppServiceProvider
$this->app->bind(
    OrderRepository::class,
    EloquentOrderRepository::class
);
```

### 6.4: Use in Controllers
```php
// In Controller
public function __construct(private OrderRepository $orders) {}

public function show($id) {
    $order = $this->orders->getById($id);
    return $this->successResponse($order);
}
```

---

## TASK 7: APM MONITORING (T7, 6 Giờ)

### 7.1: Setup New Relic (or similar)
```bash
composer require newrelic/newrelic-php-agent
```

### 7.2: Monitor Key Transactions
```php
// Monitor critical endpoints
newrelic_name_transaction("POST /api/orders");

// Monitor long-running processes
$start = microtime(true);
// ... do work ...
$duration = microtime(true) - $start;
\Log::info('Long operation', ['duration' => $duration]);
```

### 7.3: Error Tracking
```php
// Capture errors
try {
    // ... code ...
} catch (Exception $e) {
    newrelic_notice_error($e);
    throw $e;
}
```

### 7.4: Create Dashboards
- Track response times (p50, p95, p99)
- Monitor error rates
- Track database performance
- Memory usage trends

---

## TASK 8: PERFORMANCE TUNING (T7, 4 Giờ)

### 8.1: Optimize Config
```php
// config/database.php
'mysql' => [
    'pool' => [
        'min' => 5,
        'max' => 20,
    ],
],
```

### 8.2: Connection Pooling
```php
// Use database connection pooling in production
// Add to .env
DB_POOL_SIZE=20
```

### 8.3: Query Optimization
```php
// Use selects instead of select(*)
Order::select('id', 'code', 'user_id', 'total_amount')->get();

// Use cursor for large datasets
Order::cursor()->each(function ($order) { ... });
```

### 8.4: Frontend Performance
```javascript
// Lazy load images
<img loading="lazy" src="...">

// Code splitting
import('module').then(m => m.function());

// Compress assets
npm run production
```

---

## QUALITY GATES - PHASE 3

| Gate | Target | Status |
|------|--------|--------|
| Response Time (p95) | <200ms | ✓ |
| Database Queries | <5 per request | ✓ |
| Cache Hit Rate | >80% | ✓ |
| Error Rate | <0.1% | ✓ |
| Test Coverage | 70%+ | ✓ |
| Security Score | A+ | ✓ |

---

## CHECKLIST HOÀN THÀNH PHASE 3

### Caching:
- [ ] Redis configured
- [ ] Static data cached
- [ ] User permissions cached
- [ ] Seat status cached
- [ ] Cache invalidation working

### Logging:
- [ ] Structured logging implemented
- [ ] PII protected in logs
- [ ] Important events logged
- [ ] Centralized logging (optional)

### Database:
- [ ] Slow query logging
- [ ] N+1 queries identified
- [ ] Eager loading fixed
- [ ] Query caching added
- [ ] Indexes verified

### Audit & Compliance:
- [ ] Soft deletes implemented
- [ ] Audit logging added
- [ ] Change tracking working

### Documentation:
- [ ] OpenAPI docs generated
- [ ] Endpoints documented
- [ ] Models documented
- [ ] Deployment guide updated

### Monitoring:
- [ ] APM configured
- [ ] Dashboards created
- [ ] Alerts configured
- [ ] Performance baselines set

---

## POST-DEPLOYMENT TASKS

### 1. Monitoring (Ongoing)
```bash
# Daily checks
- Response time trends
- Error rate tracking
- Database performance
- Cache effectiveness
```

### 2. Maintenance (Weekly)
```bash
- Review slow queries
- Optimize hot paths
- Update cache TTLs
- Analyze user patterns
```

### 3. Security (Monthly)
```bash
- composer audit
- Security patches
- Penetration testing
- Compliance review
```

---

## PRODUCTION DEPLOYMENT CHECKLIST

- [ ] All Phase 1-3 complete
- [ ] 70%+ test coverage
- [ ] 0 security warnings
- [ ] Performance benchmarks met
- [ ] Monitoring configured
- [ ] Backup strategy ready
- [ ] Rollback plan documented
- [ ] Team trained
- [ ] Documentation complete
- [ ] SLA defined

---

## FINAL METRICS

**Before Review**: 6.5/10  
**After Phase 1**: 7.0/10  
**After Phase 2**: 8.0/10  
**After Phase 3**: 8.5/10

**Production Ready**: ✅ YES

---

**Ước tính**: 50-60 giờ để hoàn thành Phase 3  
**Kết quả**: Hệ thống production-grade, sẵn sàng scale
