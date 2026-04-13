<div class="saveActions form-group my-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($saveAction['active']) && !is_null($saveAction['active']['value'])): ?>
    
        <input type="hidden" name="_save_action" value="<?php echo e($saveAction['active']['value']); ?>">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($saveAction['options'])): ?>
            <button type="submit" class="btn btn-success text-white">
                <span class="la la-save" role="presentation" aria-hidden="true"></span> &nbsp;
                <span data-value="<?php echo e($saveAction['active']['value']); ?>"><?php echo e($saveAction['active']['label']); ?></span>
            </button>
        <?php else: ?>
            <div class="btn-group" role="group">
                <button type="submit" class="btn btn-success text-white">
                    <span class="la la-save" role="presentation" aria-hidden="true"></span> &nbsp;
                    <span data-value="<?php echo e($saveAction['active']['value']); ?>"><?php echo e($saveAction['active']['label']); ?></span>
                </button>
                <button type="button" class="bpSaveButtonsGroup btn btn-success text-white dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu" aria-labelledby="bpSaveButtonsGroup">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $saveAction['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><button class="dropdown-item" type="button" data-value="<?php echo e($value); ?>"><?php echo e($label); ?></button></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$crud->hasOperationSetting('showCancelButton') || $crud->getOperationSetting('showCancelButton') == true): ?>
        <a href="<?php echo e($crud->hasAccess('list') ? url($crud->route) : url()->previous()); ?>" class="btn btn-secondary text-decoration-none"><span class="la la-ban"></span> &nbsp;<?php echo e(trans('backpack::crud.cancel')); ?></a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->get('update.showDeleteButton') && $crud->get('delete.configuration') && $crud->hasAccess('delete')): ?>
        <button onclick="confirmAndDeleteEntry()" type="button" class="btn btn-danger float-right float-end"><i class="la la-trash-alt"></i> <?php echo e(trans('backpack::crud.delete')); ?></button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<?php $__env->startPush('after_scripts'); ?>
<script>

    // this function checks if form is valid.
    function checkFormValidity(form) {
        // the condition checks if `checkValidity` is defined in the form (browser compatibility)
        if (form[0].checkValidity) {
            return form[0].checkValidity();
        }
        return false;
    }

    // this function checks if any of the inputs has errors and report them on page.
    // we use it to report the errors after form validation fails and making the error fields visible
    function reportValidity(form) {
        // the condition checks if `reportValidity` is defined in the form (browser compatibility)
        if (form[0].reportValidity) {
            // hide the save actions drop down if open
            form.find('.dropdown-menu').removeClass('show');
            // validate and display form errors
            form[0].reportValidity();
        }
    }

    function changeTabIfNeededAndDisplayErrors(form) {
        // we get the first erroed field
        var $firstErrorField = form.find(":invalid").first();
        // we find the closest tab
        var $closestTab = $($firstErrorField).closest('.tab-pane');
        // if we found the tab we will change to that tab before reporting validity of form
        if($closestTab.length) {
            var id = $closestTab.attr('id');
                // switch tabs
                $('.nav a[href="#' + id + '"]').tab('show');
        }
        reportValidity(form);
    }

    // make all submit buttons trigger HTML5 validation
    jQuery(document).ready(function($) {
        // Find all save actions buttons and attach handlers to each one individually
        $('.saveActions').each(function() {
            var saveActionsContainer = $(this);
            var form = saveActionsContainer.closest('form');
            var saveActionField = form.find('[name="_save_action"]');
            var defaultSubmitButton = form.find(':submit').first();
            
            // Handle the main submit button (default save action)
            defaultSubmitButton.on('click', function(e) {
                e.preventDefault();
                var $saveAction = $(this).find('span:last');
                
                // if form is valid just submit it
                if(checkFormValidity(form)) {
                    saveActionField.val($saveAction.attr('data-value'));
                    form[0].requestSubmit();
                } else {
                    // navigate to the tab where the first error happens
                    changeTabIfNeededAndDisplayErrors(form);
                }
            });

            // Handle the dropdown save actions
            saveActionsContainer.find('.dropdown-item').each(function() {
                $(this).click(function(e) {
                    // we check if form is valid
                    if (checkFormValidity(form)) {
                        // if everything is validated we proceed with the submission
                        var saveAction = $(this).data('value');
                        saveActionField.val(saveAction);
                        form[0].requestSubmit();
                    } else {
                        // navigate to the tab where the first error happens
                        changeTabIfNeededAndDisplayErrors(form);
                    }
                    e.stopPropagation();
                });
            });
        });
    });
</script>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->get('update.showDeleteButton') && $crud->get('delete.configuration') && $crud->hasAccess('delete')): ?>
<script>
    function confirmAndDeleteEntry() {
        // Ask for confirmation before deleting an item
        swal({
            title: "<?php echo trans('backpack::base.warning'); ?>",
            text: "<?php echo trans('backpack::crud.delete_confirm'); ?>",
            icon: "warning",
            buttons: {
          	cancel: {
                text: "<?php echo trans('backpack::crud.cancel'); ?>",
                value: null,
                visible: true,
                className: "bg-secondary",
                closeModal: true,
            },
            delete: {
                text: "<?php echo trans('backpack::crud.delete'); ?>",
                value: true,
                visible: true,
                className: "bg-danger",
                },
            },
            dangerMode: true,
        }).then((value) => {
            if (value) {
                $.ajax({
                    url: '<?php echo e(url($crud->route.'/'.$entry->getKey())); ?>',
                    type: 'DELETE',
                    success: function(result) {
                        if (result !== '1') {
                            // if the result is an array, it means
                            // we have notification bubbles to show
                            if (result instanceof Object) {
                                // trigger one or more bubble notifications
                                Object.entries(result).forEach(function(entry) {
                                    var type = entry[0];
                                    entry[1].forEach(function(message, i) {
                                        new Noty({
                                            type: type,
                                            text: message
                                        }).show();
                                    });
                                });
                            } else { // Show an error alert
                                swal({
                                    title: "<?php echo trans('backpack::crud.delete_confirmation_not_title'); ?>",
                                    text: "<?php echo trans('backpack::crud.delete_confirmation_not_message'); ?>",
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        }
                        // All is good, show a success message!
                        swal({
                            title: "<?php echo trans('backpack::crud.delete_confirmation_title'); ?>",
                            text: "<?php echo trans('backpack::crud.delete_confirmation_message'); ?>",
                            icon: "success",
                            buttons: false,
                            closeOnClickOutside: false,
                            closeOnEsc: false,
                        });

                        // Redirect in 1 sec so that admins get to see the success message
                        setTimeout(function () {
                            window.location.href = '<?php echo e(is_bool($crud->get('update.showDeleteButton')) ? url($crud->route) : (string) $crud->get('update.showDeleteButton')); ?>';
                        }, 1000);
                    },
                    error: function() {
                        // Show an alert with the result
                        swal({
                            title: "<?php echo trans('backpack::crud.delete_confirmation_not_title'); ?>",
                            text: "<?php echo trans('backpack::crud.delete_confirmation_not_message'); ?>",
                            icon: "error",
                            timer: 4000,
                            buttons: false,
                        });
                    }
                });
            }
        });
    }
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopPush(); ?><?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/inc/form_save_buttons.blade.php ENDPATH**/ ?>