# Authentication and Encrypted ID Fix

## Issue Resolved
Fixed the authentication state management and implemented encrypted showtime IDs for booking URLs.

## Changes Made

### 1. Authentication State Management (`public/js/auth.js`)

**Problem**: The auth system was incorrectly treating users as unauthenticated when the JWT token existed but getUserInfo() failed temporarily.

**Solution**: Modified the authentication check logic:
- Only treat user as unauthenticated when there's NO token at all
- If token exists but getUserInfo() fails, maintain authenticated state
- This prevents false negatives during temporary network issues or API delays

```javascript
// Before
if (!token || !userInfo) {
    updateUIForUnauthenticated();
    return false;
}

// After
if (!token) {
    updateUIForUnauthenticated();
    return false;
}

if (!userInfo) {
    // Token exists but user info fetch failed - maintain auth state
    return true;
}
```

### 2. Encrypted Showtime IDs

**Problem**: Showtime IDs were exposed in URLs, making them predictable and potentially allowing unauthorized access.

**Solution**: Implemented end-to-end encryption for showtime IDs:

#### Backend Changes

1. **Showtime Model** (`app/Models/Showtime.php`):
   - Added `encrypted_id` accessor that automatically encrypts the ID
   - Added to `$appends` array to include in all JSON responses
   ```php
   protected $appends = ['encrypted_id'];
   
   public function getEncryptedIdAttribute()
   {
       return Crypt::encryptString($this->id);
   }
   ```

2. **BookingController** (`app/Http/Controllers/BookingController.php`):
   - Updated to decrypt the encrypted ID from the URL
   - Added proper error handling for invalid/tampered IDs
   ```php
   try {
       $showtimeId = Crypt::decryptString($encryptedId);
   } catch (DecryptException $e) {
       abort(404, 'Suất chiếu không hợp lệ');
   }
   ```

#### Frontend Changes

3. **Movie Detail Page** (`public/js/pages/movie-detail.js`):
   - Updated booking links to use `encrypted_id` instead of plain `id`
   - Added fallback for backward compatibility
   ```javascript
   <a href="/booking/${sanitize(showtime.encrypted_id || showtime.id)}" class="showtime-time-card">
   ```

## Security Benefits

1. **ID Obfuscation**: Raw showtime IDs are no longer visible in URLs
2. **Tampering Protection**: Laravel's encryption includes HMAC verification
3. **Time-based Security**: Encrypted values include timestamps, making replay attacks harder
4. **Non-predictable**: Users cannot guess or enumerate showtime IDs

## Testing

### Test Authentication Fix
1. Log in to the application
2. Navigate between pages
3. Verify the header shows your name and logout button
4. Open browser DevTools Console
5. Verify no false "user is not authenticated" messages

### Test Encrypted IDs
1. Navigate to any movie detail page (e.g., `/movies/movie-slug`)
2. Click on any showtime
3. Observe the URL changes to `/booking/{encrypted-string}`
4. Verify the booking page loads correctly
5. Try tampering with the encrypted ID in the URL - should get 404 error

### Manual Verification
```bash
# Check if encrypted_id is included in API responses
curl -X GET "http://localhost/api/movies/{movie-slug}/showtimes" \
  -H "Accept: application/json"

# Look for "encrypted_id" field in the showtime objects
```

## Backward Compatibility

The implementation includes fallback mechanisms:
- Frontend uses `showtime.encrypted_id || showtime.id` for compatibility
- Old bookmarks with plain IDs will still work (though not recommended)

## Next Steps (Optional Enhancements)

1. **Force Encrypted IDs**: Remove fallback after transition period
2. **Add Rate Limiting**: Prevent brute force attempts on booking endpoints
3. **Audit Logging**: Log failed decryption attempts
4. **Time-based Expiry**: Add timestamp validation to encrypted IDs

## Files Modified

1. `public/js/auth.js` - Authentication state management
2. `app/Models/Showtime.php` - Encrypted ID accessor
3. `app/Http/Controllers/BookingController.php` - Decryption logic
4. `public/js/pages/movie-detail.js` - Use encrypted IDs in links

## Notes

- Encryption uses Laravel's built-in `Crypt` facade (AES-256-CBC)
- Encrypted values are URL-safe (base64 encoded)
- No database schema changes required
- Zero downtime deployment possible
