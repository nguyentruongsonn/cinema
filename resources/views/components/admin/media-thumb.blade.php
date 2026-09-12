@props([
    'src' => null,
    'alt' => 'Thumbnail',
    'icon' => 'bi-film',
    'sizeClass' => '',
])

<div class="movie-poster-container admin-media-thumb {{ $sizeClass }}">
    @if($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" data-admin-image-fallback="{{ $icon }}">
    @else
        <i class="bi {{ $icon }} text-white-50 fs-3"></i>
    @endif
</div>
