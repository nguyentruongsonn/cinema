# File Review: Role.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Role.php  
**Lines:** 32  
**Type:** Eloquent Model - RBAC Role

---

## File Information

**Path:** `app/Models/Role.php`  
**Type:** Eloquent Model  
**Lines:** 32  
**Complexity:** Low  

**Purpose:**  
Represents an authorization role:
- Stores role metadata (`name`, `slug`, `description`)
- Defines user relationship
- Defines permission relationship through `permission_role`
- Provides slug query scope

**Security Impact:** 🔴 CRITICAL - Core RBAC model used for authorization decisions

---

## Overall Score

**Code Quality:** 6.5/10  
**Security:** 6.5/10  
**Performance:** 7.0/10  
**Maintainability:** 6.5/10  
**Laravel Best Practice:** 6.5/10  

**Overall Score:** 6.6/10

**Decision:** ⚠️ **APPROVE WITH IMPROVEMENTS**

---

## Strengths

1. ✅ **Small and Focused** - Model is easy to read
2. ✅ **Typed Relationships** - `users()` and `permissions()` declare relationship return types
3. ✅ **Explicit Fillable** - Uses `$fillable` instead of unguarded mass assignment
4. ✅ **Permission Pivot Timestamps** - `permissions()` includes `withTimestamps()`
5. ✅ **Convenience Scope** - `scopeBySlug()` improves query readability

---

## Issues Found

### Issue #1: Role Attributes Are Mass Assignable

**Severity:** 🟡 MEDIUM  
**Category:** Security - Mass Assignment / Authorization Integrity  
**Location:** Lines 11-15

**Evidence:**
```php
protected $fillable = [
    'name',
    'slug',
    'description',
];
```

**Problem:**
The model allows mass assignment of role identity fields. In RBAC systems, `name` and `slug` are security-sensitive because they are commonly used by middleware and authorization checks.

If a controller or service passes untrusted request data directly into `Role::create()` or `$role->update()`, a user with insufficient privileges could potentially alter a role slug such as `admin` or `super-admin`.

**Why this matters:**
Authorization often depends on stable role slugs/names. Allowing unrestricted mass assignment increases the blast radius of mistakes elsewhere in the application.

**How to fix:**
Use strict FormRequest validation and authorization for role management, and consider making immutable/security-sensitive fields guarded from broad updates.

**Example:**
```php
// Safer model posture
protected $fillable = [
    'description',
];

// Create/update role identity only through explicit service methods:
$role->forceFill([
    'name' => $validated['name'],
    'slug' => $validated['slug'],
])->save();
```

If roles are admin-manageable, keep `$fillable`, but enforce:
- admin-only controller access
- unique validation for `slug`
- immutable system roles
- audit logging for role changes

---

### Issue #2: Missing Attribute Casting

**Severity:** 🔵 LOW  
**Category:** Laravel Best Practice / Maintainability  
**Location:** Missing functionality

**Evidence:**
```php
class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];
```

**Problem:**
The model does not define `$casts`. There are no non-string custom fields in this file, so this is not currently a correctness bug. However, for RBAC models, explicit casts can improve consistency if timestamps, booleans, metadata, or JSON attributes are added later.

**How to fix:**
No immediate fix required unless additional attributes exist in the database. If the table contains extra columns, cast them explicitly.

**Example:**
```php
protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
```

---

### Issue #3: `users()` Relationship May Be Too Restrictive for RBAC

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Authorization Model  
**Location:** Lines 17-20

**Evidence:**
```php
public function users(): HasMany
{
    return $this->hasMany(User::class);
}
```

**Problem:**
This defines a one-role-per-user structure through a `role_id` foreign key on users. That may be valid if the business rule is strictly "one user has one role".

However, this same model also defines a many-to-many relationship with permissions:

```php
public function permissions(): BelongsToMany
{
    return $this->belongsToMany(Permission::class, 'permission_role')
        ->withTimestamps();
}
```

The model therefore supports many permissions per role, but only one role per user. This is not inherently wrong, but it is a strict RBAC design decision that should be explicit and enforced consistently.

**Why this matters:**
If the application later needs multiple roles per user, `hasMany(User::class)` becomes a schema and architecture limitation. Middleware like `hasAnyRole()` must also align with this relationship.

**How to fix:**
If business rule is one role per user, document it and enforce a non-null `role_id` if appropriate.

If users can have multiple roles, use a pivot table:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'role_user')
        ->withTimestamps();
}
```

---

### Issue #4: `scopeBySlug()` Lacks Type Declarations

**Severity:** 🔵 LOW  
**Category:** Code Quality / Type Safety  
**Location:** Lines 28-31

**Evidence:**
```php
public function scopeBySlug($query, $slug)
{
    return $query->where('slug', $slug);
}
```

**Problem:**
The scope has no parameter or return type declarations. This reduces static analysis quality and makes the method contract less clear.

**How to fix:**
Use Eloquent builder types where possible.

**Example:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeBySlug(Builder $query, string $slug): Builder
{
    return $query->where('slug', $slug);
}
```

---

### Issue #5: No Constants for System Role Slugs

**Severity:** 🔵 LOW  
**Category:** Maintainability / Duplicate Strings  
**Location:** Missing functionality

**Evidence:**
```php
protected $fillable = [
    'name',
    'slug',
    'description',
];
```

**Problem:**
The model has no constants for known role slugs. Other files may hardcode role strings, making role names difficult to refactor and easy to mistype.

**How to fix:**
Define central constants for system roles.

**Example:**
```php
class Role extends Model
{
    public const ADMIN = 'admin';
    public const SUPER_ADMIN = 'super-admin';
    public const USER = 'user';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];
}
```

Then authorization code can use:
```php
$user->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN]);
```

---

## Recommendations

### IMMEDIATE

1. **Confirm RBAC Cardinality** - Decide whether users can have one role or many roles
2. **Protect Role Identity Updates** - Avoid unrestricted updates to `name` and `slug`
3. **Add Type Declarations** - Type `scopeBySlug()` for static analysis
4. **Add Role Constants** - Centralize system role slugs

### SHORT TERM

5. **Add Audit Logging Around Role Changes** - Role edits are security-sensitive
6. **Enforce Unique Slug at DB Level** - `slug` must be unique for authorization correctness
7. **Document System Roles** - Define which roles are reserved/immutable
8. **Add Tests for Relationships** - Verify `users()` and `permissions()` match schema

### LONG TERM

9. **Consider Policies for Role Management** - Do not rely only on middleware
10. **Consider Immutable System Roles** - Prevent deleting/renaming admin roles
11. **Add Permission Sync Service** - Avoid scattering RBAC mutation logic
12. **Cache Role Permissions** - If permission checks are frequent

---

## Improved Version

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';
    public const SUPER_ADMIN = 'super-admin';
    public const USER = 'user';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withTimestamps();
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
```

If users can have multiple roles, replace `users()` with `BelongsToMany`.

---

## Summary

Role.php is small and readable, but because it is part of the authorization system, small design choices have high security impact.

**Strengths:**
- Simple model
- Explicit fillable list
- Typed relationship methods
- Permission pivot relationship is clear

**Main Gaps:**
1. Role identity fields are mass assignable
2. User-role cardinality should be explicitly validated against business requirements
3. Query scope lacks type declarations
4. No constants for system role slugs
5. No visible safeguards for reserved/system roles

**Status:** ⚠️ Functional, but RBAC hardening is recommended before production scale

---

*Review completed: 2026-07-14 11:44 AM*  
*File #25/137 - Phase 2: Security Layer (9/12 complete)*
