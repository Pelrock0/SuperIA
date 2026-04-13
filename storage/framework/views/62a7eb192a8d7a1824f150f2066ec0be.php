

<?php
  $field['value'] = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '';
  $field['attributes']['class'] = $field['attributes']['class'] ?? 'form-check-input';
?>

<?php echo $__env->make('crud::fields.inc.wrapper_start', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('crud::fields.inc.translatable_icon', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <div class="form-check d-flex mb-0 align-items-center">
        <input type="hidden" name="<?php echo e($field['name']); ?>" value="<?php echo e($field['value']); ?>">
    	  <input type="checkbox"
          data-init-function="bpFieldInitCheckbox"

          <?php if((bool)$field['value']): ?>
            checked="checked"
          <?php endif; ?>

          <?php if(isset($field['attributes'])): ?>
            <?php $__currentLoopData = $field['attributes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attribute => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    			  <?php echo e($attribute); ?>="<?php echo e($value); ?>"
        	  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php endif; ?>
          >
    	  <label class="font-weight-normal fw-normal mb-0 ml-2 ms-2"><?php echo $field['label']; ?></label>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($field['hint'])): ?>
            <p class="help-block ml-2 ms-2"><?php echo $field['hint']; ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
<?php echo $__env->make('crud::fields.inc.wrapper_end', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>





    
    <?php $__env->startPush('crud_fields_scripts'); ?>
        <?php $bassetBlock = 'backpack/crud/fields/checkbox-field.js'; ob_start(); ?>
        <script>
            function bpFieldInitCheckbox(element) {
                var hidden_element = element.siblings('input[type=hidden]');
                var id = 'checkbox_'+Math.floor(Math.random() * 1000000);

                // make sure the value is a boolean (so it will pass validation)
                if (hidden_element.val() === '') hidden_element.val(0).trigger('change');

                // set unique IDs so that labels are correlated with inputs
                element.attr('id', id);
                element.siblings('label').attr('for', id);

                // set the default checked/unchecked state
                // if the field has been loaded with javascript
                if (hidden_element.val() != 0) {
                  element.prop('checked', 'checked');
                } else {
                  element.prop('checked', false);
                }

                hidden_element.on('CrudField:disable', function(e) {
                  element.prop('disabled', true);
                });
                hidden_element.on('CrudField:enable', function(e) {
                  element.removeAttr('disabled');
                });

                // when the checkbox is clicked
                // set the correct value on the hidden input
                element.change(function() {
                  if (element.is(":checked")) {
                    hidden_element.val(1).trigger('change');
                  } else {
                    hidden_element.val(0).trigger('change');
                  }
                })
            }
        </script>
        <?php Basset::bassetBlock($bassetBlock, ob_get_clean()); ?>
    <?php $__env->stopPush(); ?>



<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/fields/checkbox.blade.php ENDPATH**/ ?>