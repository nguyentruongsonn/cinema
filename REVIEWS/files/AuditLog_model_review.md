====================================================

File:
app/Models/AuditLog.php

Overall Score:
5.8/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Defines a dedicated model for audit log records instead of mixing audit data into business tables.
- Supports polymorphic association through `auditable()`.
- Casts `old_values` and `new_values` as JSON.
- Provides query scopes for common audit-log lookups by action, user, and auditable entity.
- Keeps the model small and easy to read.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Security / Audit Integrity / Mass Assignment

Location:
app/Models/AuditLog.php:10-19

Problem

All sensitive audit fields are mass assignable:

```php
protected $fillable = [
    'user_id',
    'action',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
];
```

Why this matters

Audit logs are security evidence. Allowing all audit fields to be mass assigned makes it easier for application code paths to accidentally or maliciously create forged audit records with arbitrary users, actions, entities, IP addresses, and values. In production, audit trails must be trustworthy and controlled.

How to fix

Do not expose audit log creation through generic mass assignment from request data. Prefer a dedicated audit writer/service that accepts an authenticated actor and a domain event, then constructs immutable audit records internally.

Example

Before

```php
AuditLog::create($request->all());
```

After

```php
final class AuditLogger
{
    public function record(User $actor, string $action, Model $auditable, array $oldValues, array $newValues, Request $request): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
```

If direct model creation must remain possible, use guarded fields and prevent request-driven assignment.

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Security / Data Exposure

Location:
app/Models/AuditLog.php:15-16,21-24

Problem

The model stores full `old_values` and `new_values` JSON payloads with no redaction mechanism:

```php
'old_values',
'new_values',
```

```php
protected $casts = [
    'old_values' => 'json',
    'new_values' => 'json',
];
```

Why this matters

Audit diffs often contain sensitive data: password hashes, refresh tokens, reset tokens, payment identifiers, email addresses, phone numbers, addresses, and potentially customer PII. Without model-level or service-level redaction guarantees, audit logs can become a secondary sensitive data store with broad exposure and longer retention.

How to fix

Implement a centralized audit sanitizer that strips or masks sensitive fields before values are stored.

Example

```php
private const SENSITIVE_KEYS = [
    'password',
    'remember_token',
    'refresh_token',
    'reset_token',
    'token',
    'secret',
    'api_key',
];

private function sanitize(array $values): array
{
    return collect($values)
        ->mapWithKeys(fn ($value, $key) => [
            $key => in_array($key, self::SENSITIVE_KEYS, true) ? '[REDACTED]' : $value,
        ])
        ->all();
}
```

----------------------------------------------------

### Issue #3

Severity:
Medium

Category:
Database Correctness / Maintainability

Location:
app/Models/AuditLog.php:13-14,31-34

Problem

The model uses a polymorphic relation but does not define or enforce a morph map:

```php
'auditable_type',
'auditable_id',
```

```php
public function auditable(): MorphTo
{
    return $this->morphTo();
}
```

Why this matters

Without a morph map, Laravel stores full class names in `auditable_type`. This tightly couples persisted audit data to PHP namespaces. Refactoring model namespaces or class names can break historical audit lookup. It can also expose internal implementation details through API responses if raw audit models are serialized.

How to fix

Define a morph map in a service provider and store stable aliases.

Example

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'order' => \App\Models\Order::class,
    'payment' => \App\Models\Payment::class,
    'user' => \App\Models\User::class,
]);
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Data Integrity / Immutability

Location:
app/Models/AuditLog.php:8-51

Problem

The model does not enforce audit-log immutability.

There is no protection against updates or deletes in the model:

```php
class AuditLog extends Model
{
    // ...
}
```

Why this matters

Audit logs should be append-only. If any internal code can update or delete audit records through Eloquent, the audit trail is not reliable for investigation, fraud review, or compliance.

How to fix

Prevent updates and deletes at the model level and enforce append-only behavior at the database/permission level where possible.

Example

```php
protected static function booted(): void
{
    static::updating(function () {
        throw new \RuntimeException('Audit logs are immutable.');
    });

    static::deleting(function () {
        throw new \RuntimeException('Audit logs cannot be deleted.');
    });
}
```

For stronger protection, use restricted database permissions or append-only storage.

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/AuditLog.php:36-50

Problem

The query scopes imply frequent filtering by `action`, `user_id`, `auditable_type`, and `auditable_id`:

```php
public function scopeByAction($query, $action)
{
    return $query->where('action', $action);
}

public function scopeByUser($query, $userId)
{
    return $query->where('user_id', $userId);
}

public function scopeByAuditable($query, $type, $id)
{
    return $query->where('auditable_type', $type)
        ->where('auditable_id', $id);
}
```

The model itself cannot confirm indexes exist, but these access patterns require supporting indexes in the migration.

Why this matters

Audit logs grow quickly in production. Filtering by action, user, or auditable entity without indexes will degrade into expensive table scans and make admin/security audit views slow or unavailable.

How to fix

Ensure database migrations include indexes matching these scopes.

Example

```php
$table->index('action');
$table->index('user_id');
$table->index(['auditable_type', 'auditable_id']);
$table->index(['user_id', 'created_at']);
$table->index(['auditable_type', 'auditable_id', 'created_at']);
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Type Safety / Laravel Best Practices

Location:
app/Models/AuditLog.php:36-50

Problem

The local scopes have untyped parameters and no return type declarations:

```php
public function scopeByAction($query, $action)
public function scopeByUser($query, $userId)
public function scopeByAuditable($query, $type, $id)
```

Why this matters

Scopes are shared query APIs. Untyped inputs allow invalid values such as arrays, empty strings, non-integer IDs, or arbitrary class names. This weakens readability, static analysis, and testability.

How to fix

Use `Builder` type hints and strict scalar types.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeByAction(Builder $query, string $action): Builder
{
    return $query->where('action', $action);
}

public function scopeByUser(Builder $query, int $userId): Builder
{
    return $query->where('user_id', $userId);
}

public function scopeByAuditable(Builder $query, string $type, int $id): Builder
{
    return $query->where('auditable_type', $type)
        ->where('auditable_id', $id);
}
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Security / Input Validation

Location:
app/Models/AuditLog.php:36-50

Problem

The scope inputs are passed directly into query conditions with no domain validation:

```php
return $query->where('action', $action);
```

```php
return $query->where('auditable_type', $type)
    ->where('auditable_id', $id);
```

Why this matters

Although Eloquent parameter binding prevents SQL injection here, unrestricted audit filters can expose unrelated audit records if controller/service authorization is weak. In particular, `scopeByAuditable($type, $id)` allows querying any auditable type/id pair.

How to fix

Keep scopes low-level, but ensure any externally driven audit searches go through a policy/authorization layer and validate allowed actions/types.

Example

```php
public const ALLOWED_AUDITABLE_TYPES = [
    'order',
    'payment',
    'user',
];

$request->validate([
    'auditable_type' => ['sometimes', Rule::in(AuditLog::ALLOWED_AUDITABLE_TYPES)],
    'auditable_id' => ['required_with:auditable_type', 'integer', 'min:1'],
]);
```

----------------------------------------------------

### Issue #8

Severity:
Low

Category:
Clean Code / Import Consistency

Location:
app/Models/AuditLog.php:26

Problem

The `user()` relationship uses a fully qualified class name in the return type instead of importing it:

```php
public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
```

Why this matters

This is inconsistent with the imported `MorphTo` relation and reduces readability.

How to fix

Import `BelongsTo` and use the short class name.

Example

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

----------------------------------------------------

### Issue #9

Severity:
Low

Category:
API Safety / Serialization

Location:
app/Models/AuditLog.php:8-51

Problem

The model does not define `$hidden` or an API resource boundary for sensitive audit attributes.

Why this matters

If an `AuditLog` model is returned directly from a controller, it may expose internal class names, raw diff payloads, IP addresses, user agents, and user IDs. Audit records are sensitive and should not be serialized directly.

How to fix

Do not return raw audit models. Use an API resource that redacts or conditionally exposes fields based on authorization.

Example

```php
final class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'auditable' => [
                'type' => $this->auditable_type,
                'id' => $this->auditable_id,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
```

----------------------------------------------------

Final Assessment

`AuditLog` is a useful foundation for auditability, but it is not production-ready as a trustworthy security audit model. The major concerns are mass-assignable audit evidence, no redaction of sensitive values, no immutability enforcement, and polymorphic type coupling. Audit logs must be append-only, sanitized, authorization-protected, indexed for expected access patterns, and written through a controlled service rather than treated like a normal mutable Eloquent record.