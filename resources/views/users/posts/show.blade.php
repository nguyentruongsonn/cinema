@extends('layouts.app')

@section('title', $post->title . ' - Cinema Premium')
@section('meta_description', $post->excerpt)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/pages/posts.css') }}?v={{ config('app.asset_version', time()) }}">
@endpush

@section('content')
<div class="article-page">

    {{-- 1. Hero Article Header with Backdrop --}}
    <section class="article-hero-section">
        <img class="article-hero-image" src="{{ $post->image_url }}" alt="" aria-hidden="true">
        <div class="article-hero-overlay"></div>
        <div class="container article-hero-content">
            <div class="mb-3">
                <span class="hero-badge-exclusive">{{ strtoupper($post->category_label) }}</span>
            </div>
            <h1 class="article-hero-title mb-4">{{ $post->title }}</h1>

            <div class="article-hero-meta d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="author-avatar-circle">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="author-info">
                        <span class="author-name d-block">Đăng bởi <strong class="text-white">{{ $post->author_name }}</strong></span>
                        <span class="author-date text-white-50 small">
                            {{ $post->published_at?->format('d Tháng m, Y') }} &bull; {{ $post->reading_time }} phút đọc
                        </span>
                    </div>
                </div>

                <div class="article-hero-actions d-flex align-items-center gap-2">
                    <button type="button" class="btn-article-action" title="Lưu bài viết" aria-label="Lưu bài viết">
                        <i class="bi bi-bookmark"></i>
                    </button>
                    <button type="button" class="btn-article-action" id="btnShareArticle" title="Chia sẻ bài viết" aria-label="Chia sẻ bài viết">
                        <i class="bi bi-share"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Main Content & Sidebar Layout --}}
    <section class="article-main-section py-5">
        <div class="container">
            <div class="row g-5">

                {{-- Left Column: Article Content & Tags --}}
                <div class="col-lg-8">
                    {{-- Formatted Content Body --}}
                    <div class="article-content-body">
                        {!! $safeContent !!}
                    </div>

                    {{-- Topic Tags --}}
                    <div class="article-tags-bar mt-5 pt-4 border-top">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="cinema-pill-tag">#Cinematography</span>
                            <span class="cinema-pill-tag">#Lighting Design</span>
                            <span class="cinema-pill-tag">#Film Production</span>
                            <span class="cinema-pill-tag">#Visual Arts</span>
                            <span class="cinema-pill-tag">#CinemaPremium</span>
                        </div>
                    </div>
                </div>

                {{-- Right Column: Recommended & Newsletter --}}
                <aside class="col-lg-4">
                    <div class="sidebar-wrapper">

                        {{-- Widget 1: Dành Cho Bạn --}}
                        <div class="sidebar-widget mb-5">
                            <div class="sidebar-widget-header mb-3">
                                <h3 class="sidebar-widget-title mb-0">
                                    <i class="bi bi-fire text-danger me-2"></i>Dành Cho Bạn
                                </h3>
                            </div>
                            <div class="sidebar-recommended-list">
                                @forelse($relatedPosts as $rec)
                                <article class="sidebar-rec-card d-flex align-items-center gap-3 mb-3">
                                    <a href="{{ route('posts.show', ['post' => $rec->slug]) }}" class="sidebar-rec-img flex-shrink-0">
                                        <img src="{{ $rec->image_url }}" alt="{{ $rec->title }}" loading="lazy">
                                    </a>
                                    <div class="sidebar-rec-info">
                                        <h4 class="sidebar-rec-title mb-1">
                                            <a href="{{ route('posts.show', ['post' => $rec->slug]) }}">{{ $rec->title }}</a>
                                        </h4>
                                        <span class="sidebar-rec-meta">{{ $rec->reading_time }} phút đọc</span>
                                    </div>
                                </article>
                                @empty
                                <p class="text-white-50 small">Chưa có bài viết gợi ý.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Widget 2: Bản Tin Điện Ảnh --}}
                        <div class="sidebar-widget">
                            <div class="sidebar-newsletter-card">
                                <h3 class="newsletter-title mb-2">Bản Tin Điện Ảnh</h3>
                                <p class="newsletter-subtitle mb-4">
                                    Nhận những bài viết phân tích chuyên sâu và tin tức độc quyền hàng tuần.
                                </p>
                                <form id="detailNewsletterForm" class="newsletter-form" onsubmit="event.preventDefault();">
                                    <div class="mb-3">
                                        <input type="email" class="cinema-newsletter-input" placeholder="Email của bạn" required aria-label="Email đăng ký nhận bản tin">
                                    </div>
                                    <button type="submit" class="btn-newsletter-submit">
                                        <span>Đăng Ký</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </aside>

            </div>
        </div>
    </section>

    {{-- 3. Related Articles Section (Bài Viết Liên Quan) --}}
    @if($relatedPosts->isNotEmpty())
    <section class="article-related-section home-posts-section content-posts-section py-5" aria-labelledby="relatedPostsTitle">
        <div class="container">
            <x-user.content-section-header
                eyebrow="CINEMA JOURNAL"
                title="Bài Viết Liên Quan"
                title-id="relatedPostsTitle"
                :href="route('posts.index')"
            />

            <div class="row g-4">
                @foreach($relatedPosts as $relPost)
                <div class="col-12 col-md-4">
                    <x-user.post-card :post="$relPost" :excerpt-limit="100" />
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- 4. Cinema Premium Footer Bar --}}
    <div class="container py-4 text-center border-top">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 text-white-50 small">
            <span class="fw-bold text-white letter-spacing-1">CINEMA PREMIUM</span>
            <button type="button" class="btn-back-to-top" data-back-to-top aria-label="Quay lên đầu trang">
                <i class="bi bi-arrow-up"></i>
            </button>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/users/pages/posts.js') }}?v={{ config('app.asset_version', time()) }}"></script>
@endpush
