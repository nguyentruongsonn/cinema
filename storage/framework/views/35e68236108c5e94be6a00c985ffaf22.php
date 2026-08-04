<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'post',
    'excerptLimit' => 95,
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
    'post',
    'excerptLimit' => 95,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<article <?php echo e($attributes->class(['content-post-card'])); ?>>
    <a href="<?php echo e(route('posts.show', ['post' => $post->slug])); ?>" class="content-post-card__media">
        <img src="<?php echo e($post->image_url); ?>" alt="<?php echo e($post->title); ?>" loading="lazy" decoding="async">
        <span class="content-post-card__badge"><?php echo e($post->category_label); ?></span>
    </a>

    <div class="content-post-card__body">
        <time class="content-post-card__meta" datetime="<?php echo e($post->published_at?->toISOString()); ?>">
            <?php echo e($post->published_at?->format('d/m/Y')); ?>

        </time>
        <h3 class="content-post-card__title">
            <a href="<?php echo e(route('posts.show', ['post' => $post->slug])); ?>"><?php echo e($post->title); ?></a>
        </h3>
        <p class="content-post-card__excerpt"><?php echo e(Str::limit($post->excerpt, $excerptLimit)); ?></p>
    </div>
</article>
<?php /**PATH C:\xampp\htdocs\cinema\resources\views/components/user/post-card.blade.php ENDPATH**/ ?>