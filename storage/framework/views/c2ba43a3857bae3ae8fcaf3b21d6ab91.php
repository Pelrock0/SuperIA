<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($auth ?? true): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_auth()->guest()): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo e(route('backpack.auth.login')); ?>">
                <i class="nav-icon la la-sign-in-alt d-block d-lg-none d-xl-block"></i> <span><?php echo e(trans('backpack::base.login')); ?></span>
            </a>
        </li>
    <?php else: ?>
        <li class="nav-item dropdown d-none d-lg-block">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="true">
                <span class="avatar avatar-sm rounded-circle me-2">
                    <img class="avatar avatar-sm rounded-circle bg-transparent" src="<?php echo e(backpack_avatar_url(backpack_auth()->user())); ?>"
                        alt="<?php echo e(backpack_auth()->user()->name); ?>" onerror="this.style.display='none'"
                        style="margin: 0;position: absolute;left: 0;z-index: 1;">
                    <span class="avatar avatar-sm rounded-circle backpack-avatar-menu-container text-center">
                        <?php echo e(backpack_user()->getAttribute('name') ? mb_substr(backpack_user()->name, 0, 1, 'UTF-8') : 'A'); ?>

                    </span>
                </span>
                <?php echo e(backpack_user()->name); ?>

            </a>
            <div class="dropdown-menu" data-bs-popper="static">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('backpack.base.setup_my_account_routes')): ?>
                    <a class="dropdown-item" href="<?php echo e(route('backpack.account.info')); ?>">
                        <i class="nav-icon la la-lock d-block"></i>
                        <?php echo e(trans('backpack::base.my_account')); ?>

                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a class="dropdown-item text-danger" href="<?php echo e(backpack_url('logout')); ?>">
                    <i class="nav-icon la la-sign-out-alt d-block"></i>
                    <?php echo e(trans('backpack::base.logout')); ?>

                </a>
            </div>
        </li>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <li class="nav-separator"></li>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/layouts/_vertical/sidebar_content_top.blade.php ENDPATH**/ ?>