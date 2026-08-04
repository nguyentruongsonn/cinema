@props([
    'id',
    'titleId' => null,
    'title' => '',
    'icon' => 'bi-pencil-square',
    'size' => 'modal-lg',
    'formId' => null,
    'formAction' => null,
    'formMethod' => 'POST',
    'submitLabel' => 'Lưu thay đổi',
    'submitIcon' => 'bi-check-circle',
    'submitBtnId' => null,
    'cancelLabel' => 'Hủy',
    'submitClass' => 'btn btn-danger d-flex align-items-center gap-2',
    'bodyClass' => '',
    'dialogClass' => '',
    'contentClass' => 'bg-dark text-white border-secondary',
    'formEnctype' => null,
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" @if($titleId) aria-labelledby="{{ $titleId }}" @endif aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $size }} {{ $dialogClass }}">
        <div class="modal-content {{ $contentClass }}">
            <div class="modal-header border-secondary">
                <h5 class="modal-title d-flex align-items-center gap-2" @if($titleId) id="{{ $titleId }}" @endif>
                    <i class="bi {{ $icon }} text-danger"></i>
                    <span>{{ $title }}</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @if($formId)
                <form id="{{ $formId }}" @if($formAction) action="{{ $formAction }}" @endif method="{{ $formMethod }}" @if($formEnctype) enctype="{{ $formEnctype }}" @endif novalidate>
            @endif
                <div class="modal-body {{ $bodyClass }}">
                    {{ $slot }}
                </div>
                <div class="modal-footer border-secondary">
                    @isset($footer)
                        {{ $footer }}
                    @else
                        @if($cancelLabel)
                            <button type="button" class="btn text-white btn-modal-cancel admin-modal-cancel" data-bs-dismiss="modal">{{ $cancelLabel }}</button>
                        @endif
                        @if($submitLabel)
                            <button type="submit" @if($submitBtnId) id="{{ $submitBtnId }}" @endif class="{{ $submitClass }}">
                                <i class="bi {{ $submitIcon }}"></i>
                                <span>{{ $submitLabel }}</span>
                            </button>
                        @endif
                    @endisset
                </div>
            @if($formId)
                </form>
            @endif
        </div>
    </div>
</div>
