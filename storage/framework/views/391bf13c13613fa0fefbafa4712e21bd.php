<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'id' => null,
    'type' => 'empty',
    'icon' => 'bi-inbox',
    'title',
    'message' => null,
    'hidden' => true,
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
    'id' => null,
    'type' => 'empty',
    'icon' => 'bi-inbox',
    'title',
    'message' => null,
    'hidden' => true,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php if($id): ?> id="<?php echo e($id); ?>" <?php endif; ?> <?php echo e($attributes->class(['cinema-data-state', "cinema-data-state--{$type}", 'd-none' => $hidden])); ?> data-state-panel="<?php echo e($type); ?>" role="<?php echo e($type === 'error' ? 'alert' : 'status'); ?>">
    <i class="bi <?php echo e($icon); ?> cinema-data-state__icon" aria-hidden="true"></i>
    <h3 class="cinema-data-state__title"><?php echo e($title); ?></h3>
    <?php if($message): ?>
        <p class="cinema-data-state__message"><?php echo e($message); ?></p>
    <?php endif; ?>
    <?php echo e($slot); ?>

</div>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/components/user/data-state.blade.php ENDPATH**/ ?>