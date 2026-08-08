# Frontend Directory Structure

**Cinema Booking System — historical frontend structure reference**
**Last reviewed:** August 8, 2026
**Status:** The directory examples below are legacy documentation. Treat `vite.config.js`, the Blade layouts, and the active `public/css`/`public/js` files as the source of truth until this document is fully rewritten.

## Overview

The frontend is organized into two distinct interfaces with clear separation of concerns:
- **User Interface** (`public/css/users/`, `public/js/pages/`, `public/js/components/`)
- **Admin Interface** (`public/css/admin/`, `public/js/admin/`)

Both interfaces share a common design system foundation while maintaining their own unique styles and behaviors.

---

## Directory Structure

```
public/
├── css/
│   ├── admin/                          # Admin interface styles
│   │   ├── base/                       # Admin foundation
│   │   │   ├── variables.css          # Admin design tokens
│   │   │   ├── reset.css              # Admin CSS reset
│   │   │   └── typography.css         # Admin typography
│   │   ├── components/                 # Admin UI components
│   │   │   ├── buttons.css
│   │   │   ├── forms.css
│   │   │   ├── tables.css
│   │   │   ├── modals.css
│   │   │   ├── cards.css
│   │   │   ├── filters.css
│   │   │   ├── pagination.css
│   │   │   ├── badges.css
│   │   │   ├── alerts.css
│   │   │   └── skeleton.css
│   │   ├── admin.css                   # Admin main stylesheet
│   │   ├── admin-common.css            # Admin common utilities
│   │   ├── admin-modals.css            # Admin modal overrides
│   │   └── style.css                   # Admin legacy styles
│   │
│   └── users/                          # User interface styles
│       ├── base/                       # User foundation (Phase 1)
│       │   ├── variables.css          # User design tokens
│       │   ├── reset.css              # User CSS reset
│       │   └── typography.css         # User typography
│       ├── components/                 # User UI components
│       │   ├── buttons.css            # Button system (Phase 1)
│       │   ├── skeleton.css           # Loading states (Phase 1)
│       │   ├── toast.css              # Notifications (Phase 1)
│       │   ├── forms.css              # Form components (Phase 2)
│       │   ├── cards.css              # Card components (Phase 2)
│       │   └── modals.css             # Modal dialogs (Phase 2)
│       ├── utils/                      # User utilities (Phase 1)
│       │   └── animations.css         # Reusable animations
│       └── pages/                      # User page-specific styles
│           ├── booking.css
│           ├── movies.css
│           ├── movie-detail.css
│           └── profile.css
│
├── js/
│   ├── admin/                          # Admin JavaScript
│   │   ├── base/                       # Admin base classes
│   │   │   ├── AdminBasePage.js
│   │   │   ├── AdminTable.js
│   │   │   ├── AdminForm.js
│   │   │   └── AdminModal.js
│   │   ├── pages/                      # Admin page controllers
│   │   │   ├── movies.js
│   │   │   ├── showtimes.js
│   │   │   ├── theaters.js
│   │   │   ├── branches.js
│   │   │   ├── products.js
│   │   │   ├── combos.js
│   │   │   ├── promotions.js
│   │   │   ├── users.js
│   │   │   ├── posts.js
│   │   │   ├── banners.js
│   │   │   └── seat-layout-templates.js
│   │   ├── app.js                      # Admin application entry
│   │   ├── responsive-menu.js          # Admin mobile menu
│   │   ├── mobile-search-toggle.js     # Admin mobile search
│   │   └── ticket-scanner.js           # Admin ticket scanner
│   │
│   ├── components/                     # Shared components
│   │   ├── skeleton.js                 # Skeleton loader utility (Phase 1)
│   │   ├── toast.js                    # Toast notification utility (Phase 1)
│   │   └── modal.js                    # Modal dialog utility (Phase 2)
│   │
│   ├── pages/                          # User page controllers
│   │   ├── home.js
│   │   ├── movies.js
│   │   ├── movie-detail.js
│   │   ├── booking.js
│   │   ├── payment.js
│   │   └── profile.js
│   │
│   ├── core/                           # Core utilities
│   │   └── api-client.js              # API communication layer
│   │
│   ├── utils/                          # Shared utilities
│   │   ├── security.js
│   │   ├── security-standalone.js
│   │   └── form-validator.js          # Form validation (Phase 2)
│   │
│   ├── app.js                          # User application entry
│   └── auth.js                         # Authentication logic
│
└── [other public assets]
```

---

## User Interface Structure (Phase 1 & 2 Complete)

### Base Layer (`public/css/users/base/`)
Foundation styles that define the user interface design system:

- **variables.css** - Design tokens (colors, spacing, typography, shadows, transitions)
- **reset.css** - Modern CSS reset with accessibility features
- **typography.css** - Text styles, headings, utilities

### Components (`public/css/users/components/`)
Reusable UI components:

**Phase 1:**
- **buttons.css** - Comprehensive button system with variants, sizes, states
- **skeleton.css** - Loading placeholders for improved perceived performance
- **toast.css** - Non-intrusive notification system

**Phase 2:**
- **forms.css** - Complete form system with validation states
- **cards.css** - Versatile card components (movie cards, ticket cards, profile cards, info cards)
- **modals.css** - Accessible modal dialogs with animations

### Utilities (`public/css/users/utils/`)
Utility classes and reusable patterns:

- **animations.css** - Common animations (fade, slide, bounce, spin, hover effects)

### Pages (`public/css/users/pages/`)
Page-specific styles:

- **booking.css** - Booking flow styles
- **movies.css** - Movie listing page
- **movie-detail.css** - Movie detail page
- **profile.css** - User profile page

### JavaScript Components (`public/js/components/`)
Reusable JavaScript utilities:

**Phase 1:**
- **skeleton.js** - SkeletonLoader class for generating loading states
- **toast.js** - Toast class for showing notifications

**Phase 2:**
- **modal.js** - Modal class for accessible dialogs with convenience methods

### JavaScript Utilities (`public/js/utils/`)
Specialized utilities:

**Phase 2:**
- **form-validator.js** - FormValidator class for real-time form validation

---

## Admin Interface Structure

### Base Layer (`public/css/admin/base/`)
- **variables.css** - Admin-specific design tokens
- **reset.css** - Admin CSS reset
- **typography.css** - Admin typography system

### Components (`public/css/admin/components/`)
Complete admin component library with tables, forms, modals, cards, filters, pagination, badges, alerts, and skeleton loaders.

### JavaScript (`public/js/admin/`)
Object-oriented admin interface with base classes, page controllers, and utilities for responsive behavior and ticket scanning.

---

## CSS Architecture

### Naming Conventions

**User Interface:**
- Variables: `--color-primary`, `--space-4`, `--text-base`
- Classes: `.btn`, `.btn-primary`, `.skeleton`, `.toast-success`

**Admin Interface:**
- Variables: `--admin-primary`, `--admin-space-md`, `--admin-text-primary`
- Classes: `.admin-btn`, `.admin-table`, `.admin-modal`

### Import Order (Recommended)

```css
/* User Interface */
@import 'base/variables.css';          /* 1. Design tokens first */
@import 'base/reset.css';              /* 2. Reset/normalize */
@import 'base/typography.css';         /* 3. Typography */
@import 'utils/animations.css';        /* 4. Utilities */
@import 'components/buttons.css';      /* 5. Components */
@import 'components/skeleton.css';
@import 'components/toast.css';
@import 'pages/[page-name].css';       /* 6. Page-specific */
```

---

## Best Practices

### CSS
1. **Use CSS variables** for all colors, spacing, and common values
2. **BEM-like naming** for component classes (`.btn-primary`, `.toast-error`)
3. **Mobile-first** responsive design with min-width media queries
4. **Accessibility** - Focus states, reduced motion support, ARIA-friendly
5. **Performance** - Minimize specificity, use efficient selectors

### JavaScript
1. **Modular design** - Each component/page in its own file
2. **Class-based components** for reusability (SkeletonLoader, Toast)
3. **Async/await** for API calls through api-client.js
4. **Error handling** - Graceful degradation with user feedback
5. **Progressive enhancement** - Core functionality works without JS

### File Organization
1. **Separate concerns** - User vs Admin clearly separated
2. **Logical grouping** - base/components/utils/pages hierarchy
3. **Consistent naming** - kebab-case for files, PascalCase for classes
4. **Single responsibility** - Each file has one clear purpose

---

## Phase 1 Accomplishments

✅ **Design System Foundation**
- Complete design tokens (colors, spacing, typography)
- Modern CSS reset with accessibility
- Comprehensive typography system

✅ **Core Components**
- Button system with variants and states
- Skeleton loaders for perceived performance
- Toast notifications for user feedback

✅ **Utilities**
- Animation library with reduced motion support
- Transition utilities and hover effects

✅ **Directory Structure**
- Clear user/admin separation
- Logical base/components/utils/pages hierarchy
- Consistent naming conventions

✅ **Documentation**
- Comprehensive structure documentation
- Best practices and conventions
- Import order recommendations

---

## Phase 2 Accomplishments

✅ **Form System**
- Complete form component styles with validation states
- Real-time form validator with debouncing
- Preset validation rules (email, phone, password)
- Error/success state management

✅ **Card Components**
- Movie cards with hover effects
- Ticket cards for booking history
- Profile cards for user information
- Info cards with variants (success, warning, error)

✅ **Modal System**
- Accessible modal dialogs with ARIA support
- Multiple animation options (fade, slide, zoom)
- Modal variants (confirm, success, warning, error)
- Convenience methods (alert, confirm, success, error)
- Keyboard navigation and focus management

✅ **Enhanced UX**
- Loading states across all interactive elements
- Clear error handling and user feedback
- Responsive components for all screen sizes
- Dark mode support for all new components

---

## Next Steps (Future Phases)

**Phase 2 - ✅ COMPLETE**

**Phase 3 - Page Enhancements:**
- Homepage improvements
- Movie listing enhancements
- Booking flow refinements
- Profile page updates

**Phase 3 - Page Enhancements:**
- Homepage improvements
- Movie listing enhancements
- Booking flow refinements
- Profile page updates

**Phase 4 - Advanced Features:**
- Dark mode support
- Accessibility audit and improvements
- Performance optimization
- Animation polish

---

## Usage Examples

### Using Skeleton Loaders
```javascript
// Show skeleton while loading
SkeletonLoader.show('.movie-grid', SkeletonLoader.movieCard, 6);

// Hide skeleton when content ready
SkeletonLoader.hide('.movie-grid', actualContent);
```

### Using Toast Notifications
```javascript
// Success notification
Toast.success('Booking confirmed!', 'Your tickets have been sent to your email.');

// Error notification
Toast.error('Payment failed', 'Please try again or contact support.');

// Custom options
Toast.info('New movies available', '', { 
    position: 'bottom-right',
    duration: 5000 
});
```

### Using Button Classes
```html
<!-- Primary action -->
<button class="btn btn-primary">Book Now</button>

<!-- Secondary action with icon -->
<button class="btn btn-secondary btn-icon-left">
    <svg>...</svg>
    View Details
</button>

<!-- Loading state -->
<button class="btn btn-primary btn-loading">Processing...</button>
```

### Using Form Validator (Phase 2)
```javascript
// Initialize form validator
const validator = new FormValidator('#booking-form', {
    email: { required: true, email: true },
    phone: { required: true, phone: true },
    password: { required: true, minLength: 8 }
});

// Validate on submit
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (validator.validateAll()) {
        await submitForm();
    }
});
```

### Using Modal Utility (Phase 2)
```javascript
// Simple alert
Modal.alert('Success', 'Your booking has been confirmed!');

// Confirmation dialog
Modal.confirm('Delete Account', 'Are you sure? This cannot be undone.', () => {
    deleteAccount();
});

// Custom modal
const modal = new Modal({
    title: 'Movie Trailer',
    content: videoElement,
    size: 'lg',
    animation: 'zoom'
});
modal.open();
```

### Using Card Components (Phase 2)
```html
<!-- Movie card -->
<div class="movie-card">
    <div class="movie-card-image">
        <img src="poster.jpg" alt="Movie Title">
        <div class="movie-card-overlay">
            <button class="btn btn-primary">Book Now</button>
        </div>
    </div>
    <div class="movie-card-content">
        <h3 class="movie-card-title">Movie Title</h3>
        <p class="movie-card-meta">2024 • Action • 2h 30m</p>
    </div>
</div>

<!-- Info card with variant -->
<div class="info-card info-card-success">
    <div class="info-card-icon">✓</div>
    <div class="info-card-content">
        <h3>Payment Successful</h3>
        <p>Your tickets have been sent to your email.</p>
    </div>
</div>
```

---

## Maintenance Notes

- **CSS Variables:** Defined in `base/variables.css` - modify there for global changes
- **Components:** Self-contained in `components/` - easy to update independently
- **Page Styles:** Specific overrides in `pages/` - don't affect global components
- **Utilities:** Reusable patterns in `utils/` - use before creating new styles

---

**Last Updated:** July 9, 2026  
**Phase:** 1 & 2 Complete ✅  
**Next:** Phase 3 - Page Enhancements
