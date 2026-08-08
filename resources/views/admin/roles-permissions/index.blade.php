@extends('layouts.admin')

@section('title', 'Phân quyền')
@section('header_title', 'Phân quyền vai trò')

@push('styles')
@vite('resources/css/admin/pages/roles-permissions.css')
@endpush

@section('content')
<div class="roles-permissions-page">
    <div class="role-permission-hero">
        <div class="role-hero-copy">
            <span class="admin-eyebrow">Access Control</span>
            <h1>Vai trò & quyền truy cập</h1>
            <p>Quản lý quyền theo từng vai trò nghiệp vụ. Thay đổi sẽ áp dụng ngay cho người dùng thuộc vai trò đó.</p>
        </div>
        <div class="role-permission-actions">
            <div class="role-permission-dirty" id="rolePermissionDirty">Đã lưu</div>
            <div class="role-permission-counter" id="selectedPermissionCount">0 quyền</div>
            <button type="button" class="admin-action-btn" id="saveRolePermissionsBtn" disabled>
                <i class="bi bi-shield-check"></i>
                <span>Lưu thay đổi</span>
            </button>
        </div>
    </div>

    <div class="role-permission-layout">
        <aside class="role-permission-sidebar admin-card-container">
            <div class="role-panel-header">
                <div>
                    <span class="admin-eyebrow">Roles</span>
                    <h2>Vai trò</h2>
                </div>
                <span class="role-panel-total" id="rolesTotal">0</span>
            </div>
            <div id="rolesList" class="role-list">
                <div class="admin-empty-state compact">Đang tải vai trò...</div>
            </div>
        </aside>

        <section class="role-permission-main admin-card-container">
            <div class="permission-panel-header">
                <div>
                    <span class="admin-eyebrow">Permissions</span>
                    <h2 id="selectedRoleTitle">Chọn vai trò</h2>
                    <p id="selectedRoleMeta">Chưa có vai trò nào được chọn.</p>
                </div>
                <div class="permission-panel-status" id="selectedRoleStatus">Chưa chọn</div>
            </div>

            <div class="permission-toolbar">
                <div class="permission-search">
                    <i class="bi bi-search"></i>
                    <input type="search" id="permissionSearchInput" placeholder="Tìm theo tên quyền hoặc mã quyền...">
                </div>
                <label class="permission-filter-toggle">
                    <input type="checkbox" id="showSelectedOnlyToggle">
                    <span>Chỉ hiện quyền đang bật</span>
                </label>
            </div>

            <div id="permissionsPanel" class="permission-groups">
                <div class="admin-empty-state">Chọn một vai trò để xem quyền.</div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script type="module" src="{{ asset('js/admin/pages/roles-permissions.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush
