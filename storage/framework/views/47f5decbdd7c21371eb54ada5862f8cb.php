<form action="<?php echo e(url(config('backpack.base.route_prefix') . '/waitlist-entry/export-csv')); ?>"
      method="POST" style="display:inline;">
    <?php echo csrf_field(); ?>
    <button type="submit" class="btn btn-primary">
        <i class="la la-download"></i> Exportar CSV
    </button>
</form>
<?php /**PATH I:\proyectos\SuperIA\resources\views/vendor/backpack/crud/buttons/export_csv.blade.php ENDPATH**/ ?>