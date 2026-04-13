
<li filter-name="<?php echo e($filter->name); ?>"
    filter-type="<?php echo e($filter->type); ?>"
    filter-key="<?php echo e($filter->key); ?>"
	filter-init-function="<?php echo e($filter->init_function ?? 'initDropdownFilter'); ?>"
	filter-debounce="<?php echo e($filter->options['debounce'] ?? 0); ?>"
	class="nav-item dropdown <?php echo e(Request::get($filter->name)?'active':''); ?>">
    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><?php echo e($filter->label); ?> <span class="caret"></span></a>
    <ul class="dropdown-menu">
		<a class="dropdown-item" parameter="<?php echo e($filter->name); ?>" dropdownkey="" href="">-</a>
		<div role="separator" class="dropdown-divider"></div>
		<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($filter->values) && count($filter->values)): ?>
			<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $filter->values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
				<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'dropdown-separator'): ?>
					<div role="separator" class="dropdown-divider"></div>
				<?php else: ?>
					<a  class="dropdown-item <?php echo e(($filter->isActive() && $filter->currentValue == $key)?'active':''); ?>"
						parameter="<?php echo e($filter->name); ?>"
						href=""
						dropdownkey="<?php echo e($key); ?>"
						><?php echo e($value); ?></a>
				<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
			<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
		<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </li>












<?php $__env->startPush('after_scripts'); ?>
    <script>
		function initDropdownFilter(filter, filterNavbar) {

			let filterName = filter.getAttribute('filter-name');
			let filterKey = filter.getAttribute('filter-key');
			let filterDebounce = filter.getAttribute('filter-debounce');
			let navBarId = filterNavbar.getAttribute('id');
			let filterDropdownAnchor = filter.querySelectorAll('.dropdown-menu a');
			let filterDropdownSelected = filter.querySelector('.dropdown-menu a.active') ?? null;

			// check if the filter was already initialized
			if (filter.getAttribute('data-filter-initialized') === 'true') {
				return;
			}
			filter.setAttribute('data-filter-initialized', 'true');

			filterDropdownAnchor.forEach(function(dropdown) {
				dropdown.addEventListener('click', async function(e) {
					e.preventDefault();

					let value = this.getAttribute('dropdownkey');

					// mark this filter as active in the navbar-filters
					// mark dropdown items active accordingly
					if (value) {
						filter.classList.add('active');
						filterDropdownAnchor.forEach(function(anchor) {
							anchor.classList.remove('active');
						});
						this.classList.add('active');
					} else {
						filter.dispatchEvent(new CustomEvent('backpack:filter:clear'));
					}
					
					document.dispatchEvent(new CustomEvent('backpack:filter:changed', {
						detail: {
							filterName: filterName, 
							filterValue: value, 
							shouldUpdateUrl: true,
							debounce: filterDebounce,
							componentId: filterNavbar.getAttribute('data-component-id'), // Include the table ID in the event
						}
					}));
				});
			});

			// clear filter event (used here and by the Remove all filters button)
			filter.addEventListener('backpack:filter:clear', function(e) {
				this.classList.remove('active');
				this.querySelectorAll('.dropdown-menu a').forEach(function(anchor) {
					anchor.classList.remove('active');
				});
			});
		};
	</script>
<?php $__env->stopPush(); ?>


<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\pro/resources/views/filters/dropdown.blade.php ENDPATH**/ ?>