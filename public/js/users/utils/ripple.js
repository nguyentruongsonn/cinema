/**
 * Ripple Effect Utility
 * Cinema Booking System - User Interface
 *
 * Provides Material Design-style ripple effect for buttons
 * Usage: Add 'btn-ripple' class to any button element
 */

class RippleEffect {
    constructor() {
        this.rippleButtons = [];
        this.init();
    }

    /**
     * Initialize ripple effect on all .btn-ripple buttons
     */
    init() {
        // Find all ripple buttons
        this.rippleButtons = document.querySelectorAll('.btn-ripple');

        // Add click listeners
        this.rippleButtons.forEach(button => {
            button.addEventListener('click', (e) => this.createRipple(e));
        });

        // Watch for dynamically added buttons
        this.observeDOM();
    }

    /**
     * Create ripple effect at click position
     * @param {MouseEvent} event - Click event
     */
    createRipple(event) {
        const button = event.currentTarget;

        // Don't create ripple if button is disabled
        if (button.disabled || button.classList.contains('disabled')) {
            return;
        }

        // Remove any existing ripples
        const existingRipple = button.querySelector('.ripple-effect');
        if (existingRipple) {
            existingRipple.remove();
        }

        // Create ripple element
        const ripple = document.createElement('span');
        ripple.classList.add('ripple-effect');

        // Calculate ripple size (should cover entire button)
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        // Position ripple at click coordinates
        const rect = button.getBoundingClientRect();
        const x = event.clientX - rect.left - radius;
        const y = event.clientY - rect.top - radius;

        ripple.style.width = ripple.style.height = `${diameter}px`;
        ripple.style.left = `${x}px`;
        ripple.style.top = `${y}px`;

        // Add ripple to button
        button.appendChild(ripple);

        // Remove ripple after animation completes
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    /**
     * Observe DOM for dynamically added ripple buttons
     */
    observeDOM() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        // Check if added node is a ripple button
                        if (node.classList && node.classList.contains('btn-ripple')) {
                            node.addEventListener('click', (e) => this.createRipple(e));
                        }

                        // Check if added node contains ripple buttons
                        const rippleButtons = node.querySelectorAll && node.querySelectorAll('.btn-ripple');
                        if (rippleButtons && rippleButtons.length > 0) {
                            rippleButtons.forEach(button => {
                                button.addEventListener('click', (e) => this.createRipple(e));
                            });
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Manually trigger ripple effect on an element
     * @param {HTMLElement} element - Button element
     * @param {Object} options - Position options {x, y}
     */
    static trigger(element, options = {}) {
        if (!element) return;

        const ripple = document.createElement('span');
        ripple.classList.add('ripple-effect');

        const diameter = Math.max(element.clientWidth, element.clientHeight);
        const radius = diameter / 2;

        // Use provided coordinates or center of button
        const rect = element.getBoundingClientRect();
        const x = options.x !== undefined
            ? options.x - rect.left - radius
            : element.clientWidth / 2 - radius;
        const y = options.y !== undefined
            ? options.y - rect.top - radius
            : element.clientHeight / 2 - radius;

        ripple.style.width = ripple.style.height = `${diameter}px`;
        ripple.style.left = `${x}px`;
        ripple.style.top = `${y}px`;

        element.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    /**
     * Add ripple effect to specific element
     * @param {HTMLElement|String} element - Element or selector
     */
    static addTo(element) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (el) {
            el.classList.add('btn-ripple');
            el.addEventListener('click', (e) => {
                const instance = new RippleEffect();
                instance.createRipple(e);
            });
        }
    }

    /**
     * Remove ripple effect from specific element
     * @param {HTMLElement|String} element - Element or selector
     */
    static removeFrom(element) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (el) {
            el.classList.remove('btn-ripple');
        }
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.RippleEffect = new RippleEffect();
    });
} else {
    window.RippleEffect = new RippleEffect();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RippleEffect;
}
