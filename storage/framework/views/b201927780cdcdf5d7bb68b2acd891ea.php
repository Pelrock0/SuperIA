<?php
    $horizontalTabs = $crud->getTabsType()=='horizontal' ? true : false;
    $tabWithError = (function() use ($crud) {
        if(! session()->get('errors')) {
            return false;
        }
        foreach(session()->get('errors')->getBags() as $bag => $errorMessages) {
            foreach($errorMessages->getMessages() as $fieldName => $messages) {

                // extract the "parent" field name by separating the field name string by `.digit.` (eg. relation.0.fieldName)
                // and take the first element,  AKA the "parent" name. In the above example: `relation` 
                $fieldName = preg_split('/\.\d+\./',$fieldName)[0];

                if(array_key_exists($fieldName, $crud->getCurrentFields()) && array_key_exists('tab', $crud->getCurrentFields()[$fieldName])) {
                    return $crud->getCurrentFields()[$fieldName]['tab'];
                }
            }
        }
        return false;
    })();
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->getFieldsWithoutATab()->filter(function ($value, $key) { return $value['type'] != 'hidden'; })->count()): ?>
<div class="card">
    <div class="card-body row">
    <?php echo $__env->make('crud::inc.show_fields', ['fields' => $crud->getFieldsWithoutATab()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
</div>
<?php else: ?>
    <?php echo $__env->make('crud::inc.show_fields', ['fields' => $crud->getFieldsWithoutATab()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="tab-container <?php echo e($horizontalTabs ? '' : 'container'); ?> mb-2">

    <?php $formId = $formId ?? 'crudForm'; ?>
    <div class="nav-tabs-custom <?php echo e($horizontalTabs ? '' : 'row'); ?>" id="form_tabs" data-form-id="<?php echo e($formId); ?>">
        <ul class="nav <?php echo e($horizontalTabs ? 'nav-tabs' : 'flex-column nav-pills'); ?> <?php echo e($horizontalTabs ? '' : 'col-md-3'); ?>" role="tablist">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->getTabs(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $tabSlug = Str::slug($tab);
                if(empty($tabSlug)) {
                    $tabSlug = $k;
                }
            ?>
                <li role="presentation" class="nav-item">
                    <a href="#tab_<?php echo e($tabSlug); ?>_<?php echo e($formId); ?>"
                        aria-controls="tab_<?php echo e($tabSlug); ?>_<?php echo e($formId); ?>"
                        role="tab"
                        data-toggle="tab" 
                        tab_name="<?php echo e($tabSlug); ?>" 
                        data-name="<?php echo e($tabSlug); ?>" 
                        data-bs-toggle="tab" 
            class="nav-link text-decoration-none <?php echo e(isset($tabWithError) && $tabWithError ? ($tab == $tabWithError ? 'active' : '') : ($k == 0 ? 'active' : '')); ?>"
                        ><?php echo e($tab); ?></a>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>

        <div class="tab-content <?php echo e($horizontalTabs ? '' : 'col-md-9'); ?>">

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->getTabs(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $tabLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $tabSlug = Str::slug($tabLabel);
                if(empty($tabSlug)) {
                    $tabSlug = $k;
                }
            ?>
            <div role="tabpanel" class="tab-pane <?php echo e(isset($tabWithError) && $tabWithError ? ($tabLabel == $tabWithError ? ' active' : '') : ($k == 0 ? ' active' : '')); ?>" id="tab_<?php echo e($tabSlug); ?>_<?php echo e($formId); ?>">

                <div class="row">
                    <?php echo $__env->make('crud::inc.show_fields', ['fields' => $crud->getTabItems($tabLabel, 'fields')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>
    </div>
</div>

<?php $__env->startPush('crud_fields_styles'); ?>
    <style>
        .nav-tabs-custom {
            box-shadow: none;
        }
        .nav-tabs-custom > .nav-tabs.nav-stacked > li {
            margin-right: 0;
        }

        .tab-pane .form-group h1:first-child,
        .tab-pane .form-group h2:first-child,
        .tab-pane .form-group h3:first-child {
            margin-top: 0;
        }

        /*
            when select2 is multiple and it's not on the first displayed tab the placeholder would
            not display correctly because the element was not "visible" on the page (hidden by tab)
            thus getting `0px` width. This makes sure that the placeholder element is always 100% width
            by preventing the select2 inline style (0px) from applying using !important
        */
        .select2-search__field {
            width: 100% !important;
        }
    </style>
<?php $__env->stopPush(); ?>

<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/inc/show_tabbed_fields.blade.php ENDPATH**/ ?>