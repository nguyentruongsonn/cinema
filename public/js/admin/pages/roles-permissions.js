(function () {
    'use strict';

    const els = {
        rolesList: document.getElementById('rolesList'),
        rolesTotal: document.getElementById('rolesTotal'),
        permissionsPanel: document.getElementById('permissionsPanel'),
        selectedRoleTitle: document.getElementById('selectedRoleTitle'),
        selectedRoleMeta: document.getElementById('selectedRoleMeta'),
        selectedRoleStatus: document.getElementById('selectedRoleStatus'),
        selectedPermissionCount: document.getElementById('selectedPermissionCount'),
        dirtyIndicator: document.getElementById('rolePermissionDirty'),
        permissionSearch: document.getElementById('permissionSearchInput'),
        showSelectedOnly: document.getElementById('showSelectedOnlyToggle'),
        saveBtn: document.getElementById('saveRolePermissionsBtn'),
    };

    let roles = [];
    let permissionGroups = [];
    let selectedRole = null;
    let selectedPermissions = new Set();
    let originalPermissions = new Set();
    let eventsBound = false;
    let permissionQuery = '';
    let showSelectedOnly = false;
    const groupLabels = {
        users: 'Người dùng',
        movies: 'Phim',
        theaters: 'Hệ thống rạp',
        showtimes: 'Suất chiếu',
        booking: 'Đặt vé',
        orders: 'Đơn hàng',
        payments: 'Thanh toán',
        concessions: 'Bắp nước',
        promotions: 'Ưu đãi',
        content: 'Nội dung',
        system: 'Hệ thống',
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showError(message) {
        return `<div class="admin-empty-state error"><i class="bi bi-exclamation-triangle"></i>${escapeHtml(message)}</div>`;
    }

    async function loadInitialData(preferredRoleId = null) {
        try {
            els.rolesList.innerHTML = '<div class="admin-empty-state compact">Đang tải vai trò...</div>';
            els.permissionsPanel.innerHTML = '<div class="admin-empty-state">Đang tải danh mục quyền...</div>';

            const [rolesResponse, permissionsResponse] = await Promise.all([
                window.AdminCore.apiFetch('/api/v1/admin/roles-permissions/roles'),
                window.AdminCore.apiFetch('/api/v1/admin/roles-permissions/permissions'),
            ]);

            if (!rolesResponse?.ok || !permissionsResponse?.ok) {
                throw new Error('Không thể tải dữ liệu phân quyền.');
            }

            roles = (await rolesResponse.json()).data || [];
            permissionGroups = (await permissionsResponse.json()).data || [];
            renderRoles();

            const fallbackRoleId = preferredRoleId || selectedRole?.id || roles[0]?.id;
            if (fallbackRoleId) {
                await selectRole(fallbackRoleId);
            } else {
                renderEmptyState();
            }
        } catch (error) {
            console.error('Role permission load error:', error);
            els.rolesList.innerHTML = showError('Không thể tải vai trò.');
            els.permissionsPanel.innerHTML = showError('Không thể tải danh sách quyền.');
        }
    }

    function renderRoles() {
        els.rolesTotal.textContent = roles.length;

        if (!roles.length) {
            els.rolesList.innerHTML = '<div class="admin-empty-state compact">Chưa có vai trò.</div>';
            return;
        }

        els.rolesList.innerHTML = roles.map((role) => {
            const isActive = selectedRole && Number(selectedRole.id) === Number(role.id);
            const readonlyBadge = role.is_readonly
                ? '<span class="role-badge readonly">Khóa</span>'
                : '<span class="role-badge editable">Có thể sửa</span>';

            return `
                <button type="button" class="admin-role-item ${isActive ? 'active' : ''}" data-role-id="${role.id}">
                    <span class="role-icon"><i class="bi bi-person-badge"></i></span>
                    <span class="role-copy">
                        <span class="role-name">${escapeHtml(role.display_name || role.name)}</span>
                        <span class="role-meta">${escapeHtml(role.slug)} · ${role.permissions_count ?? 0} quyền</span>
                    </span>
                    ${readonlyBadge}
                </button>
            `;
        }).join('');
    }

    async function selectRole(roleId) {
        const role = roles.find((item) => Number(item.id) === Number(roleId));
        if (!role) return;

        selectedRole = role;
        renderRoles();
        els.selectedRoleTitle.textContent = role.display_name || role.name;
        els.selectedRoleMeta.textContent = role.is_readonly
            ? 'Vai trò quản trị viên được khóa để tránh tự vô hiệu hóa quyền hệ thống.'
            : `${role.slug} · có thể cập nhật quyền runtime`;
        els.selectedRoleStatus.textContent = role.is_readonly ? 'Chỉ xem' : 'Có thể chỉnh sửa';
        els.selectedRoleStatus.className = `permission-panel-status ${role.is_readonly ? 'readonly' : 'editable'}`;
        els.permissionsPanel.innerHTML = '<div class="admin-empty-state">Đang tải quyền...</div>';

        try {
            const response = await window.AdminCore.apiFetch(`/api/v1/admin/roles-permissions/roles/${role.id}`, {
                requestKey: `roles-permissions:${role.id}`,
            });

            if (!response?.ok) throw new Error('Không thể tải quyền của vai trò.');

            const data = (await response.json()).data || {};
            selectedPermissions = new Set(data.permissions || []);
            originalPermissions = new Set(data.permissions || []);
            renderPermissions();
            updateDirtyState();
        } catch (error) {
            console.error('Role permission detail error:', error);
            els.permissionsPanel.innerHTML = showError('Không thể tải quyền của vai trò này.');
        }
    }

    function renderEmptyState() {
        els.selectedRoleTitle.textContent = 'Chọn vai trò';
        els.selectedRoleMeta.textContent = 'Chưa có vai trò nào được chọn.';
        els.selectedRoleStatus.textContent = 'Chưa chọn';
        els.selectedRoleStatus.className = 'permission-panel-status';
        els.selectedPermissionCount.textContent = '0 quyền';
        els.dirtyIndicator.textContent = 'Đã lưu';
        els.dirtyIndicator.className = 'role-permission-dirty';
        els.saveBtn.disabled = true;
    }

    function renderPermissions() {
        const disabled = selectedRole?.is_readonly ? 'disabled' : '';

        els.permissionsPanel.innerHTML = permissionGroups.map((group) => {
            const filteredPermissions = group.permissions.filter((permission) => {
                const isSelected = selectedPermissions.has(permission.slug);
                const permissionName = String(permission.name || '').toLowerCase();
                const permissionSlug = String(permission.slug || '').toLowerCase();
                const groupLabel = String(groupLabels[group.group] || group.label || group.group).toLowerCase();
                const matchesQuery = !permissionQuery
                    || permissionName.includes(permissionQuery)
                    || permissionSlug.includes(permissionQuery)
                    || groupLabel.includes(permissionQuery);

                return matchesQuery && (!showSelectedOnly || isSelected);
            });
            const selectedInGroup = group.permissions.filter((permission) => selectedPermissions.has(permission.slug)).length;

            if (!filteredPermissions.length) {
                return '';
            }

            return `
                <section class="admin-permission-group">
                    <div class="admin-permission-group-header">
                        <div>
                            <h3>${escapeHtml(groupLabels[group.group] || group.label || group.group)}</h3>
                            <p>${selectedInGroup}/${group.permissions.length} quyền đang bật</p>
                        </div>
                        <div class="permission-group-actions">
                            <span>${escapeHtml(group.group)}</span>
                            <button type="button" class="permission-mini-btn" data-group="${escapeHtml(group.group)}" data-action="select" ${selectedRole?.is_readonly ? 'disabled' : ''}>Bật nhóm</button>
                            <button type="button" class="permission-mini-btn muted" data-group="${escapeHtml(group.group)}" data-action="clear" ${selectedRole?.is_readonly ? 'disabled' : ''}>Tắt nhóm</button>
                        </div>
                    </div>
                    <div class="admin-permission-grid">
                        ${filteredPermissions.map((permission) => {
                            const checked = selectedPermissions.has(permission.slug) ? 'checked' : '';
                            return `
                                <label class="permission-card ${checked ? 'selected' : ''}">
                                    <input class="form-check-input permission-checkbox" type="checkbox" value="${escapeHtml(permission.slug)}" ${checked} ${disabled}>
                                    <span>
                                        <strong>${escapeHtml(permission.name)}</strong>
                                        <small>${escapeHtml(permission.slug)}</small>
                                    </span>
                                </label>
                            `;
                        }).join('')}
                    </div>
                </section>
            `;
        }).join('') || '<div class="admin-empty-state">Không tìm thấy quyền phù hợp.</div>';
    }

    function updateDirtyState() {
        const selected = Array.from(selectedPermissions).sort().join('|');
        const original = Array.from(originalPermissions).sort().join('|');
        const isDirty = selected !== original;

        els.selectedPermissionCount.textContent = `${selectedPermissions.size} quyền`;
        els.dirtyIndicator.textContent = isDirty ? 'Chưa lưu' : 'Đã lưu';
        els.dirtyIndicator.className = `role-permission-dirty ${isDirty ? 'dirty' : ''}`;
        els.saveBtn.disabled = !isDirty || selectedRole?.is_readonly;
    }

    async function savePermissions() {
        if (!selectedRole || selectedRole.is_readonly) return;

        const confirmed = await window.AdminDialog.confirm({
            message: 'Lưu thay đổi phân quyền?',
            description: `Vai trò ${selectedRole.display_name || selectedRole.name} sẽ áp dụng quyền mới ngay lập tức.`,
            confirmLabel: 'Lưu thay đổi',
            variant: 'danger',
        });

        if (!confirmed) return;

        try {
            els.saveBtn.disabled = true;
            const roleId = selectedRole.id;
            const response = await window.AdminCore.apiFetch(`/api/v1/admin/roles-permissions/roles/${roleId}`, {
                method: 'PUT',
                body: JSON.stringify({ permissions: Array.from(selectedPermissions) }),
            });

            const payload = await response.json();
            if (!response.ok) {
                throw new Error(window.formatAdminErrors?.(payload.errors || payload.message) || 'Lưu phân quyền thất bại.');
            }

            originalPermissions = new Set(payload.data?.permissions || Array.from(selectedPermissions));
            window.showAdminToast?.('Đã cập nhật phân quyền vai trò.', 'success');
            await loadInitialData(roleId);
        } catch (error) {
            window.showAdminToast?.(error.message || 'Lưu phân quyền thất bại.', 'error');
            updateDirtyState();
        }
    }

    function bindEvents() {
        if (eventsBound) return;
        eventsBound = true;

        els.rolesList.addEventListener('click', (event) => {
            const button = event.target.closest('[data-role-id]');
            if (!button) return;
            selectRole(button.dataset.roleId);
        });

        els.permissionsPanel.addEventListener('change', (event) => {
            const checkbox = event.target.closest('.permission-checkbox');
            if (!checkbox) return;

            if (checkbox.checked) {
                selectedPermissions.add(checkbox.value);
            } else {
                selectedPermissions.delete(checkbox.value);
            }

            checkbox.closest('.permission-card')?.classList.toggle('selected', checkbox.checked);
            renderPermissions();
            updateDirtyState();
        });

        els.permissionsPanel.addEventListener('click', (event) => {
            const button = event.target.closest('.permission-mini-btn');
            if (!button || selectedRole?.is_readonly) return;

            const group = permissionGroups.find((item) => item.group === button.dataset.group);
            if (!group) return;

            group.permissions.forEach((permission) => {
                if (button.dataset.action === 'select') {
                    selectedPermissions.add(permission.slug);
                } else {
                    selectedPermissions.delete(permission.slug);
                }
            });

            renderPermissions();
            updateDirtyState();
        });

        els.permissionSearch.addEventListener('input', () => {
            permissionQuery = els.permissionSearch.value.trim().toLowerCase();
            renderPermissions();
        });

        els.showSelectedOnly.addEventListener('change', () => {
            showSelectedOnly = els.showSelectedOnly.checked;
            renderPermissions();
        });

        els.saveBtn.addEventListener('click', savePermissions);
    }

    window.onAdminPageLoad(() => {
        bindEvents();
        loadInitialData();
    });
})();
