# Phase 5 Implementation Plan: API-First Tickets System

**Status:** Ready for Implementation  
**Architecture:** API-First Hybrid (Blade shell + JS frontend)  
**Timeline:** 1-2 days  
**Priority:** HIGH  

---

## Executive Summary

Implement tickets management following API-first architecture pattern already used by booking.js and payment system.

### Pattern Consistency

| Component | Existing (Booking) | New (Tickets) |
|-----------|------------------|---------------|
| **Backend** | PaymentController + PaymentService | TicketController + TicketService |
| **Frontend** | booking.js class-based | tickets.js class-based |
| **Auth** | JWT in localStorage | JWT in localStorage |
| **API** | /api/v1/payments | /api/v1/tickets |
| **Response** | ApiResponse trait | ApiResponse trait |
| **Errors** | Try/catch + error codes | Try/catch + error codes |

---

## Files to Create/Modify

### Backend (3 files)
1. **CREATE** `app/Http/Controllers/Api/V1/TicketController.php`
2. **MODIFY** `routes/api.php` (add ticket routes)
3. **CREATE** `app/Http/Resources/TicketResource.php` (optional but recommended)

### Frontend (2 files)
1. **CREATE** `public/js/pages/tickets.js`
2. **VERIFY** `resources/views/users/tickets/index.blade.php` (shell already correct)

### Tests (2 files)
1. **CREATE** `tests/Feature/Api/TicketControllerTest.php`
2. **CREATE** `tests/Feature/TicketFunctionalTest.php`

---

## Implementation Steps

### Phase 5.1: Create API Endpoint (3 hours)

**File:** `app/Http/Controllers/Api/V1/TicketController.php`

Pattern:
- Constructor with dependency injection (TicketService, AuthService)
- Use ApiResponse trait (ok(), error(), unauthorized())
- Validate with Form Requests
- Use Resources for response formatting
- Proper error handling (try/catch)

Methods needed:
- `index(Request $request)` - list user's tickets with pagination/filtering
- `show(Request $request, string $ticketCode)` - get single ticket details

**File:** `routes/api.php`

Add after line 111 (inside auth:api middleware):
```php
Route::prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'index']);
    Route::get('{ticketCode}', [TicketController::class, 'show']);
});
```

---

### Phase 5.2: Create Frontend Module (3 hours)

**File:** `public/js/pages/tickets.js`

Pattern (from booking.js):
1. Class-based: `class TicketsPage`
2. Constructor: cache DOM elements, store config
3. `init()` method: setup events, load data
4. Async methods: `loadTickets()`, `renderTickets()`
5. Event handlers: pagination, filtering, refresh
6. Error handling: user-friendly messages
7. Loading states: show/hide spinners

Key features:
- Fetch from `/api/v1/tickets` with JWT token
- Support pagination (next/prev buttons)
- Support filtering (status: all, valid, used, etc)
- Render ticket cards client-side
- QR code display
- Responsive design (mobile-first)

---

### Phase 5.3: Update Routes & Views (1 hour)

**File:** `routes/api.php`
- Add ticket routes (see Phase 5.1)

**File:** `resources/views/users/tickets/index.blade.php`
- Verify shell structure (should be correct)
- Add tickets.js script tag
- Ensure containers for JS to populate

---

### Phase 5.4: Write Tests (2 hours)

**File:** `tests/Feature/Api/TicketControllerTest.php`
- Test authenticated access
- Test unauthorized access
- Test pagination
- Test filtering by status
- Test 404 for non-existent ticket

**File:** `tests/Feature/TicketFunctionalTest.php`
- Test full flow: load page → fetch API → render list
- Test pagination clicks
- Test filter interactions
- Test responsive behavior

---

## Code Architecture

### Backend Response Format

Following existing pattern from PaymentController:

```php
// Success
{
    "success": true,
    "data": {
        "data": [...],
        "meta": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 15,
            "total": 75
        }
    },
    "message": "..."
}

// Error
{
    "success": false,
    "message": "Error description",
    "code": "error_code"
}
```

### Frontend Architecture

Following booking.js pattern:

```js
class TicketsPage {
    constructor() {
        this.apiUrl = window.APP_CONFIG.apiUrl;
        this.currentPage = 1;
        this.currentFilter = 'all';
        this.init();
    }
    
    async loadTickets() {
        // Fetch from API
        // Update state
        // Render UI
        // Handle errors
    }
    
    renderTickets() {
        // Clear container
        // Generate HTML for each ticket
        // Update pagination
        // Update filters
    }
}
```

---

## API Endpoint Specifications

### GET /api/v1/tickets

**Auth:** Required (JWT Bearer token)

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 15)
- `status` (optional): Filter by status (all, valid, used, cancelled, refunded)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": 123,
                "ticket_code": "TKT-ABC123DEF",
                "status": "valid",
                "movie": {
                    "id": 1,
                    "title": "Avengers",
                    "poster_url": "..."
                },
                "showtime": {
                    "id": 45,
                    "start_time": "2026-06-15T19:00:00",
                    "screen": {
                        "name": "Screen 1",
                        "cinema": {
                            "name": "Cinema Saigon"
                        }
                    }
                },
                "seat": {
                    "id": 567,
                    "label": "A5",
                    "row": "A",
                    "number": 5
                },
                "qr_code": "data:image/png;...",
                "created_at": "2026-06-10T14:30:00"
            }
        ],
        "meta": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 15,
            "total": 75
        }
    },
    "message": "Tickets loaded successfully"
}
```

**Errors:**
- 401: Unauthorized (no token or invalid token)
- 500: Server error

---

### GET /api/v1/tickets/{ticketCode}

**Auth:** Required (JWT Bearer token)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 123,
        "ticket_code": "TKT-ABC123DEF",
        "status": "valid",
        "qr_code": "...",
        "movie": {...},
        "showtime": {...},
        "seat": {...},
        "order": {...}
    }
}
```

---

## Testing Strategy

### Unit Tests
- TicketController methods
- Authorization checks
- Pagination logic
- Filter logic

### Integration Tests
- Full API flow
- Database queries
- Auth middleware

### Frontend Tests
- JS module initialization
- API call success/error
- DOM rendering
- Pagination interactions
- Filter interactions

### Manual Testing
- Load page as authenticated user
- Verify data loads
- Test pagination
- Test filters
- Test responsive design
- Test error states

---

## Rollback Strategy

If issues occur:

1. **API Controller Issue:**
   - Remove TicketController routes from api.php
   - Tickets page returns error "Feature temporarily unavailable"
   - Revert changes, fix, redeploy

2. **Frontend Issue:**
   - Disable tickets.js loading in Blade
   - Show "Loading..." message
   - Fix JS, redeploy

3. **Database Issue:**
   - API returns 500 error
   - Check Ticket model relations
   - Verify migration
   - Fix, redeploy

**Rollback Time:** 15 minutes

---

## Success Criteria

✅ TicketController created with index/show methods  
✅ Routes added to api.php with auth middleware  
✅ tickets.js created and functional  
✅ Tickets page loads and displays tickets  
✅ Pagination works (next/prev buttons)  
✅ Filtering by status works  
✅ Error handling displays user-friendly messages  
✅ Mobile responsive  
✅ All tests pass  
✅ Zero console errors  

---

## Next Steps (After Implementation)

1. Code review
2. Test in staging
3. QA sign-off
4. Deploy to production
5. Monitor logs
6. Gather user feedback

---

**End of Plan**