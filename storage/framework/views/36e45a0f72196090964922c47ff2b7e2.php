<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->hasAccess('show', $entry)): ?>
	<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$crud->model->translationEnabled() || $crud->getOperationSetting('showLanguagesDirectlyInShowButton') === false): ?>

	
	<a href="<?php echo e(url($crud->route.'/'.$entry->getKey().'/show')); ?>" bp-button="show" class="btn btn-sm btn-link">
		<i class="la la-eye"></i> <span><?php echo e(trans('backpack::crud.preview')); ?></span>
	</a>

	<?php else: ?>

	
	<div class="btn-group">
	  <a href="<?php echo e(url($crud->route.'/'.$entry->getKey().'/show')); ?>" class="btn btn-sm btn-link pr-0">
	  	<span><i class="la la-eye"></i> <?php echo e(trans('backpack::crud.preview')); ?></span>
	  </a>
	  <a class="btn btn-sm btn-link dropdown-toggle text-primary pl-1" data-toggle="dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	    <span class="caret"></span>
	  </a>
	  <ul class="dropdown-menu dropdown-menu-right">
  	    <li class="dropdown-header"><?php echo e(trans('backpack::crud.preview')); ?>:</li>
	  	<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->model->getAvailableLocales(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $locale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
		  	<a class="dropdown-item" href="<?php echo e(url($crud->route.'/'.$entry->getKey().'/show')); ?>?_locale=<?php echo e($key); ?>"><?php echo e($locale); ?></a>
	  	<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
	  </ul>
	</div>

	<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/buttons/show.blade.php ENDPATH**/ ?>