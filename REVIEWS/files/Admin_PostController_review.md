# File Review: Admin/PostController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/PostController.php  
**Lines:** 192  
**Type:** Admin Post Management Controller

---

## File Summary

`Admin\PostController` implements admin post management for listing, creating, updating, deleting, publishing, rendering the admin page, and returning post categories. It uses Eloquent directly inside the controller, validates with inline `$request->validate()`, uploads images to the public disk, and returns manual JSON responses.

This controller is not production-ready. It has no visible authorization, contains business logic and persistence logic directly in controller actions, has unsafe file/database consistency behavior, uses unbounded pagination, exposes raw models, allows client-controlled publication timestamps, and contains bugs around optional boolean handling.

---

## Overall Score

**Overall Score:** 4.7/10

**Decision:** 🔴 **REQUEST CHANGES**

---

## Strengths

- Uses route model binding for `Post $post`.
- Validates basic create/update fields.
- Uses `image` validation and a 5 MB upload limit.
- Eager loads the post author in list/detail response paths.
- Deletes old featured images when replacing them.
- Provides pagination metadata.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/PostController.php:16-190

**Problem**

No method shows authentication, authorization middleware, policy, gate, ownership check, or permission check.

```php
public function store(Request $request)
```

```php
public function update(Request $request, Post $post)
```

```php
public function destroy(Post $post)
```

```php
public function togglePublish(Post $post)
```

**Why this matters**

This is an admin content management controller. Without explicit protection, a route configuration mistake can allow unauthorized users to create, edit, delete, or publish posts. That can cause defacement, phishing content, SEO spam, reputational damage, and stored XSS exposure if rendered content is not sanitized elsewhere.

**How to fix**

Add explicit middleware and policies.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:posts.manage']);
}
```

And authorize per action:

```php
$this->authorize('update', $post);
$this->authorize('delete', $post);
```

---

### Issue #2

**Severity:** High  
**Category:** Security / XSS  
**Location:** app/Http/Controllers/Admin/PostController.php:73,116

**Problem**

`content` accepts arbitrary strings and is stored without sanitization or documented HTML policy.

```php
'content' => 'required|string',
```

**Why this matters**

Posts are likely rendered publicly. If HTML is allowed, this can become stored XSS unless output is strictly escaped or sanitized at render time. If HTML is not allowed, the controller should reject unsafe markup. Stored XSS in CMS content can compromise users and admins.

**How to fix**

Define an explicit content policy.

For plain text:

```php
'content' => ['required', 'string', 'max:50000'],
```

Render escaped.

For limited HTML, sanitize with a trusted allowlist sanitizer before persistence.

```php
$validated['content'] = app(HtmlSanitizer::class)->sanitize($validated['content']);
```

---

### Issue #3

**Severity:** High  
**Category:** Data Integrity / Slug Uniqueness  
**Location:** app/Http/Controllers/Admin/PostController.php:81-84

**Problem**

Auto-generated slugs are not checked for uniqueness after generation.

```php
if (empty($validated['slug'])) {
    $validated['slug'] = Str::slug($validated['title']);
}
```

**Why this matters**

The validation rule only checks uniqueness for a submitted `slug`, not the generated slug. Two posts with the same title can generate the same slug. If the database has a unique index, this throws a database exception. If it does not, public routes can become ambiguous.

**How to fix**

Generate a unique slug in a service or model observer.

```php
$baseSlug = Str::slug($validated['title']);
$slug = $baseSlug;
$counter = 2;

while (Post::where('slug', $slug)->exists()) {
    $slug = "{$baseSlug}-{$counter}";
    $counter++;
}

$validated['slug'] = $slug;
```

Also enforce a database unique index on `posts.slug`.

---

### Issue #4

**Severity:** High  
**Category:** Bug / Validation  
**Location:** app/Http/Controllers/Admin/PostController.php:96,135

**Problem**

The code assumes `is_published` always exists in `$validated`, but the validation rule is not required.

```php
'is_published' => 'boolean',
```

```php
if ($validated['is_published'] && empty($validated['published_at'])) {
```

```php
if ($validated['is_published'] && empty($validated['published_at']) && !$post->is_published) {
```

**Why this matters**

If the client omits `is_published`, accessing `$validated['is_published']` can trigger an undefined array key error. This breaks create/update requests that otherwise pass validation.

**How to fix**

Use a default value or `boolean()` helper.

```php
$validated['is_published'] = $request->boolean('is_published');
```

Or guard with null coalescing:

```php
if (($validated['is_published'] ?? false) && empty($validated['published_at'])) {
```

---

### Issue #5

**Severity:** High  
**Category:** Data Integrity / Filesystem Consistency  
**Location:** app/Http/Controllers/Admin/PostController.php:124-139

**Problem**

The old image is deleted before the database update succeeds.

```php
if ($post->featured_image) {
    Storage::disk('public')->delete($post->featured_image);
}
$path = $request->file('featured_image')->store('posts', 'public');
$validated['featured_image'] = $path;
...
$post->update($validated);
```

**Why this matters**

If upload succeeds but `$post->update()` fails, the old image has already been deleted and the new image may become orphaned. The database and filesystem can become inconsistent.

**How to fix**

Upload first, update inside a transaction, and delete old files only after successful commit.

```php
$oldImage = $post->featured_image;
$newPath = $request->file('featured_image')->store('posts', 'public');

DB::transaction(function () use ($post, $validated, $newPath) {
    $validated['featured_image'] = $newPath;
    $post->update($validated);
});

if ($oldImage) {
    Storage::disk('public')->delete($oldImage);
}
```

---

### Issue #6

**Severity:** High  
**Category:** Data Integrity / Filesystem Consistency  
**Location:** app/Http/Controllers/Admin/PostController.php:150-157

**Problem**

The featured image is deleted before the post row is deleted.

```php
if ($post->featured_image) {
    Storage::disk('public')->delete($post->featured_image);
}

$post->delete();
```

**Why this matters**

If `$post->delete()` fails after the image is deleted, the post remains in the database pointing to a missing file.

**How to fix**

Delete the row first, then delete the file after successful deletion.

```php
$path = $post->featured_image;

DB::transaction(function () use ($post) {
    $post->delete();
});

if ($path) {
    Storage::disk('public')->delete($path);
}
```

---

### Issue #7

**Severity:** Medium  
**Category:** Performance / Resource Protection  
**Location:** app/Http/Controllers/Admin/PostController.php:49-50

**Problem**

`per_page` is accepted directly from the request without validation or upper bound.

```php
->paginate($request->get('per_page', 15));
```

**Why this matters**

A client can request a huge `per_page` value and force large database reads and large JSON responses. This can degrade admin API performance or exhaust memory.

**How to fix**

Validate and cap pagination.

```php
$perPage = min((int) $request->input('per_page', 15), 100);
```

Prefer FormRequest validation:

```php
'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
```

---

### Issue #8

**Severity:** Medium  
**Category:** API Consistency  
**Location:** app/Http/Controllers/Admin/PostController.php:52-62,102-105,141-144,159-161,177-180,188-190

**Problem**

The controller manually returns different JSON response shapes and does not use the project `ApiResponse` trait.

```php
return response()->json([
    'data' => $posts->items(),
    'pagination' => [...]
]);
```

```php
return response()->json([
    'message' => 'Tạo bài viết thành công',
    'data' => $post->load('author:id,name')
], 201);
```

**Why this matters**

Inconsistent response envelopes make client error handling, success parsing, and documentation harder. This also differs from other controllers that use shared response helpers.

**How to fix**

Use a consistent response helper and resources.

```php
return $this->successResponse(PostResource::collection($posts), 'Posts retrieved successfully');
```

---

### Issue #9

**Severity:** Medium  
**Category:** Architecture / Clean Code  
**Location:** app/Http/Controllers/Admin/PostController.php:24-190

**Problem**

The controller directly performs querying, filtering, validation, slug generation, file storage, publication state changes, deletion, and response shaping.

```php
$query = Post::with('author:id,name');
```

```php
$validated['slug'] = Str::slug($validated['title']);
```

```php
$post = Post::create($validated);
```

**Why this matters**

This is a fat controller. Business rules are hard to test, reuse, or enforce consistently. File lifecycle and publication state logic should be centralized.

**How to fix**

Move business logic to `PostService`, validation to FormRequests, and response shaping to Resources.

---

### Issue #10

**Severity:** Medium  
**Category:** Security / Upload Hardening  
**Location:** app/Http/Controllers/Admin/PostController.php:76,119,87-89,125-131

**Problem**

Uploaded images are stored directly on the public disk with only Laravel's `image` rule and size limit.

```php
'featured_image' => 'nullable|image|max:5120',
```

```php
$path = $request->file('featured_image')->store('posts', 'public');
```

**Why this matters**

Public uploads should be hardened. The code does not constrain MIME types, dimensions, filename strategy visibility, image re-encoding, or malware scanning. SVG-like or polyglot image risks depend on server behavior and validation details.

**How to fix**

Use stricter validation and image processing.

```php
'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=3000,max_height=3000'],
```

Re-encode images server-side before storing public files.

---

### Issue #11

**Severity:** Medium  
**Category:** Authorization / Business Rules  
**Location:** app/Http/Controllers/Admin/PostController.php:93,111-139

**Problem**

Posts are assigned `author_id` on creation, but update/delete/publish actions do not check author ownership or editor permissions.

```php
$validated['author_id'] = auth()->id();
```

```php
public function update(Request $request, Post $post)
```

**Why this matters**

If multiple admin roles exist, any admin with route access may be able to modify or delete another author's content. This must be an explicit policy decision.

**How to fix**

Use policies.

```php
$this->authorize('update', $post);
$this->authorize('delete', $post);
$this->authorize('publish', $post);
```

---

### Issue #12

**Severity:** Medium  
**Category:** Data Integrity / Publication Workflow  
**Location:** app/Http/Controllers/Admin/PostController.php:77-78,95-98,120-121,134-137,167-175

**Problem**

The client can submit arbitrary `published_at` dates and the toggle action never clears `published_at` when unpublishing.

```php
'published_at' => 'nullable|date',
```

```php
if ($post->is_published && !$post->published_at) {
    $post->published_at = now();
}
```

**Why this matters**

Publication timestamps are business/audit data. Allowing arbitrary client timestamps can falsify publishing history. Keeping `published_at` after unpublishing may be valid for "first published at", but the code does not make that intent explicit.

**How to fix**

Define explicit fields such as `first_published_at`, `published_at`, and `unpublished_at`, and control them server-side.

---

### Issue #13

**Severity:** Medium  
**Category:** Validation / Search Abuse  
**Location:** app/Http/Controllers/Admin/PostController.php:29-34

**Problem**

Search input is not validated or length-limited before being used in wildcard LIKE conditions.

```php
$search = $request->search;
$query->where(function ($q) use ($search) {
    $q->where('title', 'like', "%{$search}%")
      ->orWhere('content', 'like', "%{$search}%");
});
```

**Why this matters**

Leading-wildcard searches on large text columns can become expensive table scans. Very long search strings can further increase load.

**How to fix**

Validate and limit search length.

```php
'search' => ['nullable', 'string', 'max:100'],
```

Consider full-text indexes for content search.

---

### Issue #14

**Severity:** Low  
**Category:** API Serialization / Sensitive Data Exposure  
**Location:** app/Http/Controllers/Admin/PostController.php:53,104,143,179

**Problem**

Raw Eloquent models are returned directly.

```php
'data' => $posts->items(),
```

```php
'data' => $post->load('author:id,name')
```

```php
'data' => $post
```

**Why this matters**

Raw model serialization exposes all visible model attributes and can accidentally leak fields added later. API Resources provide stable output contracts.

**How to fix**

Use `PostResource`.

```php
return new PostResource($post->load('author:id,name'));
```

---

### Issue #15

**Severity:** Low  
**Category:** Maintainability / Magic Strings  
**Location:** app/Http/Controllers/Admin/PostController.php:75,118,189

**Problem**

Categories are hard-coded in multiple places.

```php
'category' => 'required|in:news,blog,announcement,event,promotion',
```

```php
'data' => ['news', 'blog', 'announcement', 'event', 'promotion']
```

**Why this matters**

Duplicated category lists drift over time and make changes error-prone.

**How to fix**

Use a PHP enum, config, or model constant.

```php
PostCategory::values()
```

---

### Issue #16

**Severity:** Low  
**Category:** Type Safety  
**Location:** app/Http/Controllers/Admin/PostController.php:16,24,68,111,150,167,186

**Problem**

Controller methods do not declare return types.

```php
public function list(Request $request)
```

**Why this matters**

Explicit return types improve static analysis and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

public function index(): View
public function list(Request $request): JsonResponse
```

---

## Security Review

Security concerns:

- No visible authorization for admin content management.
- Potential stored XSS through unsanitized post content.
- Public file uploads are not sufficiently hardened.
- Raw model serialization can leak future fields.
- Ownership/editor permissions are not enforced in this controller.

No direct SQL injection is visible because Eloquent bindings are used for filters.

---

## Performance Review

Performance risks:

- Unbounded `per_page`.
- Wildcard search on `title` and `content`.
- Potentially expensive `content LIKE "%term%"` scans.
- Raw model serialization instead of resource-limited output.

Recommended improvements:

- Cap pagination.
- Validate filter input.
- Add indexes for `category`, `is_published`, `created_at`, and potentially full-text search for `title/content`.
- Use API Resources.

---

## Database Review

Database/data correctness concerns:

- Generated slugs may collide.
- Publication timestamp is client-controllable.
- File deletion and database mutation are not coordinated safely.
- No transaction boundaries around multi-step file/database operations.
- No visible database unique constraint guarantee for slug in this file.

---

## Concurrency Review

Concurrency risks:

- Two concurrent creates with same title can generate the same slug.
- Concurrent update/delete can race with file deletion and replacement.
- Toggle publish is a read-modify-write operation without locking.

Use database constraints and transactions where appropriate.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequest classes for list/store/update.
- Use policies for update/delete/publish.
- Move business logic into a service.
- Use API Resources.
- Use shared `ApiResponse` convention.
- Avoid raw model serialization.
- Add return types.
- Avoid hard-coded category strings.

---

## Testing Review

Recommended tests:

1. Guest cannot access any admin post endpoint.
2. Non-authorized admin cannot create/update/delete/publish posts.
3. Duplicate title slug generation produces unique slugs.
4. Create without `is_published` does not throw undefined key errors.
5. Update without `is_published` does not throw undefined key errors.
6. Invalid category is rejected.
7. Huge `per_page` is rejected or capped.
8. Search length is validated.
9. Upload validation rejects unsupported formats.
10. Failed update does not delete old image.
11. Failed delete does not leave database row pointing to a missing image.
12. Raw HTML content policy is enforced.
13. Response schema is stable through resources.

---

## Final Decision

🔴 **REQUEST CHANGES**

`Admin\PostController` requires significant production hardening. The highest priority issues are missing authorization, stored-XSS risk from content, slug collision handling, optional boolean bugs, unsafe file/database consistency, unbounded pagination, and fat-controller architecture.

---

_Review completed: 2026-07-14 04:10 PM_  
_File #67/137 - Phase 4: Controllers (19/34 complete)_
