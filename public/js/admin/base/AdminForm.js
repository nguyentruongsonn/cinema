/**
 * ═══════════════════════════════════════════════════════════════════════════
 * AdminForm - Reusable Form Component
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Handles common form operations:
 * - Form validation
 * - Data collection
 * - Submission handling
 * - Error display
 * - Reset & clear
 * 
 * @version 1.0.0
 */

class AdminForm {
    constructor(page, config = {}) {
        this.page = page;
        this.config = {
            formSelector: 'form',
            submitButton: '[type="submit"]',
            resetButton: '[type="reset"]',
            validate: true,
            ...config
        };

        this.state = {
            isSubmitting: false,
            errors: {}
        };

        this.elements = {};
        this.validators = new Map();
        this.init();
    }

    /**
     * Initialize form
     */
    init() {
        this.cacheElements();
        this.attachEventListeners();
        this.setupValidation();
    }

    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.elements.form = document.querySelector(this.config.formSelector);
        this.elements.submitButton = this.elements.form?.querySelector(this.config.submitButton);
        this.elements.resetButton = this.elements.form?.querySelector(this.config.resetButton);
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        if (!this.elements.form) return;

        this.elements.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });

        if (this.elements.resetButton) {
            this.elements.resetButton.addEventListener('click', () => {
                this.reset();
            });
        }

        // Real-time validation
        if (this.config.validate) {
            this.elements.form.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('blur', () => this.validateField(field));
                field.addEventListener('input', () => this.clearFieldError(field));
            });
        }
    }

    /**
     * Setup validation rules
     * Override in subclass to add custom validators
     */
    setupValidation() {
        // Built-in validators
        this.addValidator('required', (value) => {
            return value !== null && value !== undefined && value.toString().trim() !== '';
        }, 'This field is required');

        this.addValidator('email', (value) => {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }, 'Please enter a valid email');

        this.addValidator('min', (value, min) => {
            return value.length >= parseInt(min);
        }, 'Minimum length is {param}');

        this.addValidator('max', (value, max) => {
            return value.length <= parseInt(max);
        }, 'Maximum length is {param}');

        this.addValidator('number', (value) => {
            return !isNaN(parseFloat(value)) && isFinite(value);
        }, 'Please enter a valid number');
    }

    /**
     * Add custom validator
     */
    addValidator(name, validator, message) {
        this.validators.set(name, { validator, message });
    }

    /**
     * Validate single field
     */
    validateField(field) {
        const rules = field.dataset.validate?.split('|') || [];
        
        for (const rule of rules) {
            const [name, param] = rule.split(':');
            const validatorData = this.validators.get(name);
            
            if (validatorData) {
                const { validator, message } = validatorData;
                const isValid = validator(field.value, param);
                
                if (!isValid) {
                    const errorMessage = message.replace('{param}', param);
                    this.showFieldError(field, errorMessage);
                    return false;
                }
            }
        }

        this.clearFieldError(field);
        return true;
    }

    /**
     * Validate entire form
     */
    validate() {
        this.state.errors = {};
        let isValid = true;

        this.elements.form.querySelectorAll('[data-validate]').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Show field error
     */
    showFieldError(field, message) {
        this.state.errors[field.name] = message;
        
        // Add error class
        const formGroup = field.closest('.admin-form-group');
        if (formGroup) {
            formGroup.classList.add('has-error');
            
            // Show error message
            let errorEl = formGroup.querySelector('.admin-form-error');
            if (!errorEl) {
                errorEl = document.createElement('div');
                errorEl.className = 'admin-form-error';
                formGroup.appendChild(errorEl);
            }
            errorEl.textContent = message;
        }
    }

    /**
     * Clear field error
     */
    clearFieldError(field) {
        delete this.state.errors[field.name];
        
        const formGroup = field.closest('.admin-form-group');
        if (formGroup) {
            formGroup.classList.remove('has-error');
            const errorEl = formGroup.querySelector('.admin-form-error');
            if (errorEl) {
                errorEl.remove();
            }
        }
    }

    /**
     * Clear all errors
     */
    clearErrors() {
        this.state.errors = {};
        this.elements.form.querySelectorAll('.admin-form-group.has-error').forEach(group => {
            group.classList.remove('has-error');
            const errorEl = group.querySelector('.admin-form-error');
            if (errorEl) {
                errorEl.remove();
            }
        });
    }

    /**
     * Get form data
     */
    getData() {
        const formData = new FormData(this.elements.form);
        const data = {};

        for (const [key, value] of formData.entries()) {
            // Handle multiple values (checkboxes, multi-select)
            if (data[key]) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }

        return data;
    }

    /**
     * Set form data
     */
    setData(data) {
        Object.entries(data).forEach(([key, value]) => {
            const field = this.elements.form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = Boolean(value);
                } else if (field.type === 'radio') {
                    const radio = this.elements.form.querySelector(`[name="${key}"][value="${value}"]`);
                    if (radio) radio.checked = true;
                } else {
                    field.value = value;
                }
            }
        });
    }

    /**
     * Handle form submission
     */
    async handleSubmit() {
        if (this.state.isSubmitting) return;

        // Validate
        if (this.config.validate && !this.validate()) {
            this.page.showToast('Please fix form errors', 'error');
            return;
        }

        this.state.isSubmitting = true;
        this.setSubmitting(true);

        try {
            const data = this.getData();
            await this.onSubmit(data);
        } catch (error) {
            this.page.handleError('Form submission failed', error);
        } finally {
            this.state.isSubmitting = false;
            this.setSubmitting(false);
        }
    }

    /**
     * Set submitting state
     */
    setSubmitting(isSubmitting) {
        if (this.elements.submitButton) {
            this.elements.submitButton.disabled = isSubmitting;
            
            const originalText = this.elements.submitButton.dataset.originalText || 
                               this.elements.submitButton.textContent;
            
            if (isSubmitting) {
                this.elements.submitButton.dataset.originalText = originalText;
                this.elements.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            } else {
                this.elements.submitButton.textContent = originalText;
            }
        }

        this.elements.form.classList.toggle('is-submitting', isSubmitting);
    }

    /**
     * Reset form
     */
    reset() {
        this.elements.form.reset();
        this.clearErrors();
        this.onReset();
    }

    /**
     * Disable form
     */
    disable() {
        this.elements.form.querySelectorAll('input, textarea, select, button').forEach(el => {
            el.disabled = true;
        });
    }

    /**
     * Enable form
     */
    enable() {
        this.elements.form.querySelectorAll('input, textarea, select, button').forEach(el => {
            el.disabled = false;
        });
    }

    // Event callbacks - override in implementation
    async onSubmit(data) {}
    onReset() {}
}

export default AdminForm;