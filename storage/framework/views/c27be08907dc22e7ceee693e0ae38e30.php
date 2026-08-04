<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'eyebrow' => null,
    'title',
    'titleId' => null,
    'href' => null,
    'linkLabel' => 'Xem tất cả',
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
    'eyebrow' => null,
    'title',
    'titleId' => null,
    'href' => null,
    'linkLabel' => 'Xem tất cả',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<header <?php echo e($attributes->class(['content-section-header'])); ?>>
    <div>
        <?php if($eyebrow): ?>
            <span class="content-section-eyebrow"><?php echo e($eyebrow); ?></span>
        <?php endif; ?>
        <h2 <?php if($titleId): ?> id="<?php echo e($titleId); ?>" <?php endif; ?> class="content-section-title"><?php echo e($title); ?></h2>
    </div>

    <?php if($href): ?>
        <a href="<?php echo e($href); ?>" class="content-section-link">
            <span><?php echo e($linkLabel); ?></span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
</header>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/components/user/content-section-header.blade.php ENDPATH**/ ?>