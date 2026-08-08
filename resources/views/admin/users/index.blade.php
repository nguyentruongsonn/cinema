@extends('layouts.admin')

@section('title', 'Quản lý tài khoản')
@section('header_title', 'Quản lý tài khoản')

@section('content')

{{-- Filter Bar --}}
<div class="admin-filter-container">
    <div class="admin-filter-bar">
        <div class="admin-filter-fields">
            <div class="admin-filter-group auto-width">
                <select id="roleFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả vai trò</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="statusFilter" class="admin-filter-select filter-select-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Đã khóa</option>
                </select>
            </div>
            <div class="admin-filter-group auto-width">
                <select id="verifiedFilter" class="admin-filter-select filter-select-md">
                    <option value="">Tất cả xác thực</option>
                    <option value="1">Đã xác thực</option>
                    <option value="0">Chưa xác thực</option>
                </select>
            </div>
        </div>

        <form id="searchForm" class="admin-filter-search">
            {{-- Search --}}
            <div class="input-group">
                <input type="text" id="search" class="admin-filter-input search-input-rounded-left" placeholder="Tìm theo tên, email, SĐT...">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <button type="button" class="admin-action-btn admin-filter-primary-action" id="btnCreateUser">
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
                    <th>Rạp phụ trách</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Xác thực</th>
                    <th>Ngày tạo</th>
                    <th class="text-center col-actions">Hành động</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <x-admin.skeleton-table cols="10" rows="5" :hasImage="false" />
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4 pt-3" id="paginationContainer"></div>
</div>

{{-- Modal Create/Edit User --}}
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="userModalLabel">
                    <i class="bi bi-person me-2 admin-accent-icon"></i>T&#7841;o t&#224;i kho&#7843;n m&#7899;i
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button>
            </div>
            <form id="userForm" novalidate>
                <input type="hidden" id="formMethod" value="POST">
                <input type="hidden" id="userIdInput" value="">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="userName" class="form-label text-secondary">H&#7885; t&#234;n <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="userName" name="name" required autocomplete="name">
                        </div>

                        <div class="col-md-6">
                            <label for="userEmail" class="form-label text-secondary">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control bg-dark text-white border-secondary" id="userEmail" name="email" required autocomplete="email">
                        </div>

                        <div class="col-md-6">
                            <label for="userUsername" class="form-label text-secondary">T&#234;n &#273;&#259;ng nh&#7853;p</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="userUsername" name="username" autocomplete="username">
                        </div>

                        <div class="col-md-6">
                            <label for="userPhone" class="form-label text-secondary">S&#7889; &#273;i&#7879;n tho&#7841;i</label>
                            <input type="tel" class="form-control bg-dark text-white border-secondary" id="userPhone" name="phone" autocomplete="tel">
                        </div>

                        <div class="col-md-6 user-password-field">
                            <label for="userPassword" class="form-label text-secondary">
                                M&#7853;t kh&#7849;u <span class="text-danger" id="passwordRequired">*</span>
                            </label>
                            <input type="password" class="form-control bg-dark text-white border-secondary" id="userPassword" name="password" minlength="8" autocomplete="new-password">
                            <small class="text-light opacity-75 admin-form-help">T&#7889;i thi&#7875;u 8 k&#253; t&#7921;.</small>
                        </div>

                        <div class="col-md-6 user-password-field">
                            <label for="userPasswordConfirmation" class="form-label text-secondary">X&#225;c nh&#7853;n m&#7853;t kh&#7849;u <span class="text-danger user-password-required">*</span></label>
                            <input type="password" class="form-control bg-dark text-white border-secondary" id="userPasswordConfirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                        </div>

                        <div class="col-md-6">
                            <label for="userBirthday" class="form-label text-secondary">Ng&#224;y sinh</label>
                            <input type="date" class="form-control bg-dark text-white border-secondary" id="userBirthday" name="birthday">
                        </div>

                        <div class="col-md-6">
                            <label for="userGender" class="form-label text-secondary">Gi&#7899;i t&#237;nh</label>
                            <select class="form-select bg-dark text-white border-secondary" id="userGender" name="gender">
                                <option value="">-- Ch&#7885;n gi&#7899;i t&#237;nh --</option>
                                <option value="male">Nam</option>
                                <option value="female">N&#7919;</option>
                                <option value="other">Kh&#225;c</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="userLoyaltyPoints" class="form-label text-secondary">&#272;i&#7875;m th&#224;nh vi&#234;n</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" id="userLoyaltyPoints" name="loyalty_points" value="0" min="0">
                        </div>

                        <div class="col-md-6">
                            <label for="userRoles" class="form-label text-secondary">Vai trò</label>
                            <select class="form-select bg-dark text-white border-secondary" id="userRoles" name="role_id">
                                <option value="">-- Chọn vai trò --</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="theaterAssignmentWrapper">
                            <label class="form-label text-secondary">Phân công rạp (Dành cho nhân viên)</label>
                            <div class="dropdown">
                                <button class="form-select bg-dark text-white border-secondary text-start" type="button" id="theaterDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                    -- Chọn rạp --
                                </button>
                                <div class="dropdown-menu bg-dark border-secondary w-100 p-2 shadow" id="theaterDropdownMenu" style="max-height: 200px; overflow-y: auto;">
                                    <!-- Theaters checkboxes populated by JS -->
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="userAddress" class="form-label text-secondary">&#272;&#7883;a ch&#7881;</label>
                            <textarea class="form-control bg-dark text-white border-secondary" id="userAddress" name="address" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="userStatus" name="status" value="1" checked>
                                <label class="form-check-label text-white" for="userStatus">K&#237;ch ho&#7841;t t&#224;i kho&#7843;n</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">H&#7911;y</button>
                    <button type="submit" class="btn-primary-custom border-0">L&#432;u t&#224;i kho&#7843;n</button>
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
                <h5 class="modal-title" id="resetPasswordModalLabel">
                    <i class="bi bi-shield-lock me-2 admin-accent-icon"></i>&#272;&#7863;t l&#7841;i m&#7853;t kh&#7849;u
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button>
            </div>
            <form id="resetPasswordForm">
                <input type="hidden" id="resetUserId" value="">

                <div class="modal-body">
                    <p class="text-light opacity-75 mb-3 admin-form-help">&#272;&#7863;t l&#7841;i m&#7853;t kh&#7849;u cho ng&#432;&#7901;i d&#249;ng: <strong id="resetUserName" class="text-white"></strong></p>

                    <div class="mb-3">
                        <label for="newPassword" class="form-label text-secondary">M&#7853;t kh&#7849;u m&#7899;i <span class="text-danger">*</span></label>
                        <input type="password" class="form-control bg-dark text-white border-secondary" id="newPassword" name="password" required minlength="8">
                        <small class="text-light opacity-75 admin-form-help">T&#7889;i thi&#7875;u 8 k&#253; t&#7921;</small>
                    </div>

                    <div class="mb-0">
                        <label for="newPasswordConfirmation" class="form-label text-secondary">X&#225;c nh&#7853;n m&#7853;t kh&#7849;u <span class="text-danger">*</span></label>
                        <input type="password" class="form-control bg-dark text-white border-secondary" id="newPasswordConfirmation" name="password_confirmation" required minlength="8">
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">H&#7911;y</button>
                    <button type="submit" class="btn-primary-custom border-0">&#272;&#7863;t l&#7841;i m&#7853;t kh&#7849;u</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    @vite(['resources/css/admin/pages/stats.css', 'resources/css/admin/pages/users.css'])
@endpush

@push('scripts')
<script src="{{ asset('js/admin/pages/users.js') }}?v={{ config('app.asset_version') }}" defer></script>
@endpush
