@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân')
@section('meta_description', 'Quản lý hồ sơ cá nhân, thông tin tài khoản và bảo mật tài khoản Cinema.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/pages/profile.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users/pages/tickets.css') }}">
@endpush

@section('content')
<main class="profile-page" data-page="profile">
    <div class="profile-container">

        {{-- ── Chưa đăng nhập ──────────────────────────────────────── --}}
        @guest('web')
        <div class="profile-auth-required">
            <div class="profile-auth-card">
                <i class="bi bi-person-lock"></i>
                <h1>Vui lòng đăng nhập</h1>
                <p>Bạn cần đăng nhập để xem hồ sơ cá nhân.</p>
                <button type="button" class="profile-primary-btn" data-auth-action="login">Đăng nhập</button>
            </div>
        </div>
        @else

        {{-- ── Skeleton loading ────────────────────────────────────── --}}
        <div id="profileLoading" class="profile-layout profile-loading">
            <aside class="profile-sidebar profile-skeleton"></aside>
            <section class="profile-main profile-skeleton"></section>
        </div>

        {{-- ── Nội dung chính ──────────────────────────────────────── --}}
        <div id="profileContent" class="profile-layout d-none">

            {{-- Sidebar ─────────────────────────────────────────────── --}}
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

            {{-- Main content ─────────────────────────────────────────── --}}
            <section class="profile-main">

                {{-- Cover / Avatar card --}}
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

                {{-- Alerts --}}
                <div id="profileUpdateAlert"   class="alert d-none profile-alert" role="alert"></div>
                <div id="profilePasswordAlert" class="alert d-none profile-alert" role="alert"></div>

                {{-- Info + Password cards --}}
                <div class="profile-card-grid" id="profileSection">
                    {{-- Personal info --}}
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

                    {{-- Change password --}}
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

                            {{-- Hidden honeypot confirm field --}}
                            <input type="password" id="newPasswordConfirmation" name="new_password_confirmation"
                                   class="visually-hidden" tabindex="-1" autocomplete="new-password">

                            <button type="submit" class="profile-password-submit" id="profilePasswordBtn">
                                <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                                <span class="btn-text">Cập nhật mật khẩu</span>
                            </button>
                        </form>
                    </section>
                </div>

                {{-- Tickets Section --}}
                <section id="ticketsSection" class="d-none">
                    <div class="tickets-header mb-4">
                        <div class="tickets-header-content">
                            <h2 style="color: #fff; font-size: 1.8rem; font-weight: 900;">Vé của tôi</h2>
                            <p style="color: var(--tk-muted-2);">Xem lịch sử đặt vé tại Cinema Premium.</p>
                        </div>
                        <div class="tickets-tabs" role="tablist">
                            <button class="tickets-tab active" data-filter-status="all" role="tab">Tất cả</button>
                            <button class="tickets-tab" data-filter-status="valid" role="tab">Sắp chiếu</button>
                            <button class="tickets-tab" data-filter-status="used" role="tab">Đã xem</button>
                        </div>
                    </div>

                    <div id="ticketsLoading" class="text-center py-5 d-none">
                        <div class="spinner-border text-danger" role="status"></div>
                    </div>

                    <div id="ticketsEmpty" class="tickets-empty d-none">
                        <i class="bi bi-ticket-detailed"></i>
                        <h3>Chưa có vé nào</h3>
                        <p>Bạn chưa đặt vé xem phim nào. Hãy khám phá các bộ phim đang chiếu!</p>
                        <a href="{{ route('movies.index') }}" class="tickets-primary-btn">
                            <i class="bi bi-film me-2"></i>Xem phim ngay
                        </a>
                    </div>

                    <div id="ticketsGrid" class="tickets-list" role="list"></div>
                    <div id="ticketsPagination" class="mt-4"></div>
                </section>
                
                {{-- Loyalty points card --}}
                <section class="profile-xp-card" id="profileXpCard">
                    <div class="profile-xp-header">
                        <h2>Điểm Cinema</h2>
                        <strong id="profileXpValue">0 điểm</strong>
                    </div>
                    <div class="profile-xp-track" aria-hidden="true">
                        <div id="profileXpProgress" class="profile-xp-progress" style="width: 0%"></div>
                    </div>
                    <p id="profileXpMessage">Tiếp tục đặt vé để tích điểm và nhận nhiều ưu đãi hơn.</p>
                </section>

            </section>{{-- .profile-main --}}
        </div>{{-- #profileContent --}}

        @endguest
    </div>{{-- .profile-container --}}

    <button type="button" class="profile-scroll-top" aria-label="Lên đầu trang">
        <i class="bi bi-arrow-up"></i>
    </button>
</main>

{{-- ── Detail Modal ─────────────────────────────────────────────────────── --}}
<div id="ticketDetailModal" class="ticket-modal-overlay" role="dialog" aria-modal="true" aria-label="Chi tiết vé">
    <div class="ticket-modal">
        <div class="ticket-modal-header">
            <span class="ticket-modal-title">
                <i class="bi bi-ticket-perforated-fill me-2"></i>Chi tiết vé
            </span>
            <button id="ticketModalClose" class="ticket-modal-close" aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ticket-modal-body"></div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/profile.js') }}"></script>
@endpush
