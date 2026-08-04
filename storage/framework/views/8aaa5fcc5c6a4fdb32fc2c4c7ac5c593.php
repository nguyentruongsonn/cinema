<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'cols' => 6,
    'rows' => 5,
    'hasImage' => false,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'cols' => 6,
    'rows' => 5,
    'hasImage' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php for($r = 0; $r < $rows; $r++): ?>
    <tr class="admin-table-skeleton-row">
        <td class="text-center">
            <div class="admin-skeleton admin-skeleton-text skeleton-w-30 skeleton-center"></div>
        </td>
        <?php if($hasImage): ?>
            <td class="text-center">
                <div class="admin-skeleton admin-skeleton-img skeleton-img-sm skeleton-center"></div>
            </td>
        <?php endif; ?>
        <td>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-70"></div>
            <div class="admin-skeleton admin-skeleton-text skeleton-w-40"></div>
        </td>
        <?php for($c = 0; $c < ($cols - ($hasImage ? 3 : 2) - 1); $c++): ?>
            <td class="text-center">
                <div class="admin-skeleton admin-skeleton-text skeleton-w-65 skeleton-center"></div>
            </td>
        <?php endfor; ?>
        <td class="text-center">
            <div class="admin-skeleton admin-skeleton-button-sm skeleton-center"></div>
        </td>
    </tr>
<?php endfor; ?>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/components/admin/skeleton-table.blade.php ENDPATH**/ ?>