@extends('layouts.app')

@section('title', 'Cinema Premium - Đặt vé xem phim')
@section('meta_description', 'Cinema premium - đặt vé xem phim trực tuyến với trải nghiệm tối giản, hiện đại và nhanh chóng.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/skeleton.css') }}">
<link rel="stylesheet" href="{{ asset('css/users/pages/home.css') }}">
@endpush

@section('content')
    {{-- Hero Section - Full Bleed Cinematic --}}
    <section class="hero-section" aria-labelledby="heroTitle">
        {{-- Skeleton Loading --}}
        <div id="heroSkeleton" class="skeleton-hero">
            <div class="skeleton-hero-content">
                <div class="skeleton-hero-copy">
                    <div class="skeleton skeleton-badge"></div>
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text-short"></div>
                    <div class="d-flex gap-3 mt-4">
                        <div class="skeleton skeleton-button"></div>
                        <div class="skeleton skeleton-button"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hero Backdrop (background image set by JS) --}}
        <div id="heroBackdrop" class="hero-backdrop"></div>

        {{-- Gradient overlays --}}
        <div class="hero-gradient-overlay"></div>

        <div class="hero-carousel-controls d-none" aria-label="Điều khiển banner">
            <button id="heroPrevious" class="hero-carousel-arrow" type="button" aria-label="Banner trước">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button id="heroNext" class="hero-carousel-arrow" type="button" aria-label="Banner tiếp theo">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        {{-- Actual Hero Content (hidden until loaded) --}}
        <div id="heroContent" class="hero-content d-none">
            <div class="container">
                <div class="hero-inner"></div>
            </div>
        </div>
        <div id="heroDots" class="hero-carousel-dots" role="tablist" aria-label="Chọn banner"></div>
    </section>

    {{-- Quick Booking Widget --}}
    <section class="quick-booking-section">
        <div class="container">
            <div class="quick-booking-widget">
                {{-- Skeleton --}}
                <div id="bookingSkeleton" class="skeleton skeleton-booking"></div>

                {{-- Actual Booking Form (hidden until loaded) --}}
                <div id="bookingWidget" class="booking-form d-none">
                    <div class="booking-controls">
                        <div class="booking-control">
                            <label class="booking-label">SELECT MOVIE</label>
                            <input type="hidden" name="movie" id="movieInput">
                            <div class="custom-select" data-select="movie">
                                <div class="select-trigger">
                                    <span class="select-value placeholder">Choose movie</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="select-dropdown">
                                    <div class="select-search">
                                        <input type="text" placeholder="Search..." autocomplete="off">
                                    </div>
                                    <div class="select-options"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-control">
                            <label class="booking-label">DATE</label>
                            <input type="hidden" name="date" id="dateInput">
                            <div class="custom-select" data-select="date">
                                <div class="select-trigger">
                                    <span class="select-value placeholder">Choose date</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="select-dropdown">
                                    <div class="select-options"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-control">
                            <label class="booking-label">CINEMA</label>
                            <input type="hidden" name="cinema" id="cinemaInput">
                            <div class="custom-select" data-select="cinema">
                                <div class="select-trigger">
                                    <span class="select-value placeholder">Choose cinema</span>
                                    <i class="bi bi-chevron-down"></i>
                                </div>
                                <div class="select-dropdown">
                                    <div class="select-options"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-control-btn">
                            <button type="button" class="btn-find-seats" id="btnFindSeats">
                                <i class="bi bi-search"></i>
                                <span>Find Seats</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Now Showing Section --}}
    <section id="movies" class="movies-section" aria-labelledby="moviesTitle">
        <div class="container">
            <div class="section-header">
                <h2 id="moviesTitle" class="section-title">Now Showing</h2>
                <a href="/movies" class="view-all-link">
                    View All <i class="bi bi-plus"></i>
                </a>
            </div>

            {{-- Skeleton Grid --}}
            <div id="moviesSkeleton" class="skeleton-grid">
                @for ($i = 0; $i < 4; $i++)
                <div class="skeleton-movie-card">
                    <div class="skeleton skeleton-movie-poster"></div>
                    <div class="skeleton-movie-info">
                        <div class="skeleton skeleton-movie-title"></div>
                        <div class="skeleton skeleton-movie-meta"></div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- Actual Movies Grid (hidden until loaded) --}}
            <div id="moviesGrid" class="movies-grid d-none"></div>
        </div>
    </section>

    {{-- Latest Promotions & News Section --}}
    @if(isset($latestPosts) && $latestPosts->isNotEmpty())
    <section class="home-posts-section py-5" aria-labelledby="postsTitle" style="border-top: 1px solid rgba(255,255,255,0.08);">
        <div class="container">
            <div class="section-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="content-eyebrow text-danger fw-semibold d-block mb-1" style="font-size: 0.75rem; letter-spacing: 2px;">CINEMA JOURNAL</span>
                    <h2 id="postsTitle" class="section-title mb-0">Tin Tức & Ưu Đãi Mới</h2>
                </div>
                <a href="{{ route('posts.index') }}" class="view-all-link text-decoration-none d-flex align-items-center gap-1">
                    <span>Xem tất cả</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="row g-4">
                @foreach($latestPosts as $post)
                <div class="col-12 col-md-4">
                    <article class="home-post-card">
                        <a href="{{ route('posts.show', ['post' => $post->slug]) }}" class="home-post-image position-relative d-block overflow-hidden" style="border-radius: 12px; aspect-ratio: 16/9; background: #1a1a1a;">
                            <img src="{{ $post->image_url }}"
                                 alt="{{ $post->title }}" loading="lazy" class="w-100 h-100 object-fit-cover" style="transition: transform 0.4s ease;">
                            <span class="home-post-badge position-absolute top-0 start-0 m-2 px-2 py-1 badge text-uppercase fw-semibold" style="font-size: 0.68rem; background: rgba(229, 9, 20, 0.9); backdrop-filter: blur(4px);">{{ $post->category_label }}</span>
                        </a>
                        <div class="home-post-body mt-3">
                            <div class="home-post-meta text-white-50 small mb-1">
                                <time datetime="{{ $post->published_at?->toISOString() }}">{{ $post->published_at?->format('d/m/Y') }}</time>
                            </div>
                            <h3 class="home-post-title h6 mb-2 fw-bold" style="line-height: 1.4;">
                                <a href="{{ route('posts.show', ['post' => $post->slug]) }}" class="text-white text-decoration-none">{{ $post->title }}</a>
                            </h3>
                            <p class="home-post-excerpt text-white-50 small mb-0" style="line-height: 1.6;">{{ Str::limit($post->excerpt, 95) }}</p>
                        </div>
                    </article>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/users/pages/home.js') }}" type="module"></script>
@endpush
