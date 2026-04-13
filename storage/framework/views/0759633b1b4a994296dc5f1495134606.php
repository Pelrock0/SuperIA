<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->status === 'pending'): ?>
    <form action="<?php echo e(url(config('backpack.base.route_prefix') . '/waitlist-entry/' . $entry->getKey() . '/invite')); ?>"
          method="POST" style="display:inline;"
          onsubmit="return confirm('¿Enviar invitación a <?php echo e($entry->email); ?>?')">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-sm btn-success">
            <i class="la la-envelope"></i> Invitar
        </button>
    </form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH I:\proyectos\SuperIA\resources\views/vendor/backpack/crud/buttons/invite.blade.php ENDPATH**/ ?>