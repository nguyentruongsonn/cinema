@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân')
@section('meta_description', 'Quản lý hồ sơ cá nhân, thông tin tài khoản và bảo mật tài khoản Cinema.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
    <main class="profile-page" data-page="profile">
        <div class="profile-container">
            {{-- Guest users: Show login prompt (SSR) --}}
            @if (!Auth::guard('web')->check())
                <div class="profile-auth-required">
                    <div class="profile-auth-card">
                        <i class="bi bi-person-lock"></i>
                        <h1>Vui lòng đăng nhập</h1>
                        <p>Bạn cần đăng nhập để xem hồ sơ cá nhân.</p>
                        <button type="button" class="profile-primary-btn" data-auth-action="login">Đăng nhập</button>
                    </div>
                </div>
            @else

            {{-- Authenticated users: Show profile (SSR) --}}
                <div id="profileLoading" class="profile-layout profile-loading">
                <aside class="profile-sidebar profile-skeleton"></aside>
                <section class="profile-main profile-skeleton"></section>
            </div>

            <div id="profileContent" class="profile-layout d-none">
                <aside class="profile-sidebar" aria-label="Menu tài khoản">
                    <nav class="profile-menu">
                        <button class="profile-menu-item active" type="button" data-profile-nav="profile">
                            <i class="bi bi-person"></i>
                            <span>Thông tin cá nhân</span>
                        </button>
                        <button class="profile-menu-item" type="button" data-profile-nav="tickets">
                            <i class="bi bi-ticket-detailed"></i>
                            <span>Vé của tôi</span>
                        </button>
                        <button class="profile-menu-item" type="button" data-profile-nav="points">
                            <i class="bi bi-stars"></i>
                            <span>Điểm Cinema</span>
                        </button>
                        <button class="profile-menu-item" type="button" data-profile-nav="voucher">
                            <i class="bi bi-ticket-perforated"></i>
                            <span>Voucher của tôi</span>
                        </button>

                        <div class="profile-menu-divider"></div>

                        <button class="profile-menu-item profile-logout" type="button" id="profileLogoutBtn">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Đăng xuất</span>
                        </button>
                    </nav>
                </aside>

                <section class="profile-main">
                    <section class="profile-cover-card">
                        <div class="profile-cover-gradient"></div>

                        <div class="profile-cover-content">
                            <div class="profile-avatar-box">
                                <img id="profileAvatar" class="profile-avatar d-none" src="" alt="Avatar người dùng">
                                <div id="profileAvatarFallback" class="profile-avatar-fallback">U</div>
                            </div>

                            <div class="profile-cover-text">
                                <h1 id="profileDisplayName">Người dùng</h1>
                                <p id="profileMemberRank">Thành viên Cinema</p>
                            </div>
                        </div>
                    </section>

                    <div id="profileUpdateAlert" class="alert d-none profile-alert" role="alert"></div>
                    <div id="profilePasswordAlert" class="alert d-none profile-alert" role="alert"></div>

                    <div class="profile-card-grid">
                        <section class="profile-card profile-info-card">
                            <header class="profile-card-title">
                                <i class="bi bi-info-circle"></i>
                                <h2>Thông tin cá nhân</h2>
                            </header>

                            <form id="profileUpdateForm" class="profile-inline-form" novalidate>
                                <div class="profile-inline-grid">
                                    <div class="profile-form-group">
                                        <label for="profileName">Họ và tên</label>
                                        <div class="profile-editable-control">
                                            <input type="text" id="profileName" name="name" maxlength="255" required disabled data-editable-field="name">
                                            <button type="button" class="profile-field-edit-btn" data-edit-field="profileName" aria-label="Sửa họ và tên">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback" id="profileNameError"></div>
                                    </div>

                                    <div class="profile-form-group">
                                        <label for="profileEmail">Email</label>
                                        <input type="email" id="profileEmail" name="email" disabled>
                                    </div>

                                    <div class="profile-form-group">
                                        <label for="profilePhone">Số điện thoại</label>
                                        <input type="tel" id="profilePhone" name="phone" maxlength="20" disabled>
                                        <div class="invalid-feedback" id="profilePhoneError"></div>
                                    </div>

                                    <div class="profile-form-group">
                                        <label for="profileBirthday">Ngày sinh</label>
                                        <input type="date" id="profileBirthday" name="birthday" disabled>
                                        <div class="invalid-feedback" id="profileBirthdayError"></div>
                                    </div>

                                    <div class="profile-form-group">
                                        <label for="profileGender">Giới tính</label>
                                        <div class="profile-editable-control">
                                            <select id="profileGender" name="gender" disabled data-editable-field="gender">
                                                <option value="">Chưa cập nhật</option>
                                                <option value="male">Nam</option>
                                                <option value="female">Nữ</option>
                                                <option value="other">Khác</option>
                                            </select>
                                            <button type="button" class="profile-field-edit-btn" data-edit-field="profileGender" aria-label="Sửa giới tính">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback" id="profileGenderError"></div>
                                    </div>

                                    <div class="profile-form-group profile-edit-full">
                                        <label for="profileAddress">Địa chỉ</label>
                                        <div class="profile-editable-control">
                                            <textarea id="profileAddress" name="address" rows="3" maxlength="1000" disabled data-editable-field="address"></textarea>
                                            <button type="button" class="profile-field-edit-btn" data-edit-field="profileAddress" aria-label="Sửa địa chỉ">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback" id="profileAddressError"></div>
                                    </div>
                                </div>

                                <div class="profile-edit-actions">
                                    <button type="submit" class="profile-primary-btn" id="profileUpdateBtn" disabled>
                                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                                        <span class="btn-text">Lưu thay đổi</span>
                                    </button>
                                    <button type="button" class="profile-secondary-btn" id="profileResetBtn" disabled>Khôi phục</button>
                                </div>
                            </form>
                        </section>

                        <section class="profile-card profile-password-card">
                            <header class="profile-card-title">
                                <i class="bi bi-arrow-clockwise"></i>
                                <h2>Đổi mật khẩu</h2>
                            </header>

                            <form id="profilePasswordForm" class="profile-password-form" novalidate>
                                <div class="profile-form-group">
                                    <label for="currentPassword">Mật khẩu hiện tại</label>
                                    <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" placeholder="••••••••" required>
                                    <div class="invalid-feedback" id="currentPasswordError"></div>
                                </div>

                                <div class="profile-form-group">
                                    <label for="newPassword">Mật khẩu mới</label>
                                    <input type="password" id="newPassword" name="new_password" autocomplete="new-password" placeholder="••••••••" minlength="6" required>
                                    <div class="invalid-feedback" id="newPasswordError"></div>
                                </div>

                                <input type="password" id="newPasswordConfirmation" name="new_password_confirmation" class="visually-hidden" tabindex="-1" autocomplete="new-password">

                                <button type="submit" class="profile-password-submit" id="profilePasswordBtn">
                                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                                    <span class="btn-text">Cập nhật mật khẩu</span>
                                </button>
                            </form>
                        </section>
                    </div>

                    <section class="profile-xp-card" id="profileSection">
                        <div class="profile-xp-header">
                            <h2>Điểm Cinema</h2>
                            <strong id="profileXpValue">0 điểm</strong>
                        </div>
                        <div class="profile-xp-track" aria-hidden="true">
                            <div id="profileXpProgress" class="profile-xp-progress" style="width: 0%"></div>
                        </div>
                        <p id="profileXpMessage">
                            Tiếp tục đặt vé để tích điểm và nhận nhiều ưu đãi hơn.
                        </p>
                    </section>

                    {{-- Tickets Section --}}
                    <section id="ticketsSection" class="d-none">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h2 class="h3 mb-2">Lịch sử đặt vé</h2>
                                <p class="text-muted mb-0">Xem lại các bộ phim bạn đã thưởng thức tại Cinema Premium.</p>
                            </div>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary active" data-ticket-filter="all">Tất cả</button>
                                <button type="button" class="btn btn-outline-secondary" data-ticket-filter="year">Năm nay</button>
                            </div>
                        </div>

                        <div id="ticketsEmpty" class="text-center py-5 d-none">
                            <i class="bi bi-ticket-detailed" style="font-size: 4rem; color: var(--bs-danger);"></i>
                            <h3 class="mt-3">Chưa có vé nào</h3>
                            <p class="text-muted">Bạn chưa đặt vé xem phim nào. Hãy khám phá các bộ phim đang chiếu!</p>
                            <a href="{{ route('movies.index') }}" class="btn btn-danger mt-3">Xem phim</a>
                        </div>

                        <div id="ticketsList" class="d-flex flex-column gap-3"></div>

                        <div class="text-center mt-4">
                            <button type="button" id="ticketsLoadMore" class="btn btn-outline-secondary" style="display: none;">
                                <i class="bi bi-chevron-down me-2"></i>
                                Xem thêm lịch sử
                            </button>
                        </div>
                    </section>
                </section>
            </div>
            @endif
        </div>

        <button type="button" class="profile-scroll-top" aria-label="Lên đầu trang">
            <i class="bi bi-arrow-up"></i>
        </button>
    </main>

    {{-- Ticket Card Template --}}
    <template id="ticketCardTemplate">
        <div class="card mb-3 bg-dark border-secondary">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-auto">
                        <img class="ticket-poster" src="" alt="" style="width: 90px; height: 130px; object-fit: cover; border-radius: 6px;">
                    </div>
                    <div class="col">
                        <div class="ticket-formats mb-2"></div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="ticket-title mb-0 text-white"></h5>
                            <span class="badge bg-secondary ticket-id ms-2"></span>
                        </div>
                        <div class="ticket-info mb-3 text-muted small">
                            <p class="mb-1"><strong>NGÀY CHIẾU</strong><br><span class="ticket-showtime"></span></p>
                            <p class="mb-1"><strong>RẠP CHIẾU</strong><br><span class="ticket-theater"></span></p>
                            <p class="mb-0"><strong>GHẾ</strong><br><span class="ticket-seats"></span></p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="ticket-status"></span>
                            <button class="btn btn-sm btn-danger ticket-rebook-btn" type="button">Đặt lại vé</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/profile.js') }}"></script>
@endpush
