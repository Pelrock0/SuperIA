<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->hasAccess('create')): ?>
    
    <a href="<?php echo e(url($crud->route.'/create')); ?>" class="btn btn-primary" bp-button="create" data-style="zoom-in">
        <i class="la la-plus"></i> <span><?php echo e(trans('backpack::crud.add')); ?> <?php echo e($crud->entity_name); ?></span>
    </a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/buttons/create.blade.php ENDPATH**/ ?>