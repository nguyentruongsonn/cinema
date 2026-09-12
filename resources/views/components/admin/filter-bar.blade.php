@props([
    'searchId' => 'search',
    'searchPlaceholder' => 'Tìm kiếm...',
    'btnCreateId' => null,
    'btnCreateLabel' => null,
    'btnCreateIcon' => 'bi-plus-lg',
    'formId' => 'searchForm',
])

<div class="admin-filter-container">
    <div class="admin-filter-bar">
        @isset($filters)
            <div class="admin-filter-fields">{{ $filters }}</div>
        @endisset

        <form id="{{ $formId }}" class="admin-filter-search">
            <div class="input-group">
                <input type="search" id="{{ $searchId }}" class="admin-filter-input search-input-rounded-left"
                       placeholder="{{ $searchPlaceholder }}" autocomplete="off">
                <button class="admin-filter-btn search-btn-rounded-right" type="submit" aria-label="Tìm kiếm">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        {{ $slot }}

        @if($btnCreateLabel)
            <button type="button" @if($btnCreateId) id="{{ $btnCreateId }}" @endif class="admin-action-btn admin-filter-primary-action">
                <i class="bi {{ $btnCreateIcon }}"></i>
                <span>{{ $btnCreateLabel }}</span>
            </button>
        @endif
    </div>
</div>
