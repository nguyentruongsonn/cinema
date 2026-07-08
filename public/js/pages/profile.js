/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PROFILE PAGE MODULE
 * Handles user profile management, tickets, and account settings
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';

    let userData = null;
    let currentSection = 'profile';
    let originalFormData = {};

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function init() {
        try {
            await loadUserProfile();
            renderProfile();
            setupEventListeners();
            hideLoading();
        } catch (error) {
            console.error('Failed to initialize profile page:', error);
        }
    }

    async function loadUserProfile() {
        try {
            const response = await fetch('/api/v1/profile');
            if (!response.ok) throw new Error('Failed to load profile');

            const data = await response.json();
            userData = data.data || data.user || data;
        } catch (error) {
            console.error('Error loading profile:', error);
            throw error;
        }
    }

    function renderProfile() {
        if (!userData) return;

        // Display name and avatar
        const displayName = document.getElementById('profileDisplayName');
        if (displayName) {
            displayName.textContent = userData.name || 'Người dùng';
        }

        // Avatar
        const avatar = document.getElementById('profileAvatar');
        const avatarFallback = document.getElementById('profileAvatarFallback');
        if (userData.avatar_url) {
            avatar.src = userData.avatar_url;
            avatar.classList.remove('d-none');
            if (avatarFallback) avatarFallback.style.display = 'none';
        } else if (avatarFallback) {
            avatarFallback.textContent = userData.name ? userData.name[0].toUpperCase() : 'U';
        }

        // Fill form fields
        const formFields = {
            profileName: userData.name || '',
            profileEmail: userData.email || '',
            profilePhone: userData.phone || '',
            profileBirthday: userData.birthday || '',
            profileGender: userData.gender || '',
            profileAddress: userData.address || ''
        };

        Object.keys(formFields).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = formFields[fieldId];
                originalFormData[fieldId] = formFields[fieldId];
            }
        });

        // Loyalty points
        if (userData.loyalty_points !== undefined) {
            const xpValue = document.getElementById('profileXpValue');
            if (xpValue) {
                xpValue.textContent = `${userData.loyalty_points} điểm`;
            }
        }
    }

    function setupEventListeners() {
        // Navigation
        document.querySelectorAll('[data-profile-nav]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const section = e.currentTarget.dataset.profileNav;
                switchSection(section);
            });
        });

        // Field editing
        document.querySelectorAll('[data-edit-field]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const fieldId = e.currentTarget.dataset.editField;
                toggleFieldEdit(fieldId);
            });
        });

        // Profile update form
        const updateForm = document.getElementById('profileUpdateForm');
        if (updateForm) {
            updateForm.addEventListener('submit', handleProfileUpdate);
        }

        // Reset button
        const resetBtn = document.getElementById('profileResetBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetFormData);
        }

        // Password form
        const passwordForm = document.getElementById('profilePasswordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', handlePasswordChange);
        }

        // Logout
        const logoutBtn = document.getElementById('profileLogoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }

        // Track form changes
        const form = document.getElementById('profileUpdateForm');
        if (form) {
            form.addEventListener('input', handleFormChange);
        }
    }

    function switchSection(section) {
        currentSection = section;

        // Update nav buttons
        document.querySelectorAll('[data-profile-nav]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.profileNav === section);
        });

        // Show/hide sections
        const sections = {
            profile: document.getElementById('profileSection'),
            tickets: document.getElementById('ticketsSection'),
            points: document.getElementById('profileXpCard'),
            voucher: document.getElementById('voucherSection')
        };

        Object.keys(sections).forEach(key => {
            const el = sections[key];
            if (el) {
                if (key === section) {
                    el.classList.remove('d-none');
                    if (key === 'tickets') {
                        loadTickets();
                    }
                } else {
                    el.classList.add('d-none');
                }
            }
        });
    }

    function toggleFieldEdit(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        const isDisabled = field.disabled;
        field.disabled = !isDisabled;

        if (!isDisabled) {
            field.focus();
        }
    }

    function handleFormChange() {
        const updateBtn = document.getElementById('profileUpdateBtn');
        const resetBtn = document.getElementById('profileResetBtn');

        let hasChanges = false;
        Object.keys(originalFormData).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && field.value !== originalFormData[fieldId]) {
                hasChanges = true;
            }
        });

        if (updateBtn) updateBtn.disabled = !hasChanges;
        if (resetBtn) resetBtn.disabled = !hasChanges;
    }

    function resetFormData() {
        Object.keys(originalFormData).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = originalFormData[fieldId];
            }
        });
        handleFormChange();
    }

    async function handleProfileUpdate(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = document.getElementById('profileUpdateBtn');
        const spinner = submitBtn.querySelector('.spinner-border');

        try {
            submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const response = await fetch('/api/v1/profile', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Cập nhật thất bại');
            }

            // Update original data
            Object.keys(originalFormData).forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    originalFormData[fieldId] = field.value;
                }
            });

            showAlert('profileUpdateAlert', 'Cập nhật thông tin thành công!', 'success');
            handleFormChange();

        } catch (error) {
            console.error('Update error:', error);
            showAlert('profileUpdateAlert', error.message || 'Có lỗi xảy ra', 'danger');
        } finally {
            submitBtn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
        }
    }

    async function handlePasswordChange(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = document.getElementById('profilePasswordBtn');
        const spinner = submitBtn.querySelector('.spinner-border');

        try {
            submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const response = await fetch('/api/v1/profile/password', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Đổi mật khẩu thất bại');
            }

            showAlert('profilePasswordAlert', 'Đổi mật khẩu thành công!', 'success');
            form.reset();

        } catch (error) {
            console.error('Password change error:', error);
            showAlert('profilePasswordAlert', error.message || 'Có lỗi xảy ra', 'danger');
        } finally {
            submitBtn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
        }
    }

    async function loadTickets() {
        const ticketsGrid = document.getElementById('ticketsGrid');
        const ticketsEmpty = document.getElementById('ticketsEmpty');
        const ticketsLoading = document.getElementById('ticketsLoading');

        try {
            if (ticketsLoading) ticketsLoading.classList.remove('d-none');

            const response = await fetch('/api/v1/tickets');
            if (!response.ok) throw new Error('Failed to load tickets');

            const data = await response.json();
            const tickets = data.data || data.tickets || [];

            if (tickets.length === 0) {
                if (ticketsGrid) ticketsGrid.innerHTML = '';
                if (ticketsEmpty) ticketsEmpty.classList.remove('d-none');
            } else {
                if (ticketsEmpty) ticketsEmpty.classList.add('d-none');
                if (ticketsGrid) {
                    ticketsGrid.innerHTML = tickets.map(ticket => renderTicketCard(ticket)).join('');
                }
            }

        } catch (error) {
            console.error('Error loading tickets:', error);
        } finally {
            if (ticketsLoading) ticketsLoading.classList.add('d-none');
        }
    }

    function renderTicketCard(ticket) {
        return `
            <div class="ticket-card">
                <div class="ticket-movie-info">
                    <h3>${escapeHtml(ticket.movie_title || 'Movie')}</h3>
                    <p>${escapeHtml(ticket.showtime_date || '')} - ${escapeHtml(ticket.showtime_time || '')}</p>
                </div>
                <div class="ticket-seats">
                    <span>Ghế: ${escapeHtml(ticket.seat_labels || '')}</span>
                </div>
                <div class="ticket-status">
                    <span class="badge ${ticket.status === 'used' ? 'bg-secondary' : 'bg-success'}">
                        ${ticket.status === 'used' ? 'Đã xem' : 'Còn hạn'}
                    </span>
                </div>
            </div>
        `;
    }

    async function handleLogout() {
        if (!confirm('Bạn có chắc chắn muốn đăng xuất?')) {
            return;
        }

        try {
            const response = await fetch('/api/v1/logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });

            if (response.ok) {
                window.location.href = '/';
            }
        } catch (error) {
            console.error('Logout error:', error);
            alert('Có lỗi xảy ra khi đăng xuất');
        }
    }

    function showAlert(alertId, message, type) {
        const alert = document.getElementById(alertId);
        if (!alert) return;

        alert.className = `alert alert-${type} profile-alert`;
        alert.textContent = message;
        alert.classList.remove('d-none');

        setTimeout(() => {
            alert.classList.add('d-none');
        }, 5000);
    }

    function hideLoading() {
        const loading = document.getElementById('profileLoading');
        const content = document.getElementById('profileContent');

        if (loading) loading.style.display = 'none';
        if (content) content.classList.remove('d-none');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Expose for debugging
    window.profilePage = {
        userData: () => userData,
        reload: init
    };
})();
