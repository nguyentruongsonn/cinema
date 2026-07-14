# File Review: UserController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/UserController.php  
**Lines:** 287  
**Type:** Admin User Management Controller

---

## File Summary

`UserController` implements an admin-facing user management page plus API endpoints for listing users, creating users, viewing users, updating users, deleting users, toggling status, resetting passwords, retrieving roles, and retrieving user statistics. The controller uses route model binding for `User $user` in several endpoints and delegates persistence operations to `UserService`.

The file is not production-ready for a user administration surface. It contains high-risk authorization gaps, raw exception disclosure, unvalidated list filters, request-wide mass assignment into service calls, weak password policy, sensitive user/order data returned without API resources, direct role/status/loyalty manipulation from request payloads, and missing audit logging for account administration actions.

User administration is security-critical. A single missing authorization or mass assignment weakness here can lead to account takeover, privilege escalation, user lockout, privacy exposure, or fraudulent loyalty-point manipulation.

---

## Overall Score

**Overall Score:** 4.6/10

**Decision:** 🔴 **BLOCKING**

---

## Strengths

- Uses dependency injection for `UserService`.
- Uses route model binding for user-specific endpoints.
- Performs some validation before create/update/password-reset operations.
- Uses unique validation rules for `email` and `username`.
- Uses pagination metadata in `list()`.
- Delegates core user operations to `UserService`.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/UserController.php:69-107, 134-173, 178-192, 198-214, 219-246, 251-266, 271-286

**Problem**

The controller exposes user administration operations without any visible authorization checks or middleware inside the controller.

```php
public function store(Request $request)
```

```php
public function update(Request $request, User $user)
```

```php
public function destroy(User $user)
```

```php
public function resetPassword(Request $request, User $user)
```

**Why this matters**

These endpoints can create users, change roles, change account status, reset passwords, delete users, expose roles, and expose user statistics. If route-level protection is missing or misconfigured, this becomes a complete account-administration takeover.

For user administration, route middleware alone is not enough. The controller should enforce explicit policy/permission checks for every privileged operation.

**How to fix**

Use policies/gates or permission middleware and enforce authorization per action.

```php
public function update(UpdateUserRequest $request, User $user): JsonResponse
{
    $this->authorize('update', $user);

    $updatedUser = $this->userService->updateUser($user, $request->validated());

    return response()->json([
        'success' => true,
        'message' => 'User updated successfully',
        'data' => new UserResource($updatedUser),
    ]);
}
```

Also add constructor-level middleware for this admin controller.

```php
public function __construct(private readonly UserService $userService)
{
    $this->middleware(['auth', 'permission:users.manage']);
}
```

---

### Issue #2

**Severity:** Critical  
**Category:** Security / Privilege Escalation  
**Location:** app/Http/Controllers/UserController.php:81-82, 94, 146-148, 160

**Problem**

The controller accepts role, status, and loyalty-point fields from the request and forwards the entire request payload to the service.

```php
'role_id' => 'nullable|exists:roles,id',
'status' => 'nullable|boolean',
```

```php
$user = $this->userService->createUser($request->all());
```

```php
'loyalty_points' => 'nullable|integer|min:0',
'role_id' => 'nullable|exists:roles,id',
'status' => 'nullable|boolean',
```

```php
$updatedUser = $this->userService->updateUser($user, $request->all());
```

**Why this matters**

This is a privilege escalation and business-integrity risk. An attacker or unauthorized admin can assign privileged roles, activate/deactivate accounts, or manipulate loyalty points if route permissions are weak. Even for admins, role/status/loyalty changes should be separate audited workflows with stricter permissions.

Forwarding `$request->all()` also includes unvalidated fields not covered by the validator.

**How to fix**

Use FormRequests and pass only validated data. Split role/status/loyalty operations into separate endpoints with dedicated permissions.

```php
$data = $request->validated();

$user = $this->userService->createUser($data);
```

For role assignment:

```php
$this->authorize('assignRole', $user);

$this->userService->assignRole($user, $request->validated('role_id'));
```

---

### Issue #3

**Severity:** High  
**Category:** Security / Mass Assignment  
**Location:** app/Http/Controllers/UserController.php:71-83, 94, 136-149, 160

**Problem**

The controller validates a subset of fields but passes `$request->all()` into service methods.

```php
$validator = Validator::make($request->all(), [
    'name' => 'required|string|max:255',
    ...
]);
```

```php
$user = $this->userService->createUser($request->all());
```

```php
$updatedUser = $this->userService->updateUser($user, $request->all());
```

**Why this matters**

Any extra client-supplied fields bypass controller validation. If the service or model later accepts those fields, this can allow mass assignment of sensitive columns such as `email_verified_at`, `remember_token`, `password_reset_token`, `role`, `is_admin`, or other security flags.

**How to fix**

Use validated data only.

```php
$validated = $validator->validated();

$user = $this->userService->createUser($validated);
```

Better: replace manual validators with FormRequest classes.

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $user = $this->userService->createUser($request->validated());
}
```

---

### Issue #4

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/UserController.php:58-63, 101-106, 123-128, 167-172, 187-191, 208-213, 240-245, 260-265, 280-285

**Problem**

The controller returns raw exception messages to API clients.

```php
'message' => 'Failed to fetch users: ' . $e->getMessage()
```

```php
'message' => 'Failed to create user: ' . $e->getMessage()
```

```php
'message' => $e->getMessage()
```

**Why this matters**

User administration operations can throw database, authorization, hashing, relationship, or validation exceptions. Returning raw messages can expose schema details, internal model names, SQL errors, stack-sensitive messages, and business rules useful to attackers.

**How to fix**

Log exceptions internally and return generic messages.

```php
catch (\Throwable $e) {
    Log::error('Failed to update user', [
        'exception' => $e,
        'target_user_id' => $user->id,
        'actor_id' => auth()->id(),
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Failed to update user',
    ], 500);
}
```

Prefer centralized exception handling over repetitive try/catch blocks.

---

### Issue #5

**Severity:** High  
**Category:** Security / Sensitive Data Exposure  
**Location:** app/Http/Controllers/UserController.php:46-57, 119-122, 162-166, 203-207, 256-259, 276-279

**Problem**

The controller returns raw Eloquent models/collections directly.

```php
'data' => $users->items(),
```

```php
'data' => $user
```

```php
'data' => $updatedUser
```

**Why this matters**

Raw user serialization can expose internal account fields depending on model `$hidden` and loaded relationships. `show()` explicitly loads `orders`, increasing the risk of exposing order internals, payment references, totals, addresses, or other user privacy data.

Controllers should not trust model serialization for public/admin API contracts.

**How to fix**

Use API Resources with explicit fields.

```php
'data' => UserAdminResource::collection($users->items()),
```

For single user:

```php
'data' => new UserAdminResource($user),
```

Use separate resources for roles, orders, and stats.

---

### Issue #6

**Severity:** High  
**Category:** Validation / API Abuse / Performance  
**Location:** app/Http/Controllers/UserController.php:31-44

**Problem**

`list()` accepts filters, sorting fields, sort direction, and page size without validation.

```php
$filters = [
    'search' => $request->input('search'),
    'role' => $request->input('role'),
    'status' => $request->input('status'),
    'verified' => $request->input('verified'),
    'sort_by' => $request->input('sort_by', 'created_at'),
    'sort_order' => $request->input('sort_order', 'desc'),
];

$perPage = $request->input('per_page', 15);
```

**Why this matters**

Unvalidated `sort_by` can become SQL injection if the service uses it in `orderBy()`. Unbounded `per_page` can cause memory pressure or slow queries. Invalid filter values can produce inconsistent behavior or broaden access unexpectedly.

**How to fix**

Validate all listing inputs.

```php
$validated = $request->validate([
    'search' => ['nullable', 'string', 'max:255'],
    'role' => ['nullable', 'integer', 'exists:roles,id'],
    'status' => ['nullable', 'boolean'],
    'verified' => ['nullable', 'boolean'],
    'sort_by' => ['nullable', Rule::in(['created_at', 'name', 'email'])],
    'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
]);
```

---

### Issue #7

**Severity:** High  
**Category:** Security / Password Policy  
**Location:** app/Http/Controllers/UserController.php:76, 141, 221-223

**Problem**

Passwords only require `min:6`.

```php
'password' => 'required|string|min:6',
```

```php
'password' => 'nullable|string|min:6',
```

```php
'password' => 'required|string|min:6|confirmed',
```

**Why this matters**

A six-character minimum is weak for production. Admin-created and reset passwords are especially sensitive because reset endpoints can be used for account takeover if abused.

**How to fix**

Use Laravel password rules and require confirmation where appropriate.

```php
use Illuminate\Validation\Rules\Password;

'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
```

Also require current-admin authorization and log password reset events.

---

### Issue #8

**Severity:** High  
**Category:** Security / Account Takeover Controls  
**Location:** app/Http/Controllers/UserController.php:219-246

**Problem**

Password reset only validates the new password and calls the service. There is no visible permission check, audit logging, session/token invalidation, user notification, or reason capture.

```php
$this->userService->resetPassword($user, $request->password);
```

**Why this matters**

Admin password reset is a high-risk operation. In production, it must be auditable and should revoke existing sessions/tokens. Otherwise, compromised sessions may persist and there is no forensic trail.

**How to fix**

- Require explicit `users.reset-password` permission.
- Revoke refresh tokens/sessions after reset.
- Audit actor ID, target user ID, timestamp, IP, and reason.
- Notify the affected user.
- Enforce strong password rules.

```php
$this->authorize('resetPassword', $user);

$this->userService->resetPassword(
    user: $user,
    password: $request->validated('password'),
    actor: $request->user()
);
```

---

### Issue #9

**Severity:** High  
**Category:** Business Logic / Account Safety  
**Location:** app/Http/Controllers/UserController.php:178-192 and 198-214

**Problem**

The controller allows deleting users and toggling status without visible safeguards for self-deletion, disabling the last admin, deleting users with paid orders, or disabling users with active bookings.

```php
$this->userService->deleteUser($user);
```

```php
$updatedUser = $this->userService->toggleStatus($user);
```

**Why this matters**

Unsafe user deletion/status changes can lock administrators out, orphan orders, break audit trails, disrupt active bookings, and create compliance issues.

**How to fix**

Enforce domain restrictions and return `409 Conflict` for unsafe operations.

```php
if ($request->user()->is($user)) {
    throw new DomainException('You cannot disable your own account.');
}
```

Avoid hard deletes for users with order history. Prefer deactivation/anonymization policies.

---

### Issue #10

**Severity:** Medium  
**Category:** Validation / Data Quality  
**Location:** app/Http/Controllers/UserController.php:75, 80, 140, 145

**Problem**

Phone and address fields are weakly validated.

```php
'phone' => 'nullable|string|max:20',
```

```php
'address' => 'nullable|string',
```

**Why this matters**

Phone numbers and addresses are customer PII and operational data. Weak validation allows malformed or excessively large address values. `address` has no max length.

**How to fix**

Add size limits and format rules suitable for your locale.

```php
'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-()]{8,20}$/'],
'address' => ['nullable', 'string', 'max:1000'],
```

---

### Issue #11

**Severity:** Medium  
**Category:** API Consistency  
**Location:** app/Http/Controllers/UserController.php:46-57, 86-90, 96-100, 119-122, 152-156, 183-186, 236-239, 256-259, 276-279

**Problem**

This controller builds response envelopes manually instead of using the shared `ApiResponse` trait used elsewhere.

```php
return response()->json([
    'success' => true,
    'data' => $users->items(),
    'pagination' => [
        ...
    ]
]);
```

**Why this matters**

Manual response construction creates inconsistent API shape, error handling, pagination metadata, and status codes across controllers.

**How to fix**

Adopt the shared response trait or standardized resources/responses.

```php
use App\Traits\ApiResponse;

return $this->paginatedResponse($users, 'Users retrieved successfully');
```

---

### Issue #12

**Severity:** Medium  
**Category:** Architecture / Fat Controller  
**Location:** app/Http/Controllers/UserController.php:31-64, 69-107, 134-173, 219-246

**Problem**

The controller performs inline validation, constructs filters, handles exceptions repeatedly, loads relationships, and manually formats responses.

**Why this matters**

The controller is doing too much, which increases duplication, makes testing harder, and spreads API behavior across methods.

**How to fix**

- Use FormRequest classes.
- Move filter DTO/validated filter handling into a request object.
- Use API Resources.
- Use centralized exception handling.
- Keep controller methods thin.

---

### Issue #13

**Severity:** Medium  
**Category:** Performance / Data Loading  
**Location:** app/Http/Controllers/UserController.php:115-117

**Problem**

`show()` loads a user role and latest 10 orders directly in the controller.

```php
$user->load(['role', 'orders' => function ($query) {
    $query->latest()->limit(10);
}]);
```

**Why this matters**

The controller decides relationship loading and order limits, which is presentation/business logic leakage. If order serialization includes nested relationships, this can become heavy and expose data.

**How to fix**

Move user detail query construction into the service and serialize with resources.

```php
$user = $this->userService->getAdminUserDetail($user);

return new UserAdminDetailResource($user);
```

---

### Issue #14

**Severity:** Medium  
**Category:** Observability / Audit Logging  
**Location:** app/Http/Controllers/UserController.php:69-107, 134-173, 178-192, 198-214, 219-246

**Problem**

The controller performs sensitive account-administration actions without visible audit logging.

```php
$user = $this->userService->createUser($request->all());
```

```php
$updatedUser = $this->userService->updateUser($user, $request->all());
```

```php
$this->userService->resetPassword($user, $request->password);
```

**Why this matters**

User creation, role assignment, status changes, deletion, and password reset must be auditable in production. Without audit logs, incidents cannot be investigated reliably.

**How to fix**

Audit in the service after successful transaction commit.

```php
AuditLog::record('user.password_reset', [
    'actor_id' => auth()->id(),
    'target_user_id' => $user->id,
    'ip' => request()->ip(),
]);
```

---

### Issue #15

**Severity:** Medium  
**Category:** HTTP Semantics / Exception Handling  
**Location:** app/Http/Controllers/UserController.php:187-191

**Problem**

`destroy()` returns all exceptions as `400` and exposes the exception message.

```php
return response()->json([
    'success' => false,
    'message' => $e->getMessage()
], 400);
```

**Why this matters**

Authorization failures, database failures, business conflicts, and not-found errors should not all be returned as `400`. This breaks API clients and hides server errors.

**How to fix**

Use typed exceptions and appropriate status codes:

- `403` for authorization failures
- `404` for missing users
- `409` for domain conflicts
- `500` for unexpected failures

---

### Issue #16

**Severity:** Medium  
**Category:** Authentication / Session Safety  
**Location:** app/Http/Controllers/UserController.php:198-214

**Problem**

Account status toggling does not visibly revoke sessions or refresh tokens when a user is disabled.

```php
$updatedUser = $this->userService->toggleStatus($user);
```

**Why this matters**

If a user is deactivated but existing tokens remain valid, the account may still be usable until token expiration unless middleware checks status on every request. The controller gives no indication that revocation happens.

**How to fix**

When disabling a user, revoke active sessions/refresh tokens and force logout.

```php
$this->userService->disableUser($user, actor: $request->user(), revokeTokens: true);
```

---

### Issue #17

**Severity:** Low  
**Category:** Type Safety / Readability  
**Location:** app/Http/Controllers/UserController.php:13-18, 23, 31, 69, 112, 134, 178, 198, 219, 251, 271

**Problem**

The controller lacks property and method return types.

```php
protected $userService;
```

```php
public function list(Request $request)
```

**Why this matters**

Missing types reduce static analysis value and make the code harder to maintain.

**How to fix**

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

protected UserService $userService;

public function list(Request $request): JsonResponse
```

Or use promoted readonly injection:

```php
public function __construct(private readonly UserService $userService)
{
}
```

---

### Issue #18

**Severity:** Low  
**Category:** Clean Code / Duplication  
**Location:** app/Http/Controllers/UserController.php:58-63, 101-106, 123-128, 167-172, 208-213, 240-245, 260-265, 280-285

**Problem**

The controller duplicates try/catch error response code across nearly every endpoint.

**Why this matters**

Duplicated exception handling makes behavior inconsistent and harder to update safely.

**How to fix**

Use centralized exception handling in `app/Exceptions/Handler.php` or Laravel's exception render callbacks.

---

### Issue #19

**Severity:** Low  
**Category:** Maintainability / FormRequest Best Practice  
**Location:** app/Http/Controllers/UserController.php:71-83, 136-149, 221-223

**Problem**

Manual validation is embedded inside controller methods instead of dedicated FormRequest classes.

**Why this matters**

FormRequests improve reuse, authorize at the request layer, simplify controllers, and centralize validation rules.

**How to fix**

Create:

- `StoreUserRequest`
- `UpdateUserRequest`
- `ResetUserPasswordRequest`
- `ListUsersRequest`

---

## Security Review

Security concerns:

- Critical missing visible authorization for user-admin operations.
- Privilege escalation risk through `role_id`.
- Account manipulation risk through `status`.
- Fraud/business-integrity risk through `loyalty_points`.
- Mass assignment risk by passing `$request->all()` into service methods.
- Raw exception messages leak internals.
- Raw user/order model serialization risks sensitive data exposure.
- Weak password policy.
- Password reset lacks visible token/session revocation and audit logging.
- User disable lacks visible token/session revocation.
- User deletion/status toggle lacks self-protection and last-admin safeguards.

No raw SQL is visible in this controller. SQL injection risk depends on `UserService::getPaginatedUsers()` because `sort_by`, `sort_order`, and `per_page` are unvalidated before being passed to the service.

---

## Performance Review

Performance concerns:

- `list()` allows unbounded `per_page`.
- `sort_by` and filters are unvalidated, which can result in poor query plans depending on service implementation.
- Raw model serialization can accidentally include heavy relationships.
- `show()` loads orders directly and returns raw user data.
- User statistics endpoint has no visible caching or date/range control.

Recommended improvements:

- Cap pagination to `max:50`.
- Whitelist sortable columns.
- Use resources to control payload size.
- Cache role lists and user statistics where appropriate.
- Move detail loading into service/query classes.

---

## Database Review

Database correctness concerns:

- Role/status/loyalty changes lack domain-specific workflows.
- User deletion may break order/audit history if service hard deletes.
- No visible transaction boundary for create/update/delete/reset workflows.
- No visible last-admin protection.
- No visible optimistic locking for concurrent admin edits.

Recommended protections:

- Use transactions in the service for role/status/password changes.
- Prevent deleting or disabling the last admin.
- Prefer soft-delete/deactivation for user accounts.
- Preserve immutable order/audit history.
- Revoke sessions/tokens transactionally when disabling or resetting password.

---

## Concurrency Review

Potential concurrency risks:

- Two admins can update the same user concurrently with lost updates.
- Two admins can simultaneously modify roles/status/password with no visible conflict handling.
- Last-admin protection must be atomic; otherwise concurrent status changes could disable all admins.
- Loyalty-point edits are especially dangerous if multiple updates occur concurrently.

Recommended improvements:

- Use row-level locks for role/status/loyalty updates.
- Use optimistic locking via `updated_at` precondition.
- Make last-admin checks atomic inside a transaction.
- Audit every mutation after commit.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequests instead of manual `Validator::make()`.
- Add authorization to FormRequests and controller policies.
- Use API Resources for users, roles, orders, and stats.
- Use `ApiResponse` or standardized response classes.
- Avoid broad `catch (\Exception)` blocks.
- Add explicit `JsonResponse`/`View` return types.
- Use constructor property promotion with readonly service dependency.
- Validate all list filters and pagination inputs.
- Pass only validated data to services.

---

## Testing Review

Recommended tests:

1. Guests cannot access any user management endpoint.
2. Non-admin users cannot list/create/update/delete/reset users.
3. Admin without role-management permission cannot set `role_id`.
4. Admin without loyalty permission cannot change `loyalty_points`.
5. Extra unvalidated fields are ignored/rejected during create/update.
6. Weak passwords are rejected.
7. Password reset revokes tokens/sessions.
8. Disabled user tokens can no longer authenticate.
9. Admin cannot delete or disable self.
10. Last admin cannot be deleted or disabled under concurrent requests.
11. `list()` rejects invalid `sort_by`, invalid `sort_order`, and `per_page > 50`.
12. Raw exception messages are not exposed.
13. User list/detail responses do not expose hidden or sensitive fields.
14. Deleting a user with paid orders is rejected or soft-deletes safely.
15. User admin actions create audit logs with actor and target IDs.

---

## Final Decision

🔴 **BLOCKING**

`UserController` is a security-critical admin controller and is not production-ready. The biggest blockers are missing visible authorization, privilege escalation through `role_id`/`status`/`loyalty_points`, passing `$request->all()` into service methods, raw sensitive model serialization, raw exception disclosure, and weak password/reset controls. These issues can cause account takeover, privilege escalation, customer data exposure, fraud, and loss of administrative control.

---

_Review completed: 2026-07-14 03:31 PM_  
_File #60/137 - Phase 4: Controllers (12/34 complete)_
