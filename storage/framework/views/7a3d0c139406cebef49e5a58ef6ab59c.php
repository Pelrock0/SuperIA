<?php
    $defaultBreadcrumbs = [
      trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
      $crud->entity_name_plural => url($crud->route),
      trans('backpack::crud.preview') => false,
    ];

    // if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
?>

<?php $__env->startSection('header'); ?>
    <div class="container-fluid d-flex justify-content-between my-3">
        <section class="header-operation animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
            <h1 class="text-capitalize mb-0" bp-section="page-heading"><?php echo $crud->getHeading() ?? $crud->entity_name_plural; ?></h1>
            <p class="ms-2 ml-2 mb-0" bp-section="page-subheading"><?php echo $crud->getSubheading() ?? mb_ucfirst(trans('backpack::crud.preview')).' '.$crud->entity_name; ?></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->hasAccess('list')): ?>
                <p class="ms-2 ml-2 mb-0" bp-section="page-subheading-back-button">
                    <small><a href="<?php echo e(url($crud->route)); ?>" class="font-sm"><i class="la la-angle-double-left"></i> <?php echo e(trans('backpack::crud.back_to_all')); ?> <span><?php echo e($crud->entity_name_plural); ?></span></a></small>
                </p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <a href="javascript: window.print();" class="btn float-end float-right"><i class="la la-print"></i></a>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="row" bp-section="crud-operation-show">
    <div class="<?php echo e($crud->getShowContentClass()); ?>">

	
	<div class="">
	<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->model->translationEnabled()): ?>
		<div class="row">
			<div class="col-md-12 mb-2" bp-section="show-operation-language-dropdown">
				
				<div class="btn-group float-right">
				<button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<?php echo e(trans('backpack::crud.language')); ?>: <?php echo e($crud->model->getAvailableLocales()[request()->input('_locale')?request()->input('_locale'):App::getLocale()]); ?> &nbsp; <span class="caret"></span>
				</button>
				<ul class="dropdown-menu">
					<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->model->getAvailableLocales(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $locale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
						<a class="dropdown-item" href="<?php echo e(url($crud->route.'/'.$entry->getKey().'/show')); ?>?_locale=<?php echo e($key); ?>"><?php echo e($locale); ?></a>
					<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
				</ul>
				</div>
			</div>
		</div>
	<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
		<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->tabsEnabled() && count($crud->getUniqueTabNames('columns'))): ?>
			<?php echo $__env->make('crud::inc.show_tabbed_table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
		<?php else: ?>
			<div class="card no-padding no-border mb-0">
				<?php echo $__env->make('crud::inc.show_table', ['columns' => $crud->columns()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
			</div>
		<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
	</div>
	</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(backpack_view('blank'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/show.blade.php ENDPATH**/ ?>