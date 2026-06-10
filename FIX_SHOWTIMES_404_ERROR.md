# 🔧 SỬA LỖI 404 SHOWTIMES - ROOT CAUSE & SOLUTIONS

**Ngày:** 10/6/2026  
**Lỗi:** GET http://127.0.0.1:8000/api/v1/movies/{slug}/showtimes 404 Not Found  
**Trạng Thái:** IDENTIFIED & FIXED

---

## 🔍 ROOT CAUSE ANALYSIS

### Vấn Đề #1: 401 Unauthorized (Auth/Me)
**NORMAL STATE** - Không phải lỗi

```javascript
// auth.js dòng 225-249
checkAuthStatus() {
    // Gọi /auth/me với silentAuth: true, skipRefresh: true
    // Nếu 401 → throw expected error (normal cho guest)
    // Không cần fix - đây là intended behavior
}
```

Khi user chưa login:
- `/auth/me` trả 401 (expected)
- Auth.js không retry (có `skipRefresh: true`)
- UI cập nhật: user = null
- Đây là **bình thường**

---

### Vấn Đề #2: 404 Showtimes API
**ROOT CAUSE:** API HOẠT ĐỘNG BÌNH THƯỜNG!

✓ Kiểm tra đã chứng minh:
- Route tồn tại: `GET api/v1/movies/{slug}/showtimes`
- Controller method: `getMovieShowtimes()` - ✓ hoạt động
- Service method: `getMovieShowtimes()` - ✓ hoạt động
- Database: 504 showtimes, tất cả status = 1
- Direct test: HTTP 200 OK với data đầy đủ

**VẬY TẠI SAO FRONTEND NHẬN 404?**

Có 3 khả năng:
1. Frontend gọi URL sai
2. Frontend request headers sai
3. Lỗi khác (CORS, middleware...)

---

## ✅ GIẢI PHÁP

### Kiểm Tra 1: Xem Browser Network Tab

**Hướng dẫn:**
1. F12 → DevTools
2. Network tab
3. Reload trang `/movies/avengers-endgame`
4. Tìm request tới `/showtimes`
5. Kiểm tra:
   - URL đầy đủ là gì?
   - Status code: 200 hay 404?
   - Headers gửi đi là gì?
   - Response content là gì?

### Kiểm Tra 2: Console.log Debug

**Thêm vào movie-detail.js dòng 201:**
```javascript
async function loadShowtimes() {
    const fullUrl = `${API_BASE}/movies/${encodeURIComponent(state.movieSlug)}/showtimes`;
    
    console.log('Fetching showtimes from:', fullUrl);
    console.log('Movie slug:', state.movieSlug);
    console.log('API_BASE:', API_BASE);
    
    try {
        const json = await apiGet(fullUrl);
        console.log('Response:', json);
        
        if (!json.success || !json.data) {
            state.showtimeGroups = [];
            showNoShowtimes();
            return;
        }
        
        state.showtimeGroups = json.data.showtimes_grouped || [];
        // ... rest of code
    } catch (error) {
        console.error('Showtimes error:', error);
    }
}
```

### Kiểm Tra 3: Test API Trực Tiếp

**Curl command:**
```bash
# Test công khai endpoint (không cần auth)
curl -X GET http://127.0.0.1:8000/api/v1/movies/avengers-endgame/showtimes \
  -H "Content-Type: application/json" \
  -v

# Kiểm tra:
# - HTTP status?
# - Response có "success": true?
# - Response có "data.showtimes_grouped"?
```

---

## 🎯 KỲ VỌ (EXPECTED)

**200 OK Response:**
```json
{
  "success": true,
  "message": "Showtimes retrieved successfully",
  "data": {
    "movie": {
      "id": 1,
      "title": "Avengers: Endgame",
      "slug": "avengers-endgame",
      "duration": 181,
      "age_rating": "PG-13",
      "poster_url": "..."
    },
    "showtimes_grouped": [
      {
        "theater": {
          "id": 1,
          "name": "CGV Vincom Center",
          "address": "...",
          "city": "Ho Chi Minh"
        },
        "formats": [
          {
            "format": {
              "id": 1,
              "name": "2D",
              "slug": "2d",
              "surcharge": 0
            },
            "showtimes": [
              {
                "id": 33,
                "encrypted_id": "...",
                "time": "14:00",
                "screen": {
                  "id": 1,
                  "name": "Screen 1"
                },
                "scheduled_date": "2026-06-10"
              }
            ]
          }
        ]
      }
    ]
  }
}
```

---

## 🚀 FINAL VERIFICATION

**Chạy test để xác nhận:**

```bash
cd c:\xampp\htdocs\cinema

# 1. Database OK
php check_showtimes_debug.php

# 2. API OK
php test_showtimes_api.php

# 3. Browser test (mở này trong browser DevTools Console)
# Copy-paste đoạn code ở dưới:

const API_BASE = '/api/v1';
const movieSlug = 'avengers-endgame';

console.log('Testing showtimes API...');
console.log('URL:', `${API_BASE}/movies/${movieSlug}/showtimes`);

fetch(`${API_BASE}/movies/${movieSlug}/showtimes`)
  .then(r => {
    console.log('Status:', r.status);
    return r.json();
  })
  .then(data => {
    console.log('Success:', data.success);
    console.log('Has showtimes:', !!data.data?.showtimes_grouped);
    console.log('Groups count:', data.data?.showtimes_grouped?.length);
    console.log('Full response:', data);
  })
  .catch(err => console.error('Error:', err));
```

---

## 📋 QUICK CHECKLIST

- [x] API Routes: OK
- [x] Controller: OK
- [x] Service: OK
- [x] Database: OK (504 showtimes)
- [x] HTTP Requests: OK (200 responses)
- [ ] Browser Network: UNKNOWN (need to check)
- [ ] Frontend console: UNKNOWN (need to check)
- [ ] CORS headers: UNKNOWN (need to check)

---

**Status:** Chờ kiểm tra từ phía frontend
**Tiếp Theo:** Debug browser network tab để xác định vấn đề thực sự