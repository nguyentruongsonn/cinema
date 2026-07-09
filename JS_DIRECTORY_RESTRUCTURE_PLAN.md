# JavaScript Directory Restructure Plan

**Date:** 2026-07-09  
**Goal:** Reorganize JavaScript structure to mirror CSS structure (users/ vs admin/)

---

## 📊 Current Structure Analysis

### Root Level Files (public/js/)
```
admin-core.js          # Admin utility (misplaced?)
app.js                 # User app initialization
auth.js                # Authentication (user-facing)
```

### Folders
```
pages/                 # 7 files - User pages
├── booking.js
├── booking.js.backup  # Backup file
├── home.js
├── movie-detail.js
├── movies.js
├── payment.js
└── profile.js

components/            # 3 files - User components
├── modal.js
├── skeleton.js
└── toast.js

utils/                 # 3 files - Mixed utilities
├── form-validator.js      # User-specific
├── security.js            # Shared (used by both)
└── security-standalone.js # Shared

core/                  # 1 file - Shared core
└── api-client.js      # Used by both user & admin

admin/                 # Admin code (already well-structured)
├── base/
├── pages/
├── app.js
└── ... (other admin files)
```

**Total User Files to Move:** ~17 files

---

## 🎯 Proposed New Structure

```
public/js/
├── users/                        # NEW: All user-facing code
│   ├── pages/                    # Moved from public/js/pages/
│   │   ├── booking.js
│   │   ├── home.js
│   │   ├── movie-detail.js
│   │   ├── movies.js
│   │   ├── payment.js
│   │   └── profile.js
│   │
│   ├── components/               # Moved from public/js/components/
│   │   ├── modal.js
│   │   ├── skeleton.js
│   │   └── toast.js
│   │
│   ├── utils/                    # NEW: User-specific utilities
│   │   └── form-validator.js    # Moved from public/js/utils/
│   │
│   ├── app.js                    # Moved from public/js/app.js
│   └── auth.js                   # Moved from public/js/auth.js
│
├── admin/                        # KEEP: Admin code
│   ├── base/
│   ├── pages/
│   ├── app.js
│   ├── admin-core.js            # Moved from public/js/admin-core.js
│   └── ... (existing files)
│
├── core/                         # KEEP: Shared utilities
│   └── api-client.js            # KEEP (used by both)
│
└── shared/                       # NEW: Truly shared utilities
    └── security.js              # Moved from public/js/utils/
    └── security-standalone.js   # Moved from public/js/utils/
```

**Note:** Delete `public/js/pages/booking.js.backup` during migration (backup file not needed in Git)

---

## 💡 Rationale & Decisions

### 1. Why `users/` instead of keeping `pages/` at root?
- **Consistency:** Mirrors CSS structure (`css/users/` vs `css/admin/`)
- **Clarity:** Immediately obvious what's user code vs admin code
- **Scalability:** Easy to add user-specific subdirectories if needed

### 2. Why move `components/` into `users/`?
- Current components (modal, skeleton, toast) are USER-facing only
- Admin has its own components in `admin/base/`
- If we need shared components later, create `core/components/`

### 3. Why split `utils/` folder?
- **form-validator.js** → `users/utils/` (only used in user forms)
- **security*.js** → `shared/` (used by both user & admin)
- Clearer separation of concerns

### 4. Why move `admin-core.js` into `admin/`?
- Name indicates it's admin-specific
- Currently misplaced at root level
- Should be with other admin code

### 5. Why keep `core/` separate?
- `api-client.js` is truly shared (used by both sides)
- `core/` can grow to include other shared business logic
- Distinct from `shared/` which is more about utilities

---

## 🚀 Migration Commands

**IMPORTANT:** Use `git mv` to preserve Git history!

```bash
# Step 1: Create new directories
mkdir -p public/js/users/pages
mkdir -p public/js/users/components
mkdir -p public/js/users/utils
mkdir -p public/js/shared

# Step 2: Move user pages (exclude backup)
git mv public/js/pages/booking.js public/js/users/pages/
git mv public/js/pages/home.js public/js/users/pages/
git mv public/js/pages/movie-detail.js public/js/users/pages/
git mv public/js/pages/movies.js public/js/users/pages/
git mv public/js/pages/payment.js public/js/users/pages/
git mv public/js/pages/profile.js public/js/users/pages/

# Step 3: Move user components
git mv public/js/components/modal.js public/js/users/components/
git mv public/js/components/skeleton.js public/js/users/components/
git mv public/js/components/toast.js public/js/users/components/

# Step 4: Move root user files
git mv public/js/app.js public/js/users/
git mv public/js/auth.js public/js/users/

# Step 5: Move user-specific utils
git mv public/js/utils/form-validator.js public/js/users/utils/

# Step 6: Move shared utilities
git mv public/js/utils/security.js public/js/shared/
git mv public/js/utils/security-standalone.js public/js/shared/

# Step 7: Move admin-core to admin folder
git mv public/js/admin-core.js public/js/admin/

# Step 8: Clean up empty directories and backup
rm public/js/pages/booking.js.backup
rmdir public/js/pages
rmdir public/js/components
rmdir public/js/utils

# Step 9: Commit the moves
git add -A
git commit -m "refactor: reorganize JS structure - users/ vs admin/ separation"
```

---

## 📝 Import Path Updates Required

### Files with Import Statements to Update

**1. User Page Files (6 files):**
```
public/js/users/pages/home.js
public/js/users/pages/movies.js
public/js/users/pages/movie-detail.js
public/js/users/pages/payment.js
public/js/users/pages/profile.js
public/js/users/pages/booking.js
```

**Changes in each:**
- `import Toast from '../components/toast.js';` ✅ (already correct after move)
- `import Modal from '../components/modal.js';` ✅ (already correct)
- `import { apiRequest } from '../../core/api-client.js';` ← if used

**2. User App File:**
```
public/js/users/app.js
```
- Update any imports from components or core

**3. User Auth File:**
```
public/js/users/auth.js
```
- `import { apiRequest } from '../core/api-client.js';` ← check if used
- Update Toast import if used

**4. Admin Files Using Security:**
Check if any admin files import from utils/security:
```
public/js/admin/**/*.js
```
- Update `from '../../utils/security.js'` → `from '../../shared/security.js'`

---

## 🔍 Template/View Updates Required

### Blade Templates Referencing JS Files

**Need to update `<script src="">` paths in:**

1. **User Layout:** `resources/views/layouts/app.blade.php`
   - `/js/app.js` → `/js/users/app.js`
   - `/js/auth.js` → `/js/users/auth.js`

2. **User Pages:**
   - `resources/views/users/home.blade.php` → `/js/pages/home.js` → `/js/users/pages/home.js`
   - `resources/views/users/movies/index.blade.php` → update paths
   - `resources/views/users/movies/show.blade.php` → update paths
   - `resources/views/users/booking/index.blade.php` → update paths
   - `resources/views/users/payment/index.blade.php` → update paths
   - `resources/views/users/profile/index.blade.php` → update paths

3. **Admin Layout:** `resources/views/layouts/admin.blade.php`
   - `/js/admin-core.js` → `/js/admin/admin-core.js`

---

## ⚠️ Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Broken imports | High | High | Systematic update of all imports |
| Missing template updates | Medium | High | Search all Blade files for old paths |
| Git history loss | Low | Medium | Use `git mv` not manual move |
| 404 errors in browser | High | High | Test all pages after migration |
| Cache issues | Medium | Low | Clear browser cache & Laravel cache |

---

## ✅ Verification Checklist

After migration, verify:

- [ ] All user pages load without 404 errors
- [ ] All admin pages load without 404 errors
- [ ] Toast notifications work on user pages
- [ ] Modal works if used
- [ ] Form validation works
- [ ] Authentication flow works
- [ ] No console errors in browser
- [ ] Git history preserved (check with `git log --follow`)
- [ ] No leftover files in old directories

---

## 📊 Summary

- **Files to move:** 17 files
- **Directories to create:** 4 new (users/, users/pages/, users/components/, users/utils/, shared/)
- **Directories to remove:** 3 old (pages/, components/, utils/)
- **Import updates:** ~10-15 files estimated
- **Template updates:** ~8-10 Blade files estimated
- **Estimated time:** 30-45 minutes
- **Risk level:** Medium (many path changes but straightforward)

---

## 🎯 Next Steps

1. **Review & Approve** this plan
2. **Backup** current working state (Git commit)
3. **Execute** migration commands
4. **Update** all imports in JS files
5. **Update** all paths in Blade templates
6. **Test** all user pages
7. **Test** all admin pages
8. **Commit** final changes

---

*Plan created by Kiro - 2026-07-09*
