<input type="hidden" name="_http_referrer" value="<?php echo e(session('referrer_url_override') ?? old('_http_referrer') ?? \URL::previous() ?? url($crud->route)); ?>">
<input type="hidden" name="_form_id" value="<?php echo e($formId ?? $id ?? 'crudForm'); ?>">


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->tabsEnabled() && count($crud->getTabs())): ?>
    <?php echo $__env->make('crud::inc.show_tabbed_fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <input type="hidden" name="_current_tab" value="<?php echo e(Str::slug($crud->getTabs()[0])); ?>" />
<?php else: ?>
  <div class="<?php echo e(isset($formInsideCard) && $formInsideCard ? 'card' : (!isset($formInsideCard) ? 'card' : '')); ?>">
    <div class="<?php echo e(isset($formInsideCard) && $formInsideCard ? 'card-body' : (!isset($formInsideCard) ? 'card-body' : '')); ?> row">
      <?php echo $__env->make('crud::inc.show_fields', ['fields' => $crud->fields()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = app('widgets')->toArray(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currentWidget): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
    $currentWidget = \Backpack\CRUD\app\Library\Widget::add($currentWidget);
?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentWidget->getAttribute('inline')): ?>
        <?php echo $__env->make($currentWidget->getFinalViewPath(), ['widget' => $currentWidget->toArray()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



<?php $__env->startPush('after_styles'); ?>

    
    <?php echo $__env->yieldPushContent('crud_fields_styles'); ?>

<?php $__env->stopPush(); ?>

<?php $__env->startPush('before_scripts'); ?>
  <?php echo $__env->make('crud::inc.form_fields_script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('after_scripts'); ?>

    
    <?php echo $__env->yieldPushContent('crud_fields_scripts'); ?>

    <script>
    <?php echo $__env->make('crud::components.dataform.common_js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    jQuery('document').ready(function($){

      <?php if(! isset($initFields) || $initFields !== false): ?>
        initializeFieldsWithJavascript('form');
      <?php endif; ?>

      // Retrieves the current form data
      function getFormData() {
        let formData = new FormData(document.querySelector("main form"));
        // remove internal inputs from formData, the ones that start with "_", like _token, _http_referrer, etc.
        let pairs = [...formData].map(pair => pair[0]);
        for (let pair of pairs) {
          if (pair.startsWith('_')) {
            formData.delete(pair);
          }
        }
        return new URLSearchParams(formData).toString();
      }

      // Prevents unloading of page if form data was changed
      function preventUnload(event) {
        if (initData !== getFormData()) {
          // Cancel the event as stated by the standard.
          event.preventDefault();
          // Older browsers supported custom message
          event.returnValue = '';
        }
      }

      <?php if($crud->getOperationSetting('warnBeforeLeaving')): ?>
        const initData = getFormData();
        window.addEventListener('beforeunload', preventUnload);
      <?php endif; ?>

      // Save button has multiple actions: save and exit, save and edit, save and new
      document.querySelectorAll('form').forEach(function(form) {
          if (form.querySelector('.saveActions')) {
              // prevent duplicate entries on double-clicking the submit form
              form.addEventListener('submit', function(event) {
                  window.removeEventListener('beforeunload', preventUnload);
                  const submitButtons = form.querySelectorAll('button[type=submit]');
                  submitButtons.forEach(button => button.disabled = true);
              });
          }
      });
      
      // Ctrl+S and Cmd+S trigger Save button click
      document.addEventListener('keydown', function(e) {
          if ((e.which === 115 || e.which === 83) && (e.ctrlKey || e.metaKey)) {
              e.preventDefault();
              
              // Find the form that contains the currently focused element
              let activeForm = null;
              const focusedElement = document.activeElement;
              
              if (focusedElement) {
                  activeForm = focusedElement.closest('form');
                  // Check if this form has saveActions
                  if (!activeForm || !activeForm.querySelector('.saveActions')) {
                      activeForm = null;
                  }
              }
              
              // If no focused form with save actions, use the first form with save actions
              if (!activeForm) {
                  const formsWithSaveActions = document.querySelectorAll('form');
                  for (let form of formsWithSaveActions) {
                      if (form.querySelector('.saveActions')) {
                          activeForm = form;
                          break;
                      }
                  }
              }
              
              if (activeForm) {
                  const submitButton = activeForm.querySelector('.saveActions button[type=submit]');
                  
                  if (submitButton) {
                      submitButton.click();
                  } else {
                      // Create and dispatch a submit event
                      const submitEvent = new Event('submit', {
                          bubbles: true,
                          cancelable: true
                      });
                      activeForm.dispatchEvent(submitEvent);
                  }
              }
              return false;
          }
          return true;
      });

      // Place the focus on the first element in the form
      <?php if( $crud->getAutoFocusOnFirstField() ): ?>
        <?php
          $focusField = Arr::first($fields, function($field) {
              return isset($field['auto_focus']) && $field['auto_focus'] === true;
          });
        ?>

        let focusField, focusFieldTab;

        <?php if($focusField): ?>
          <?php
            $focusFieldName = isset($focusField['value']) && is_iterable($focusField['value']) ? $focusField['name'] . '[]' : $focusField['name'];
            $focusFieldTab = $focusField['tab'] ?? null;
          ?>
            focusFieldTab = '<?php echo e(Str::slug($focusFieldTab)); ?>';

                // if focus is not 'null' navigate to that tab before focusing.
                if(focusFieldTab !== 'null'){
                  try {
                    // find the form id stored in the hidden input within this form instance
                    const currentFormEl = focusField.closest('form');
                    const formIdInput = currentFormEl ? currentFormEl.querySelector('input[name="_form_id"]') : null;
                    const theFormId = formIdInput ? formIdInput.value : ('<?php echo e($formId ?? 'crudForm'); ?>');
                    const selector = `#form_tabs[data-form-id="${theFormId}"] a[tab_name="${focusFieldTab}"]`;
                    $(selector).tab('show');
                  } catch (e) {
                    // fallback to global selector
                    $('#form_tabs a[tab_name="'+focusFieldTab+'"]').tab('show');
                  }
                }
            focusField = $('[name="<?php echo e($focusFieldName); ?>"]').eq(0);
        <?php else: ?>
            focusField = getFirstFocusableField($('form'));
        <?php endif; ?>
        if(focusField.length !== 0) {
          const fieldOffset = focusField.offset().top;
          const scrollTolerance = $(window).height() / 2;

          triggerFocusOnFirstInputField(focusField);

          if( fieldOffset > scrollTolerance ){
              $('html, body').animate({scrollTop: (fieldOffset - 30)});
          }
        }
      <?php endif; ?>

      // Add inline errors to the DOM
      <?php if($crud->inlineErrorsEnabled() && session()->get('errors')): ?>

        window.errors = <?php echo json_encode(session()->get('errors')->getBags()); ?>;
        var submittedFormId = "<?php echo e(old('_form_id') ?? 'crudForm'); ?>";
        var currentFormId = '<?php echo e($formId ?? $id ?? 'crudForm'); ?>';

        // Only display errors if this is the form that was submitted
        if (submittedFormId && submittedFormId === currentFormId) {
          var firstErrorField = null;
          var firstErrorTab = null;
          
          $.each(errors, function(bag, errorMessages){
            $.each(errorMessages, function (inputName, messages) {
              var normalizedProperty = inputName.split('.').map(function(item, index){
                      return index === 0 ? item : '['+item+']';
                  }).join('');

              // Only select fields within the current form
              var field = $('#' + currentFormId + ' [name="' + normalizedProperty + '[]"]').length ?
                          $('#' + currentFormId + ' [name="' + normalizedProperty + '[]"]') :
                          $('#' + currentFormId + ' [name="' + normalizedProperty + '"]'),
                          container = field.closest('.form-group');

              // Store the first error field for focusing
              if (firstErrorField === null && field.length > 0) {
                firstErrorField = field.first();
                <?php if($crud->tabsEnabled()): ?>
                var tab_container = $(container).closest('[role="tabpanel"]');
                if (tab_container.length) {
                  firstErrorTab = tab_container.attr('id');
                }
                <?php endif; ?>
              }

              // iterate the inputs to add invalid classes to fields and red text to the field container.
              container.find('input, textarea, select').each(function() {
                  let containerField = $(this);
                  // add the invalid class to the field.
                  containerField.addClass('is-invalid');
                  // get field container
                  let container = containerField.closest('.form-group');

                  // TODO: `repeatable-group` should be deprecated in future version as a BC in favor of a more generic class `no-error-display`
                  if(!container.hasClass('repeatable-group') && !container.hasClass('no-error-display')){
                    container.addClass('text-danger');
                  }
              });

              $.each(messages, function(key, msg){
                  // highlight the input that errored
                  var row = $('<div class="invalid-feedback d-block">' + msg + '</div>');

                  // TODO: `repeatable-group` should be deprecated in future version as a BC in favor of a more generic class `no-error-display`
                  if(!container.hasClass('repeatable-group') && !container.hasClass('no-error-display')){
                    row.appendTo(container);
                  }


                  // highlight its parent tab
                  <?php if($crud->tabsEnabled()): ?>
                    var tab_id = $(container).closest('[role="tabpanel"]').attr('id');
                    try {
                      $('#form_tabs[data-form-id="' + (typeof currentFormId !== 'undefined' ? currentFormId : '<?php echo e($formId ?? 'crudForm'); ?>') + '"] [aria-controls="'+tab_id+'"]').addClass('text-danger');
                    } catch (e) {
                      $("#form_tabs [aria-controls="+tab_id+"]").addClass('text-danger');
                    }
                            <?php endif; ?>
                        });
                      });
                    });

                    // Focus on the first error field
                    if (firstErrorField !== null) {
                      <?php if($crud->tabsEnabled()): ?>
                      // Switch to the tab containing the first error if needed
                      if (firstErrorTab) {
                        try {
                            var selector = '#form_tabs[data-form-id="' + (typeof currentFormId !== 'undefined' ? currentFormId : '<?php echo e($formId ?? 'crudForm'); ?>') + '"] .nav a[href="#' + firstErrorTab + '"]';
                            $(selector).tab('show');
                        } catch (e) {
                            $('.nav a[href="#' + firstErrorTab + '"]').tab('show');
                        }
                      }
            <?php endif; ?>
            
            // Focus on the first error field
            setTimeout(function() {
              const fieldOffset = firstErrorField.offset().top;
              const scrollTolerance = $(window).height() / 2;
              
              triggerFocusOnFirstInputField(firstErrorField);
              
              if (fieldOffset > scrollTolerance) {
                $('html, body').animate({scrollTop: (fieldOffset - 30)});
              }
            }, 100);
          }
        }
      <?php endif; ?>

      $("a[data-bs-toggle='tab']").click(function(){
          currentTabName = $(this).attr('tab_name');
          $("input[name='_current_tab']").val(currentTabName);
      });

      if (window.location.hash) {
          $("input[name='_current_tab']").val(window.location.hash.substr(1));
      }
      });
    </script>
<?php $__env->stopPush(); ?><?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/form_content.blade.php ENDPATH**/ ?>