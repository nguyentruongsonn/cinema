@extends('layouts.app')

@section('title', 'Vé của tôi')
@section('meta_description', 'Xem lịch sử đặt vé xem phim tại Cinema Premium.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/tickets.css') }}">
@endpush

@section('content')
    <main class="tickets-page" data-page="tickets">
        <div class="tickets-container">
            {{-- Auth Required State --}}
            <div id="ticketsAuthRequired" class="tickets-auth-required d-none">
                <div class="tickets-auth-card">
                    <i class="bi bi-ticket-detailed"></i>
                    <h1>Vui lòng đăng nhập</h1>
                    <p>Bạn cần đăng nhập để xem lịch sử đặt vé.</p>
                    <button type="button" class="tickets-primary-btn" data-auth-action="login">Đăng nhập</button>
                </div>
            </div>

            {{-- Loading State --}}
            <div id="ticketsLoading" class="tickets-layout">
                <aside class="tickets-sidebar">
                    <div class="tickets-sidebar-skeleton"></div>
                </aside>
                <section class="tickets-main">
                    <div class="tickets-main-skeleton"></div>
                </section>
            </div>

            {{-- Main Content --}}
            <div id="ticketsContent" class="tickets-layout d-none">
                {{-- Sidebar --}}
                <aside class="tickets-sidebar" aria-label="Menu tài khoản">
                    <div class="tickets-user-info">
                        <div class="tickets-avatar-box">
                            <img id="ticketsAvatar" class="tickets-avatar d-none" src="" alt="Avatar">
                            <div id="ticketsAvatarFallback" class="tickets-avatar-fallback">U</div>
                        </div>
                        <div class="tickets-user-details">
                            <h2 id="ticketsUserName">Người dùng</h2>
                            <p id="ticketsUserRank">Thành viên</p>
                        </div>
                    </div>

                    <nav class="tickets-menu">
                        <a href="{{ route('profile.index') }}" class="tickets-menu-item">
                            <i class="bi bi-person"></i>
                            <span>Thông tin cá nhân</span>
                        </a>
                        <a href="{{ route('tickets.index') }}" class="tickets-menu-item active">
                            <i class="bi bi-ticket-detailed"></i>
                            <span>Lịch sử đặt vé</span>
                        </a>
                        <button class="tickets-menu-item" type="button" data-tickets-nav="favorites">
                            <i class="bi bi-heart"></i>
                            <span>Phim yêu thích</span>
                        </button>
                        <button class="tickets-menu-item" type="button" data-tickets-nav="payment">
                            <i class="bi bi-credit-card"></i>
                            <span>Phương thức thanh toán</span>
                        </button>
                        <button class="tickets-menu-item" type="button" data-tickets-nav="settings">
                            <i class="bi bi-gear"></i>
                            <span>Cài đặt</span>
                        </button>
                    </nav>
                </aside>

                {{-- Main Section --}}
                <section class="tickets-main">
                    <header class="tickets-header">
                        <div class="tickets-header-content">
                            <h1>Lịch sử đặt vé</h1>
                            <p>Xem lại các bộ phim bạn đã thưởng thức tại Cinema Premium.</p>
                        </div>
                        <div class="tickets-tabs">
                            <button class="tickets-tab active" data-filter="all">Tất cả</button>
                            <button class="tickets-tab" data-filter="current-year">Năm nay</button>
                        </div>
                    </header>

                    {{-- Empty State --}}
                    <div id="ticketsEmpty" class="tickets-empty d-none">
                        <i class="bi bi-ticket-detailed"></i>
                        <h3>Chưa có vé nào</h3>
                        <p>Bạn chưa đặt vé xem phim nào. Hãy khám phá các bộ phim đang chiếu!</p>
                        <a href="{{ route('movies.index') }}" class="tickets-primary-btn">Xem phim</a>
                    </div>

                    {{-- Tickets List --}}
                    <div id="ticketsList" class="tickets-list"></div>

                    {{-- Load More Button --}}
                    <div id="ticketsLoadMore" class="tickets-load-more d-none">
                        <button type="button" class="tickets-load-more-btn" id="ticketsLoadMoreBtn">
                            <span class="btn-text">Xem thêm lịch sử</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    {{-- Loading More Indicator --}}
                    <div id="ticketsLoadingMore" class="tickets-loading-more d-none">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    {{-- Ticket Card Template --}}
    <template id="ticketCardTemplate">
        <article class="ticket-card">
            <div class="ticket-poster">
                <img class="ticket-poster-img" src="" alt="">
                <div class="ticket-formats"></div>
            </div>
            <div class="ticket-details">
                <div class="ticket-header">
                    <span class="ticket-id"></span>
                </div>
                <h3 class="ticket-title"></h3>
                <div class="ticket-info">
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">NGÀY CHIẾU</span>
                        <span class="ticket-info-value ticket-showtime"></span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">RẠP CHIẾU</span>
                        <span class="ticket-info-value ticket-theater"></span>
                    </div>
                    <div class="ticket-info-item">
                        <span class="ticket-info-label">GHẾ</span>
                        <span class="ticket-info-value ticket-seats"></span>
                    </div>
                </div>
            </div>
            <div class="ticket-actions">
                <div class="ticket-status"></div>
                <button class="ticket-rebook-btn" type="button">
                    Đặt lại vé
                </button>
            </div>
        </article>
    </template>

    {{-- Format Badge Template --}}
    <template id="formatBadgeTemplate">
        <span class="ticket-format-badge"></span>
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/tickets.js?v=' . time()) }}"></script>
@endpush
