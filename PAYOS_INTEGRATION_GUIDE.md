# PayOS Integration Guide

## Overview
PayOS là payment gateway của Việt Nam, hỗ trợ thanh toán qua QR code, chuyển khoản ngân hàng, và ví điện tử.

---

## Step 1: Get PayOS Credentials

### Production
1. Đăng ký tài khoản tại: https://payos.vn
2. Xác thực doanh nghiệp
3. Lấy credentials từ dashboard:
   - `Client ID`
   - `API Key`
   - `Checksum Key`

### Sandbox/Test
1. Liên hệ PayOS để lấy sandbox credentials
2. Hoặc dùng test credentials (nếu có)

---

## Step 2: Environment Configuration

Add to `.env`:

```env
# PayOS Configuration
PAYOS_CLIENT_ID=your_client_id_here
PAYOS_API_KEY=your_api_key_here
PAYOS_CHECKSUM_KEY=your_checksum_key_here
PAYOS_RETURN_URL="${APP_URL}/payment/payos/callback"
PAYOS_CANCEL_URL="${APP_URL}/payment/payos/cancel"
PAYOS_ENV=sandbox
```

Add to `config/services.php`:

```php
'payos' => [
    'client_id' => env('PAYOS_CLIENT_ID'),
    'api_key' => env('PAYOS_API_KEY'),
    'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
    'return_url' => env('PAYOS_RETURN_URL'),
    'cancel_url' => env('PAYOS_CANCEL_URL'),
    'env' => env('PAYOS_ENV', 'sandbox'),
    'api_url' => env('PAYOS_ENV', 'sandbox') === 'production' 
        ? 'https://api-merchant.payos.vn' 
        : 'https://api-merchant.payos.vn',
],
```

---

## Step 3: Install PayOS SDK

```bash
composer require payos/payos-php
```

Or manually create PayOS service class (recommended for better control).

---

## Step 4: PayOS Service Implementation

File structure:
```
app/
  Services/
    PayOS/
      PayOSService.php
      PayOSSignature.php
```

---

## Step 5: Payment Flow

### Create Payment
```php
POST /api/payments
{
  "order_id": 123,
  "payment_method": "payos"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "id": 1,
    "order_id": 123,
    "payment_method": "payos",
    "checkout_url": "https://pay.payos.vn/web/...",
    "qr_code_url": "https://api.payos.vn/qr/...",
    "bin": "970422",
    "accountNumber": "1234567890",
    "accountName": "CINEMA CO LTD",
    "amount": 150000
  }
}
```

### Payment Process
1. User clicks "Thanh toán với PayOS"
2. Redirect to `checkout_url` OR show QR code
3. User scans QR / enters bank info
4. PayOS processes payment
5. PayOS redirects to `return_url` with payment result
6. App verifies signature and updates order status

---

## Step 6: Callback Handling

PayOS will send callback to your webhook URL with:

```json
{
  "code": "00",
  "desc": "Thành công",
  "data": {
    "orderCode": 123,
    "amount": 150000,
    "description": "Thanh toan don hang #ORD-123",
    "accountNumber": "1234567890",
    "reference": "FT12345678",
    "transactionDateTime": "2024-01-15 14:30:00",
    "paymentLinkId": "abc123",
    "code": "00",
    "desc": "Thành công",
    "counterAccountBankId": "",
    "counterAccountBankName": "",
    "counterAccountName": "",
    "counterAccountNumber": "",
    "virtualAccountName": "",
    "virtualAccountNumber": ""
  },
  "signature": "..."
}
```

Verify signature before processing.

---

## Step 7: Signature Verification

PayOS uses HMAC SHA256 for signature verification:

```php
function verifySignature($data, $signature, $checksumKey) {
    $dataStr = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $hash = hash_hmac('sha256', $dataStr, $checksumKey);
    return hash_equals($hash, $signature);
}
```

---

## Step 8: Payment Methods in PayOS

PayOS supports:
1. **QR Code** - User scans QR with banking app
2. **Bank Transfer** - Manual transfer to virtual account
3. **Wallet** - MoMo, ZaloPay, etc.

---

## Step 9: Testing

### Test Cards (Sandbox)
- Bank: Any Vietnamese bank
- Account: Test accounts provided by PayOS
- Amount: Any valid amount

### Test Flow
1. Create test order
2. Get payment link
3. Use test banking app or web simulator
4. Complete payment
5. Verify callback received
6. Check order status updated

---

## Implementation Checklist

- [ ] Get PayOS credentials
- [ ] Add config to .env and config/services.php
- [ ] Create PayOSService class
- [ ] Create PayOSSignature helper
- [ ] Update PaymentService to support PayOS
- [ ] Add PayOS option to payment view
- [ ] Create PayOS callback route
- [ ] Implement callback handler
- [ ] Test payment flow
- [ ] Handle edge cases (timeout, cancel, error)

---

## Security Best Practices

1. **Always verify signature** - Never trust data without signature check
2. **Validate order exists** - Check order belongs to current user
3. **Check payment status** - Prevent duplicate processing
4. **Log all transactions** - For debugging and audit
5. **Use HTTPS** - Secure callback URL
6. **Validate amount** - Match order total with payment amount
7. **Handle timeouts** - Payment may take time to process

---

## Error Handling

| Code | Description | Action |
|------|-------------|--------|
| 00 | Success | Update order to paid |
| 01 | Failed | Show error message |
| 02 | Pending | Wait for callback |
| 03 | Canceled | Release seats |

---

## Next Steps

1. Implement PayOSService
2. Update PaymentService
3. Add PayOS to payment view
4. Create callback routes
5. Test end-to-end