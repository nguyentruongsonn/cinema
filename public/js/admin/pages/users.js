/**
 * User Management - users.js
 * Admin user account management with roles, filters, and password reset
 */
(function () {
    'use strict';

    const els = {
        tableBody: document.getElementById('usersTableBody'),
        pagination: document.getElementById('paginationContainer'),
        
        searchForm: document.getElementById('searchForm'),
        searchInput: document.getElementById('search'),
        roleFilter: document.getElementById('roleFilter'),
        statusFilter: document.getElementById('statusFilter'),
        verifiedFilter: document.getElementById('verifiedFilter'),

        btnCreate: document.getElementById('btnCreateUser'),
        modalEl: document.getElementById('userModal'),
        form: document.getElementById('userForm'),
        modalLabel: document.getElementById('userModalLabel'),
        
        formMethod: document.getElementById('formMethod'),
        idInput: document.getElementById('userIdInput'),
        
        name: document.getElementById('userName'),
        email: document.getElementById('userEmail'),
        username: document.getElementById('userUsername'),
        phone: document.getElementById('userPhone'),
        password: document.getElementById('userPassword'),
        passwordRequired: document.getElementById('passwordRequired'),
        birthday: document.getElementById('userBirthday'),
        gender: document.getElementById('userGender'),
        loyaltyPoints: document.getElementById('userLoyaltyPoints'),
        address: document.getElementById('userAddress'),
        roles: document.getElementById('userRoles'),
        status: document.getElementById('userStatus'),

        // Reset password modal
        resetPasswordModal: document.getElementById('resetPasswordModal'),
        resetPasswordForm: document.getElementById('resetPasswordForm'),
        resetUserId: document.getElementById('resetUserId'),
        resetUserName: document.getElementById('resetUserName'),
        newPassword: document.getElementById('newPassword'),
        newPasswordConfirmation: document.getElementById('newPasswordConfirmation'),
    };

    let currentPage = 1;
    let currentSearch = '';
    let currentRole = '';
    let currentStatus = '';
    let currentVerified = '';
    let availableRoles = [];

    function getModalInstance(modalEl) {
        if (!modalEl) return null;
        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    /* ── Load Roles for Dropdown ────────────────────────────────────── */
    async function loadRoles() {
        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/users/roles');
            if (res && res.ok) {
                const data = await res.json();
                availableRoles = data.data || [];
                
                // Populate role filter
                els.roleFilter.innerHTML = '<option value="">Tất cả vai trò</option>';
                availableRoles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.slug;
                    option.textContent = role.name;
                    els.roleFilter.appendChild(option);
                });

                // Populate role select in form
                els.roles.innerHTML = '';
                availableRoles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.name;
                    els.roles.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading roles:', error);
        }
    }

    /* ── Fetch & Render ────────────────────────────────────────────── */
    async function loadData(page = 1) {
        try {
            // Skeleton loading is now handled in HTML blade template
            // els.tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div></td></tr>`;
            
            const url = new URL(window.location.origin + '/api/v1/admin/users');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentRole) url.searchParams.append('role', currentRole);
            if (currentStatus !== '') url.searchParams.append('status', currentStatus);
            if (currentVerified !== '') url.searchParams.append('verified', currentVerified);

            const res = await window.AdminCore.apiFetch(url.toString());
            if (res && res.ok) {
                const response = await res.json();
                renderTable(response.data, response.pagination.from);
                renderPagination(response.pagination);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(users, startIndex) {
        if (!users || users.length === 0) {
            els.tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>Không tìm thấy người dùng nào.</td></tr>`;
            return;
        }

        els.tableBody.innerHTML = '';
        users.forEach((user, index) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            
            const dCreated = new Date(user.created_at);
            const roleNames = user.roles?.map(r => r.name).join(', ') || '<span class="text-muted">Chưa có</span>';
            const isVerified = user.email_verified_at !== null;
            
            tr.innerHTML = `
                <td class="text-center text-white-50">${(startIndex || 1) + index}</td>
                <td class="fw-medium text-white">${user.name || '<span class="text-muted">N/A</span>'}</td>
                <td class="text-white-50">${user.email}</td>
                <td class="text-white-50">${user.phone || '<span class="text-muted">-</span>'}</td>
                <td class="text-white-50">${roleNames}</td>
                <td class="text-center">
                    <div class="form-check form-switch mb-0 d-flex justify-content-center">
                        <input class="form-check-input toggle-status-btn m-0" type="checkbox" role="switch"
                            data-id="${user.id}" ${user.status ? 'checked' : ''} style="cursor:pointer;">
                    </div>
                </td>
                <td class="text-center">
                    ${isVerified 
                        ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Đã xác thực</span>' 
                        : '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> Chưa xác thực</span>'
                    }
                </td>
                <td class="text-white-50">${dCreated.toLocaleDateString('vi-VN')}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary edit-btn me-1" data-id="${user.id}" title="Sửa">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning reset-password-btn me-1" data-id="${user.id}" data-name="${user.name}" title="Đặt lại mật khẩu">
                        <i class="bi bi-key"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${user.id}" title="Xóa">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            els.tableBody.appendChild(tr);
        });

        // Attach event listeners
        document.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('change', handleToggleStatus);
        });
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', handleEdit);
        });
        document.querySelectorAll('.reset-password-btn').forEach(btn => {
            btn.addEventListener('click', handleResetPasswordClick);
        });
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', handleDelete);
        });
    }

    function renderPagination(pagination) {
        if (!pagination || pagination.last_page <= 1) {
            els.pagination.innerHTML = '';
            return;
        }

        let html = '<nav><ul class="pagination pagination-sm mb-0">';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current_page - 1}">«</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">«</span></li>`;
        }

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.last_page, startPage + maxVisible - 1);
        
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            if (i === pagination.current_page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
        }

        if (endPage < pagination.last_page) {
            if (endPage < pagination.last_page - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.last_page}">${pagination.last_page}</a></li>`;
        }

        // Next button
        if (pagination.current_page < pagination.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current_page + 1}">»</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">»</span></li>`;
        }

        html += '</ul></nav>';
        els.pagination.innerHTML = html;

        // Attach click events
        els.pagination.querySelectorAll('a.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page);
                if (page) {
                    currentPage = page;
                    loadData(page);
                }
            });
        });
    }

    /* ── CRUD Operations ───────────────────────────────────────────── */
    async function handleCreate() {
        const formData = new FormData(els.form);
        const data = Object.fromEntries(formData.entries());
        
        // Convert roles to array
        data.roles = Array.from(els.roles.selectedOptions).map(opt => opt.value);
        
        // Convert status to boolean
        data.status = els.status.checked ? 1 : 0;

        try {
            const res = await window.AdminCore.apiFetch('/api/v1/admin/users', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (res && res.ok) {
                const result = await res.json();
                window.showAdminToast(result.message || 'Tạo tài khoản thành công!', 'success');
                getModalInstance(els.modalEl).hide();
                loadData(currentPage);
            } else {
                const error = await res.json();
                window.showAdminToast(error.message || 'Lỗi tạo tài khoản!', 'danger');
            }
        } catch (error) {
            console.error('Error creating user:', error);
            window.showAdminToast('Lỗi kết nối!', 'danger');
        }
    }

    async function handleUpdate() {
        const formData = new FormData(els.form);
        const data = Object.fromEntries(formData.entries());
        const userId = els.idInput.value;
        
        // Convert roles to array
        data.roles = Array.from(els.roles.selectedOptions).map(opt => opt.value);
        
        // Convert status to boolean
        data.status = els.status.checked ? 1 : 0;

        // Remove password if empty
        if (!data.password) {
            delete data.password;
        }

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/users/${userId}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });

            if (res && res.ok) {
                const result = await res.json();
                window.showAdminToast(result.message || 'Cập nhật tài khoản thành công!', 'success');
                getModalInstance(els.modalEl).hide();
                loadData(currentPage);
            } else {
                const error = await res.json();
                window.showAdminToast(error.message || 'Lỗi cập nhật tài khoản!', 'danger');
            }
        } catch (error) {
            console.error('Error updating user:', error);
            window.showAdminToast('Lỗi kết nối!', 'danger');
        }
    }

    async function handleEdit(e) {
        const userId = e.currentTarget.dataset.id;
        
        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/users/${userId}`);
            if (res && res.ok) {
                const response = await res.json();
                const user = response.data;

                // Populate form
                els.idInput.value = user.id;
                els.formMethod.value = 'PUT';
                els.name.value = user.name || '';
                els.email.value = user.email || '';
                els.username.value = user.username || '';
                els.phone.value = user.phone || '';
                els.password.value = '';
                els.birthday.value = user.birthday || '';
                els.gender.value = user.gender || '';
                els.loyaltyPoints.value = user.loyalty_points || 0;
                els.address.value = user.address || '';
                els.status.checked = user.status;

                // Select roles
                const userRoleIds = user.roles?.map(r => r.id.toString()) || [];
                Array.from(els.roles.options).forEach(opt => {
                    opt.selected = userRoleIds.includes(opt.value);
                });

                // Update modal UI
                els.modalLabel.textContent = 'Cập nhật tài khoản';
                els.password.removeAttribute('required');
                els.passwordRequired.style.display = 'none';
                
                getModalInstance(els.modalEl).show();
            }
        } catch (error) {
            console.error('Error loading user:', error);
            window.showAdminToast('Lỗi tải thông tin người dùng!', 'danger');
        }
    }

    async function handleDelete(e) {
        const userId = e.currentTarget.dataset.id;
        
        if (!confirm('Bạn có chắc chắn muốn xóa người dùng này?')) return;

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/users/${userId}`, {
                method: 'DELETE'
            });

            if (res && res.ok) {
                const result = await res.json();
                window.showAdminToast(result.message || 'Xóa tài khoản thành công!', 'success');
                loadData(currentPage);
            } else {
                const error = await res.json();
                window.showAdminToast(error.message || 'Lỗi xóa tài khoản!', 'danger');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            window.showAdminToast('Lỗi kết nối!', 'danger');
        }
    }

    async function handleToggleStatus(e) {
        const userId = e.currentTarget.dataset.id;
        const checkbox = e.currentTarget;
        const originalState = !checkbox.checked;

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/users/${userId}/toggle-status`, {
                method: 'POST'
            });

            if (res && res.ok) {
                const result = await res.json();
                window.showAdminToast(result.message || 'Cập nhật trạng thái thành công!', 'success');
            } else {
                // Revert on error
                checkbox.checked = originalState;
                const error = await res.json();
                window.showAdminToast(error.message || 'Lỗi cập nhật trạng thái!', 'danger');
            }
        } catch (error) {
            console.error('Error toggling status:', error);
            checkbox.checked = originalState;
            window.showAdminToast('Lỗi kết nối!', 'danger');
        }
    }

    /* ── Reset Password ────────────────────────────────────────────── */
    function handleResetPasswordClick(e) {
        const userId = e.currentTarget.dataset.id;
        const userName = e.currentTarget.dataset.name;

        els.resetUserId.value = userId;
        els.resetUserName.textContent = userName;
        els.newPassword.value = '';
        els.newPasswordConfirmation.value = '';

        getModalInstance(els.resetPasswordModal).show();
    }

    async function handleResetPassword(e) {
        e.preventDefault();

        const userId = els.resetUserId.value;
        const password = els.newPassword.value;
        const passwordConfirmation = els.newPasswordConfirmation.value;

        // Validate
        if (password !== passwordConfirmation) {
            window.showAdminToast('Mật khẩu xác nhận không khớp!', 'danger');
            return;
        }

        if (password.length < 6) {
            window.showAdminToast('Mật khẩu phải có ít nhất 6 ký tự!', 'danger');
            return;
        }

        try {
            const res = await window.AdminCore.apiFetch(`/api/v1/admin/users/${userId}/reset-password`, {
                method: 'POST',
                body: JSON.stringify({ password, password_confirmation: passwordConfirmation })
            });

            if (res && res.ok) {
                const result = await res.json();
                window.showAdminToast(result.message || 'Đặt lại mật khẩu thành công!', 'success');
                getModalInstance(els.resetPasswordModal).hide();
            } else {
                const error = await res.json();
                window.showAdminToast(error.message || 'Lỗi đặt lại mật khẩu!', 'danger');
            }
        } catch (error) {
            console.error('Error resetting password:', error);
            window.showAdminToast('Lỗi kết nối!', 'danger');
        }
    }

    /* ── Event Listeners ───────────────────────────────────────────── */
    function attachEventListeners() {
        // Search form
        if (els.searchForm) {
            els.searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                currentSearch = els.searchInput.value.trim();
                currentPage = 1;
                loadData(1);
            });
        }

        // Filters
        if (els.roleFilter) {
            els.roleFilter.addEventListener('change', () => {
                currentRole = els.roleFilter.value;
                currentPage = 1;
                loadData(1);
            });
        }

        if (els.statusFilter) {
            els.statusFilter.addEventListener('change', () => {
                currentStatus = els.statusFilter.value;
                currentPage = 1;
                loadData(1);
            });
        }

        if (els.verifiedFilter) {
            els.verifiedFilter.addEventListener('change', () => {
                currentVerified = els.verifiedFilter.value;
                currentPage = 1;
                loadData(1);
            });
        }

        // Create button
        if (els.btnCreate) {
            els.btnCreate.addEventListener('click', () => {
                els.form.reset();
                els.idInput.value = '';
                els.formMethod.value = 'POST';
                els.modalLabel.textContent = 'Tạo tài khoản mới';
                els.password.setAttribute('required', 'required');
                els.passwordRequired.style.display = 'inline';
                els.status.checked = true;
                getModalInstance(els.modalEl).show();
            });
        }

        // Form submit
        if (els.form) {
            els.form.addEventListener('submit', (e) => {
                e.preventDefault();
                const method = els.formMethod.value;
                if (method === 'POST') {
                    handleCreate();
                } else {
                    handleUpdate();
                }
            });
        }

        // Reset password form
        if (els.resetPasswordForm) {
            els.resetPasswordForm.addEventListener('submit', handleResetPassword);
        }
    }

    /* ── Initialize ────────────────────────────────────────────────── */
    function init() {
        loadRoles().then(() => {
            loadData(1);
        });
        attachEventListeners();
    }

    // Start on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
