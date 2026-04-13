<!DOCTYPE html>

<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(backpack_theme_config('html_direction')); ?>">

<head>
    <?php echo $__env->make(backpack_view('inc.head'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</head>

<body class="<?php echo e(backpack_theme_config('classes.body')); ?>" bp-layout="vertical-dark">

<?php echo $__env->make(backpack_view('layouts.partials.light_dark_mode_logic'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="page">

    <?php echo $__env->make(backpack_view('layouts._vertical_dark.menu_container'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="page-wrapper">

        <div class="page-body">
            <main class="<?php echo e(backpack_theme_config('options.useFluidContainers') ? 'container-fluid' : 'container-xxl'); ?>">

                <?php echo $__env->yieldContent('before_breadcrumbs_widgets'); ?>
                <?php echo $__env->renderWhen(isset($breadcrumbs), backpack_view('inc.breadcrumbs'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
                <?php echo $__env->yieldContent('after_breadcrumbs_widgets'); ?>
                <?php echo $__env->yieldContent('header'); ?>

                <div class="container-fluid animated fadeIn">
                    <?php echo $__env->yieldContent('before_content_widgets'); ?>
                    <?php echo $__env->yieldContent('content'); ?>
                    <?php echo $__env->yieldContent('after_content_widgets'); ?>
                </div>
            </main>
        </div>

        <?php echo $__env->make(backpack_view('inc.footer'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
</div>

<?php echo $__env->yieldContent('before_scripts'); ?>
<?php echo $__env->yieldPushContent('before_scripts'); ?>

<?php echo $__env->make(backpack_view('inc.scripts'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make(backpack_view('inc.theme_scripts'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->yieldContent('after_scripts'); ?>
<?php echo $__env->yieldPushContent('after_scripts'); ?>
</body>
</html>
<?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/layouts/vertical_dark.blade.php ENDPATH**/ ?>