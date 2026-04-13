<?php $__env->startSection('after_scripts'); ?>
<script type="module">
    let input = document.querySelector('.password-visibility-toggler input');
    let buttons = document.querySelectorAll('.password-visibility-toggler a');

    buttons.forEach(button => {
        button.addEventListener('click', () => {
            buttons.forEach(b => b.classList.toggle('d-none'));
            input.type = input.type === 'password' ? 'text' : 'password';
            input.focus();
        });
    });
</script>
<?php $__env->stopSection(); ?>

<h2 class="h2 text-center my-4"><?php echo e(trans('backpack::base.login')); ?></h2>
<form method="POST" action="<?php echo e(route('backpack.auth.login')); ?>" autocomplete="off" novalidate="">
    <?php echo csrf_field(); ?>
    <div class="mb-3">
        <label class="form-label" for="<?php echo e($username); ?>"><?php echo e(trans('backpack::base.'.strtolower(config('backpack.base.authentication_column_name')))); ?></label>
        <input autofocus tabindex="1" type="text" name="<?php echo e($username); ?>" value="<?php echo e(old($username)); ?>" id="<?php echo e($username); ?>" class="form-control <?php echo e($errors->has($username) ? 'is-invalid' : ''); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->has($username)): ?>
            <div class="invalid-feedback"><?php echo e($errors->first($username)); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="mb-2">
        <label class="form-label" for="password">
            <?php echo e(trans('backpack::base.password')); ?>

        </label>
        <div class="input-group input-group-flat password-visibility-toggler">
            <input tabindex="2" type="password" name="password" id="password" class="form-control <?php echo e($errors->has('password') ? 'is-invalid' : ''); ?>" value="">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('options.showPasswordVisibilityToggler')): ?>
            <span class="input-group-text p-0 px-2">
                <a href="#" data-input-type="text" class="link-secondary p-2" data-bs-toggle="tooltip" aria-label="<?php echo e(trans('backpack.theme-tabler::theme-tabler.password-show')); ?>" data-bs-original-title="<?php echo e(trans('backpack.theme-tabler::theme-tabler.password-show')); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"></path><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"></path></svg>
                </a>
                <a href="#" data-input-type="password" class="link-secondary p-2 d-none" data-bs-toggle="tooltip" aria-label="<?php echo e(trans('backpack.theme-tabler::theme-tabler.password-hide')); ?>" data-bs-original-title="<?php echo e(trans('backpack.theme-tabler::theme-tabler.password-hide')); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye-off" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.585 10.587a2 2 0 0 0 2.829 2.828" /><path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87" /><path d="M3 3l18 18" /></svg>
                </a>
            </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->has('password')): ?>
            <div class="invalid-feedback"><?php echo e($errors->first('password')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-check mb-0">
            <input name="remember" tabindex="3" type="checkbox" class="form-check-input">
            <span class="form-check-label"><?php echo e(trans('backpack::base.remember_me')); ?></span>
        </label>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_users_have_email() && backpack_email_column() == 'email' && config('backpack.base.setup_password_recovery_routes', true)): ?>
            <div class="form-label-description">
                <a tabindex="4" href="<?php echo e(route('backpack.auth.password.reset')); ?>"><?php echo e(trans('backpack::base.forgot_your_password')); ?></a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="form-footer">
        <button tabindex="5" type="submit" class="btn btn-primary w-100"><?php echo e(trans('backpack::base.login')); ?></button>
    </div>
</form>
<?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/auth/login/inc/form.blade.php ENDPATH**/ ?>