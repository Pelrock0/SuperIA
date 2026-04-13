<?php if (isset($component)) { $__componentOriginalead85e76a923e64d9eae23947232cf9a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalead85e76a923e64d9eae23947232cf9a = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuItem::resolve(['title' => 'DevTools','icon' => 'la la-toolbox','link' => backpack_url('devtools/model')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalead85e76a923e64d9eae23947232cf9a)): ?>
<?php $attributes = $__attributesOriginalead85e76a923e64d9eae23947232cf9a; ?>
<?php unset($__attributesOriginalead85e76a923e64d9eae23947232cf9a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalead85e76a923e64d9eae23947232cf9a)): ?>
<?php $component = $__componentOriginalead85e76a923e64d9eae23947232cf9a; ?>
<?php unset($__componentOriginalead85e76a923e64d9eae23947232cf9a); ?>
<?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\devtools\src/../resources/views/buttons/sidebar_item.blade.php ENDPATH**/ ?>