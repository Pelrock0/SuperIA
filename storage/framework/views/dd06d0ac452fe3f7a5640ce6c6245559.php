<?php
  // Define the table ID - use the provided tableId or default to 'crudTable'
  $tableId = $tableId ?? 'crudTable';
  $fixedHeader = $useFixedHeader ?? $crud->getOperationSetting('useFixedHeader') ?? true;
?>
<section class="header-operation datatable-header animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
          <h1 class="text-capitalize mb-0" bp-section="page-heading"><?php echo $crud->getHeading() ?? $crud->entity_name_plural; ?></h1>
          <p class="ms-2 ml-2 mb-0" id="datatable_info_stack_<?php echo e($tableId); ?>" bp-section="page-subheading"><?php echo $crud->getSubheading() ?? ''; ?></p>
        </section>
<div class="row mb-2 align-items-center">
  <div class="col-sm-9">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if( $crud->buttons()->where('stack', 'top')->count() ||  $crud->exportButtons()): ?>
      <div class="d-print-none <?php echo e($crud->hasAccess('create')?'with-border':''); ?> top_buttons_<?php echo e($tableId); ?>">
        <?php echo $__env->make('crud::inc.button_stack', ['stack' => 'top', 'crudTableId' => $tableId], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->getOperationSetting('searchableTable')): ?>
  <div class="col-sm-3">
    <div id="datatable_search_stack_<?php echo e($tableId); ?>" class="mt-sm-0 mt-2 d-print-none datatable_search_stack">
      <div class="input-icon">
        <span class="input-icon-addon">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path><path d="M21 21l-6 -6"></path></svg>
        </span>
        <input type="search" class="form-control datatable-search-input" data-table-id="<?php echo e($tableId); ?>" placeholder="<?php echo e(trans('backpack::crud.search')); ?>..."/>
      </div>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($crud->filtersEnabled()): ?>
  <?php echo $__env->make('crud::inc.filters_navbar', ['componentId' => $tableId], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="<?php echo e(backpack_theme_config('classes.tableWrapper')); ?>">
  <table
      id="<?php echo e($tableId); ?>"
      class="<?php echo e(backpack_theme_config('classes.table') ?? 'table table-striped table-hover nowrap rounded card-table table-vcenter card d-table shadow-xs border-xs'); ?> crud-table"
      data-use-fixed-header="<?php echo e($fixedHeader ? 'true' : 'false'); ?>"
      data-responsive-table="<?php echo e((int) $crud->getOperationSetting('responsiveTable')); ?>"
      data-has-details-row="<?php echo e((int) $crud->getOperationSetting('detailsRow')); ?>"
      data-has-bulk-actions="<?php echo e((int) $crud->getOperationSetting('bulkActions')); ?>"
      data-has-line-buttons-as-dropdown="<?php echo e((int) $crud->getOperationSetting('lineButtonsAsDropdown')); ?>"
      data-line-buttons-as-dropdown-minimum="<?php echo e((int) $crud->getOperationSetting('lineButtonsAsDropdownMinimum')); ?>"
      data-line-buttons-as-dropdown-show-before-dropdown="<?php echo e((int) $crud->getOperationSetting('lineButtonsAsDropdownShowBefore')); ?>"
      data-url-start="<?php echo e($datatablesUrl); ?>"
      data-responsive-table="<?php echo e($crud->getResponsiveTable() ? 'true' : 'false'); ?>"
      data-persistent-table="<?php echo e($crud->getPersistentTable() ? 'true' : 'false'); ?>"
      data-persistent-table-slug="<?php echo e(Str::slug($crud->getOperationSetting('datatablesUrl'))); ?>"
      data-persistent-table-duration="<?php echo e($crud->getPersistentTableDuration() ?: ''); ?>"
      data-subheading="<?php echo e($crud->getSubheading() ? 'true' : 'false'); ?>"
      data-reset-button="<?php echo e(($crud->getOperationSetting('resetButton') ?? true) ? 'true' : 'false'); ?>"
      data-modifies-url="<?php echo e(($modifiesUrl ?? false) ? 'true' : 'false'); ?>"
      data-has-export-buttons="<?php echo e(var_export($crud->get('list.exportButtons') ?? false)); ?>"
      data-default-page-length="<?php echo e($crud->getDefaultPageLength()); ?>"
      data-page-length-menu="<?php echo e(json_encode($crud->getPageLengthMenu())); ?>"
      data-show-entry-count="<?php echo e(var_export($crud->getOperationSetting('showEntryCount') ?? true)); ?>"
      data-searchable-table="<?php echo e(var_export($crud->getOperationSetting('searchableTable') ?? true)); ?>"
      data-search-delay="<?php echo e($crud->getOperationSetting('searchDelay') ?? 500); ?>"
      data-total-entry-count="<?php echo e(var_export($crud->getOperationSetting('totalEntryCount') ?? false)); ?>"
      cellspacing="0">
    <thead>
      <tr>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->columns(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
          $exportOnlyColumn = $column['exportOnlyColumn'] ?? false;
          $visibleInTable = $column['visibleInTable'] ?? ($exportOnlyColumn ? false : true);
          $visibleInModal = $column['visibleInModal'] ?? ($exportOnlyColumn ? false : true);
          $visibleInExport = $column['visibleInExport'] ?? true;
          $forceExport = $column['forceExport'] ?? (isset($column['exportOnlyColumn']) ? true : false);
          ?>
          <th
            data-orderable="<?php echo e(var_export($column['orderable'], true)); ?>"
            data-priority="<?php echo e($column['priority']); ?>"
            data-column-name="<?php echo e($column['name']); ?>"
            

            data-visible="<?php echo e($exportOnlyColumn ? 'false' : var_export($visibleInTable)); ?>"
            data-visible-in-table="<?php echo e(var_export($visibleInTable)); ?>"
            data-can-be-visible-in-table="<?php echo e($exportOnlyColumn ? 'false' : 'true'); ?>"
            data-visible-in-modal="<?php echo e(var_export($visibleInModal)); ?>"
            data-visible-in-export="<?php echo e($exportOnlyColumn ? 'true' : ($visibleInExport ? 'true' : 'false')); ?>"
            data-force-export="<?php echo e(var_export($forceExport)); ?>"
          >
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->first && $crud->getOperationSetting('bulkActions')): ?>
                <?php echo View::make('crud::columns.inc.bulk_actions_checkbox')->render(); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php echo $column['label']; ?>

          </th>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if( $crud->buttons()->where('stack', 'line')->count() ): ?>
          <th data-orderable="false"
              data-priority="<?php echo e($crud->getActionsColumnPriority()); ?>"
              data-visible-in-export="false"
              data-action-column="true"
              ><?php echo e(trans('backpack::crud.actions')); ?></th>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
      <tr>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $crud->columns(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <th>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->first && $crud->getOperationSetting('bulkActions')): ?>
                <?php echo View::make('crud::columns.inc.bulk_actions_checkbox')->render(); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php echo $column['label']; ?>

          </th>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if( $crud->buttons()->where('stack', 'line')->count() ): ?>
          <th><?php echo e(trans('backpack::crud.actions')); ?></th>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tr>
    </tfoot>
  </table>
</div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if( $crud->buttons()->where('stack', 'bottom')->count() ): ?>
    <div id="bottom_buttons_<?php echo e($tableId); ?>" class="bottom_buttons d-print-none text-sm-left">
        <?php echo $__env->make('crud::inc.button_stack', ['stack' => 'bottom', 'crudTableId' => $tableId], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div id="datatable_button_stack_<?php echo e($tableId); ?>" class="datatable_button_stack float-right float-end text-right hidden-xs"></div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php $__env->startSection('after_styles'); ?>
  
  <?php echo $__env->yieldPushContent('crud_list_styles'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('after_scripts'); ?>
  <?php echo $__env->make('crud::components.datatable.datatable_logic', ['tableId' => $tableId], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('crud::inc.export_buttons', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('crud::inc.details_row_logic', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <?php echo $__env->yieldPushContent('crud_list_scripts'); ?>
<?php $__env->stopSection(); ?>
<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\crud\src\resources\views\crud/components/datatable/datatable.blade.php ENDPATH**/ ?>