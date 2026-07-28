@props([
    'src' => null,
    'alt' => 'Thumbnail',
    'icon' => 'bi-film',
    'sizeClass' => '',
])

<div class="movie-poster-container admin-media-thumb {{ $sizeClass }}">
    @if($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" onerror="this.outerHTML='<i class=\'bi {{ $icon }} text-white-50 fs-3\'></i>'">
    @else
        <i class="bi {{ $icon }} text-white-50 fs-3"></i>
    @endif
</div>
