<li class="nav-item">
    <a <?php echo e($attributes->merge(['class' => 'nav-link', 'href' => $link])); ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon): ?><i class="nav-icon <?php echo e($icon); ?> d-block d-lg-none d-xl-block"></i><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title): ?><span><?php echo e($title); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </a>
</li>
<?php /**PATH I:\proyectos\SuperIA\vendor/backpack/theme-tabler/resources/views/components/menu-item.blade.php ENDPATH**/ ?>