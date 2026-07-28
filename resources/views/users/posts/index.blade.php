@extends('layouts.app')

@section('title', 'Bài viết - Cinema Premium')
@section('meta_description', 'Tin tức, sự kiện và chương trình khuyến mãi mới nhất từ Cinema Premium.')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/pages/posts.css') }}">
@endpush

@section('content')
<main class="content-page container py-5">
    <header class="content-page-header mb-5">
        <span class="content-eyebrow">CINEMA JOURNAL</span>
        <h1>Bài viết mới nhất</h1>
        <p>Cập nhật tin phim, sự kiện và ưu đãi đang diễn ra.</p>
    </header>

    @if($posts->isEmpty())
        <div class="content-empty">Chưa có bài viết nào được xuất bản.</div>
    @else
        <div class="post-grid">
            @foreach($posts as $post)
                <article class="post-card">
                    <a href="{{ route('posts.show', ['post' => $post->slug]) }}" class="post-card-image" aria-label="Xem {{ $post->title }}">
                        <img src="{{ $post->featured_image ? asset('storage/' . $post->featured_image) : asset('images/default-banner.jpg') }}"
                             alt="{{ $post->title }}" loading="lazy">
                    </a>
                    <div class="post-card-body">
                        <div class="post-card-meta">
                            <span>{{ strtoupper($post->category) }}</span>
                            <time datetime="{{ $post->published_at?->toISOString() }}">{{ $post->published_at?->format('d/m/Y') }}</time>
                        </div>
                        <h2><a href="{{ route('posts.show', ['post' => $post->slug]) }}">{{ $post->title }}</a></h2>
                        <p>{{ $post->excerpt }}</p>
                        <a href="{{ route('posts.show', ['post' => $post->slug]) }}" class="post-read-more">Đọc bài viết <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="content-pagination mt-5">{{ $posts->links() }}</div>
    @endif
</main>
@endsection
