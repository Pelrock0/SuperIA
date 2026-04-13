
<?php
    $column['value'] = $column['value'] ?? data_get($entry, $column['name']);
    $column['escaped'] = $column['escaped'] ?? true;
    $column['prefix'] = $column['prefix'] ?? '';
    $column['suffix'] = $column['suffix'] ?? '';
    $column['format'] = $column['format'] ?? backpack_theme_config('default_date_format');
    $column['locale'] = $column['locale'] ?? App::getLocale();
    $column['text'] = $column['default'] ?? '-';

    if($column['value'] instanceof \Closure) {
        $column['value'] = $column['value']($entry);
    }

    if(!empty($column['value'])) {
        $date = \Carbon\Carbon::parse($column['value']);

        if ($column['format'] instanceof \Closure) {
            $date = $column['format']($date, $entry);
        } else {
            $date = $date->locale($column['locale'])
                ->isoFormat($column['format']);
        }

        $column['text'] = $column['prefix'].$date.$column['suffix'];
    }
?>

<span data-order="<?php echo e($column['value'] ?? ''); ?>">
    <?php echo $__env->renderWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_start', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($column['escaped']): ?>
            <?php echo e($column['text']); ?>

        <?php else: ?>
            <?php echo $column['text']; ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->renderWhen(!empty($column['wrapper']), 'crud::columns.inc.wrapper_end', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
</span>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/columns/date.blade.php ENDPATH**/ ?>