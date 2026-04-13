<?php extract((new \Illuminate\Support\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['entry','crud','columns','displayButtons']));

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

foreach (array_filter((['entry','crud','columns','displayButtons']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if (isset($component)) { $__componentOriginal60a4518995ca89560abb6b097214f1c9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal60a4518995ca89560abb6b097214f1c9 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\Datagrid::resolve(['entry' => $entry,'crud' => $crud,'columns' => $columns,'displayButtons' => $displayButtons] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('bp-datagrid'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\Datagrid::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['attributes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes)]); ?>

<?php echo e($slot ?? ""); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal60a4518995ca89560abb6b097214f1c9)): ?>
<?php $attributes = $__attributesOriginal60a4518995ca89560abb6b097214f1c9; ?>
<?php unset($__attributesOriginal60a4518995ca89560abb6b097214f1c9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal60a4518995ca89560abb6b097214f1c9)): ?>
<?php $component = $__componentOriginal60a4518995ca89560abb6b097214f1c9; ?>
<?php unset($__componentOriginal60a4518995ca89560abb6b097214f1c9); ?>
<?php endif; ?><?php /**PATH I:\proyectos\SuperIA\storage\framework\views/2468d3dc360e104b75983b6dabb2cab2.blade.php ENDPATH**/ ?>