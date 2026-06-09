# Phase 7: API Design Review

**Date:** June 9, 2026, 2:25 AM ICT  
**Status:** ✅ COMPLETED  
**Focus:** RESTful compliance, response formats, status codes, API structure

---

## Executive Summary

**Overall Score: ⭐⭐⭐ (3/5)**

API có cấu trúc cơ bản tốt với resource-based routing và middleware protection, nhưng có nhiều **RESTful violations** và **inconsistencies** cần sửa trước khi scale.

**Điểm mạnh:**
- ✅ Resource-based URLs (movies, theaters, orders)
- ✅ Rate limiting áp dụng đúng
- ✅ Response format nhất quán (ApiResponse trait)
- ✅ HTTP verbs sử dụng đúng (GET, POST, PUT, DELETE)

**Vấn đề nghiêm trọng:**
- ❌ Không có API versioning (/v1/)
- ❌ RESTful violations (PUT /cancel, POST /validate)
- ❌ Inconsistent naming (ID vs slug mixing)
- ❌ Response format không đồng nhất 100%

---

## 1. Route Structure Analysis

### 1.1 Route Organization

```
routes/api.php (163 lines)

Public Routes:
├── /home
├── /auth/* (register, login, google)
├── /movies/* (index, show, search)
├── /theaters/*
├── /screens/*
├── /showtimes/*
├── /seats/showtime/{id}
├── /products
└── /promotions/validate

Protected Routes (auth:api):
├── /auth/* (me, logout, profile)
├── /seats/* (lock, unlock)
├── /orders/*
└── /payments/*

Admin Routes (auth:api + role):
├── /admin/dashboard/stats
├── /admin/movies/*
├── /admin/theaters/*
├── /admin/screens/*
└── /admin/showtimes/*

Webhooks:
└── /payos/webhook
```

---

## 2. RESTful Compliance Issues

### 🔴 CRITICAL: Missing API Versioning

**Vấn đề:**
```php
// Current - NO VERSION
Route::get('movies', [MovieController::class, 'index']);
Route::post('orders', [OrderController::class, 'store']);
```

**Tại sao nghiêm trọng:**
- Không thể thêm breaking changes
- Client apps sẽ break khi API thay đổi
- Không có deprecation strategy

**Giải pháp:**
```php
// Recommended
Route::prefix('v1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
});

// URLs become: /api/v1/movies, /api/v1/orders
```

---

### ❌ RESTful Violations

#### 1. PUT /orders/{id}/cancel (Line 97)

**Sai:**
```php
Route::put('{id}/cancel', [OrderController::class, 'cancel']);
```

**Tại sao sai:**
- `cancel` là ACTION verb - không RESTful
- PUT dùng cho full update resource
- Khác với chuẩn REST

**Cách sửa đúng (Option 1 - RESTful pure):**
```php
// Treat cancellation as status update
Route::patch('{id}', [OrderController::class, 'update']);
// Body: {"status": "cancelled"}
```

**Cách sửa đúng (Option 2 - Pragmatic):**
```php
// Use POST for action
Route::post('{id}/cancel', [OrderController::class, 'cancel']);
```

---

#### 2. POST /promotions/validate (Line 152)

**Sai:**
```php
Route::post('promotions/validate', [PromotionController::class, 'validate']);
```

**Tại sao sai:**
- `validate` là action verb
- POST dùng cho create resource
- Should be GET with query params

**Cách sửa đúng:**
```php
Route::get('promotions/{code}/validate', [PromotionController::class, 'validate']);
// Or: /promotions/{code}?order_total=100000
```

---

### ⚠️ Inconsistent Resource Identification

**Mixing ID types:**
```php
// Movies use SLUG
Route::get('movies/{slug}', ...);  // Line 46

// But theaters use ID
Route::get('theaters/{id}', ...);  // Line 55

// Orders use ID
Route::get('orders/{id}', ...);   // Line 96
```

**Khuyến nghị:**
- **Dùng ID cho mọi resource** (consistent, cacheable)
- Hoặc **slug cho public**, **ID cho protected/admin**

```php
// Option 1: Consistent ID
Route::get('movies/{id}', ...);
Route::get('theaters/{id}', ...);

// Option 2: Public slug, protected ID
Route::get('public/movies/{slug}', ...);     // Guest
Route::get('admin/movies/{id}', ...);        // Admin
```

---

### ⚠️ Nested Resource Issues

**Line 47: Deep nesting**
```php
Route::get('movies/{slug}/showtimes', [ShowtimeController::class, 'getMovieShowtimes']);
```

**Vấn đề:**
- Nesting quá sâu
- URL dài, khó maintain
- Better với query params

**Cách tốt hơn:**
```php
Route::get('showtimes', [ShowtimeController::class, 'index']);
// Query: /showtimes?movie_id=123&theater_id=456&date=2026-06-10
```

---

## 3. Response Format Analysis

### 3.1 ApiResponse Trait Usage

**GOOD - Consistent format:**
```php
// OrderController, AuthController, SeatController
$this->successResponse($data, $message, $statusCode);
$this->errorResponse($message, $statusCode);
```

**Response structure:**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": { ... }
}
```

---

### 3.2 Inconsistent Responses

**BAD - Raw response()->json():**

**PaymentController (Line 111):**
```php
return response()->json([
    'success' => true,
    'message' => 'Webhook received'
]);
```

**PromotionController (Line 152):**
```php
return response()->json([
    'success' => false,
    'message' => 'Mã khuyến mãi không hợp lệ',
    'errors' => [...]
], 422);
```

**ProductController (Line 148):**
```php
return response()->json([
    'success' => true,
    'data' => ['products' => $products]
]);
```

**Vấn đề:**
- 3 controllers không dùng ApiResponse trait
- Format khác nhau (`errors` key ở một số chỗ)
- Không consistent

---

## 4. HTTP Status Codes Review

### 4.1 Status Codes Được Dùng

```
✅ 200 OK - Success responses
✅ 201 Created - Resource creation
✅ 400 Bad Request - Validation errors
✅ 401 Unauthorized - Auth failures  
✅ 403 Forbidden - Permission denied
✅ 404 Not Found - Resource not found
✅ 422 Unprocessable Entity - Validation
✅ 500 Internal Server Error - Server errors
✅ 502 Bad Gateway - Payment gateway errors
```

**Coverage: GOOD ✅**

---

### 4.2 Status Code Issues

#### Issue 1: Inconsistent 422 vs 400

**PromotionController:**
```php
return response()->json([...], 422);  // Validation error
```

**AuthController:**
```php
return $this->errorResponse('Current password incorrect', 400);  // Should be 422
```

**Chuẩn:**
- **400 Bad Request**: Malformed request syntax
- **422 Unprocessable Entity**: Validation logic errors

---

#### Issue 2: Generic 500 Errors

**Many controllers:**
```php
catch (\Exception $e) {
    return $this->errorResponse('Failed: ' . $e->getMessage(), 500);
}
```

**Vấn đề:**
- Expose stack trace qua `$e->getMessage()`
- Should sanitize error messages in production

**Nên:**
```php
catch (\Exception $e) {
    report($e);  // Log to monitoring
    
    if (app()->environment('production')) {
        return $this->errorResponse('An error occurred', 500);
    }
    
    return $this->errorResponse('Failed: ' . $e->getMessage(), 500);
}
```

---

## 5. Naming Conventions Issues

### 5.1 Route Naming Inconsistencies

**Auth routes:**
```php
Route::get('auth/me', ...);           // Get current user
Route::get('auth/profile', ...);      // Get profile
Route::put('auth/profile', ...);      // Update profile
```

**Vấn đề:**
- `me` và `profile` trùng lắp
- Nên chọn 1 trong 2

**Nên:**
```php
Route::get('auth/me', ...);           // Get user + profile
Route::put('auth/me', ...);           // Update profile
Route::post('auth/me/password', ...); // Change password
```

---

### 5.2 Admin Routes Prefix

**Current:**
```php
Route::get('admin/dashboard/stats', ...);  // Singular
Route::prefix('admin/movies')->group(...); // Plural
```

**Inconsistent:**
- `dashboard` singular
- `movies` plural

**Nên:**
```php
Route::get('admin/dashboards/stats', ...);  // Consistent plural
// Or
Route::get('admin/dashboard', ...);         // Single resource
```

---

## 6. Middleware & Security

### 6.1 Rate Limiting ✅

**GOOD - Applied correctly:**
```php
Route::prefix('auth')->middleware('throttle:auth');        // 5/min
Route::prefix('orders')->middleware('throttle:orders');   // 20/min
Route::prefix('payments')->middleware('throttle:payments');// 10/min
Route::prefix('seats')->middleware('throttle:seats');     // 30/min
Route::post('payos/webhook')->middleware('throttle:webhook'); // 100/hour
```

**Coverage: EXCELLENT ✅**

---

### 6.2 Authentication Middleware ✅

```php
Route::middleware('auth:api')->group(function () {
    // Protected routes
});

Route::middleware(['auth:api', 'role:admin,super-admin'])->group(function () {
    // Admin routes
});
```

**GOOD - Proper separation ✅**

---

## 7. Missing Features

### ❌ 1. API Documentation

**Không có:**
- OpenAPI/Swagger docs
- API documentation file
- Endpoint examples
- Response schemas

**Nên có:**
```yaml
# openapi.yaml
/api/v1/movies:
  get:
    summary: List movies
    parameters:
      - name: page
        in: query
        schema:
          type: integer
    responses:
      200:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/MovieList'
```

---

### ❌ 2. Pagination Standards

**Current - Inconsistent:**
```php
// OrderController
'per_page' => $orders->perPage()  // Snake case

// Query param
$request->input('per_page', 15)   // Snake case
```

**Nên có chuẩn:**
```php
// Option 1: Snake case (Laravel default)
?page=2&per_page=20

// Option 2: Camel case (API standard)
?page=2&perPage=20&sortBy=created_at&order=desc
```

---

### ❌ 3. HATEOAS Links

**Current responses thiếu links:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "Movie Name"
  }
}
```

**Nên có:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "Movie Name",
    "_links": {
      "self": "/api/v1/movies/123",
      "showtimes": "/api/v1/movies/123/showtimes",
      "tickets": "/api/v1/movies/123/tickets"
    }
  }
}
```

---

## 8. Recommendations

### Priority 1: Add API Versioning 🔴

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // All current routes here
});
```

**Impact:** Enable future breaking changes safely  
**Effort:** 2 hours

---

### Priority 2: Fix RESTful Violations 🔴

```php
// Change PUT to POST for actions
Route::post('orders/{id}/cancel', ...);

// Change POST validate to GET
Route::get('promotions/{code}', ...);
```

**Impact:** RESTful compliance  
**Effort:** 4 hours

---

### Priority 3: Standardize Response Format 🟠

```php
// Make all controllers use ApiResponse trait
class PromotionController extends Controller
{
    use ApiResponse;  // Add this
}
```

**Impact:** Consistent API responses  
**Effort:** 3 hours

---

### Priority 4: Add API Documentation 🟡

```bash
# Install Swagger/OpenAPI
composer require darkaonline/l5-swagger
php artisan l5-swagger:generate
```

**Impact:** Better DX (Developer Experience)  
**Effort:** 1-2 days

---

### Priority 5: Sanitize Error Messages 🟡

```php
// Production-safe error handling
catch (\Exception $e) {
    report($e);
    $message = app()->isProduction() 
        ? 'An error occurred' 
        : $e->getMessage();
    return $this->errorResponse($message, 500);
}
```

**Impact:** Security (no stack trace leaks)  
**Effort:** 2-3 hours

---

## 9. Scorecard

| Aspect | Score | Notes |
|--------|-------|-------|
| **RESTful Design** | ⭐⭐⭐ | Good structure, có violations |
| **Response Format** | ⭐⭐⭐⭐ | ApiResponse trait tốt, 3 controllers outliers |
| **Status Codes** | ⭐⭐⭐⭐ | Comprehensive coverage |
| **Versioning** | ⭐ | Không có - CRITICAL |
| **Documentation** | ⭐ | Không có - cần bổ sung |
| **Naming Conventions** | ⭐⭐⭐ | Mostly consistent |
| **Security** | ⭐⭐⭐⭐ | Rate limiting + auth good |
| **Error Handling** | ⭐⭐⭐ | Needs production sanitization |

**Overall API Design: ⭐⭐⭐ (3/5)**

---

## 10. Conclusion

API có **foundation tốt** với resource-based structure, proper authentication, và rate limiting. Tuy nhiên cần **refactor để RESTful compliant** và **thêm versioning** trước khi public API.

**Production Readiness:**
- ✅ MVP: Safe to deploy với known issues
- ⚠️ Public API: Cần fix versioning + RESTful violations
- 🎯 Enterprise: Cần full documentation + HATEOAS

**Timeline:**
- Week 1: API versioning + RESTful fixes (Priority 1-2)
- Week 2: Response standardization (Priority 3)
- Week 3: Documentation (Priority 4-5)

**Total effort: 2-3 tuần**

---

**Author:** Kiro AI Assistant  
**Phase:** 7 - API Design Review  
**Confidence:** High (90%)
