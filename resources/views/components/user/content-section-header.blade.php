@props([
    'eyebrow' => null,
    'title',
    'titleId' => null,
    'href' => null,
    'linkLabel' => 'Xem tất cả',
])

<header {{ $attributes->class(['content-section-header']) }}>
    <div>
        @if($eyebrow)
            <span class="content-section-eyebrow">{{ $eyebrow }}</span>
        @endif
        <h2 @if($titleId) id="{{ $titleId }}" @endif class="content-section-title">{{ $title }}</h2>
    </div>

    @if($href)
        <a href="{{ $href }}" class="content-section-link">
            <span>{{ $linkLabel }}</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    @endif
</header>
