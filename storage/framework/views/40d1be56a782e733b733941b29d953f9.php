<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('show_powered_by') || backpack_theme_config('developer_link')): ?>
    <footer class="d-print-none <?php echo e(backpack_theme_config('classes.footer') ?? 'footer app-footer sticky-footer bg-transparent p-3 border-top-0'); ?>">
        <div class="<?php echo e(backpack_theme_config('options.useFluidContainers') ? 'container-fluid' : 'container-xxl'); ?>">
            <div class="<?php if(backpack_theme_config('developer_link') && backpack_theme_config('developer_name') && backpack_theme_config('show_powered_by')): ?> row <?php endif; ?> text-center align-items-center flex-row-reverse">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('show_powered_by')): ?>
                    <div class="col-lg-auto ms-lg-auto">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                <?php echo e(trans('backpack::base.powered_by')); ?>

                                <a href="https://backpackforlaravel.com?ref=panel_footer_link" rel="noopener" target="_blank">Backpack for Laravel</a>.
                            </li>
                        </ul>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(backpack_theme_config('developer_link') && backpack_theme_config('developer_name')): ?>
                    <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                <?php echo e(trans('backpack::base.handcrafted_by')); ?>

                                <a href="<?php echo e(backpack_theme_config('developer_link')); ?>" rel="noopener" target="_blank"><?php echo e(backpack_theme_config('developer_name')); ?></a>.
                            </li>
                        </ul>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </footer>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/inc/footer.blade.php ENDPATH**/ ?>