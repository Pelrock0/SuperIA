
<li class="nav-item"><a class="nav-link" href="<?php echo e(backpack_url('dashboard')); ?>"><i class="la la-home nav-icon"></i> <?php echo e(trans('backpack::base.dashboard')); ?></a></li>
<?php echo $__env->renderWhen(class_exists(\Backpack\DevTools\DevToolsServiceProvider::class), 'backpack.devtools::buttons.sidebar_item', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

<?php if (isset($component)) { $__componentOriginal3304fc1ec27516a666a2f68d6da76d86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3304fc1ec27516a666a2f68d6da76d86 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdown::resolve(['title' => 'Administración','icon' => 'la la-industry'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <?php if (isset($component)) { $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdownItem::resolve(['title' => 'Usuarios','icon' => 'la la-user','link' => backpack_url('user')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdownItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $attributes = $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $component = $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdownItem::resolve(['title' => 'Lista de espera','icon' => 'la la-clock','link' => backpack_url('waitlist-entry')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdownItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $attributes = $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $component = $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdownItem::resolve(['title' => 'Consumo IA','icon' => 'la la-robot','link' => backpack_url('ai-usage')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdownItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $attributes = $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $component = $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdownItem::resolve(['title' => 'Roles','icon' => 'la la-group','link' => backpack_url('role')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdownItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $attributes = $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $component = $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdownItem::resolve(['title' => 'Permisos','icon' => 'la la-key','link' => backpack_url('permission')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdownItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $attributes = $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $component = $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0 = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\MenuDropdownItem::resolve(['title' => 'Ajustes','icon' => 'la la-cog','link' => backpack_url('setting')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::menu-dropdown-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\MenuDropdownItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $attributes = $__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__attributesOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0)): ?>
<?php $component = $__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0; ?>
<?php unset($__componentOriginal4a7c4d33fcbfdc491dc37cabf6bac1f0); ?>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3304fc1ec27516a666a2f68d6da76d86)): ?>
<?php $attributes = $__attributesOriginal3304fc1ec27516a666a2f68d6da76d86; ?>
<?php unset($__attributesOriginal3304fc1ec27516a666a2f68d6da76d86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3304fc1ec27516a666a2f68d6da76d86)): ?>
<?php $component = $__componentOriginal3304fc1ec27516a666a2f68d6da76d86; ?>
<?php unset($__componentOriginal3304fc1ec27516a666a2f68d6da76d86); ?>
<?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\resources\views/vendor/backpack/ui/inc/menu_items.blade.php ENDPATH**/ ?>