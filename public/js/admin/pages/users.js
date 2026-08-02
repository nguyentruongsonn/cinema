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
    let usersById = new Map();

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
                const allRolesOption = document.createElement('option');
                allRolesOption.value = '';
                allRolesOption.textContent = 'Tất cả vai trò';
                els.roleFilter.replaceChildren(allRolesOption);
                availableRoles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.slug;
                    option.textContent = role.name;
                    els.roleFilter.appendChild(option);
                });

                // Populate role select in form
                els.roles.replaceChildren();
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
            if (window.renderAdminTableSkeleton && els.tableBody) {
                window.renderAdminTableSkeleton(els.tableBody, 9, 5, false);
            }
            
            const url = new URL(window.location.origin + '/api/v1/admin/users');
            url.searchParams.append('page', page);
            if (currentSearch) url.searchParams.append('search', currentSearch);
            if (currentRole) url.searchParams.append('role', currentRole);
            if (currentStatus !== '') url.searchParams.append('status', currentStatus);
            if (currentVerified !== '') url.searchParams.append('verified', currentVerified);

            const res = await window.AdminCore.apiFetch(url.toString(), { requestKey: 'users:list' });
            if (res && res.ok) {
                const response = await res.json();
                renderTable(response.data, response.pagination.from);
                renderPagination(response.pagination);
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error loading data:', error);
            els.tableBody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger">Lỗi tải dữ liệu.</td></tr>`;
        }
    }

    function renderTable(users, startIndex) {
        usersById = new Map();

        if (!users || users.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            const icon = document.createElement('i');

            cell.colSpan = 9;
            cell.className = 'text-center py-5 text-muted';
            icon.className = 'bi bi-inbox fs-1 d-block mb-3 opacity-50';
            cell.append(icon, document.createTextNode('Không tìm thấy người dùng nào.'));
            row.appendChild(cell);
            els.tableBody.replaceChildren(row);
            return;
        }

        const fragment = document.createDocumentFragment();
        const firstIndex = Number.isFinite(Number(startIndex)) ? Number(startIndex) : 1;

        users.forEach((user, index) => {
            const userId = Number.parseInt(String(user.id), 10);
            if (!Number.isSafeInteger(userId) || userId <= 0) return;

            usersById.set(userId, user);

            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

            const dCreated = new Date(user.created_at);
            const roleName = user.role ? String(user.role.name || '') : '';
            const isVerified = user.email_verified_at !== null;

            tr.appendChild(createTextCell(firstIndex + index, 'text-center text-white-50'));
            tr.appendChild(createTextCell(user.name, 'fw-medium text-white', 'N/A'));
            tr.appendChild(createTextCell(user.email, 'text-white-50'));
            tr.appendChild(createTextCell(user.phone, 'text-white-50', '-'));
            tr.appendChild(createTextCell(roleName, 'text-white-50', 'Chưa có'));

            const statusCell = document.createElement('td');
            statusCell.className = 'text-center';
            const statusWrapper = document.createElement('div');
            statusWrapper.className = 'form-check form-switch mb-0 d-flex justify-content-center';
            const statusToggle = document.createElement('input');
            statusToggle.className = 'form-check-input toggle-status-btn m-0';
            statusToggle.type = 'checkbox';
            statusToggle.setAttribute('role', 'switch');
            statusToggle.dataset.id = String(userId);
            statusToggle.checked = Boolean(user.status);
            statusToggle.style.cursor = 'pointer';
            statusToggle.addEventListener('change', handleToggleStatus);
            statusWrapper.appendChild(statusToggle);
            statusCell.appendChild(statusWrapper);
            tr.appendChild(statusCell);

            const verifiedCell = document.createElement('td');
            verifiedCell.className = 'text-center';
            const verifiedBadge = document.createElement('span');
            verifiedBadge.className = isVerified ? 'badge bg-success' : 'badge bg-warning text-dark';
            const verifiedIcon = document.createElement('i');
            verifiedIcon.className = isVerified ? 'bi bi-check-circle' : 'bi bi-exclamation-circle';
            verifiedBadge.append(verifiedIcon, document.createTextNode(isVerified ? ' Đã xác thực' : ' Chưa xác thực'));
            verifiedCell.appendChild(verifiedBadge);
            tr.appendChild(verifiedCell);

            const createdDate = Number.isNaN(dCreated.getTime()) ? '-' : dCreated.toLocaleDateString('vi-VN');
            tr.appendChild(createTextCell(createdDate, 'text-white-50'));

            const actionsCell = document.createElement('td');
            actionsCell.className = 'text-center';
            actionsCell.append(
                createActionButton(userId, 'edit-btn', 'btn-outline-primary', 'bi-pencil', 'Sửa', handleEdit, 'me-1'),
                createActionButton(userId, 'reset-password-btn', 'btn-outline-warning', 'bi-key', 'Đặt lại mật khẩu', handleResetPasswordClick, 'me-1'),
                createActionButton(userId, 'delete-btn', 'btn-outline-danger', 'bi-trash', 'Xóa', handleDelete),
            );
            tr.appendChild(actionsCell);

            fragment.appendChild(tr);
        });

        els.tableBody.replaceChildren(fragment);
    }

    function createTextCell(value, className, fallback = '') {
        const cell = document.createElement('td');
        const text = value === null || value === undefined || value === '' ? fallback : String(value);
        cell.className = className;
        cell.textContent = text;
        if (text === fallback && fallback) cell.classList.add('text-muted');
        return cell;
    }

    function createActionButton(userId, actionClass, colorClass, iconClass, title, handler, spacingClass = '') {
        const button = document.createElement('button');
        const icon = document.createElement('i');
        button.type = 'button';
        button.className = ['btn', 'btn-sm', colorClass, actionClass, spacingClass].filter(Boolean).join(' ');
        button.dataset.id = String(userId);
        button.title = title;
        icon.className = `bi ${iconClass}`;
        button.appendChild(icon);
        button.addEventListener('click', handler);
        return button;
    }

    function renderPagination(meta) {
        window.AdminCore.renderAdminPagination(els.pagination, meta, (page) => {
            currentPage = page; loadData(page);
        });
    }

    /* ── CRUD Operations ───────────────────────────────────────────── */
    async function handleCreate() {
        const formData = new FormData(els.form);
        const data = Object.fromEntries(formData.entries());
        
        // Set role_id
        data.role_id = els.roles.value ? parseInt(els.roles.value, 10) : null;
        
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
        
        // Set role_id
        data.role_id = els.roles.value ? parseInt(els.roles.value, 10) : null;
        
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

                // Select role
                const userRoleId = user.role_id || user.role?.id;
                Array.from(els.roles.options).forEach(opt => {
                    opt.selected = userRoleId && opt.value == userRoleId.toString();
                });

                // Update modal UI
                els.modalLabel.innerHTML = '<i class="bi bi-person me-2 admin-accent-icon"></i>Cập nhật tài khoản';
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
        const userId = Number.parseInt(e.currentTarget.dataset.id, 10);
        const userName = usersById.get(userId)?.name || '';

        els.resetUserId.value = String(userId);
        els.resetUserName.textContent = String(userName);
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
                els.modalLabel.innerHTML = '<i class="bi bi-person me-2 admin-accent-icon"></i>Tạo tài khoản mới';
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

    window.onAdminPageLoad(init);
})();
