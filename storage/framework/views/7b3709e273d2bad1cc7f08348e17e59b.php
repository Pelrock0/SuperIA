<div class="nav-item dropdown pe-3">
    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
        <span class="avatar avatar-sm rounded-circle">
            <img class="avatar avatar-sm rounded-circle bg-transparent" src="<?php echo e(backpack_avatar_url(backpack_auth()->user())); ?>"
                alt="<?php echo e(backpack_auth()->user()->name); ?>" onerror="this.style.display='none'"
                style="margin: 0;position: absolute;left: 0;z-index: 1;">
            <span class="avatar avatar-sm rounded-circle backpack-avatar-menu-container text-center">
                <?php echo e(backpack_user()->getAttribute('name') ? mb_substr(backpack_user()->name, 0, 1, 'UTF-8') : 'A'); ?>

            </span>
        </span>
        <div class="d-none d-xl-block ps-2">
            <div><?php echo e(backpack_user()->name); ?></div>
            <div class="mt-1 small text-muted"><?php echo e(trans('backpack::crud.admin')); ?></div>
        </div>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('backpack.base.setup_my_account_routes')): ?>
            <a href="<?php echo e(route('backpack.account.info')); ?>" class="dropdown-item"><i class="la la-user me-2"></i><?php echo e(trans('backpack::base.my_account')); ?></a>
            <div class="dropdown-divider"></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <a href="<?php echo e(backpack_url('logout')); ?>" class="dropdown-item"><i class="la la-lock me-2"></i><?php echo e(trans('backpack::base.logout')); ?></a>
    </div>
</div>
<?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/inc/menu_user_dropdown.blade.php ENDPATH**/ ?>