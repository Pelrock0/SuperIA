<?php Basset::basset('https://cdn.jsdelivr.net/npm/jquery@3.6.1/dist/jquery.min.js'); ?>
<?php Basset::basset('https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js'); ?>
<?php Basset::basset('https://cdn.jsdelivr.net/npm/noty@3.2.0-beta-deprecated/lib/noty.min.js'); ?>
<?php Basset::basset('https://cdn.jsdelivr.net/npm/sweetalert@2.1.2/dist/sweetalert.min.js'); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('scripts') && count(backpack_theme_config('scripts'))): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = backpack_theme_config('scripts'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $path): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($path)): ?>
            <?php Basset::basset(...$path); ?>
        <?php else: ?>
            <?php Basset::basset($path); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('mix_scripts') && count(backpack_theme_config('mix_scripts'))): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = backpack_theme_config('mix_scripts'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $path => $manifest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <script type="text/javascript" src="<?php echo e(mix($path, $manifest)); ?>"></script>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('vite_scripts') && count(backpack_theme_config('vite_scripts'))): ?>
    <?php echo app('Illuminate\Foundation\Vite')(backpack_theme_config('vite_scripts')); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo $__env->make(backpack_view('inc.alerts'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('app.debug')): ?>
    <?php echo $__env->make('crud::inc.ajax_error_frame', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php $__env->startPush('after_scripts'); ?>
    <?php Basset::basset(base_path('vendor/backpack/crud/src/resources/assets/js/common.js')); ?>
<?php $__env->stopPush(); ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\ui/inc/scripts.blade.php ENDPATH**/ ?>