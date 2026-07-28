@extends('layouts.app')

@section('title', $post->title . ' - Cinema Premium')
@section('meta_description', $post->excerpt)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users/pages/posts.css') }}">
@endpush

@section('content')
<main class="article-page container py-5">
    <article class="article-shell">
        <a href="{{ route('posts.index') }}" class="article-back"><i class="bi bi-arrow-left"></i> Tất cả bài viết</a>
        <header class="article-header">
            <div class="post-card-meta">
                <span>{{ strtoupper($post->category) }}</span>
                <time datetime="{{ $post->published_at?->toISOString() }}">{{ $post->published_at?->format('d/m/Y H:i') }}</time>
                <span>{{ number_format($post->view_count) }} lượt xem</span>
            </div>
            <h1>{{ $post->title }}</h1>
            @if($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
        </header>

        @if($post->featured_image)
            <img class="article-cover" src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}">
        @endif

        <div class="article-content">{!! $safeContent !!}</div>
        <footer class="article-footer">Tác giả: {{ $post->author?->name ?? 'Cinema Premium' }}</footer>
    </article>
</main>
@endsection
