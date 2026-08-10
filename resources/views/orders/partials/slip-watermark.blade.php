@php
    $watermarkRows = max(1, (int) ($rows ?? 12));
    $watermarkColumns = max(1, (int) ($columns ?? 3));
    $watermarkWidth = floor(100 / $watermarkColumns).'%';
    $watermarkEmailMode = (bool) ($emailMode ?? false);
@endphp

<div
    class="slip-watermark slip-watermark-pattern"
    data-watermark-mode="{{ $watermarkEmailMode ? 'email' : 'print' }}"
    aria-hidden="true"
    style="bottom:auto;color:#000;font-family:Arial,sans-serif;font-size:18px;font-weight:900;{{ $watermarkEmailMode ? 'height:0;max-height:0;overflow:visible;' : 'height:120%;overflow:hidden;' }}left:-20%;line-height:1;opacity:.035;pointer-events:none;position:absolute;right:auto;top:-10%;transform:rotate(-22deg);transform-origin:center;width:140%;z-index:0;"
>
    @for($row = 0; $row < $watermarkRows; $row++)
        <div style="height:38px;white-space:nowrap;">
            @for($column = 0; $column < $watermarkColumns; $column++)
                <span style="display:inline-block;text-align:center;width:{{ $watermarkWidth }};">CINEMA</span>
            @endfor
        </div>
    @endfor
</div>
