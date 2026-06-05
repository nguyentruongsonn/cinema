# Booking System Test Guide

## Prerequisites

### 1. Database Setup
```bash
# Make sure database is seeded with test data
php artisan migrate:fresh --seed
```

### 2. Start Development Server
```bash
php artisan serve
```

Server will run at: `http://localhost:8000`

---

## Test Flow

### Step 1: Register/Login
1. Navigate to `http://localhost:8000`
2. Click "Đăng nhập" button in header
3. Register a new account or login:
   - Email: `user@example.com`
   - Password: `password`

### Step 2: Browse Movies
1. After login, you should see homepage with movies
2. Click on any movie card
3. You'll be redirected to movie detail page

### Step 3: Select Showtime
1. On movie detail page, scroll down to "Select Showtime" section
2. You should see:
   - Date tabs (today + next days)
   - Theater filters
   - Format groups (2D, 3D, IMAX, etc.)
   - Showtime buttons with time and screen name

3. Click on any showtime button
4. You'll be redirected to booking page: `/booking/{showtimeId}`

### Step 4: Booking Page
When the booking page loads, verify:

✅ **Header Info Shows:**
- Movie title
- Theater name
- Screen name
- Date and time
- Format, sound, subtitle

✅ **Seat Map Shows:**
- Screen display at top
- Seat grid (10x10 or similar)
- Seat colors:
  - **Gray/White**: Available
  - **Blue**: Selected by you
  - **Orange**: Locked by others
  - **Red**: Booked
- Row labels (A, B, C...)
- Seat numbers (1, 2, 3...)

✅ **Summary Sidebar Shows:**
- Timer countdown (10:00 minutes)
- Selected seats list (empty initially)
- Seat quantity: 0
- Surcharge: 0₫
- Total price: 0₫
- "Tiếp tục thanh toán" button (disabled)
- "Hủy chọn" button (disabled)

### Step 5: Select Seats
1. Click on available seats (gray/white)
2. Seat should turn **blue** (selected)
3. Verify:
   - ✅ Selected seat appears in summary sidebar
   - ✅ Seat quantity increases
   - ✅ Total price updates (base price + surcharge)
   - ✅ Timer starts countdown from 10:00
   - ✅ Toast notification: "Đã giữ ghế cho bạn trong 10 phút"

4. Try selecting multiple seats (up to 10)
5. Try clicking a selected seat again → should deselect (turn gray)

### Step 6: Seat Locking
When you select seats:
- ✅ API call to `/api/seats/lock` should succeed
- ✅ Timer countdown shows in format MM:SS
- ✅ Timer color changes:
  - Green: > 3 minutes remaining
  - Yellow: 1-3 minutes remaining
  - Red: < 1 minute remaining

### Step 7: Edge Cases to Test

**Test 1: Max Seats Limit**
- Try selecting 11 seats
- Should show toast: "Bạn chỉ có thể chọn tối đa 10 ghế"

**Test 2: Timer Expiry**
- Select seats
- Wait for timer to reach 00:00
- Should show: "Hết thời gian giữ ghế. Vui lòng chọn lại."
- Seats should be deselected automatically

**Test 3: Locked Seats (Multiple Users)**
- Open booking page in **2 browser tabs** (or incognito)
- Login as different users in each tab
- Select same seat in tab 1
- Try to select same seat in tab 2
- Tab 2 should show seat as locked (orange)

**Test 4: Proceed to Payment**
- Select 2-3 seats
- Click "Tiếp tục thanh toán" button
- Should show loading: "Đang tạo đơn hàng..."
- Should redirect to `/payment/{orderId}` (may show 404 if payment page not created yet)

### Step 8: Browser DevTools Checks

Open browser DevTools (F12) → Network tab:

✅ **On Page Load:**
```
GET /api/seats/showtime/{showtimeId}
Status: 200
Response: {
  success: true,
  data: {
    seats: [...],
    current_user_holds: [...]
  }
}
```

✅ **On Seat Selection:**
```
POST /api/seats/lock
Status: 200
Request: {
  showtime_id: 1,
  seat_ids: [1, 2, 3]
}
Response: {
  success: true,
  data: {
    hold_id: 1,
    seat_ids: [1, 2, 3],
    expires_in_seconds: 600
  }
}
```

✅ **On Proceed to Payment:**
```
POST /api/orders
Status: 200
Request: {
  showtime_id: 1,
  seat_ids: [1, 2, 3],
  seat_hold_id: 1
}
Response: {
  success: true,
  data: {
    id: 1,
    ...
  }
}
```

---

## Common Issues & Solutions

### Issue 1: "Phiên đăng nhập hết hạn"
**Solution:** Login again. JWT token may have expired.

### Issue 2: Seat map not showing
**Check:**
- Console errors in DevTools
- Network tab: Did `/api/seats/showtime/{id}` succeed?
- Database: Are there seats for the screen?

### Issue 3: "Không thể giữ ghế"
**Possible causes:**
- Seat already booked
- Seat locked by another user
- Network error

**Solution:**
- Refresh page
- Try different seats
- Check API response in DevTools

### Issue 4: Timer not counting down
**Check:**
- Console errors
- Timer element exists: `#bookingTimer`
- `startTimer()` was called after lock success

### Issue 5: Can't proceed to payment
**Check:**
- Seats are selected
- Timer hasn't expired
- `currentHold` exists
- Order creation API succeeds

---

## Success Criteria

✅ **Day 1 Booking Flow is Complete When:**

1. User can navigate from movie detail → booking page
2. Seat map renders correctly with all states
3. User can select/deselect seats
4. Seats are locked via API call
5. Timer counts down correctly
6. Price calculation is accurate
7. Order creation succeeds
8. Redirect to payment page works

---

## Next Steps (Day 2-3)

After testing Day 1:
1. **Fix any bugs found**
2. **Day 2: Payment Integration**
   - VNPay sandbox setup
   - Payment page UI
   - Payment processing
3. **Day 3: Order Management**
   - Order history page
   - Order details page
   - Cancel order functionality

---

## Test Data Reference

From seeders:

**Users:**
- Admin: `admin@cinema.com` / `password`
- User: `user@cinema.com` / `password`

**Movies:**
- Check database for seeded movies
- Should have multiple now showing

**Showtimes:**
- Check database for seeded showtimes
- Should span multiple dates

**Seats:**
- Each screen should have ~60-100 seats
- Mix of Regular and VIP types

---

## Debugging Tips

1. **Enable Laravel Debug Mode**
   ```
   APP_DEBUG=true in .env
   ```

2. **Check Laravel Logs**
   ```
   tail -f storage/logs/laravel.log
   ```

3. **Check Browser Console**
   - Open DevTools → Console tab
   - Look for JavaScript errors

4. **Use Network Tab**
   - See all API requests/responses
   - Check status codes
   - Inspect request/response payloads

5. **Database Queries**
   ```sql
   -- Check seat holds
   SELECT * FROM seat_holds WHERE showtime_id = 1;
   
   -- Check orders
   SELECT * FROM orders WHERE user_id = 1;
   
   -- Check seats status
   SELECT s.*, st.name as seat_type
   FROM seats s
   LEFT JOIN seat_types st ON s.seat_type_id = st.id
   WHERE screen_id = 1;
   ```

---

**Good luck testing! 🎬🎫**