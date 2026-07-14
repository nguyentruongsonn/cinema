====================================================

File:
app/Models/LoginHistory.php

Overall Score:
4.8/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Defines a `user()` relationship with an explicit `BelongsTo` return type.
- Casts `success`, `logged_in_at`, and `logged_out_at`.
- Provides a centralized helper for recording login attempts.
- Captures useful security audit metadata such as IP address, user agent, login method, and failure reason.

----------------------------------------------------

Issues

### Issue #1

Severity:
Critical

Category:
Security / Sensitive Data Exposure

Location:
app/Models/LoginHistory.php:10-25

Problem

`session_token` is mass assignable and stored directly:

```php
protected $fillable = [
    'user_id',
    'ip_address',
    'user_agent',
    'device_type',
    'platform',
    'browser',
    'login_method',
    'success',
    'failure_reason',
    'country',
    'city',
    'session_token',
    'logged_in_at',
    'logged_out_at',
];
```

Why this matters

Session tokens are authentication secrets. Storing raw session tokens in login history creates a high-impact credential exposure risk if the database, logs, backups, admin panels, or debug dumps are accessed. A login-history table is typically broadly queried for audits, making token leakage more likely.

How to fix

Do not store raw session tokens. Store a non-reversible hash or token identifier only if correlation is required.

Example

Before

```php
'session_token' => $sessionToken,
```

After

```php
'session_token_hash' => $sessionToken ? hash('sha256', $sessionToken) : null,
```

Prefer naming the column `session_token_hash` to make the invariant explicit.

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Authorization / Audit Integrity

Location:
app/Models/LoginHistory.php:10-25

Problem

Security audit fields are mass assignable:

```php
'user_id',
'ip_address',
'user_agent',
'device_type',
'platform',
'browser',
'login_method',
'success',
'failure_reason',
'country',
'city',
'session_token',
'logged_in_at',
'logged_out_at',
```

Why this matters

Login history is audit data. Making all audit fields mass assignable means any code path that accepts request input and calls `LoginHistory::create($request->all())` or `update()` can forge login history, alter success/failure status, overwrite IP addresses, or set logout timestamps. Audit records should be append-only and system-generated.

How to fix

Make the model guarded from public mass assignment or only allow creation through a dedicated service that explicitly sets system-derived values.

Example

```php
protected $guarded = ['id'];
```

Better:

```php
protected $fillable = [];
```

and create through a service using force fill internally:

```php
$history = new LoginHistory();
$history->forceFill([
    'user_id' => $userId,
    'ip_address' => $ipAddress,
    // ...
])->save();
```

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Security / Privacy

Location:
app/Models/LoginHistory.php:10-25

Problem

The model stores PII/security telemetry fields such as IP address, user agent, country, and city:

```php
'ip_address',
'user_agent',
'country',
'city',
```

There is no retention policy, anonymization strategy, or deletion policy visible in the model.

Why this matters

Login history can contain sensitive personal data. Keeping it indefinitely increases privacy risk and breach impact. Production systems should define retention windows for authentication telemetry and either purge or anonymize old records.

How to fix

Implement retention through scheduled jobs and document the policy. Consider anonymizing IP addresses after a defined period.

Example

```php
LoginHistory::where('created_at', '<', now()->subDays(180))->delete();
```

For anonymization:

```php
$history->update([
    'ip_address' => null,
    'user_agent' => null,
    'city' => null,
]);
```

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Security / Input Validation

Location:
app/Models/LoginHistory.php:90-113

Problem

`record()` accepts arbitrary strings and persists them directly:

```php
public static function record(
    int $userId,
    string $ipAddress,
    ?string $userAgent,
    string $loginMethod,
    bool $success,
    ?string $failureReason = null,
    ?string $sessionToken = null
): self {
    $uaInfo = self::parseUserAgent($userAgent);

    return self::create([
        'user_id' => $userId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        ...
        'login_method' => $loginMethod,
        'failure_reason' => $failureReason,
        'session_token' => $sessionToken,
        'logged_in_at' => now(),
    ]);
}
```

No length limits, enum restrictions, IP validation, or failure reason restrictions are enforced here.

Why this matters

User-agent strings and failure reasons can be large. Persisting unbounded data can cause database truncation errors, excessive storage growth, or log/UI issues when rendered later. `login_method` should be constrained to known values to keep analytics and security reporting reliable.

How to fix

Validate and normalize inputs before persistence. Enforce database column lengths and enum-like constraints.

Example

```php
$loginMethod = in_array($loginMethod, ['password', 'refresh_token', 'oauth'], true)
    ? $loginMethod
    : 'unknown';

$userAgent = $userAgent ? mb_substr($userAgent, 0, 512) : null;
$failureReason = $failureReason ? mb_substr($failureReason, 0, 255) : null;

if (! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
    $ipAddress = '0.0.0.0';
}
```

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Database Correctness / Referential Integrity

Location:
app/Models/LoginHistory.php:33-36

Problem

The relationship is defined as:

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

The model does not define behavior for login-history records when users are deleted.

Why this matters

Authentication audit logs often need to survive account deletion for fraud investigation, abuse prevention, and security incident analysis. If the database cascades deletes from users to login histories, audit evidence may be destroyed. If there is no foreign key policy, orphaned records may accumulate.

How to fix

Define an explicit data-retention policy. If audit history must survive user deletion, use `nullOnDelete()` and allow nullable `user_id`, or copy immutable user identifiers into the audit row.

Example migration policy:

```php
$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
```

Model relationship:

```php
return $this->belongsTo(User::class)->withDefault();
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Correctness / Business Logic

Location:
app/Models/LoginHistory.php:101-113

Problem

`record()` always sets `logged_in_at` even for failed login attempts:

```php
'success' => $success,
'failure_reason' => $failureReason,
'session_token' => $sessionToken,
'logged_in_at' => now(),
```

Why this matters

A failed login attempt is not a login. Setting `logged_in_at` for failures makes analytics and audit queries ambiguous. Reports such as "last login", "active sessions", and "successful login count" can become incorrect if they rely on this timestamp.

How to fix

Separate `attempted_at` from `logged_in_at`, or only set `logged_in_at` for successful attempts.

Example

```php
'attempted_at' => now(),
'logged_in_at' => $success ? now() : null,
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Correctness / Session Tracking

Location:
app/Models/LoginHistory.php:119-122

Problem

`markLoggedOut()` updates `logged_out_at` every time it is called:

```php
public function markLoggedOut(): bool
{
    return $this->update(['logged_out_at' => now()]);
}
```

Why this matters

Logout should normally be idempotent. Repeated calls should not rewrite audit history and change the original logout time. Current behavior can corrupt audit trails if logout is triggered multiple times, retried, or called by concurrent requests.

How to fix

Only set `logged_out_at` when it is currently null.

Example

```php
public function markLoggedOut(): bool
{
    if ($this->logged_out_at !== null) {
        return true;
    }

    return $this->update(['logged_out_at' => now()]);
}
```

For stronger concurrency protection:

```php
return static::whereKey($this->getKey())
    ->whereNull('logged_out_at')
    ->update(['logged_out_at' => now()]) === 1;
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Architecture / Testability

Location:
app/Models/LoginHistory.php:41-85

Problem

The Eloquent model contains user-agent parsing logic:

```php
public static function parseUserAgent(?string $userAgent): array
{
    ...
}
```

Why this matters

Parsing user agents is not persistence responsibility. Putting parsing logic in the model makes the model harder to test, couples persistence to parsing heuristics, and encourages more business/security logic to accumulate in Eloquent models.

How to fix

Move parsing to a dedicated service/value object, injected into the authentication/audit service.

Example

```php
final class UserAgentParser
{
    public function parse(?string $userAgent): array
    {
        // parsing logic
    }
}
```

Then `LoginHistoryService` composes parsed metadata and persists the record.

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Correctness / User Agent Parsing

Location:
app/Models/LoginHistory.php:53-69

Problem

Platform detection checks Linux before Android:

```php
} elseif (preg_match('/linux/i', $userAgent)) {
    $result['platform'] = 'Linux';
} elseif (preg_match('/android/i', $userAgent)) {
    $result['platform'] = 'Android';
}
```

Why this matters

Android user-agent strings commonly contain `Linux`. This code will classify many Android devices as Linux, making device analytics and suspicious-login detection inaccurate.

How to fix

Check Android before Linux.

Example

```php
} elseif (preg_match('/android/i', $userAgent)) {
    $result['platform'] = 'Android';
} elseif (preg_match('/linux/i', $userAgent)) {
    $result['platform'] = 'Linux';
}
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Correctness / User Agent Parsing

Location:
app/Models/LoginHistory.php:72-82

Problem

Browser detection checks Chrome before Opera:

```php
if (preg_match('/chrome/i', $userAgent) && !preg_match('/edg/i', $userAgent)) {
    $result['browser'] = 'Chrome';
...
} elseif (preg_match('/opera|opr/i', $userAgent)) {
    $result['browser'] = 'Opera';
}
```

Why this matters

Opera user-agent strings often include `Chrome` and `OPR`. This code will classify Opera as Chrome because the Chrome branch runs first and only excludes Edge.

How to fix

Check Edge and Opera before Chrome.

Example

```php
if (preg_match('/edg/i', $userAgent)) {
    $result['browser'] = 'Edge';
} elseif (preg_match('/opera|opr/i', $userAgent)) {
    $result['browser'] = 'Opera';
} elseif (preg_match('/chrome/i', $userAgent)) {
    $result['browser'] = 'Chrome';
}
```

----------------------------------------------------

### Issue #11

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/LoginHistory.php:10-31

Problem

The model captures fields that are likely to be queried for security screens and analytics:

```php
'user_id',
'ip_address',
'success',
'login_method',
'logged_in_at',
'logged_out_at',
```

There is no indication of query scopes or expected indexes.

Why this matters

Login history can grow quickly. Admin dashboards and security checks often query by `user_id`, IP, success status, and time ranges. Without indexes and query scopes, these queries can become slow and expensive in production.

How to fix

Add indexes in migrations and reusable scopes.

Example indexes:

```php
$table->index(['user_id', 'logged_in_at']);
$table->index(['ip_address', 'logged_in_at']);
$table->index(['success', 'logged_in_at']);
```

Example scopes:

```php
public function scopeSuccessful($query)
{
    return $query->where('success', true);
}

public function scopeRecent($query, int $days = 30)
{
    return $query->where('logged_in_at', '>=', now()->subDays($days));
}
```

----------------------------------------------------

### Issue #12

Severity:
Low

Category:
Clean Code / Laravel Best Practices

Location:
app/Models/LoginHistory.php:41-113

Problem

The model uses static helper methods for domain behavior:

```php
public static function parseUserAgent(?string $userAgent): array
...
public static function record(...): self
```

Why this matters

Static methods are harder to mock and replace. This reduces testability of authentication flows and prevents swapping the parser/audit implementation.

How to fix

Move recording to a dedicated service.

Example

```php
final class LoginHistoryService
{
    public function record(LoginAttemptData $data): LoginHistory
    {
        // validate, parse, hash token, persist
    }
}
```

----------------------------------------------------

### Issue #13

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/LoginHistory.php:8-123

Problem

The model has no PHPDoc annotations for dynamic Eloquent properties.

Why this matters

This model contains sensitive security audit data. Static analysis should be as strong as possible to avoid accidentally exposing or misusing fields such as `session_token`, `ip_address`, and `failure_reason`.

How to fix

Add PHPDoc or adopt Laravel IDE Helper/static analysis conventions.

Example

```php
/**
 * @property int $id
 * @property int $user_id
 * @property string $ip_address
 * @property string|null $user_agent
 * @property bool $success
 * @property \Illuminate\Support\Carbon|null $logged_in_at
 * @property \Illuminate\Support\Carbon|null $logged_out_at
 */
class LoginHistory extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`LoginHistory` is intended to support security auditing, but it currently stores raw session tokens, exposes audit fields through mass assignment, mixes parsing and persistence responsibilities, and has correctness issues in both timestamp semantics and user-agent parsing. These issues should be fixed before this model is considered production-ready.
