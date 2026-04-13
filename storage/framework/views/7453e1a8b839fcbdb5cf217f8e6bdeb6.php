<?php Basset::basset('https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.compat.css'); ?>
<?php Basset::basset('https://cdn.jsdelivr.net/npm/noty@3.2.0-beta-deprecated/lib/noty.css'); ?>

<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-regular-400.woff2'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-solid-900.woff2'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-brands-400.woff2'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-regular-400.woff'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-solid-900.woff'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-brands-400.woff'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-regular-400.ttf'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-solid-900.ttf'); ?>
<?php Basset::basset('https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/fonts/la-brands-400.ttf'); ?>

<?php Basset::basset(base_path('vendor/backpack/crud/src/resources/assets/css/common.css')); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('styles') && count(backpack_theme_config('styles'))): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = backpack_theme_config('styles'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $path): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($path)): ?>
            <?php Basset::basset(...$path); ?>
        <?php else: ?>
            <?php Basset::basset($path); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('mix_styles') && count(backpack_theme_config('mix_styles'))): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = backpack_theme_config('mix_styles'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $path => $manifest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <link rel="stylesheet" type="text/css" href="<?php echo e(mix($path, $manifest)); ?>">
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('vite_styles') && count(backpack_theme_config('vite_styles'))): ?>
    <?php echo app('Illuminate\Foundation\Vite')(backpack_theme_config('vite_styles')); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\ui/inc/styles.blade.php ENDPATH**/ ?>