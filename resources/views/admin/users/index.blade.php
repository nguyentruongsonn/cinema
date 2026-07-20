@extends('layouts.admin')

@section('title', 'Quản lý tài khoản')
@section('header_title', 'Quản lý tài khoản')

@section('content')

{{-- Filter Bar --}}
<div class="admin-filter-container">
    <div class="d-flex align-items-center w-100 gap-3 flex-wrap">
        <h5 class="mb-0 text-white fw-bold flex-no-shrink">Danh sách người dùng</h5>

        <form id="searchForm" class="flex-grow-1 search-container">
            <div class="input-group">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm theo tên, email, SĐT...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <select id="roleFilter" class="admin-filter-select filter-select-md">
            <option value="">Tất cả vai trò</option>
        </select>

        <select id="statusFilter" class="admin-filter-select filter-select-sm">
            <option value="">Tất cả trạng thái</option>
            <option value="1">Đang hoạt động</option>
            <option value="0">Đã khóa</option>
        </select>

        <select id="verifiedFilter" class="admin-filter-select filter-select-md">
            <option value="">Tất cả xác thực</option>
            <option value="1">Đã xác thực</option>
            <option value="0">Chưa xác thực</option>
        </select>

        <button type="button" class="admin-action-btn ms-auto" id="btnCreateUser">
            <i class="bi bi-plus-lg"></i> Tạo tài khoản
        </button>
    </div>
</div>

{{-- Table --}}
<div class="admin-table-container">
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="text-center col-stt">STT</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Số điện thoại</th>
                    <th>Vai trò</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Xác thực</th>
                    <th>Ngày tạo</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <!-- Skeleton Loading Rows -->
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-badge skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-badge skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-badge skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-85"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-55"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-badge skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
                <tr class="skeleton-row">
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-80"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-65"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-75"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-badge skeleton-w-80"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-badge skeleton-center"></div></td>
                    <td><div class="admin-skeleton admin-skeleton-text skeleton-w-60"></div></td>
                    <td class="text-center"><div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- Modal Create/Edit User --}}
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="userModalLabel">Tạo tài khoản mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="userForm">
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="userIdInput" value="">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="userName" class="form-label text-secondary">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="userName" name="name" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="userEmail" class="form-label text-secondary">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control bg-dark text-white border-secondary" id="userEmail" name="email" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="userUsername" class="form-label text-secondary">Tên đăng nhập</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="userUsername" name="username">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="userPhone" class="form-label text-secondary">Số điện thoại</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="userPhone" name="phone">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="userPassword" class="form-label text-secondary">
                                Mật khẩu <span class="text-danger" id="passwordRequired">*</span>
                            </label>
                            <input type="password" class="form-control bg-dark text-white border-secondary" id="userPassword" name="password">
                            <small class="text-muted">Tối thiểu 6 ký tự. Để trống nếu không muốn thay đổi khi cập nhật.</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="userBirthday" class="form-label text-secondary">Ngày sinh</label>
                            <input type="date" class="form-control bg-dark text-white border-secondary" id="userBirthday" name="birthday">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="userGender" class="form-label text-secondary">Giới tính</label>
                            <select class="form-select bg-dark text-white border-secondary" id="userGender" name="gender">
                                <option value="">-- Chọn giới tính --</option>
                                <option value="male">Nam</option>
                                <option value="female">Nữ</option>
                                <option value="other">Khác</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="userLoyaltyPoints" class="form-label text-secondary">Điểm thành viên</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="userLoyaltyPoints" name="loyalty_points" value="0" min="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="userAddress" class="form-label text-secondary">Địa chỉ</label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="userAddress" name="address" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="userRoles" class="form-label text-secondary">Vai trò</label>
                        <select class="form-select bg-dark text-white border-secondary" id="userRoles" name="roles[]" multiple size="3">
                            <!-- Loaded dynamically -->
                        </select>
                        <small class="text-muted">Giữ Ctrl (Cmd) để chọn nhiều vai trò</small>
                    </div>

                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="userStatus" name="status" value="1" checked>
                            <label class="form-check-label text-white" for="userStatus">Kích hoạt tài khoản</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-primary-custom border-0">Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Reset Password --}}
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="resetPasswordModalLabel">Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="resetPasswordForm">
                <input type="hidden" id="resetUserId" value="">

                <div class="modal-body">
                    <p class="text-muted mb-3">Đặt lại mật khẩu cho người dùng: <strong id="resetUserName"></strong></p>

                    <div class="mb-3">
                        <label for="newPassword" class="form-label text-secondary">Mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" class="form-control bg-dark text-white border-secondary" id="newPassword" name="password" required minlength="6">
                        <small class="text-muted">Tối thiểu 6 ký tự</small>
                    </div>

                    <div class="mb-0">
                        <label for="newPasswordConfirmation" class="form-label text-secondary">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" class="form-control bg-dark text-white border-secondary" id="newPasswordConfirmation" name="password_confirmation" required minlength="6">
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn text-white btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-primary-custom border-0">Đặt lại mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/stats.css') }}?v={{ config('app.asset_version') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/users.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush
