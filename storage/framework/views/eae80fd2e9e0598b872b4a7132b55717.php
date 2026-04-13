
<?php
    $column['value'] = $column['value'] ?? data_get($entry, $column['name']);
    $column['escaped'] = $column['escaped'] ?? true;
    $column['prefix'] = $column['prefix'] ?? '';
    $column['suffix'] = $column['suffix'] ?? '';
    $column['decimals'] = $column['decimals'] ?? 0;
    $column['dec_point'] = $column['dec_point'] ?? '.';
    $column['thousands_sep'] = $column['thousands_sep'] ?? ',';
    $column['text'] = $column['default'] ?? '-';

    if($column['value'] instanceof \Closure) {
        $column['value'] = $column['value']($entry);
    }
    
    if (!is_null($column['value'])) {
        $column['value'] = number_format($column['value'], $column['decimals'], $column['dec_point'], $column['thousands_sep']);
        $column['text'] = $column['prefix'].$column['value'].$column['suffix'];
    }
?>

<span>
    <?php echo $__env->renderWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_start', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($column['escaped']): ?>
            <?php echo e($column['text']); ?>

        <?php else: ?>
            <?php echo $column['text']; ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->renderWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_end', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
</span>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/columns/number.blade.php ENDPATH**/ ?>