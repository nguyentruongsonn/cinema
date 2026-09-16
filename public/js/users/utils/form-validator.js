/**
 * Form Validation Utility
 * Cinema Booking System - User Interface
 *
 * Provides real-time form validation with clear feedback
 */

class FormValidator {
    constructor(form, options = {}) {
        this.form = form;
        this.options = {
            validateOnBlur: options.validateOnBlur !== false,
            validateOnInput: options.validateOnInput || false,
            showSuccessState: options.showSuccessState || false,
            debounceDelay: options.debounceDelay || 300,
            ...options
        };
        this.errors = {};
        this.rules = {};
        this.debounceTimers = {};

        this.init();
    }

    init() {
        if (this.options.validateOnBlur) {
            this.form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('blur', () => this.validateField(field));
            });
        }

        if (this.options.validateOnInput) {
            this.form.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', () => {
                    this.debouncedValidate(field);
                });
            });
        }

        this.form.addEventListener('submit', (e) => {
            if (!this.validateAll()) {
                e.preventDefault();
            }
        });
    }

    debouncedValidate(field) {
        const fieldName = field.name;
        clearTimeout(this.debounceTimers[fieldName]);

        this.debounceTimers[fieldName] = setTimeout(() => {
            this.validateField(field);
        }, this.options.debounceDelay);
    }

    addRule(fieldName, rules) {
        this.rules[fieldName] = rules;
        return this;
    }

    validateField(field) {
        const fieldName = field.name;
        const value = field.value.trim();
        const rules = this.rules[fieldName];

        if (!rules) return true;

        // Clear previous error
        this.clearFieldError(field);

        // Validate each rule
        for (const rule of rules) {
            const result = this.applyRule(value, rule, field);

            if (!result.valid) {
                this.showFieldError(field, result.message);
                this.errors[fieldName] = result.message;
                return false;
            }
        }

        // Show success state if enabled
        if (this.options.showSuccessState && value) {
            this.showFieldSuccess(field);
        }

        delete this.errors[fieldName];
        return true;
    }

    applyRule(value, rule, field) {
        // Required rule
        if (rule.type === 'required' && !value) {
            return {
                valid: false,
                message: rule.message || 'Trường này là bắt buộc'
            };
        }

        // Email rule
        if (rule.type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                return {
                    valid: false,
                    message: rule.message || 'Email không đúng định dạng'
                };
            }
        }

        // Phone rule
        if (rule.type === 'phone') {
            const phoneRegex = /^(0|\+84)[0-9]{9}$/;
            if (!phoneRegex.test(value.replace(/[\s-]/g, ''))) {
                return {
                    valid: false,
                    message: rule.message || 'Số điện thoại không hợp lệ'
                };
            }
        }

        // Min length rule
        if (rule.type === 'minLength') {
            if (value.length < rule.value) {
                return {
                    valid: false,
                    message: rule.message || `Tối thiểu ${rule.value} ký tự`
                };
            }
        }

        // Max length rule
        if (rule.type === 'maxLength') {
            if (value.length > rule.value) {
                return {
                    valid: false,
                    message: rule.message || `Tối đa ${rule.value} ký tự`
                };
            }
        }

        // Pattern rule
        if (rule.type === 'pattern') {
            if (!rule.value.test(value)) {
                return {
                    valid: false,
                    message: rule.message || 'Định dạng không hợp lệ'
                };
            }
        }

        // Match rule (for password confirmation)
        if (rule.type === 'match') {
            const matchField = this.form.querySelector(`[name="${rule.value}"]`);
            if (matchField && value !== matchField.value) {
                return {
                    valid: false,
                    message: rule.message || 'Giá trị không khớp'
                };
            }
        }

        // Custom rule
        if (rule.type === 'custom' && typeof rule.validator === 'function') {
            const result = rule.validator(value, field);
            if (!result.valid) {
                return result;
            }
        }

        return { valid: true };
    }

    validateAll() {
        let isValid = true;
        const fields = this.form.querySelectorAll('input, select, textarea');

        fields.forEach(field => {
            if (this.rules[field.name]) {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('error');
        field.classList.remove('success');

        let errorEl = field.parentElement.querySelector('.error-message');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'error-message';
            field.parentElement.appendChild(errorEl);
        }

        errorEl.textContent = message;
        errorEl.classList.add('show');
    }

    showFieldSuccess(field) {
        field.classList.add('success');
        field.classList.remove('error');
        this.clearFieldError(field);
    }

    clearFieldError(field) {
        field.classList.remove('error', 'success');
        const errorEl = field.parentElement.querySelector('.error-message');
        if (errorEl) {
            errorEl.classList.remove('show');
            errorEl.textContent = '';
        }
    }

    clearAll() {
        this.errors = {};
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            this.clearFieldError(field);
        });
    }

    isValid() {
        return Object.keys(this.errors).length === 0;
    }

    getErrors() {
        return this.errors;
    }
}

// Export
if (typeof window !== 'undefined') {
    window.FormValidator = FormValidator;
}

// Common validation rules presets
FormValidator.presets = {
    email: [
        { type: 'required', message: 'Vui lòng nhập email' },
        { type: 'email', message: 'Email không đúng định dạng' }
    ],

    password: [
        { type: 'required', message: 'Vui lòng nhập mật khẩu' },
        { type: 'minLength', value: 6, message: 'Mật khẩu tối thiểu 6 ký tự' }
    ],

    phone: [
        { type: 'required', message: 'Vui lòng nhập số điện thoại' },
        { type: 'phone', message: 'Số điện thoại không hợp lệ' }
    ],

    fullName: [
        { type: 'required', message: 'Vui lòng nhập họ tên' },
        { type: 'minLength', value: 2, message: 'Tên tối thiểu 2 ký tự' }
    ]
};
