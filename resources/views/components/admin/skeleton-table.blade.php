@props([
    'cols' => 6,
    'rows' => 5,
    'hasImage' => false,
])

@for($r = 0; $r < $rows; $r++)
    <tr class="admin-table-skeleton-row">
        <td class="text-center">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div>
        </td>
        @if($hasImage)
            <td class="text-center">
                <div class="admin-skeleton admin-skeleton-img skeleton-img-sm skeleton-center"></div>
            </td>
        @endif
        <td>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-40"></div>
        </td>
        @for($c = 0; $c < ($cols - ($hasImage ? 3 : 2) - 1); $c++)
            <td class="text-center">
                <div class="admin-skeleton admin-skeleton-text skeleton-w-65 skeleton-center"></div>
            </td>
        @endfor
        <td class="text-center">
            <div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div>
        </td>
    </tr>
@endfor
