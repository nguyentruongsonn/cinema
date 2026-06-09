# Frontend Refactor - Phases 4-6: Core Refactoring & Testing

> **Phần tiếp theo của FRONTEND_REFACTOR_IMPLEMENTATION_PLAN.md**
> **Focus:** app.js refactoring, testing, performance optimization

---

## Phase 4: app.js Refactoring - Days 11-18 (8 days)

### Objective
Break down monolithic 1850-line app.js into modular, maintainable components following BookingManager pattern.

### Strategy
**Incremental extraction** - extract one manager at a time, test, then proceed.

---

### Task 4.1: Create Base Manager Class (Day 11)

**File:** `public/js/managers/BaseManager.js`

```javascript
import { http } from '../services/HttpClient.js';
import { errorHandler } from '../utils/error-handler.js';
import { Security } from '../utils/security.js';
import { DOM } from '../utils/dom.js';

/**
 * Base class for all managers
 * Provides common functionality and lifecycle methods
 */
export class BaseManager {
    constructor(container) {
        this.container = container;
        this.state = {};
        this.subscriptions = [];
        this.timers = [];
        this.initialized = false;
    }
    
    // Lifecycle methods (to be overridden by subclasses)
    async initialize() {
        throw new Error('initialize() must be implemented by subclass');
    }
    
    async cleanup() {
        // Clear all timers
        this.timers.forEach(timer => clearTimeout(timer));
        this.timers = [];
        
        // Unsubscribe from all channels
        this.subscriptions.forEach(channel => {
            if (window.Echo) {
                window.Echo.leave(channel);
            }
        });
        this.subscriptions = [];
    }
    
    // State management
    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.onStateChange(newState);
    }
    
    getState() {
        return { ...this.state };
    }
    
    onStateChange(newState) {
        // Override in subclass to react to state changes
    }
    
    // Utility methods
    setTimeout(callback, delay) {
        const timer = setTimeout(callback, delay);
        this.timers.push(timer);
        return timer;
    }
    
    subscribe(channel) {
        this.subscriptions.push(channel);
    }
    
    // Safe DOM manipulation
    updateElement(selector, content, useHtml = false) {
        const element = this.container.querySelector(selector);
        if (!element) return;
        
        if (useHtml) {
            Security.setInnerHTML(element, content);
        } else {
            Security.setTextContent(element, content);
        }
    }
    
    showElement(selector) {
        const element = this.container.querySelector(selector);
        if (element) DOM.show(element);
    }
    
    hideElement(selector) {
        const element = this.container.querySelector(selector);
        if (element) DOM.hide(element);
    }
}
```

---

### Task 4.2: Extract MovieManager (Days 12-13)

**File:** `public/js/managers/MovieManager.js`

```javascript
import { BaseManager } from './BaseManager.js';
import { http } from '../services/HttpClient.js';
import { Formatters } from '../utils/formatters.js';
import { Security } from '../utils/security.js';

/**
 * Manages movie listing, filtering, and detail view
 */
export class MovieManager extends BaseManager {
    constructor(container) {
        super(container);
        this.movies = [];
        this.filters = {
            search: '',
            genre: '',
            status: 'now_showing'
        };
    }
    
    async initialize() {
        await this.loadMovies();
        this.setupEventListeners();
        this.render();
        this.initialized = true;
    }
    
    async loadMovies() {
        try {
            const params = new URLSearchParams(this.filters);
            this.movies = await http.get(`/movies?${params}`);
        } catch (error) {
            console.error('Failed to load movies:', error);
            this.movies = [];
        }
    }
    
    setupEventListeners() {
        // Search input
        const searchInput = this.container.querySelector('#movie-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filters.search = e.target.value;
                this.debouncedSearch();
            });
        }
        
        // Genre filter
        const genreSelect = this.container.querySelector('#genre-filter');
        if (genreSelect) {
            genreSelect.addEventListener('change', (e) => {
                this.filters.genre = e.target.value;
                this.applyFilters();
            });
        }
        
        // Status tabs
        const statusTabs = this.container.querySelectorAll('[data-status]');
        statusTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.filters.status = tab.dataset.status;
                this.updateActiveTab(tab);
                this.applyFilters();
            });
        });
    }
    
    debouncedSearch() {
        clearTimeout(this.searchTimer);
        this.searchTimer = this.setTimeout(() => {
            this.applyFilters();
        }, 300);
    }
    
    async applyFilters() {
        await this.loadMovies();
        this.render();
    }
    
    render() {
        const grid = this.container.querySelector('.movie-grid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        if (this.movies.length === 0) {
            grid.appendChild(this.renderEmptyState());
            return;
        }
        
        this.movies.forEach(movie => {
            grid.appendChild(this.renderMovieCard(movie));
        });
    }
    
    renderMovieCard(movie) {
        const card = document.createElement('div');
        card.className = 'movie-card';
        card.setAttribute('data-movie-id', movie.id);
        
        // Poster
        const poster = document.createElement('img');
        poster.className = 'movie-poster';
        poster.src = movie.poster_url;
        poster.alt = Security.escapeHtml(movie.title);
        poster.loading = 'lazy';
        
        // Content
        const content = document.createElement('div');
        content.className = 'movie-content';
        
        const title = document.createElement('h3');
        Security.setTextContent(title, movie.title);
        
        const meta = document.createElement('div');
        meta.className = 'movie-meta';
        Security.setTextContent(meta, 
            `${Formatters.duration(movie.duration)} • ${movie.age_rating}`
        );
        
        const genres = document.createElement('div');
        genres.className = 'movie-genres';
        Security.setTextContent(genres, movie.genres?.join(', ') || '');
        
        // Button
        const button = document.createElement('a');
        button.className = 'btn btn-primary';
        button.href = `/movies/${movie.id}`;
        button.textContent = 'Đặt vé';
        
        content.appendChild(title);
        content.appendChild(meta);
        content.appendChild(genres);
        content.appendChild(button);
        
        card.appendChild(poster);
        card.appendChild(content);
        
        return card;
    }
    
    renderEmptyState() {
        const empty = document.createElement('div');
        empty.className = 'empty-state';
        empty.innerHTML = `
            <p>Không tìm thấy phim nào</p>
        `;
        return empty;
    }
    
    async cleanup() {
        clearTimeout(this.searchTimer);
        await super.cleanup();
    }
}
```

**Integration in app.js:**
```javascript
// OLD CODE (remove gradually):
// function initMovieListing() { ... 1850 lines ... }

// NEW CODE:
import { MovieManager } from './managers/MovieManager.js';

document.addEventListener('DOMContentLoaded', async () => {
    const movieContainer = document.querySelector('#movie-section');
    if (movieContainer) {
        const movieManager = new MovieManager(movieContainer);
        await movieManager.initialize();
        
        // Store for cleanup
        window._managers = window._managers || [];
        window._managers.push(movieManager);
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    window._managers?.forEach(m => m.cleanup());
});
```

---

### Task 4.3: Extract NotificationManager (Day 14)

**File:** `public/js/managers/NotificationManager.js`

```javascript
import { Security } from '../utils/security.js';

export class NotificationManager {
    constructor() {
        this.queue = [];
        this.maxVisible = 3;
        this.container = null;
        this.initialize();
    }
    
    initialize() {
        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = 'notifications';
        document.body.appendChild(this.container);
    }
    
    show(message, type = 'info', duration = 5000) {
        const notification = this.create(message, type);
        
        this.queue.push(notification);
        this.container.appendChild(notification);
        
        // Limit visible notifications
        while (this.container.children.length > this.maxVisible) {
            this.container.firstChild.remove();
        }
        
        // Auto dismiss
        if (duration > 0) {
            setTimeout(() => this.dismiss(notification), duration);
        }
        
        return notification;
    }
    
    create(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = this.getIcon(type);
        const iconEl = document.createElement('i');
        iconEl.className = `bi bi-${icon}`;
        
        const messageEl = document.createElement('span');
        Security.setTextContent(messageEl, message);
        
        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.onclick = () => this.dismiss(notification);
        
        notification.appendChild(iconEl);
        notification.appendChild(messageEl);
        notification.appendChild(closeBtn);
        
        return notification;
    }
    
    dismiss(notification) {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }
    
    getIcon(type) {
        const icons = {
            success: 'check-circle-fill',
            error: 'x-circle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };
        return icons[type] || icons.info;
    }
    
    success(message) {
        return this.show(message, 'success');
    }
    
    error(message) {
        return this.show(message, 'error');
    }
    
    warning(message) {
        return this.show(message, 'warning');
    }
    
    info(message) {
        return this.show(message, 'info');
    }
}

// Create global instance
export const notifications = new NotificationManager();
```

---

### Task 4.4: Refactoring Progress Tracking (Days 15-18)

**Day 15:** Extract SearchManager, AutocompleteManager
**Day 16:** Extract FilterManager, SortManager  
**Day 17:** Cleanup old app.js code, verify all functionality works
**Day 18:** Integration testing, fix regressions

**Checklist for each manager:**
- [ ] Create manager class extending BaseManager
- [ ] Move related functions from app.js
- [ ] Update to use Security utils
- [ ] Add cleanup logic
- [ ] Test independently
- [ ] Integrate with main app
- [ ] Remove old code from app.js

---

## Phase 5: Testing & Quality - Days 19-23 (5 days)

### Objective
Add comprehensive test coverage for critical user flows.

### Task 5.1: Setup Test Environment (Day 19)

**Install dependencies:**
```bash
npm install --save-dev jest @testing-library/dom @testing-library/user-event jsdom
npm install --save-dev @playwright/test
```

**Config:** `jest.config.js`
```javascript
module.exports = {
    testEnvironment: 'jsdom',
    setupFilesAfterEnv: ['<rootDir>/tests/frontend/setup.js'],
    moduleNameMapper: {
        '^@/(.*)$': '<rootDir>/public/js/$1'
    },
    collectCoverageFrom: [
        'public/js/**/*.js',
        '!public/js/app.js' // Exclude legacy code
    ],
    coverageThreshold: {
        global: {
            statements: 70,
            branches: 65,
            functions: 70,
            lines: 70
        }
    }
};
```

---

### Task 5.2: Unit Tests (Days 20-21)

**File:** `tests/frontend/utils/security.test.js`

```javascript
import { Security } from '@/utils/security.js';

describe('Security utilities', () => {
    describe('escapeHtml', () => {
        it('should escape HTML special characters', () => {
            expect(Security.escapeHtml('<script>alert("xss")</script>'))
                .toBe('<script>alert("xss")</script>');
        });
        
        it('should handle null and undefined', () => {
            expect(Security.escapeHtml(null)).toBe('');
            expect(Security.escapeHtml(undefined)).toBe('');
        });
    });
    
    describe('sanitizeHtml', () => {
        it('should remove script tags', () => {
            const dirty = '<p>Safe</p><script>alert("bad")</script>';
            const clean = Security.sanitizeHtml(dirty);
            expect(clean).not.toContain('<script>');
            expect(clean).toContain('<p>Safe</p>');
        });
        
        it('should remove event handlers', () => {
            const dirty = '<div onclick="alert()">Click</div>';
            const clean = Security.sanitizeHtml(dirty);
            expect(clean).not.toContain('onclick');
        });
    });
});
```

**File:** `tests/frontend/managers/MovieManager.test.js`

```javascript
import { MovieManager } from '@/managers/MovieManager.js';
import { http } from '@/services/HttpClient.js';

jest.mock('@/services/HttpClient.js');

describe('MovieManager', () => {
    let container, manager;
    
    beforeEach(() => {
        container = document.createElement('div');
        container.innerHTML = `
            <div class="movie-grid"></div>
            <input id="movie-search" />
        `;
        document.body.appendChild(container);
        
        manager = new MovieManager(container);
    });
    
    afterEach(() => {
        manager.cleanup();
        document.body.removeChild(container);
    });
    
    it('should load and render movies', async () => {
        const mockMovies = [
            { id: 1, title: 'Test Movie', duration: 120, age_rating: 'P' }
        ];
        http.get.mockResolvedValue(mockMovies);
        
        await manager.initialize();
        
        expect(http.get).toHaveBeenCalledWith(expect.stringContaining('/movies'));
        expect(container.querySelectorAll('.movie-card')).toHaveLength(1);
    });
    
    it('should filter movies on search', async () => {
        http.get.mockResolvedValue([]);
        await manager.initialize();
        
        const searchInput = container.querySelector('#movie-search');
        searchInput.value = 'action';
        searchInput.dispatchEvent(new Event('input'));
        
        // Wait for debounce
        await new Promise(resolve => setTimeout(resolve, 350));
        
        expect(http.get).toHaveBeenCalledWith(
            expect.stringContaining('search=action')
        );
    });
});
```

---

### Task 5.3: E2E Tests with Playwright (Days 22-23)

**File:** `tests/e2e/booking-flow.spec.js`

```javascript
import { test, expect } from '@playwright/test';

test.describe('Movie booking flow', () => {
    test('should complete booking from movie selection to payment', async ({ page }) => {
        // Navigate to homepage
        await page.goto('/');
        
        // Select a movie
        await page.click('.movie-card:first-child .btn-primary');
        await expect(page).toHaveURL(/\/movies\/\d+/);
        
        // Select showtime
        await page.click('[data-showtime-id]');
        await expect(page).toHaveURL(/\/booking/);
        
        // Select seats
        await page.click('.seat-available:nth-child(1)');
        await page.click('.seat-available:nth-child(2)');
        await expect(page.locator('.selected-seats')).toContainText('2 ghế');
        
        // Continue to payment
        await page.click('button:has-text("Tiếp tục")');
        await expect(page).toHaveURL(/\/payment/);
        
        // Fill customer info
        await page.fill('[name="name"]', 'Test User');
        await page.fill('[name="email"]', 'test@example.com');
        await page.fill('[name="phone"]', '0123456789');
        
        // Should show order summary
        await expect(page.locator('.order-summary')).toBeVisible();
    });
    
    test('should prevent double booking', async ({ page }) => {
        await page.goto('/booking?showtime_id=1');
        
        // User 1 selects seat
        await page.click('[data-seat-id="A1"]');
        
        // Simulate real-time update (seat taken by another user)
        await page.evaluate(() => {
            window.Echo?.channel('showtime.1')
                .emit('.seat.status.updated', {
                    seat_id: 'A1',
                    status: 'locked'
                });
        });
        
        // Seat should be disabled
        await expect(page.locator('[data-seat-id="A1"]')).toHaveClass(/seat-locked/);
    });
});
```

**Run tests:**
```bash
# Unit tests
npm test

# E2E tests
npx playwright test

# Coverage report
npm test -- --coverage
```

---

## Phase 6: Performance & Accessibility - Days 24-25 (2 days)

### Task 6.1: Performance Optimization (Day 24)

#### 1. Lazy Loading Images

```javascript
// Add to MovieManager
renderMovieCard(movie) {
    // ...
    const poster = document.createElement('img');
    poster.loading = 'lazy'; // Native lazy loading
    poster.decoding = 'async';
    // ...
}
```

#### 2. Virtual Scrolling for Large Lists

**File:** `public/js/utils/virtual-scroll.js`

```javascript
export class VirtualScroll {
    constructor(container, items, renderItem, itemHeight = 100) {
        this.container = container;
        this.items = items;
        this.renderItem = renderItem;
        this.itemHeight = itemHeight;
        this.visibleRange = { start: 0, end: 0 };
        
        this.initialize();
    }
    
    initialize() {
        this.calculateVisibleRange();
        this.render();
        
        this.container.addEventListener('scroll', () => {
            this.calculateVisibleRange();
            this.render();
        });
    }
    
    calculateVisibleRange() {
        const scrollTop = this.container.scrollTop;
        const containerHeight = this.container.clientHeight;
        
        this.visibleRange = {
            start: Math.floor(scrollTop / this.itemHeight),
            end: Math.ceil((scrollTop + containerHeight) / this.itemHeight)
        };
    }
    
    render() {
        const fragment = document.createDocumentFragment();
        const visibleItems = this.items.slice(
            this.visibleRange.start,
            this.visibleRange.end
        );
        
        visibleItems.forEach(item => {
            fragment.appendChild(this.renderItem(item));
        });
        
        this.container.innerHTML = '';
        this.container.appendChild(fragment);
    }
}
```

#### 3. Debounce & Throttle

```javascript
// Already implemented in managers, but ensure usage:
// - Search: debounce 300ms
// - Scroll: throttle 100ms
// - Resize: debounce 200ms
```

---

### Task 6.2: Accessibility Improvements (Day 25)

#### 1. ARIA Labels

```javascript
// Add to all interactive elements
button.setAttribute('aria-label', 'Đóng thông báo');
input.setAttribute('aria-describedby', 'search-help');
```

#### 2. Keyboard Navigation

```javascript
// MovieManager - add keyboard support
setupKeyboardNavigation() {
    const cards = this.container.querySelectorAll('.movie-card');
    cards.forEach((card, index) => {
        card.setAttribute('tabindex', '0');
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                card.querySelector('a').click();
            }
        });
    });
}
```

#### 3. Focus Management

```javascript
// After modal opens
export function openModal(modal) {
    modal.style.display = 'block';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    
    // Trap focus
    const focusableElements = modal.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    
    firstElement?.focus();
    
    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
        
        if (e.key === 'Escape') {
            closeModal(modal);
        }
    });
}
```

---

## Success Metrics

### Phase 4 Completion:
- [ ] app.js reduced from 1850 to <300 lines
- [ ] All features work identically
- [ ] No console errors
- [ ] Memory leaks fixed (no timers/listeners remain)

### Phase 5 Completion:
- [ ] Unit test coverage >70%
- [ ] E2E tests cover critical flows
- [ ] All tests passing

### Phase 6 Completion:
- [ ] Lighthouse Performance score >85
- [ ] Lighthouse Accessibility score >95
- [ ] No accessibility violations in axe-core

---

## Rollback Strategy

If issues arise during refactoring:

1. **Per-manager rollback:** Each manager is independent, can be reverted individually
2. **Feature flags:** Use flags to toggle new/old code
3. **Git branches:** Work on `refactor/frontend` branch, merge only when stable
4. **Gradual rollout:** Enable new code for 10% users first

**Emergency rollback code:**

```javascript
// In app.js
const USE_NEW_ARCHITECTURE = window.localStorage.getItem('new_arch') === 'true';

if (USE_NEW_ARCHITECTURE) {
    // New managers
} else {
    // Old monolithic code
}
```

---

## Timeline Summary

| Week | Days | Focus |
|------|------|-------|
| Week 1 | 1-3 | Security fixes (P0) |
| Week 1 | 4-5 | Error handling (P0) |
| Week 2 | 6-10 | Architecture foundation |
| Week 3 | 11-15 | app.js refactoring (part 1) |
| Week 4 | 16-18 | app.js refactoring (part 2) |
| Week 4-5 | 19-23 | Testing & quality |
| Week 5 | 24-25 | Performance & accessibility |

**Total: 25 working days (5 weeks)**

---

## Next Steps

1. Review and approve this plan
2. Create feature branch: `refactor/frontend-phases-4-6`
3. Start Phase 4 Day 11
4. Daily standups to track progress
5. Deploy to staging after each phase
