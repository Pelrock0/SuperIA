<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($widgets)): ?>
	<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $widgets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currentWidget): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

		<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($currentWidget)): ?>
			<?php
				$currentWidget = \Backpack\CRUD\app\Library\Widget::add($currentWidget);
			?>
		<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

		<?php echo $__env->make($currentWidget->getFinalViewPath(), ['widget' => $currentWidget->toArray()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

	<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/inc/widgets.blade.php ENDPATH**/ ?>