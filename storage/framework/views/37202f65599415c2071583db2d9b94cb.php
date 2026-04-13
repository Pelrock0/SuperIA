<?php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    $crud->entity_name_plural => url($crud->route),
    trans('backpack::crud.list') => false,
  ];

  // if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
?>

<?php $__env->startSection('content'); ?>
  
  <div class="row" bp-section="crud-operation-list">

    
    <div class="<?php echo e($crud->getListContentClass()); ?>">

      <?php if (isset($component)) { $__componentOriginal80990212c744ca518f80f80947a3193f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80990212c744ca518f80f80947a3193f = $attributes; } ?>
<?php $component = Backpack\CRUD\app\View\Components\Datatable::resolve(['controller' => $controller,'crud' => $crud,'modifiesUrl' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backpack::datatable'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Backpack\CRUD\app\View\Components\Datatable::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80990212c744ca518f80f80947a3193f)): ?>
<?php $attributes = $__attributesOriginal80990212c744ca518f80f80947a3193f; ?>
<?php unset($__attributesOriginal80990212c744ca518f80f80947a3193f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80990212c744ca518f80f80947a3193f)): ?>
<?php $component = $__componentOriginal80990212c744ca518f80f80947a3193f; ?>
<?php unset($__componentOriginal80990212c744ca518f80f80947a3193f); ?>
<?php endif; ?>

    </div>

  </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make(backpack_view('blank'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/list.blade.php ENDPATH**/ ?>