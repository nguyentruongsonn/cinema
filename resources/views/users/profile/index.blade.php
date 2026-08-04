@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân')
@section('meta_description', 'Quản lý hồ sơ cá nhân, thông tin tài khoản và bảo mật tài khoản Cinema.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users/pages/profile.css') }}?v={{ config('app.asset_version') }}">
@endpush

@section('content')
    <main class="profile-page" data-page="profile">
        <div class="profile-container">
            <div id="profileAuthRequired" class="profile-auth-required d-none">
                <div class="profile-auth-card">
                    <i class="bi bi-person-lock"></i>
                    <h1>Vui lòng đăng nhập</h1>
                    <p>Bạn cần đăng nhập để xem hồ sơ cá nhân.</p>
                    <button type="button" class="profile-primary-btn" data-auth-action="login">Đăng nhập</button>
                </div>
            </div>

            <div id="profileLoading" class="profile-layout profile-loading">
                <!-- Sidebar Skeleton -->
                <aside class="profile-sidebar">
                    <div class="profile-sidebar-user profile-sidebar-user--compact">
                        <div class="skeleton skel-avatar rounded-circle"></div>
                        <div class="profile-sidebar-info w-100">
                            <div class="skeleton w-75 skel-subtitle mb-2 rounded"></div>
                            <div class="skeleton w-50 skel-label rounded"></div>
                        </div>
                    </div>

                    <div class="profile-menu-divider profile-menu-divider--section"></div>

                    <div class="d-flex flex-column gap-2 px-3 py-2">
                        <div class="skeleton skel-input rounded"></div>
                        <div class="skeleton skel-input rounded"></div>
                        <div class="skeleton skel-input rounded"></div>
                        <div class="skeleton skel-input rounded"></div>
                        <div class="profile-menu-divider profile-menu-divider--compact"></div>
                        <div class="skeleton skel-input rounded"></div>
                    </div>
                </aside>
                
                <!-- Main Content Skeleton -->
                <section class="profile-main">
                    <!-- Cover Card Skeleton -->
                    <div class="profile-cover-card mb-4">
                        <div class="profile-cover-content">
                            <div class="profile-avatar-box">
                                <div class="skeleton skel-cover-avatar"></div>
                            </div>
                            <div class="profile-cover-text w-100">
                                <div class="skeleton w-50 skel-title mb-3 rounded"></div>
                                <div class="skeleton w-25 skel-subtitle rounded"></div>
                            </div>
                            <div class="profile-cover-stats w-100 d-flex justify-content-end">
                                <div class="skeleton skel-card-stats rounded"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Cards Skeleton -->
                    <div class="profile-card-grid">
                        <!-- Info Card Skeleton -->
                        <section class="profile-card">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <div class="skeleton skel-icon rounded-circle"></div>
                                <div class="skeleton w-50 skel-title rounded"></div>
                            </div>
                            
                            <div class="profile-inline-grid mt-4">
                                @for($i = 0; $i < 5; $i++)
                                <div class="profile-form-group">
                                    <div class="skeleton w-25 skel-label mb-2 rounded"></div>
                                    <div class="skeleton skel-input rounded"></div>
                                </div>
                                @endfor
                                <div class="profile-form-group profile-edit-full">
                                    <div class="skeleton w-25 skel-label mb-2 rounded"></div>
                                    <div class="skeleton skel-textarea rounded"></div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 mt-4">
                                <div class="skeleton skel-btn rounded"></div>
                                <div class="skeleton skel-btn rounded"></div>
                            </div>
                        </section>
                        
                        <!-- Password & Support Skeleton -->
                        <div class="profile-right-column">
                            <section class="profile-card mb-4">
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <div class="skeleton skel-icon rounded-circle"></div>
                                    <div class="skeleton w-50 skel-title rounded"></div>
                                </div>
                                
                                <div class="d-flex flex-column gap-3 mb-4">
                                    @for($i = 0; $i < 3; $i++)
                                    <div class="profile-form-group">
                                        <div class="skeleton w-25 skel-label mb-2 rounded"></div>
                                        <div class="skeleton skel-input rounded"></div>
                                    </div>
                                    @endfor
                                </div>
                                
                                <div class="skeleton skel-input mt-4 rounded"></div>
                            </section>

                            <section class="profile-card">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="skeleton skel-icon rounded-circle"></div>
                                    <div class="skeleton w-25 skel-title rounded"></div>
                                </div>
                                <div class="skeleton w-100 skel-label mb-2 rounded"></div>
                                <div class="skeleton w-75 skel-label mb-4 rounded"></div>
                                <div class="skeleton skel-box rounded"></div>
                            </section>
                        </div>
                    </div>
                </section>
            </div>

            <div id="profileContent" class="profile-layout d-none">
                <aside class="profile-sidebar" aria-label="Menu tài khoản">
                    <!-- Sidebar User Info -->
                    <div class="profile-sidebar-user">
                        <div class="profile-sidebar-avatar">
                            <img id="sidebarAvatar" class="d-none" src="" alt="Avatar">
                            <div id="sidebarAvatarFallback" class="profile-sidebar-avatar-fallback">U</div>
                        </div>
                        <div class="profile-sidebar-info">
                            <div id="sidebarDisplayName" class="profile-sidebar-name">Người dùng</div>
                            <div id="sidebarMemberRank" class="profile-sidebar-rank">Thành viên</div>
                        </div>
                    </div>

                    <div class="profile-menu-divider profile-menu-divider--section"></div>

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
                        <div class="profile-cover-content">
                            <div class="profile-avatar-box">
                                <img id="profileAvatar" class="profile-avatar d-none" src="" alt="Avatar người dùng">
                                <div id="profileAvatarFallback" class="profile-avatar-fallback">U</div>
                            </div>

                            <div class="profile-cover-text">
                                <div class="profile-rank-heading">
                                    <h1 id="profileDisplayName">Người dùng</h1>
                                    <span id="profileMemberBadge" class="badge-member">Thành viên</span>
                                </div>
                                <p id="profileMemberRank" class="profile-member-rank">Chưa có thông tin</p>
                            </div>

                            <div class="profile-cover-stats">
                                <div class="profile-cover-stat">
                                    <span class="profile-cover-stat-value" id="coverStatTickets">0</span>
                                    <span class="profile-cover-stat-label">Vé đã mua</span>
                                </div>
                                <div class="profile-cover-stat-divider"></div>
                                <div class="profile-cover-stat">
                                    <span class="profile-cover-stat-value" id="coverStatPoints">0</span>
                                    <span class="profile-cover-stat-label">Điểm thưởng</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="profile-card-grid">
                        <section class="profile-card profile-info-card">
                            <header class="profile-card-title profile-card-title--split">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-info-circle"></i>
                                    <h2>Thông tin cá nhân</h2>
                                </div>
                                <button type="button" class="profile-edit-all-btn">Chỉnh sửa tất cả</button>
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

                        <!-- Right column: password + support stacked -->
                        <div class="profile-right-column">

                        <section class="profile-card profile-password-card">
                            <header class="profile-card-title">
                                <i class="bi bi-shield-lock"></i>
                                <h2>Bảo mật</h2>
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

                                <div class="profile-form-group">
                                    <label for="newPasswordConfirmation">Xác nhận mật khẩu</label>
                                    <input type="password" id="newPasswordConfirmation" name="new_password_confirmation" autocomplete="new-password" placeholder="••••••••" required>
                                    <div class="invalid-feedback" id="newPasswordConfirmationError"></div>
                                </div>

                                <button type="submit" class="profile-password-submit profile-password-submit--full" id="profilePasswordBtn">
                                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                                    <span class="btn-text">Cập nhật mật khẩu</span>
                                </button>
                            </form>
                        </section>

                        <!-- Support Card -->
                        <section class="profile-card profile-support-card">
                            <header class="profile-support-header">
                                <i class="bi bi-headset profile-support-icon"></i>
                                <h3 class="profile-support-title">Cần hỗ trợ?</h3>
                            </header>
                            <p class="profile-support-copy">Liên hệ đội ngũ hỗ trợ VIP để được giải quyết nhanh nhất về vé và tài khoản.</p>
                            <button type="button" class="profile-secondary-btn profile-support-action">Liên hệ ngay</button>
                        </section>

                        </div><!-- /.profile-right-column -->
                    </div>

                    {{-- Tickets Section --}}
                    <section id="ticketsSection" class="d-none">
                        <!-- Header -->
                        <div class="tickets-header">
                            <h2 class="tickets-title">Lịch sử đặt vé</h2>
                            <div class="tickets-filter-group">
                                <button type="button" class="ticket-filter-btn active" data-ticket-filter="all">Tất cả</button>
                                <button type="button" class="ticket-filter-btn" data-ticket-filter="year">Năm nay</button>
                            </div>
                        </div>

                        <div id="ticketsEmpty" class="tickets-empty d-none">
                            <i class="bi bi-ticket-detailed"></i>
                            <h3>Chưa có vé nào</h3>
                            <p>Bạn chưa đặt vé xem phim nào. Hãy khám phá các bộ phim đang chiếu!</p>
                            <a href="{{ route('movies.index') }}" class="ticket-filter-btn active">Xem phim</a>
                        </div>

                        <div id="ticketsList" class="tickets-list"></div>

                        <div class="text-center mt-4">
                            <button type="button" id="ticketsLoadMore" class="ticket-filter-btn d-none">
                                <i class="bi bi-chevron-down me-2"></i>
                                Xem thêm lịch sử
                            </button>
                        </div>
                    </section>

                    {{-- Points Section --}}
                    <section id="pointsSection" class="d-none">
                        <div class="profile-card-grid">
                            <!-- Left Column: Membership Status -->
                            <section class="profile-card profile-info-card">
                                <header class="profile-card-title profile-card-title--split">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-star"></i>
                                        <h2>Trạng thái thành viên</h2>
                                    </div>
                                </header>
                                <div class="profile-form-container mt-3">
                                    <div class="d-flex flex-column gap-4">
                                        <div>
                                            <h1 class="display-4 fw-bold mb-0 text-white profile-points-total" id="pointsDashboardTotal">
                                                0 <span class="fs-5 text-muted fw-normal profile-points-unit">Points</span>
                                            </h1>
                                            <div class="d-flex align-items-center mt-2">
                                                <i class="bi bi-star-fill text-danger me-2"></i>
                                                <span class="fs-5 fw-semibold text-white profile-points-rank" id="pointsDashboardRank">Thành viên</span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <div class="profile-points-progress-meta">
                                                <span id="pointsDashboardNextTier">Loading...</span>
                                                <span id="pointsDashboardPercent">0%</span>
                                            </div>
                                            <div class="progress profile-points-progress">
                                                <div id="pointsDashboardProgress" class="progress-bar bg-danger profile-points-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
                                            </div>
                                            <p class="text-muted mt-3 mb-0 profile-points-benefits" id="pointsDashboardBenefits">
                                                Tiếp tục tích lũy điểm để mở khóa các đặc quyền cao cấp.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Right Column: Point History -->
                            <div class="profile-right-column">
                                <section class="profile-card">
                                    <header class="profile-card-title profile-card-title--split">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-clock-history"></i>
                                            <h2>Lịch sử điểm thưởng</h2>
                                        </div>
                                        <a href="#" class="profile-history-link">Xem tất cả</a>
                                    </header>
                                    
                                    <div class="mt-2 profile-table-scroll">
                                        <table class="table table-borderless mb-0 point-history-table">
                                            <thead>
                                                <tr>
                                                    <th class="profile-history-heading profile-history-heading--date">Ngày</th>
                                                    <th class="profile-history-heading">Hoạt động</th>
                                                    <th class="profile-history-heading profile-history-heading--points text-end">Điểm</th>
                                                </tr>
                                            </thead>
                                            <tbody id="pointHistoryList">
                                                <!-- Dynamic rows -->
                                            </tbody>
                                        </table>
                                        <div id="pointHistoryEmpty" class="text-center py-5 d-none">
                                            <i class="bi bi-clock-history profile-history-empty-icon"></i>
                                            <p class="text-muted mt-2 profile-history-empty-copy">Chưa có giao dịch điểm nào.</p>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </section>

                    {{-- Voucher Section --}}
                    <section id="voucherSection" class="d-none">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h2 class="h3 mb-2">Voucher của tôi</h2>
                            </div>
                        </div>

                        <div id="voucherLoading" class="text-center py-5 d-none">
                            <div class="spinner-border text-danger"></div>
                        </div>

                        <div id="voucherEmpty" class="text-center py-5 d-none">
                            <i class="bi bi-ticket-perforated profile-ticket-empty-icon"></i>
                            <h3 class="mt-3">Chưa có voucher nào</h3>
                            <p class="text-muted">Bạn chưa đăng ký voucher nào. Nhập mã để nhận ưu đãi!</p>
                        </div>

                        <div id="voucherList" class="row g-3"></div>
                    </section>
                </section>
            </div>
        </div>

        <button type="button" class="profile-scroll-top" aria-label="Lên đầu trang">
            <i class="bi bi-arrow-up"></i>
        </button>
    </main>

    {{-- Ticket Card Template --}}
    <template id="ticketCardTemplate">
        <div class="ticket-card">
            <!-- Poster -->
            <div class="ticket-poster-wrap">
                <img class="ticket-poster" src="" alt="">
                <div class="ticket-cancelled-overlay">CANCELLED</div>
            </div>

            <!-- Content -->
            <div class="ticket-body">
                <!-- Top row: title + formats + ID -->
                <div class="ticket-top-row">
                    <div class="ticket-title-row">
                        <h3 class="ticket-title"></h3>
                        <div class="ticket-formats"></div>
                    </div>
                    <span class="ticket-id"></span>
                </div>

                <!-- Info grid: 2 columns -->
                <div class="ticket-info-grid">
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">NGÀY CHIẾU</span>
                        <span class="ticket-showtime ticket-info-value"></span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">RẠP CHIẾU</span>
                        <span class="ticket-theater ticket-info-value"></span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">GHẾ</span>
                        <span class="ticket-seats ticket-info-value"></span>
                    </div>
                </div>

                <!-- Bottom row: status + detail button -->
                <div class="ticket-bottom-row">
                    <span class="ticket-status"></span>
                    <button type="button" class="ticket-detail-btn">Xem chi tiết</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Order Detail Modal --}}
    <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl order-detail-modal-dialog">
            <div class="modal-content border-0 profile-order-modal-content">
                <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 profile-order-code-label">MÃ ĐƠN HÀNG</h6>
                        <h4 class="text-white fw-bold mb-0 profile-order-code" id="odModalCode">ORD-XXXXXXXX</h4>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge rounded-pill d-flex align-items-center px-3 py-2 profile-order-status" id="odModalStatus">
                            <!-- Status dynamic -->
                        </span>
                        <button type="button" class="btn-close btn-close-white profile-order-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                
                <div class="modal-body p-4 pt-3">
                    <div class="row g-4">
                        <!-- Left Column: E-Ticket Design -->
                        <div class="col-lg-5">
                            <div class="e-ticket-card">
                                <div class="e-ticket-top">
                                    <img id="odModalPoster" src="" alt="Poster" class="e-ticket-poster">
                                    <div class="e-ticket-title-overlay">
                                        <h4 id="odModalMovieTitle" class="e-ticket-title">Tên Phim</h4>
                                    </div>
                                </div>
                                <div class="e-ticket-body">
                                    <div class="e-ticket-info-grid">
                                        <div class="e-ticket-info-item">
                                            <span class="e-ticket-label">RẠP CHIẾU</span>
                                            <span id="odModalTheater" class="e-ticket-value">Tên Rạp</span>
                                        </div>
                                        <div class="e-ticket-info-item">
                                            <span class="e-ticket-label">PHÒNG CHIẾU</span>
                                            <span id="odModalRoom" class="e-ticket-value">Tên Phòng</span>
                                        </div>
                                        <div class="e-ticket-info-item full-width">
                                            <span class="e-ticket-label">THỜI GIAN</span>
                                            <span id="odModalShowtime" class="e-ticket-value">Giờ chiếu</span>
                                        </div>
                                        <div class="e-ticket-info-item full-width">
                                            <span class="e-ticket-label">ĐỊA CHỈ</span>
                                            <span id="odModalAddress" class="e-ticket-value">Địa chỉ rạp</span>
                                        </div>
                                    </div>
                                    <div class="e-ticket-seats-section">
                                        <span class="e-ticket-label">VỊ TRÍ GHẾ NGỒI CỦA BẠN</span>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <h3 id="odModalSeats" class="e-ticket-seats-val text-truncate">J12, J13</h3>
                                            <span id="odModalSeatType" class="e-ticket-seat-type-badge text-nowrap"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="e-ticket-barcode-section">
                                    <div class="e-ticket-rip-line"></div>
                                    <div id="odModalBarcodeContainer" class="e-ticket-barcode-container">
                                        <!-- Dynamic Barcode SVG -->
                                    </div>
                                    <div class="e-ticket-barcode-hint">Vui lòng quét mã tại cửa phòng chiếu</div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Invoice Details -->
                        <div class="col-lg-7">
                            <div class="invoice-detail-card h-100 d-flex flex-column">
                                <h5 class="invoice-title">Chi tiết hóa đơn</h5>
                                
                                <div class="invoice-section border-bottom mb-4 pb-4">
                                    <h6 class="invoice-section-title">Thông tin giao dịch</h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <span class="invoice-label">Người thanh toán</span>
                                            <span id="odModalPayerName" class="invoice-value"></span>
                                        </div>
                                        <div class="col-6">
                                            <span class="invoice-label">Phương thức</span>
                                            <span id="odModalPaymentMethod" class="invoice-value"></span>
                                        </div>
                                        <div class="col-12">
                                            <span class="invoice-label">Ngày giao dịch</span>
                                            <span id="odModalTxDate" class="invoice-value"></span>
                                        </div>
                                    </div>
                                </div>

                                <div id="odModalTicketsList" class="invoice-items-list mb-3"></div>
                                <div id="odModalProductsList" class="invoice-items-list mb-4 pb-4 border-bottom"></div>

                                <div class="invoice-totals mt-auto">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="invoice-label">Tạm tính</span>
                                        <span class="text-white" id="odModalSubtotal">0đ</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 d-none" id="odModalVoucherList">
                                        <span class="invoice-label">
                                            Voucher (<span id="odModalVoucherCode">CODE</span>)
                                        </span>
                                        <span class="invoice-discount-val" id="odModalVoucherValue">-0đ</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 d-none" id="odModalPointsList">
                                        <span class="invoice-label">Giảm bằng điểm (<span id="odModalPointsUsed">0</span> điểm)</span>
                                        <span class="invoice-discount-val" id="odModalPointsValue">-0đ</span>
                                    </div>
                                    <div class="invoice-grand-total mt-3 pt-3 border-top profile-invoice-grand-total">
                                        <span>Tổng cộng</span>
                                        <span id="odModalTotal">0đ</span>
                                    </div>
                                </div>

                                <div class="invoice-actions mt-4">
                                    <div class="d-flex gap-3 w-100">
                                        <button class="invoice-action-btn w-50">
                                            <i class="bi bi-wallet2"></i> Apple Wallet
                                        </button>
                                        <button class="invoice-action-btn w-50">
                                            <i class="bi bi-google"></i> Google Wallet
                                        </button>
                                    </div>
                                    <div class="text-center mt-3">
                                        <button class="invoice-download-btn order-detail-download-btn">
                                            <i class="bi bi-download"></i> Tải hóa đơn (PDF)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pages/profile.js')
@endpush
