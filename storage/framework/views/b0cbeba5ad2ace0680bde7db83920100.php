
<?php
    $column['value'] = $column['value'] ?? data_get($entry, $column['name']);
    $column['wrapper']['element'] = $column['wrapper']['element'] ?? 'a';
    $column['wrapper']['href'] = $column['wrapper']['href'] ?? 'mailto:'.$column['value'];
?>
<?php echo $__env->make('crud::columns.text', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/columns/email.blade.php ENDPATH**/ ?>