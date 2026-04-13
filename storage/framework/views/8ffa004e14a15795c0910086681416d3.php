
<li filter-name="<?php echo e($filter->name); ?>"
    filter-type="<?php echo e($filter->type); ?>"
    filter-key="<?php echo e($filter->key); ?>"
	filter-init-function="<?php echo e($filter->init_function ?? 'initSelect2Filter'); ?>"
	filter-debounce="<?php echo e($filter->options['debounce'] ?? 0); ?>"
	class="nav-item dropdown <?php echo e(Request::get($filter->name)?'active':''); ?>">
    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><?php echo e($filter->label); ?> <span class="caret"></span></a>
    <div class="dropdown-menu p-0">
      <div class="form-group backpack-filter mb-0">
			<select
				name="filter_<?php echo e($filter->key); ?>"
				class="form-control input-sm select2"
				placeholder="<?php echo e($filter->placeholder); ?>"
				data-filter-key="<?php echo e($filter->key); ?>"
				data-filter-type="select2"
				data-filter-name="<?php echo e($filter->name); ?>"
				data-language="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>"
				>
				<option value="">-</option>
				<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($filter->values) && count($filter->values)): ?>
					<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $filter->values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
						<option value="<?php echo e($key); ?>"
							<?php if($filter->isActive() && $filter->currentValue == $key): ?>
								selected
							<?php endif; ?>
							>
							<?php echo e($value); ?>

						</option>
					<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
				<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
			</select>
		</div>
    </div>
  </li>







<?php $__env->startPush('before_styles'); ?>
    
    <?php Basset::basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css'); ?>
    <?php Basset::basset('https://cdn.jsdelivr.net/npm/select2-bootstrap-theme@0.1.0-beta.10/dist/select2-bootstrap.min.css'); ?>
    <style>
	  .form-inline .select2-container {
	    display: inline-block;
	  }
	  .select2-drop-active {
	  	border:none;
	  }
	  .select2-container .select2-choices .select2-search-field input, .select2-container .select2-choice, .select2-container .select2-choices {
	  	border: none;
	  }
	  .select2-container-active .select2-choice {
	  	border: none;
	  	box-shadow: none;
	  }
	  .select2-container--bootstrap .select2-dropdown {
	  	margin-top: -2px;
	  	margin-left: -1px;
	  }
	  .select2-container--bootstrap {
	  	position: relative!important;
	  	top: 0px!important;
	  }
    </style>
<?php $__env->stopPush(); ?>





<?php $__env->startPush('after_scripts'); ?>
	
    <?php Basset::basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js'); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app()->getLocale() !== 'en'): ?>
        <?php Basset::basset('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/i18n/' . str_replace('_', '-', app()->getLocale()) . '.js'); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <script>
        function initSelect2Filter(filter, filterNavbar) {
			let filterName = filter.getAttribute('filter-name');
			let filterKey = filter.getAttribute('filter-key');
			let selectElement = filter.querySelector('select');
			let closeOnSelect = selectElement.getAttribute('data-close-on-select') === 'true';
			let filterDebounce = filter.getAttribute('filter-debounce');
			let shouldUpdateUrl = true;

			// check if the filter was already initialized
			if (filter.getAttribute('data-filter-initialized') === 'true') {
				return;
			}
			filter.setAttribute('data-filter-initialized', 'true');

            $(selectElement).select2({
				allowClear: true,
				closeOnSelect: false,
				theme: "bootstrap",
				dropdownParent: selectElement.closest('.form-group'),
				placeholder: selectElement.getAttribute('placeholder'),
			}).on('change', async function(c) {
				var value = $(this).val();

				if(!value) {
					return;
				}

				filter.classList.add('active');

				document.dispatchEvent(new CustomEvent('backpack:filter:changed', {detail: {
					filterName: filterName, 
					filterValue: value, 
					shouldUpdateUrl: shouldUpdateUrl,
					debounce: filterDebounce,
					componentId: filterNavbar.getAttribute('data-component-id'),
				}}));

			}).on('select2:unselecting', async function (e) {
				$(selectElement).val(null)
				filter.classList.remove('active');
				filter.querySelector('.dropdown-menu').classList.remove('show');		

				document.dispatchEvent(new CustomEvent('backpack:filter:changed', {detail: {
					filterName: filterName, 
					filterValue: null, 
					shouldUpdateUrl: true,
					debounce: filterDebounce,
					componentId: filterNavbar.getAttribute('data-component-id'),
				}}));
				
				e.stopPropagation();
				return true;
			});

			// when the dropdown is opened, autofocus on the select2
			filter.addEventListener('shown.bs.dropdown', function() {
				setTimeout(() => {
					$(selectElement).select2('open');
					$(selectElement).data('select2').dropdown.$search.get(0).focus();
				}, 50);
			});

			// clear filter event (used here and by the Remove all filters button)
			filter.addEventListener('backpack:filter:clear', function(e) {
				filter.classList.remove('active');
				$(selectElement).val(null).trigger('change');
			});
		};
	</script>
<?php $__env->stopPush(); ?>


<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\pro/resources/views/filters/select2.blade.php ENDPATH**/ ?>