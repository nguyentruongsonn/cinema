# Phase 2 Code Quality Refactoring - Kickoff Guide

**Status:** Ready to Start  
**Priority:** MEDIUM (Can be done incrementally)  
**Estimated Time:** 1-2 hours per file (8-16 hours total)

---

## ✅ Prerequisites Complete

1. ✅ Phase 1 XSS fixes deployed
2. ✅ Security utilities available globally (`window.Security`)
3. ✅ Layout updated to include security-standalone.js

---

## 🎯 Refactoring Goals

### Primary Objectives
1. **Eliminate innerHTML** - Replace with safer DOM methods
2. **Centralize utilities** - Use `window.Security` functions
3. **Improve performance** - Targeted updates instead of full rewrites
4. **Maintain security** - Keep XSS protection from Phase 1

### Non-Goals
- Not adding new features
- Not changing application behavior
- Not modifying CSS or styling
- Not refactoring backend code

---

## 📋 Files to Refactor (Priority Order)

### High Priority (Contains innerHTML)
1. ✅ `public/js/pages/profile.js` - Minimal innerHTML (3 instances)
2. 🔴 `public/js/pages/booking.js` - Heavy innerHTML usage
3. 🔴 `public/js/pages/payment.js` - Moderate innerHTML
4. 🔴 `public/js/pages/tickets.js` - Heavy innerHTML

### Medium Priority
5. 🟡 `public/js/pages/home.js` - Some innerHTML
6. 🟡 `public/js/pages/movies.js` - Some innerHTML  
7. 🟡 `public/js/pages/movie-detail.js` - Some innerHTML

### Low Priority (Already good)
8. ✅ `public/js/app.js` - Minimal refactoring needed

---

## 🔍 Current State: profile.js Analysis

**Lines with innerHTML:**
- Line 353: Loading spinner
- Line 412: Error message  
- Line 438: Clear formats container

**Good patterns already present:**
- ✅ Uses `createTicketCard()` with DOM methods
- ✅ Uses `createElement()` and `appendChild()`
- ✅ Template cloning for complex elements
- ✅ Proper event delegation

**Refactoring needed:** Only 3 innerHTML instances (minor)

---

## 🛠️ Refactoring Patterns

### Pattern 1: Replace innerHTML with DOM Methods

#### ❌ Before (Unsafe)
```javascript
container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
```

#### ✅ After (Safe)
```javascript
function createAlert(message, type = 'danger') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = window.Security.escapeHtml(message);
    return alert;
}

container.textContent = ''; // Clear
container.appendChild(createAlert(message));
```

---

### Pattern 2: Loading States

#### ❌ Before
```javascript
container.innerHTML = '<div class="spinner-border"></div>';
```

#### ✅ After
```javascript
function createSpinner() {
    const wrapper = document.createElement('div');
    wrapper.className = 'text-center py-5';
    
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-danger';
    
    wrapper.appendChild(spinner);
    return wrapper;
}

container.textContent = '';
container.appendChild(createSpinner());
```

---

### Pattern 3: Building Complex Elements

#### ❌ Before
```javascript
card.innerHTML = `
    <img src="${escapeAttr(movie.poster)}">
    <h3>${escapeHtml(movie.title)}</h3>
    <p>${escapeHtml(movie.description)}</p>
`;
```

#### ✅ After
```javascript
function createMovieCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    
    const img = document.createElement('img');
    img.src = window.Security.sanitizeUrl(movie.poster);
    img.alt = window.Security.escapeAttr(movie.title);
    
    const title = document.createElement('h3');
    title.textContent = movie.title;
    
    const desc = document.createElement('p');
    desc.textContent = movie.description;
    
    card.appendChild(img);
    card.appendChild(title);
    card.appendChild(desc);
    
    return card;
}
```

---

### Pattern 4: Template Cloning (Recommended for Complex UI)

#### HTML Template
```html
<template id="movieCardTemplate">
    <div class="movie-card">
        <img class="movie-poster" alt="">
        <h3 class="movie-title"></h3>
        <p class="movie-description"></p>
    </div>
</template>
```

#### ✅ JavaScript
```javascript
function createMovieCardFromTemplate(movie) {
    const template = document.getElementById('movieCardTemplate');
    const card = template.content.cloneNode(true);
    
    const img = card.querySelector('.movie-poster');
    img.src = window.Security.sanitizeUrl(movie.poster);
    img.alt = movie.title;
    
    card.querySelector('.movie-title').textContent = movie.title;
    card.querySelector('.movie-description').textContent = movie.description;
    
    return card;
}
```

---

## 📝 Refactoring Checklist for Each File

When refactoring a file, follow this checklist:

### 1. Preparation
- [ ] Read the entire file
- [ ] Identify all innerHTML usages
- [ ] Note complex HTML structures
- [ ] Check if templates exist in Blade files

### 2. Refactoring
- [ ] Create helper functions for common elements
- [ ] Replace innerHTML with DOM methods
- [ ] Use `window.Security` functions consistently
- [ ] Update event handling if needed

### 3. Verification
- [ ] Run syntax check: `node --check public/js/pages/[file].js`
- [ ] Test in browser (all user flows)
- [ ] Check console for errors
- [ ] Verify XSS protection still works

### 4. Documentation
- [ ] Update comments if needed
- [ ] Document any breaking changes
- [ ] Note performance improvements

---

## 🚀 Quick Start: Refactor profile.js (Example)

### Step 1: Identify innerHTML instances
```bash
grep -n "innerHTML" public/js/pages/profile.js
```

**Result:** Lines 353, 412, 438

### Step 2: Create helper functions at top of class

```javascript
class ProfilePage {
    constructor() {
        // existing code...
    }

    // Add these helper methods
    createLoadingSpinner() {
        const wrapper = document.createElement('div');
        wrapper.className = 'text-center py-5';
        const spinner = document.createElement('div');
        spinner.className = 'spinner-border text-danger';
        wrapper.appendChild(spinner);
        return wrapper;
    }

    createErrorAlert(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.textContent = message;
        return alert;
    }

    clearContainer(container) {
        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }
    }

    // existing methods...
}
```

### Step 3: Replace innerHTML calls

#### Line 353 (Loading state)
```javascript
// Before
ticketsList.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-danger"></div></div>';

// After
this.clearContainer(ticketsList);
ticketsList.appendChild(this.createLoadingSpinner());
```

#### Line 412 (Error message)
```javascript
// Before
ticketsList.innerHTML = '<div class="alert alert-danger">Không thể tải danh sách vé</div>';

// After
this.clearContainer(ticketsList);
ticketsList.appendChild(this.createErrorAlert('Không thể tải danh sách vé'));
```

#### Line 438 (Clear formats)
```javascript
// Before
formatsContainer.innerHTML = '';

// After
this.clearContainer(formatsContainer);
```

### Step 4: Verify
```bash
node --check public/js/pages/profile.js
```

---

## 📊 Estimated Time Per File

| File | innerHTML Count | Complexity | Time Estimate |
|------|----------------|------------|---------------|
| profile.js | 3 | Low | 30 mins |
| booking.js | 15+ | High | 2-3 hours |
| payment.js | 8 | Medium | 1-2 hours |
| tickets.js | 12+ | High | 2-3 hours |
| home.js | 6 | Medium | 1 hour |
| movies.js | 5 | Medium | 1 hour |
| movie-detail.js | 7 | Medium | 1-2 hours |
| app.js | 2 | Low | 30 mins |

**Total:** 8-16 hours (can be done incrementally)

---

## ✅ Benefits After Completion

### Security
- ✅ Defense-in-depth against XSS
- ✅ Harder to introduce vulnerabilities
- ✅ Consistent security practices

### Performance
- ✅ Targeted DOM updates (no full rewrites)
- ✅ Better memory management
- ✅ Faster rendering for large lists

### Maintainability
- ✅ Cleaner, more readable code
- ✅ Easier to debug
- ✅ Better testability
- ✅ Centralized utility functions

### Developer Experience
- ✅ Clear patterns to follow
- ✅ Reusable components
- ✅ Better code reviews

---

## 🎯 Recommended Approach

### Option A: All at Once (1-2 days)
- Block time for focused refactoring
- Complete all 8 files in sequence
- Single PR with comprehensive testing
- **Pros:** Done quickly, consistent approach
- **Cons:** Merge conflicts, large code review

### Option B: Incremental (1 file per day)
- Refactor one file per day
- Individual PRs per file
- Deploy and test each change
- **Pros:** Safer, easier reviews, less risky
- **Cons:** Takes 1-2 weeks

### Option C: By Priority (Recommended)
1. Week 1: High priority files (booking, payment, tickets)
2. Week 2: Medium priority files (home, movies, movie-detail)
3. Week 3: Low priority files (profile, app) + testing
- **Pros:** Addresses worst code first
- **Cons:** Requires good task tracking

---

## 📋 Next Actions

### Immediate (Do Now)
1. ✅ Security utilities registered in layout
2. ✅ Phase 1 deployed to production
3. ⏳ Choose refactoring approach (A, B, or C)
4. ⏳ Schedule time for refactoring work

### Short-term (This Week)
1. Start with profile.js (easiest, ~30 mins)
2. Move to booking.js or payment.js
3. Test thoroughly after each file
4. Create individual git commits

### Long-term (This Month)
1. Complete all 8 files
2. Add automated tests
3. Document patterns for team
4. Training session on new patterns

---

## 🔧 Tools & Commands

### Syntax Validation
```bash
# Single file
node --check public/js/pages/profile.js

# All files
for file in public/js/pages/*.js; do
    echo "Checking $file..."
    node --check "$file"
done
```

### Find innerHTML Usage
```bash
# Count innerHTML per file
grep -c "innerHTML" public/js/pages/*.js

# Show line numbers
grep -n "innerHTML" public/js/pages/*.js

# Count total
grep -r "innerHTML" public/js/pages/ | wc -l
```

### Testing Checklist
- [ ] All pages load without errors
- [ ] User workflows function correctly
- [ ] No console errors or warnings
- [ ] XSS protection still works
- [ ] Performance is same or better

---

## 📞 Support

**Questions?**
- Review PHASE2_CODE_QUALITY_PLAN.md for detailed plan
- Check PHASE1_FRONTEND_XSS_COMPLETE.md for security patterns
- Refer to public/js/utils/security-standalone.js for available functions

**Need help?**
- Start with profile.js (simplest example)
- Follow the patterns shown above
- Test incrementally

---

**Status:** 🟢 Ready to begin  
**Next File:** profile.js (30 minutes)  
**Documentation:** Complete  
**Security Utilities:** ✅ Available globally
