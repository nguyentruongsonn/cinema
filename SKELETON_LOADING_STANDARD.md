# Skeleton Loading Standard

## Overview
All pages in the Cinema website must implement skeleton loading to provide a smooth, professional user experience during data fetching.

## Core Principle
**Show skeleton states while loading, then smoothly transition to actual content when ready.**

## Implementation Guidelines

### 1. CSS Structure
All skeleton styles are centralized in `/public/css/skeleton.css`:

```css
.skeleton {
    background: linear-gradient(90deg, #1a1a1a 25%, #2a2a2a 50%, #1a1a1a 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s ease-in-out infinite;
    border-radius: 4px;
}
```

### 2. HTML Pattern
Every page must follow this pattern:

```html
<!-- Skeleton (visible by default) -->
<div id="contentSkeleton" class="skeleton-[type]">
    <!-- Skeleton elements matching actual content structure -->
</div>

<!-- Actual Content (hidden initially with d-none) -->
<div id="actualContent" class="d-none">
    <!-- Real content rendered here -->
</div>
```

### 3. JavaScript Pattern
All pages must implement this loading sequence:

```javascript
// 1. Cache DOM elements on load
function cacheDoms() {
    els = {
        skeleton: document.getElementById('contentSkeleton'),
        content: document.getElementById('actualContent'),
        // ... other elements
    };
}

// 2. Fetch data and render
async function loadData() {
    try {
        const res = await fetch(API_ENDPOINT);
        const json = await res.json();
        
        if (!json.success) throw new Error(json.message);
        
        // Render actual content
        renderContent(json.data);
        
        // Show loaded state with smooth transition
        setTimeout(showLoaded, 350); // 350ms delay for smooth UX
        
    } catch (err) {
        showError(err.message);
    }
}

// 3. Show loaded state
function showLoaded() {
    els.skeleton?.classList.add('d-none');
    els.content?.classList.remove('d-none');
}

// 4. Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    cacheDoms();
    loadData();
});
```

## Skeleton Component Library

### Hero Section Skeleton
```html
<div class="skeleton-hero">
    <div class="skeleton-hero-content">
        <div class="skeleton skeleton-badge"></div>
        <div class="skeleton skeleton-title"></div>
        <div class="skeleton skeleton-text"></div>
        <div class="skeleton skeleton-text-short"></div>
    </div>
</div>
```

### Card Grid Skeleton
```html
<div class="skeleton-grid">
    <div class="skeleton-movie-card">
        <div class="skeleton skeleton-movie-poster"></div>
        <div class="skeleton-movie-info">
            <div class="skeleton skeleton-movie-title"></div>
            <div class="skeleton skeleton-movie-meta"></div>
        </div>
    </div>
    <!-- Repeat 3-4 times -->
</div>
```

### Form Skeleton
```html
<div class="skeleton skeleton-booking"></div>
```

### Table Skeleton
```html
<div class="skeleton-table">
    <div class="skeleton skeleton-table-row" style="height: 48px;"></div>
    <div class="skeleton skeleton-table-row" style="height: 48px;"></div>
    <div class="skeleton skeleton-table-row" style="height: 48px;"></div>
</div>
```

### List Skeleton
```html
<div class="skeleton-list">
    <div class="skeleton skeleton-list-item"></div>
    <div class="skeleton skeleton-list-item"></div>
    <div class="skeleton skeleton-list-item"></div>
</div>
```

## Page-Specific Implementation

### Homepage (✅ Implemented)
- Hero section skeleton
- Booking widget skeleton
- Movie grid skeleton (4 cards)
- Smooth 350ms transition to loaded state

### Movie List Page
```html
<div id="filtersSkeleton" class="skeleton skeleton-filters"></div>
<div id="moviesSkeleton" class="skeleton-grid">
    <!-- 8-12 movie card skeletons -->
</div>
```

### Movie Detail Page
```html
<div id="detailSkeleton">
    <div class="skeleton skeleton-movie-header"></div>
    <div class="skeleton skeleton-movie-content"></div>
    <div class="skeleton skeleton-showtimes-section"></div>
</div>
```

### Seat Selection Page
```html
<div id="seatMapSkeleton" class="skeleton skeleton-seat-map"></div>
<div id="seatSummarySkeleton" class="skeleton skeleton-summary"></div>
```

### User Dashboard
```html
<div id="ordersSkeleton" class="skeleton-table">
    <div class="skeleton skeleton-table-row"></div>
    <div class="skeleton skeleton-table-row"></div>
    <div class="skeleton skeleton-table-row"></div>
</div>
```

### Admin Pages
```html
<div id="dashboardSkeleton">
    <div class="skeleton-stats-grid">
        <div class="skeleton skeleton-stat-card"></div>
        <div class="skeleton skeleton-stat-card"></div>
        <div class="skeleton skeleton-stat-card"></div>
        <div class="skeleton skeleton-stat-card"></div>
    </div>
    <div class="skeleton skeleton-chart"></div>
</div>
```

## Best Practices

### 1. Match Real Content Structure
Skeleton should mirror the actual content layout for seamless transition.

### 2. Appropriate Count
Show realistic number of skeleton items (e.g., 4 cards on homepage, 8-12 on listing page).

### 3. Timing
- Minimum display: 350ms (prevent flash of skeleton)
- Maximum wait: Show error after 10s timeout

### 4. Error Handling
Always handle errors gracefully:
```javascript
function showError(message) {
    els.skeleton?.classList.add('d-none');
    els.content?.classList.remove('d-none');
    els.content.innerHTML = `
        <div class="error-state">
            <i class="bi bi-exclamation-circle"></i>
            <p>${message}</p>
            <button onclick="location.reload()">Thử lại</button>
        </div>
    `;
}
```

### 5. Responsive Behavior
Skeleton must adapt to screen sizes:
```css
@media (max-width: 768px) {
    .skeleton-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .skeleton-grid {
        grid-template-columns: 1fr;
    }
}
```

## Performance Considerations

1. **CSS Animation over JS**: Use CSS animations for skeleton shimmer effect
2. **No Extra Requests**: Skeleton is pure HTML/CSS, no additional API calls
3. **Lazy Loading**: Only load skeleton CSS on pages that need it
4. **Reusable Components**: Build skeleton library once, reuse everywhere

## Checklist for New Pages

- [ ] Include `/public/css/skeleton.css` in page styles
- [ ] Create skeleton HTML matching actual content structure
- [ ] Hide actual content with `d-none` class initially
- [ ] Implement `cacheDoms()`, `loadData()`, `showLoaded()` pattern
- [ ] Add error handling with `showError()` function
- [ ] Test skeleton on slow 3G network
- [ ] Verify smooth transition (350ms delay)
- [ ] Ensure responsive behavior on mobile

## Example: Complete Page Implementation

```html
<!-- Blade Template -->
@push('styles')
<link rel="stylesheet" href="{{ asset('css/skeleton.css') }}">
@endpush

<div id="pageSkeleton" class="skeleton-grid">
    <!-- Skeleton elements -->
</div>

<div id="pageContent" class="d-none">
    <!-- Actual content -->
</div>

@push('scripts')
<script src="{{ asset('js/pages/example.js') }}"></script>
@endpush
```

```javascript
// example.js
(function() {
    'use strict';
    
    let els = {};
    
    function cacheDoms() {
        els = {
            skeleton: document.getElementById('pageSkeleton'),
            content: document.getElementById('pageContent'),
        };
    }
    
    function showLoaded() {
        els.skeleton?.classList.add('d-none');
        els.content?.classList.remove('d-none');
    }
    
    async function loadData() {
        try {
            const res = await fetch('/api/endpoint');
            const json = await res.json();
            
            if (!json.success) throw new Error(json.message);
            
            renderContent(json.data);
            setTimeout(showLoaded, 350);
        } catch (err) {
            console.error('[Page] Load error:', err);
            showError(err.message);
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        cacheDoms();
        loadData();
    });
})();
```

## Summary

**Every page MUST:**
1. Show skeleton loading by default
2. Fetch data asynchronously
3. Render actual content when ready
4. Transition smoothly (350ms delay)
5. Handle errors gracefully

This standard ensures consistent, professional UX across the entire Cinema platform.
