<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->buttons()->where('stack', $stack)->count()): ?>
	<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->buttons()->where('stack', $stack); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $button): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
	  <?php echo $button->getHtml($entry ?? null, $crud, $crudTableId ?? null); ?>

	<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/inc/button_stack.blade.php ENDPATH**/ ?>