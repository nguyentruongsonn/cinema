@props([
    'searchId' => 'search',
    'searchPlaceholder' => 'Tìm kiếm...',
    'btnCreateId' => null,
    'btnCreateLabel' => null,
    'btnCreateIcon' => 'bi-plus-lg',
    'formId' => 'searchForm',
])

<div class="card bg-dark border-secondary mb-4">
    <div class="card-body p-3">
        <form id="{{ $formId }}" class="row g-2 align-items-center" onsubmit="return false;">
            <div class="col-12 col-md flex-grow-1">
                <div class="input-group">
                    <span class="input-group-text bg-dark text-white-50 border-secondary">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text"
                           id="{{ $searchId }}"
                           class="form-control bg-dark text-white border-secondary"
                           placeholder="{{ $searchPlaceholder }}"
                           autocomplete="off">
                </div>
            </div>

            {{ $slot }}

            @if($btnCreateLabel)
                <div class="col-12 col-md-auto">
                    <button type="button"
                            @if($btnCreateId) id="{{ $btnCreateId }}" @endif
                            class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                        <i class="bi {{ $btnCreateIcon }}"></i>
                        <span>{{ $btnCreateLabel }}</span>
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
