<!DOCTYPE html>

<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(backpack_theme_config('html_direction')); ?>">

<head>
    <?php echo $__env->make(backpack_view('inc.head'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        footer {
            width: 100%;
            position: fixed;
            bottom: 0;
            background-color: transparent !important;
            border: none !important;
        }
        .switch-mode {
            position: absolute;
            top: 0;
            right: 0;
            z-index: 999;
        }
    </style>
</head>

<body class="<?php echo e(backpack_theme_config('classes.body')); ?> <?php if(backpack_theme_config('auth_layout') === 'cover'): ?> d-flex flex-column theme-light <?php endif; ?>">

<?php echo $__env->make(backpack_view('layouts.partials.light_dark_mode_logic'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('options.showColorModeSwitcher')): ?>
    <div class="switch-mode p-3">
        <?php echo $__env->renderWhen(backpack_theme_config('options.showColorModeSwitcher'), backpack_view('layouts.partials.switch_theme'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo $__env->yieldContent('content'); ?>

<?php echo $__env->yieldContent('before_scripts'); ?>
<?php echo $__env->yieldPushContent('before_scripts'); ?>

<?php echo $__env->make(backpack_view('inc.footer'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make(backpack_view('inc.scripts'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make(backpack_view('inc.theme_scripts'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->yieldContent('after_scripts'); ?>
<?php echo $__env->yieldPushContent('after_scripts'); ?>
</body>
</html><?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/layouts/auth.blade.php ENDPATH**/ ?>