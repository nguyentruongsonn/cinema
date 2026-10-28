(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('resetPasswordFormElement');
        const submitButton = document.getElementById('resetPasswordSubmitBtn');
        const alert = document.getElementById('resetPasswordAlert');

        document.querySelectorAll('[data-reset-password-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.querySelector(button.dataset.resetPasswordToggle);
                const icon = button.querySelector('i');
                if (!input || !icon) return;

                const shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';
                icon.className = shouldShow ? 'bi bi-eye-slash' : 'bi bi-eye';
                button.setAttribute('aria-label', shouldShow ? 'Ẩn mật khẩu' : 'Hiển thị mật khẩu');
                button.setAttribute('aria-pressed', String(shouldShow));
            });
        });

        form?.addEventListener('submit', async event => {
            event.preventDefault();
            const formData = new FormData(form);
            const password = String(formData.get('password') || '');
            const passwordConfirmation = String(formData.get('password_confirmation') || '');

            alert.className = 'alert d-none mt-3';
            if (password.length < 8 || !/[A-Za-z]/.test(password) || !/\d/.test(password)) {
                showAlert('Mật khẩu phải có ít nhất 8 ký tự, gồm chữ và số.', 'danger');
                return;
            }
            if (password !== passwordConfirmation) {
                showAlert('Mật khẩu xác nhận không khớp.', 'danger');
                return;
            }

            setLoading(true);
            try {
                const response = await window.apiClient.post('/auth/reset-password', {
                    token: formData.get('token'),
                    email: formData.get('email'),
                    password,
                    password_confirmation: passwordConfirmation,
                });

                if (!response?.success) throw new Error(response?.message || 'Không thể đặt lại mật khẩu.');
                showAlert('Mật khẩu đã được cập nhật. Bạn có thể đăng nhập bằng mật khẩu mới.', 'success');
                form.reset();
                setTimeout(() => window.location.assign('/'), 1600);
            } catch (error) {
                showAlert(error?.message || 'Liên kết không hợp lệ hoặc đã hết hạn.', 'danger');
            } finally {
                setLoading(false);
            }
        });

        function setLoading(loading) {
            submitButton.disabled = loading;
            submitButton.querySelector('.spinner-border')?.classList.toggle('d-none', !loading);
            const text = submitButton.querySelector('.btn-text');
            if (text) text.textContent = loading ? 'Đang cập nhật...' : 'Cập nhật mật khẩu';
        }

        function showAlert(message, type) {
            alert.textContent = message;
            alert.className = `alert alert-${type} mt-3`;
        }
    });
})();
