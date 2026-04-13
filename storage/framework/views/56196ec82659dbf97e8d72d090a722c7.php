<?php
	// if not otherwise specified, the hidden input should take up no space in the form
  $field['wrapper'] = $field['wrapper'] ?? $field['wrapperAttributes'] ?? [];
  $field['wrapper']['class'] = $field['wrapper']['class'] ?? "hidden";
?>


<?php echo $__env->make('crud::fields.inc.wrapper_start', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <input
  	type="hidden"
    name="<?php echo e($field['name']); ?>"
    value="<?php echo e(old_empty_or_null($field['name'], '') ??  $field['value'] ?? $field['default'] ?? ''); ?>"
    <?php echo $__env->make('crud::fields.inc.attributes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  	>
<?php echo $__env->make('crud::fields.inc.wrapper_end', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/fields/hidden.blade.php ENDPATH**/ ?>