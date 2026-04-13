<?php $__env->startSection('content'); ?>
    <div class="row g-0 flex-fill">
        <div class="col-12 col-lg-6 col-xl-4 border-top-wide border-primary d-flex flex-column justify-content-center">
            <div class="container container-tight my-5 px-lg-5">
                <div class="text-center mb-4 display-6 auth-logo-container">
                   <img src="<?php echo backpack_theme_config('project_logo_img'); ?>" />
                </div>
                <div class="text-center mb-4 display-6 auth-logo-container">
                  <?php echo backpack_theme_config('project_logo'); ?>

                </div>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Session::has('message')): ?>
                
                    <p class="alert alert-danger"><?php echo e(Session::get('message')); ?></p>
                    <?php
                    if(Session::has('message')){
                        Session::forget('message');
                    }   
                ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <!-- os la app env está en local o la url contiene como path /loginDoubleAuth -->
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->is('loginDoubleAuth')): ?>
                    <span><?php echo e(__('opa.views.login_double_auth')); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(env("APP_ENV") == 'local' || (request()->is('loginDoubleAuth') && env("LOGIN_DOUBLE_AUTH_ENABLED") == 'true')): ?>
                    <?php echo $__env->make(backpack_view('auth.login.inc.form'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



                
            </div>
        </div>
        <div class="col-12 col-lg-6 col-xl-8 d-none d-lg-block">
            <div class="bg-cover h-100 min-vh-100" style="background-image: url(<?php echo e(asset('img/sede.jpg')); ?>)"></div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.self !== window.top) {
               window.top.location.reload();
            }
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make(backpack_view('layouts.auth'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH I:\proyectos\SuperIA\resources/views/vendor/backpack/theme-tabler/auth/login/cover.blade.php ENDPATH**/ ?>