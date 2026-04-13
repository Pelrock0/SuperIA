
<?php
    $column['attribute'] = $column['attribute'] ?? (new $column['model'])->identifiableAttribute();
    $column['value'] = $column['value'] ?? $crud->getRelatedEntriesAttributes($entry, $column['entity'], $column['attribute']);
    $column['escaped'] = $column['escaped'] ?? true;
    $column['prefix'] = $column['prefix'] ?? '';
    $column['suffix'] = $column['suffix'] ?? '';
    $column['limit'] = $column['limit'] ?? 32;

    if($column['value'] instanceof \Closure) {
        $column['value'] = $column['value']($entry);
    }

    foreach ($column['value'] as &$value) {
        $value = Str::limit($value, $column['limit'], '…');
    }
?>

<span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($column['value'])): ?>
        <?php echo e($column['prefix']); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $column['value']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $text): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $related_key = $key;
            ?>

            <span class="d-inline-flex">
                <?php echo $__env->renderWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_start', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($column['escaped']): ?>
                        <?php echo e($text); ?>

                    <?php else: ?>
                        <?php echo $text; ?>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php echo $__env->renderWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_end', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>, <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </span>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo e($column['suffix']); ?>

    <?php else: ?>
        <?php echo e($column['default'] ?? '-'); ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</span>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/columns/select.blade.php ENDPATH**/ ?>