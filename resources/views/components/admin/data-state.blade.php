@props([
    'id' => null,
    'type' => 'empty',
    'icon' => 'bi-inbox',
    'title',
    'message' => null,
    'hidden' => true,
])

<div @if($id) id="{{ $id }}" @endif {{ $attributes->class(['admin-data-state', "admin-data-state--{$type}", 'd-none' => $hidden]) }} data-state-panel="{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <i class="bi {{ $icon }} admin-data-state__icon" aria-hidden="true"></i>
    <h3 class="admin-data-state__title">{{ $title }}</h3>
    @if($message)
        <p class="admin-data-state__message">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
