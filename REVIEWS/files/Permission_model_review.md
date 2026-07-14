# File Review: Permission.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Permission.php  
**Lines:** 32  
**Type:** Eloquent Model - RBAC Permission

---

## File Information

**Path:** `app/Models/Permission.php`  
**Type:** Eloquent Model  
**Lines:** 32  
**Complexity:** Low  

**Purpose:**  
Represents an authorization permission:
- Stores permission metadata (`name`, `slug`, `description`, `group`)
- Defines many-to-many role relationship through `permission_role`
- Provides query scopes by permission slug and group

**Security Impact:** 🔴 CRITICAL - Core RBAC permission model used for authorization decisions

---

## Overall Score

**Code Quality:** 6.6/10  
**Security:** 6.4/10  
**Performance:** 7.0/10  
**Maintainability:** 6.7/10  
**Laravel Best Practice:** 6.5/10  

**Overall Score:** 6.6/10

**Decision:** ⚠️ **APPROVE WITH IMPROVEMENTS**

---

## Strengths

1. ✅ **Small and Focused** - The model is easy to understand
2. ✅ **Explicit Fillable** - Mass assignment is limited to declared fields
3. ✅ **Typed Relationship** - `roles()` declares `BelongsToMany`
4. ✅ **Pivot Timestamps** - `withTimestamps()` preserves assignment history metadata
5. ✅ **Useful Query Scopes** - `scopeBySlug()` and `scopeByGroup()` improve readability

---

## Issues Found

### Issue #1: Permission Identity Fields Are Mass Assignable

**Severity:** 🟡 MEDIUM  
**Category:** Security - Mass Assignment / Authorization Integrity  
**Location:** Lines 10-15

**Evidence:**
```php
protected $fillable = [
    'name',
    'slug',
    'description',
    'group',
];
```

**Problem:**
The model allows mass assignment of `name`, `slug`, and `group`. In an RBAC system, permission slugs are security-sensitive because authorization checks commonly depend on stable permission identifiers.

If untrusted request data is passed into `Permission::create()` or `$permission->update()`, a mistake in a controller/service could rename or reclassify permissions used by production authorization rules.

**Why this matters:**
Changing permission slugs can silently break authorization checks or grant/deny access incorrectly.

**How to fix:**
Keep permission identity changes behind explicit admin-only service methods and audited workflows.

**Example:**
```php
// Safer default posture for routine updates
protected $fillable = [
    'description',
];

// Explicit privileged operation
$permission->forceFill([
    'name' => $validated['name'],
    'slug' => $validated['slug'],
    'group' => $validated['group'],
])->save();
```

If permissions are intentionally admin-manageable, enforce:
- strict FormRequest authorization
- unique validation for `slug`
- reserved permission protection
- audit logging for create/update/delete

---

### Issue #2: Query Scopes Lack Type Declarations

**Severity:** 🔵 LOW  
**Category:** Code Quality / Type Safety  
**Location:** Lines 23-31

**Evidence:**
```php
public function scopeBySlug($query, $slug)
{
    return $query->where('slug', $slug);
}

public function scopeByGroup($query, $group)
{
    return $query->where('group', $group);
}
```

**Problem:**
The scopes do not declare parameter or return types. This reduces static analysis coverage and makes the method contracts unclear.

**How to fix:**
Use Eloquent builder type declarations.

**Example:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeBySlug(Builder $query, string $slug): Builder
{
    return $query->where('slug', $slug);
}

public function scopeByGroup(Builder $query, string $group): Builder
{
    return $query->where('group', $group);
}
```

---

### Issue #3: No Constants for Permission Slugs or Groups

**Severity:** 🔵 LOW  
**Category:** Maintainability / Duplicate Strings  
**Location:** Missing functionality

**Evidence:**
```php
protected $fillable = [
    'name',
    'slug',
    'description',
    'group',
];
```

**Problem:**
The model does not centralize known permission slugs or permission groups. Authorization logic elsewhere may hardcode strings, increasing typo risk and making refactors difficult.

**How to fix:**
Define constants for core permission groups and heavily used permission slugs.

**Example:**
```php
class Permission extends Model
{
    public const GROUP_USERS = 'users';
    public const GROUP_BOOKINGS = 'bookings';
    public const GROUP_PAYMENTS = 'payments';

    public const VIEW_BOOKINGS = 'bookings.view';
    public const MANAGE_BOOKINGS = 'bookings.manage';
    public const MANAGE_PAYMENTS = 'payments.manage';
}
```

---

### Issue #4: Missing Attribute Casting

**Severity:** 🔵 LOW  
**Category:** Laravel Best Practice / Maintainability  
**Location:** Missing functionality

**Evidence:**
```php
class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'group',
    ];
```

**Problem:**
No `$casts` are defined. This file only exposes simple string attributes, so this is not currently a functional bug. However, explicit casts are useful if timestamps, metadata, JSON configuration, or boolean flags are present in the database schema.

**How to fix:**
If the table contains only standard timestamps and string fields, no urgent change is required. If additional fields exist, cast them explicitly.

**Example:**
```php
protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
```

---

### Issue #5: No Model-Level Guardrails for Reserved Permissions

**Severity:** 🟡 MEDIUM  
**Category:** Security / Business Logic  
**Location:** Missing functionality

**Evidence:**
```php
class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'group',
    ];
```

**Problem:**
There is no visible protection against updating or deleting system-critical permissions. If permission management endpoints exist, reserved permissions could be renamed or removed unless guarded elsewhere.

**Why this matters:**
Deleting or renaming a permission can create production authorization failures:
- admins lose required access
- users gain access because checks no longer match expected slugs
- audit/compliance workflows break

**How to fix:**
Enforce reserved permission protection in the service/policy layer and optionally at the model level.

**Example:**
```php
public const RESERVED_SLUGS = [
    'users.manage',
    'roles.manage',
    'permissions.manage',
    'payments.manage',
];

public function isReserved(): bool
{
    return in_array($this->slug, self::RESERVED_SLUGS, true);
}
```

Then reject destructive operations:
```php
if ($permission->isReserved()) {
    throw new AuthorizationException('Reserved permissions cannot be modified.');
}
```

---

## Recommendations

### IMMEDIATE

1. **Protect Permission Identity Fields** - Avoid unrestricted updates to `slug`, `name`, and `group`
2. **Add Type Declarations to Scopes** - Improve static analysis and maintainability
3. **Add Constants** - Centralize known permission slugs/groups
4. **Define Reserved Permissions** - Prevent accidental deletion/renaming of system permissions

### SHORT TERM

5. **Add Audit Logging** - Log all permission create/update/delete operations
6. **Enforce Unique Slug at DB Level** - Permission slug must be unique
7. **Add Policy for Permission Management** - Middleware alone is not enough
8. **Add Relationship Tests** - Verify `roles()` pivot behavior

### LONG TERM

9. **Cache Role Permissions** - Avoid repeated permission queries during authorization
10. **Add Permission Sync Service** - Centralize RBAC mutation logic
11. **Document Permission Naming Convention** - Example: `resource.action`
12. **Consider Seeding Immutable System Permissions** - Ensure permission baseline is reproducible

---

## Improved Version

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public const GROUP_USERS = 'users';
    public const GROUP_ROLES = 'roles';
    public const GROUP_BOOKINGS = 'bookings';
    public const GROUP_PAYMENTS = 'payments';

    public const RESERVED_SLUGS = [
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'payments.manage',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'group',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function isReserved(): bool
    {
        return in_array($this->slug, self::RESERVED_SLUGS, true);
    }
}
```

---

## Summary

Permission.php is concise and functional, but because it controls authorization semantics, it needs stronger guardrails than a normal metadata model.

**Strengths:**
- Simple model
- Explicit fillable list
- Proper many-to-many relationship with roles
- Useful scopes for slug and group

**Main Gaps:**
1. Permission identity fields are mass assignable
2. Scopes lack type declarations
3. No central constants for slugs/groups
4. No reserved permission protection
5. No visible audit or mutation guardrails

**Status:** ⚠️ Functional, but RBAC hardening is recommended for production security

---

*Review completed: 2026-07-14 11:50 AM*  
*File #26/137 - Phase 2: Security Layer (10/12 complete)*
