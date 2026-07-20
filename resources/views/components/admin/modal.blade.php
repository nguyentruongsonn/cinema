@props([
    'id',
    'labelledBy' => null,
    'size' => null,
    'backdrop' => null,
])

<div
    {{ $attributes->class(['modal', 'fade']) }}
    id="{{ $id }}"
    tabindex="-1"
    @if($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
    aria-hidden="true"
    @if($backdrop) data-bs-backdrop="{{ $backdrop }}" @endif
>
    <div @class(['modal-dialog', 'modal-dialog-centered', $size => $size])>
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" @if($labelledBy) id="{{ $labelledBy }}" @endif>
                    {{ $title }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
