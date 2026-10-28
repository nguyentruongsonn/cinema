class RippleEffect {
    constructor() {
        this.rippleButtons = [];
        this.init();
    }

    init() {
        this.rippleButtons = document.querySelectorAll('.btn-ripple');

        this.rippleButtons.forEach(button => {
            button.addEventListener('click', (e) => this.createRipple(e));
        });

        this.observeDOM();
    }

    createRipple(event) {
        const button = event.currentTarget;

        if (button.disabled || button.classList.contains('disabled')) {
            return;
        }

        const existingRipple = button.querySelector('.ripple-effect');
        if (existingRipple) {
            existingRipple.remove();
        }

        const ripple = document.createElement('span');
        ripple.classList.add('ripple-effect');

        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        const rect = button.getBoundingClientRect();
        const x = event.clientX - rect.left - radius;
        const y = event.clientY - rect.top - radius;

        ripple.style.width = ripple.style.height = `${diameter}px`;
        ripple.style.left = `${x}px`;
        ripple.style.top = `${y}px`;

        button.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    observeDOM() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        if (node.classList && node.classList.contains('btn-ripple')) {
                            node.addEventListener('click', (e) => this.createRipple(e));
                        }

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

    static trigger(element, options = {}) {
        if (!element) return;

        const ripple = document.createElement('span');
        ripple.classList.add('ripple-effect');

        const diameter = Math.max(element.clientWidth, element.clientHeight);
        const radius = diameter / 2;

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

    static removeFrom(element) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (el) {
            el.classList.remove('btn-ripple');
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.RippleEffect = new RippleEffect();
    });
} else {
    window.RippleEffect = new RippleEffect();
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = RippleEffect;
}
