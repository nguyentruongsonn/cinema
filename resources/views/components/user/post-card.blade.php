@props([
    'post',
    'excerptLimit' => 95,
])

<article {{ $attributes->class(['content-post-card']) }}>
    <a href="{{ route('posts.show', ['post' => $post->slug]) }}" class="content-post-card__media">
        <img src="{{ $post->image_url }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
        <span class="content-post-card__badge">{{ $post->category_label }}</span>
    </a>

    <div class="content-post-card__body">
        <time class="content-post-card__meta" datetime="{{ $post->published_at?->toISOString() }}">
            {{ $post->published_at?->format('d/m/Y') }}
        </time>
        <h3 class="content-post-card__title">
            <a href="{{ route('posts.show', ['post' => $post->slug]) }}">{{ $post->title }}</a>
        </h3>
        <p class="content-post-card__excerpt">{{ Str::limit($post->excerpt, $excerptLimit) }}</p>
    </div>
</article>
