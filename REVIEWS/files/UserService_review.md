# File Review: UserService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/UserService.php  
**Lines:** 229  
**Type:** Service Layer - User Administration

---

## File Information

**Path:** `app/Services/UserService.php`  
**Type:** Laravel Service Class  
**Lines:** 229  
**Complexity:** Medium  

**Purpose:**  
Provides user administration operations:
- Paginated user listing with filters
- User creation
- User update
- User deletion
- Status toggling
- Password reset
- Role dropdown retrieval
- User statistics

**Business Impact:** 🔴 CRITICAL - This service manages users, roles, account status, passwords, and administrative visibility. Mistakes here can cause privilege escalation, account takeover, data exposure, or unauthorized account changes.

---

## Overall Score

**Code Quality:** 5.8/10  
**Security:** 4.8/10  
**Performance:** 6.0/10  
**Maintainability:** 5.6/10  
**Laravel Best Practice:** 5.4/10  

**Overall Score:** 5.5/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses Transactions for Create/Update/Delete** - Multi-step mutations are wrapped in transactions.
2. ✅ **Passwords Are Hashed** - `Hash::make()` is used before storing passwords.
3. ✅ **Role Eager Loading in Listing** - `User::with('role')` avoids N+1 for role display.
4. ✅ **Pagination Is Used for User Listing** - Avoids unbounded user listing.
5. ✅ **Operational Logging Exists** - Create/update/delete/status/password actions are logged.
6. ✅ **Basic Active Order Guard Before Deletion** - Prevents deleting users with pending/processing orders.
7. ✅ **User Statistics Method Is Simple and Readable**.

---

## Issues Found

### Issue #1: Unsafe Dynamic Sorting Allows Arbitrary Column Ordering

**Severity:** 🟠 HIGH  
**Category:** Security / Database / API Correctness  
**Location:** Lines 54-57

**Evidence:**
```php
$sortBy = $filters['sort_by'] ?? 'created_at';
$sortOrder = $filters['sort_order'] ?? 'desc';
$query->orderBy($sortBy, $sortOrder);
```

**Problem:**
`sort_by` and `sort_order` are used directly without a whitelist.

**Why this matters:**
Laravel parameter binding does not protect column names or directions in the same way it protects values. Even if the query builder quotes identifiers in many cases, accepting arbitrary sort fields is still not production-safe. It can expose unexpected columns, break queries, enable expensive sorting, or produce inconsistent API behavior.

**How to fix:**
Whitelist allowed columns and directions.

**Example:**
```php
$allowedSorts = ['id', 'name', 'email', 'created_at', 'status'];
$allowedDirections = ['asc', 'desc'];

$sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
    ? $filters['sort_by']
    : 'created_at';

$sortOrder = in_array(strtolower($filters['sort_order'] ?? ''), $allowedDirections, true)
    ? strtolower($filters['sort_order'])
    : 'desc';

$query->orderBy($sortBy, $sortOrder);
```

---

### Issue #2: Create User Uses Raw `$data` in Mass Assignment

**Severity:** 🔴 CRITICAL  
**Category:** Security / Mass Assignment / Privilege Escalation  
**Location:** Lines 75-80

**Evidence:**
```php
$data['status'] = $data['status'] ?? true;
$data['loyalty_points'] = $data['loyalty_points'] ?? 0;

// Create user
$user = User::create($data);
```

**Problem:**
The service passes the full `$data` array directly to `User::create()`.

**Why this matters:**
If caller validation is incomplete or bypassed, an attacker/admin client can set unintended fields supported by the model's `$fillable`, such as role/status/loyalty/security-related fields. User creation is security-critical and should only persist explicitly allowed fields.

**How to fix:**
Build an explicit payload instead of trusting raw input.

```php
$user = User::create([
    'name' => $data['name'],
    'email' => $data['email'],
    'username' => $data['username'] ?? null,
    'phone' => $data['phone'] ?? null,
    'password' => Hash::make($data['password']),
    'status' => $data['status'] ?? true,
    'loyalty_points' => 0,
]);
```

---

### Issue #3: Update User Uses Raw `$data` in Mass Assignment

**Severity:** 🔴 CRITICAL  
**Category:** Security / Mass Assignment / Privilege Escalation  
**Location:** Lines 113-121

**Evidence:**
```php
if (!empty($data['password'])) {
    $data['password'] = Hash::make($data['password']);
} else {
    unset($data['password']);
}

// Update user
$user->update($data);
```

**Problem:**
The service passes the full `$data` array directly to `$user->update()`.

**Why this matters:**
User update is a high-risk operation. Raw mass assignment can allow unintended changes to role, status, loyalty points, verification state, or other sensitive fields depending on the model fillable configuration. This can become privilege escalation or account state tampering.

**How to fix:**
Explicitly map allowed update fields per use case.

```php
$payload = Arr::only($data, [
    'name',
    'email',
    'username',
    'phone',
    'status',
]);

if (!empty($data['password'])) {
    $payload['password'] = Hash::make($data['password']);
}

$user->update($payload);
```

Role updates should be handled separately with authorization and role validation.

---

### Issue #4: Role Assignment Is Not Validated

**Severity:** 🟠 HIGH  
**Category:** Authorization / Data Integrity / Privilege Escalation  
**Location:** Lines 82-91 and 123-132

**Evidence:**
```php
if (!empty($data['role_id'])) {
    $user->role_id = $data['role_id'];
    $user->save();
} elseif (!empty($data['roles'])) {
    // Backward compatibility: if 'roles' is provided, take first one
    $roleId = is_array($data['roles']) ? $data['roles'][0] : $data['roles'];
    $user->role_id = $roleId;
    $user->save();
}
```

```php
if (isset($data['role_id'])) {
    $user->role_id = $data['role_id'];
    $user->save();
} elseif (isset($data['roles'])) {
    // Backward compatibility: if 'roles' is provided, take first one
    $roleId = is_array($data['roles']) ? $data['roles'][0] : $data['roles'];
    $user->role_id = $roleId;
    $user->save();
}
```

**Problem:**
The service assigns `role_id` without verifying that the role exists, is assignable, or that the actor is allowed to assign it.

**Why this matters:**
This is a direct privilege escalation risk. If a controller passes user-controlled role data, a user could be assigned an admin role. Even for admin endpoints, not every admin should necessarily be allowed to assign all roles.

**How to fix:**
Validate role existence and enforce authorization in a policy/action before assignment.

```php
$role = Role::whereKey($roleId)->firstOrFail();

if (!$actor->can('assignRole', $role)) {
    throw new AuthorizationException('Not allowed to assign this role.');
}

$user->role()->associate($role);
$user->save();
```

---

### Issue #5: Backward-Compatible `roles` Input Creates Ambiguous Security Behavior

**Severity:** 🟠 HIGH  
**Category:** Security / Maintainability / API Contract  
**Location:** Lines 86-90 and 127-131

**Evidence:**
```php
} elseif (!empty($data['roles'])) {
    // Backward compatibility: if 'roles' is provided, take first one
    $roleId = is_array($data['roles']) ? $data['roles'][0] : $data['roles'];
    $user->role_id = $roleId;
    $user->save();
}
```

```php
} elseif (isset($data['roles'])) {
    // Backward compatibility: if 'roles' is provided, take first one
    $roleId = is_array($data['roles']) ? $data['roles'][0] : $data['roles'];
    $user->role_id = $roleId;
    $user->save();
}
```

**Problem:**
The service accepts both `role_id` and legacy `roles`, then silently takes the first role when an array is provided.

**Why this matters:**
Security-sensitive role assignment should have one explicit API contract. Silently taking the first role can assign the wrong role depending on client ordering and makes authorization/audit behavior unclear.

**How to fix:**
Remove legacy `roles` handling or handle it in a migration-specific compatibility layer with explicit validation and logging.

---

### Issue #6: Password Strength Is Not Enforced in Service Contract

**Severity:** 🟠 HIGH  
**Category:** Authentication / Validation  
**Location:** Lines 70-73 and 191-196

**Evidence:**
```php
if (!empty($data['password'])) {
    $data['password'] = Hash::make($data['password']);
}
```

```php
$user->update([
    'password' => Hash::make($newPassword)
]);
```

**Problem:**
The service hashes passwords but does not enforce password length, complexity, compromise checks, or confirmation.

**Why this matters:**
Password reset and account creation are security-critical. If upstream validation is missing or inconsistent, weak passwords can be stored.

**How to fix:**
Validate password rules in FormRequest and/or enforce a domain-level password policy before hashing.

```php
Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
```

---

### Issue #7: Password Reset Does Not Revoke Existing Sessions or Tokens

**Severity:** 🟠 HIGH  
**Category:** Authentication / Account Security  
**Location:** Lines 191-199

**Evidence:**
```php
$user->update([
    'password' => Hash::make($newPassword)
]);

Log::info('User password reset', ['user_id' => $user->id]);
```

**Problem:**
After resetting the password, the service does not revoke refresh tokens, access tokens, remember tokens, or active sessions.

**Why this matters:**
If a password reset is performed because of compromise, existing authenticated sessions may remain valid. This weakens account recovery and incident response.

**How to fix:**
Invalidate existing credentials after password reset.

```php
$user->forceFill([
    'password' => Hash::make($newPassword),
    'remember_token' => Str::random(60),
])->save();

// Also revoke refresh tokens / Sanctum tokens / JWT token family if used by the application.
```

---

### Issue #8: User Creation Can Create Accounts Without Password

**Severity:** 🟠 HIGH  
**Category:** Authentication / Data Integrity  
**Location:** Lines 70-80

**Evidence:**
```php
// Hash password if provided
if (!empty($data['password'])) {
    $data['password'] = Hash::make($data['password']);
}
```

```php
$user = User::create($data);
```

**Problem:**
The method only hashes a password if it is present. It does not require a password for user creation.

**Why this matters:**
Depending on database constraints, this can create unusable accounts, accounts with null passwords, or inconsistent authentication behavior.

**How to fix:**
Require password for normal local-account creation or split account creation flows by authentication type.

```php
if (empty($data['password'])) {
    throw ValidationException::withMessages([
        'password' => 'Password is required.',
    ]);
}
```

---

### Issue #9: Role Filter Silently Ignores Invalid Roles

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Validation  
**Location:** Lines 32-38

**Evidence:**
```php
if (!empty($filters['role'])) {
    $roleId = Role::where('slug', $filters['role'])->value('id');
    if ($roleId) {
        $query->where('role_id', $roleId);
    }
}
```

**Problem:**
If an invalid role slug is provided, the filter is ignored and all roles are returned.

**Why this matters:**
This can expose more user records than the client intended. Invalid filters should fail closed or return an empty result, not broaden the query.

**How to fix:**
If role is invalid, throw a validation error or apply a query that returns no results.

```php
$roleId = Role::where('slug', $filters['role'])->value('id');

if (!$roleId) {
    $query->whereRaw('1 = 0');
}
```

Prefer FormRequest validation with `exists:roles,slug`.

---

### Issue #10: Search Input Is Not Length-Limited or Normalized

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Validation  
**Location:** Lines 21-30

**Evidence:**
```php
$search = $filters['search'];
$query->where(function ($q) use ($search) {
    $q->where('name', 'like', "%{$search}%")
      ->orWhere('email', 'like', "%{$search}%")
      ->orWhere('username', 'like', "%{$search}%")
      ->orWhere('phone', 'like', "%{$search}%");
});
```

**Problem:**
The search string is not trimmed, length-limited, minimum-length checked, or wildcard-escaped.

**Why this matters:**
Long or wildcard-heavy input can create expensive database queries and broad result sets. This is especially risky for admin user listing because it queries multiple PII fields.

**How to fix:**
Validate and normalize search input before querying.

```php
$search = trim((string) $filters['search']);

if (mb_strlen($search) > 100) {
    throw ValidationException::withMessages(['search' => 'Search query is too long.']);
}
```

---

### Issue #11: Leading-Wildcard Search Across PII Fields Is Expensive

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Database  
**Location:** Lines 24-29

**Evidence:**
```php
$q->where('name', 'like', "%{$search}%")
  ->orWhere('email', 'like', "%{$search}%")
  ->orWhere('username', 'like', "%{$search}%")
  ->orWhere('phone', 'like', "%{$search}%");
```

**Problem:**
`LIKE "%term%"` across four columns is not index-friendly.

**Why this matters:**
As the user table grows, admin user search can become slow and increase database load. Searching phone/email with leading wildcards is usually unnecessary.

**How to fix:**
Use indexed prefix search for email/phone/username and full-text or dedicated search for name.

```php
$q->where('email', 'like', "{$search}%")
  ->orWhere('username', 'like', "{$search}%")
  ->orWhere('phone', 'like', "{$search}%");
```

---

### Issue #12: `$perPage` Is Not Bounded

**Severity:** 🟡 MEDIUM  
**Category:** Performance / API Scalability  
**Location:** Lines 17 and 59

**Evidence:**
```php
public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
```

```php
return $query->paginate($perPage);
```

**Problem:**
The service accepts any integer `$perPage`.

**Why this matters:**
A caller can request extremely large pages and cause excessive memory usage and slow responses.

**How to fix:**
Clamp page size.

```php
$perPage = max(1, min($perPage, 100));
```

---

### Issue #13: Boolean Filters Use Unsafe Casting

**Severity:** 🟡 MEDIUM  
**Category:** Validation / API Correctness  
**Location:** Lines 40-52

**Evidence:**
```php
if (isset($filters['status']) && $filters['status'] !== '') {
    $query->where('status', (bool) $filters['status']);
}
```

```php
if (isset($filters['verified']) && $filters['verified'] !== '') {
    if ($filters['verified']) {
        $query->whereNotNull('email_verified_at');
    } else {
        $query->whereNull('email_verified_at');
    }
}
```

**Problem:**
PHP boolean casting treats non-empty strings such as `"false"` as `true`.

**Why this matters:**
API filters can return the opposite result from what the client requested. For admin user management, incorrect filters can cause incorrect account management actions.

**How to fix:**
Use `filter_var()` or Laravel boolean validation.

```php
$status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($status === null) {
    throw ValidationException::withMessages(['status' => 'Invalid status filter.']);
}
```

---

### Issue #14: Delete User Check Is Not Concurrency-Safe

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Data Integrity  
**Location:** Lines 151-159

**Evidence:**
```php
DB::beginTransaction();

// Check if user has active orders
if ($user->orders()->whereIn('status', ['pending', 'processing'])->exists()) {
    throw new \Exception('Cannot delete user with active orders');
}

$user->delete();
```

**Problem:**
The code checks for active orders and then deletes the user, but it does not lock the user row or related order state.

**Why this matters:**
A concurrent order can be created or moved to `pending` after the check but before deletion. This can leave active orders linked to a deleted user or create business inconsistencies.

**How to fix:**
Use database constraints, lock the user row, and define deletion behavior clearly.

```php
$user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

if ($user->orders()->whereIn('status', ['pending', 'processing'])->lockForUpdate()->exists()) {
    throw new DomainException('Cannot delete user with active orders.');
}
```

Also enforce foreign key behavior at the database layer.

---

### Issue #15: Generic `\Exception` Is Used for Business Rule Failure

**Severity:** 🟡 MEDIUM  
**Category:** Exception Handling / API Consistency  
**Location:** Lines 154-157

**Evidence:**
```php
if ($user->orders()->whereIn('status', ['pending', 'processing'])->exists()) {
    throw new \Exception('Cannot delete user with active orders');
}
```

**Problem:**
A generic exception is thrown for a domain/business rule violation.

**Why this matters:**
Generic exceptions are difficult for controllers/exception handlers to map to correct HTTP status codes. This should likely become `409 Conflict` or a domain-specific error.

**How to fix:**
Use a domain exception type.

```php
throw new DomainException('Cannot delete user with active orders.');
```

Then map it consistently in the API exception handler.

---

### Issue #16: Status Toggle Is Not Transactional or Lock-Safe

**Severity:** 🟡 MEDIUM  
**Category:** Concurrency / Data Integrity  
**Location:** Lines 176-185

**Evidence:**
```php
$user->update(['status' => !$user->status]);

Log::info('User status toggled', [
    'user_id' => $user->id,
    'new_status' => $user->status
]);
```

**Problem:**
The toggle reads the current status from the model instance and writes the opposite value without locking.

**Why this matters:**
Two concurrent toggle requests can produce lost updates or unexpected final state. Toggle endpoints are inherently race-prone.

**How to fix:**
Prefer explicit set-active/set-inactive operations. If toggle must remain, lock the row in a transaction.

```php
return DB::transaction(function () use ($user) {
    $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
    $locked->update(['status' => !$locked->status]);

    return $locked->fresh();
});
```

---

### Issue #17: Status Toggle Can Disable Critical Accounts Without Guardrails

**Severity:** 🟠 HIGH  
**Category:** Authorization / Business Logic  
**Location:** Lines 176-185

**Evidence:**
```php
public function toggleStatus(User $user): User
{
    $user->update(['status' => !$user->status]);
```

**Problem:**
The service does not prevent disabling self, last active admin, or protected system accounts.

**Why this matters:**
An admin could accidentally disable their own account or the last administrator, causing operational lockout.

**How to fix:**
Add business guards and policy checks before changing account status.

```php
if ($actor->is($user)) {
    throw new DomainException('You cannot disable your own account.');
}

if ($user->isLastActiveAdmin()) {
    throw new DomainException('Cannot disable the last active admin.');
}
```

---

### Issue #18: Create/Update Role Assignment Performs Redundant Saves

**Severity:** 🔵 LOW  
**Category:** Performance / Clean Code  
**Location:** Lines 79-91 and 120-132

**Evidence:**
```php
$user = User::create($data);

// Assign role if provided (now single role, not multiple)
if (!empty($data['role_id'])) {
    $user->role_id = $data['role_id'];
    $user->save();
}
```

```php
$user->update($data);

// Update role if provided (now single role, not multiple)
if (isset($data['role_id'])) {
    $user->role_id = $data['role_id'];
    $user->save();
}
```

**Problem:**
The code may write role information during mass assignment and then save role assignment again.

**Why this matters:**
Redundant writes make audit logs noisy, increase query count, and make mutation behavior harder to reason about.

**How to fix:**
Exclude role fields from the generic create/update payload and assign role exactly once.

---

### Issue #19: Logging Includes Email on User Creation

**Severity:** 🟡 MEDIUM  
**Category:** Sensitive Data Exposure / Logging  
**Location:** Line 95

**Evidence:**
```php
Log::info('User created', ['user_id' => $user->id, 'email' => $user->email]);
```

**Problem:**
The service logs the user's email address.

**Why this matters:**
Email is PII. Production logs are often accessible to more systems and people than application data. PII logging should be minimized.

**How to fix:**
Log stable identifiers and actor/action metadata, not raw PII.

```php
Log::info('User created', ['user_id' => $user->id]);
```

---

### Issue #20: Error Logs Expose Raw Exception Messages

**Severity:** 🟡 MEDIUM  
**Category:** Logging / Sensitive Data Exposure  
**Location:** Lines 100, 141, 168, and 202

**Evidence:**
```php
Log::error('Failed to create user', ['error' => $e->getMessage()]);
```

```php
Log::error('Failed to update user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
```

```php
Log::error('Failed to delete user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
```

```php
Log::error('Failed to reset password', ['user_id' => $user->id, 'error' => $e->getMessage()]);
```

**Problem:**
Raw exception messages are logged directly.

**Why this matters:**
Database exceptions can include SQL fragments, constraint names, email addresses, or other sensitive operational details. Structured logging should include sanitized context and exception object handling according to logging policy.

**How to fix:**
Log exception class and a sanitized message, or pass the exception under a controlled key if the logging stack redacts appropriately.

```php
Log::error('Failed to update user', [
    'user_id' => $user->id,
    'exception' => get_class($e),
]);
```

---

### Issue #21: Transactions Are Manually Managed Instead of `DB::transaction()`

**Severity:** 🔵 LOW  
**Category:** Laravel Best Practice / Maintainability  
**Location:** Lines 67-102, 110-143, and 151-169

**Evidence:**
```php
try {
    DB::beginTransaction();
    ...
    DB::commit();
    ...
} catch (\Exception $e) {
    DB::rollBack();
    ...
    throw $e;
}
```

**Problem:**
The service manually begins, commits, and rolls back transactions.

**Why this matters:**
Manual transaction handling is more verbose and easier to get wrong as code evolves. Laravel's `DB::transaction()` is cleaner and supports retry attempts for deadlock-prone operations.

**How to fix:**
Use `DB::transaction()`.

```php
$user = DB::transaction(function () use ($data) {
    // mutation logic
}, 3);
```

---

### Issue #22: User Statistics Run Multiple Independent Count Queries

**Severity:** 🔵 LOW  
**Category:** Performance / Scalability  
**Location:** Lines 218-227

**Evidence:**
```php
return [
    'total' => User::count(),
    'active' => User::where('status', true)->count(),
    'inactive' => User::where('status', false)->count(),
    'verified' => User::whereNotNull('email_verified_at')->count(),
    'unverified' => User::whereNull('email_verified_at')->count(),
    'recent' => User::where('created_at', '>=', now()->subDays(7))->count(),
];
```

**Problem:**
The method runs six separate aggregate queries.

**Why this matters:**
This is acceptable for small datasets but inefficient at scale, especially for admin dashboards that may refresh frequently.

**How to fix:**
Use conditional aggregation or cache dashboard statistics.

```php
User::query()->selectRaw("
    COUNT(*) as total,
    SUM(status = 1) as active,
    SUM(status = 0) as inactive,
    SUM(email_verified_at IS NOT NULL) as verified,
    SUM(email_verified_at IS NULL) as unverified,
    SUM(created_at >= ?) as recent
", [now()->subDays(7)])->first();
```

---

### Issue #23: `getAllRoles()` Returns Full Role Models Without Column Restriction

**Severity:** 🔵 LOW  
**Category:** Data Exposure / API Design  
**Location:** Lines 210-213

**Evidence:**
```php
public function getAllRoles()
{
    return Role::orderBy('name')->get();
}
```

**Problem:**
The method returns all columns from `roles`.

**Why this matters:**
Dropdown endpoints should only return fields required by the UI. Returning full models couples public/admin API response to database schema and can expose unnecessary metadata.

**How to fix:**
Select explicit columns.

```php
return Role::orderBy('name')->get(['id', 'name', 'slug']);
```

---

### Issue #24: Missing Return Type on `getAllRoles()`

**Severity:** 🔵 LOW  
**Category:** Maintainability / Type Safety  
**Location:** Lines 210-213

**Evidence:**
```php
public function getAllRoles()
```

**Problem:**
The method has no return type.

**Why this matters:**
Explicit return types improve static analysis and make service contracts clearer.

**How to fix:**
```php
public function getAllRoles(): Collection
```

Use the correct collection import.

---

### Issue #25: Service Does Not Accept Actor Context for Authorization-Sensitive Actions

**Severity:** 🔴 CRITICAL  
**Category:** Authorization / Architecture  
**Location:** Lines 65-203

**Evidence:**
```php
public function createUser(array $data): User
```

```php
public function updateUser(User $user, array $data): User
```

```php
public function deleteUser(User $user): bool
```

```php
public function toggleStatus(User $user): User
```

```php
public function resetPassword(User $user, string $newPassword): bool
```

**Problem:**
Security-sensitive operations do not receive the acting user/admin context and do not enforce authorization.

**Why this matters:**
The service can create users, update roles/status, delete users, toggle access, and reset passwords. Without actor context, authorization must be perfectly enforced by every caller. That is fragile and creates hidden security coupling.

**How to fix:**
Either enforce policies before calling this service or pass actor context and authorize inside dedicated application actions.

```php
public function resetPassword(User $actor, User $target, string $newPassword): bool
{
    Gate::forUser($actor)->authorize('resetPassword', $target);
    ...
}
```

---

## Recommendations

### IMMEDIATE

1. **Whitelist Sorting Fields and Directions** - Do not pass raw `sort_by`/`sort_order` to `orderBy()`.
2. **Remove Raw Mass Assignment** - Explicitly map create/update fields.
3. **Validate and Authorize Role Assignment** - Role changes require policy checks and role existence checks.
4. **Remove Legacy `roles` Assignment Path** - Use one explicit role assignment contract.
5. **Enforce Password Policy** - Validate strength before hashing.
6. **Revoke Existing Sessions/Tokens on Password Reset**.
7. **Add Actor Context or Enforce Policies Before Calling Service**.
8. **Fix Boolean Filter Parsing** - Do not use PHP `(bool)` on strings.

### SHORT TERM

9. **Bound `$perPage`**.
10. **Normalize and Bound Search Input**.
11. **Make Delete and Toggle Operations Lock-Safe**.
12. **Prevent Disabling Self or Last Active Admin**.
13. **Replace Generic Exceptions with Domain Exceptions**.
14. **Reduce PII in Logs**.
15. **Use `DB::transaction()` with Retry Attempts**.

### LONG TERM

16. **Split This Service by Responsibility** - UserQueryService, UserMutationService, PasswordAdminService, RoleAssignmentService.
17. **Add Audit Logging With Actor ID** for role/status/password changes.
18. **Cache or Optimize User Statistics**.
19. **Use API Resources/DTOs** for user and role admin responses.
20. **Add Comprehensive Feature Tests** for authorization, role assignment, password reset, filters, and deletion guards.

---

## Improved Version Snippet

```php
public function updateUser(User $actor, User $user, array $data): User
{
    Gate::forUser($actor)->authorize('update', $user);

    return DB::transaction(function () use ($actor, $user, $data) {
        $payload = Arr::only($data, [
            'name',
            'email',
            'username',
            'phone',
            'status',
        ]);

        if (!empty($data['password'])) {
            // Password policy should be validated before this service call.
            $payload['password'] = Hash::make($data['password']);
            $payload['remember_token'] = Str::random(60);
        }

        $user->update($payload);

        if (array_key_exists('role_id', $data)) {
            $role = Role::findOrFail($data['role_id']);

            Gate::forUser($actor)->authorize('assignRole', $role);

            $user->role()->associate($role);
            $user->save();
        }

        Log::info('User updated', [
            'actor_id' => $actor->id,
            'user_id' => $user->id,
        ]);

        return $user->fresh('role');
    }, 3);
}
```

---

## Summary

UserService.php is functional but not strict enough for production user administration. The highest-risk issues are raw mass assignment, unvalidated role assignment, missing actor/authorization context, unsafe sorting, weak password reset behavior, and non-lock-safe account status/deletion operations.

**Strengths:**
- Uses pagination for listing
- Eager loads role
- Hashes passwords
- Uses transactions for create/update/delete
- Has operational logs

**Main Gaps:**
1. Raw mass assignment in create and update
2. Role assignment is not validated or authorized
3. Service has no actor context for security-sensitive operations
4. Unsafe dynamic sorting
5. Password reset does not revoke existing sessions/tokens
6. Password policy is not enforced in service contract
7. Boolean filters can behave incorrectly with string input
8. Delete/toggle operations are not concurrency-safe
9. Logs include PII/raw exception messages
10. User statistics perform multiple independent count queries

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 01:08 PM*  
*File #36/137 - Phase 3: Business Logic (8/20 complete)*