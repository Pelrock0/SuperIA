async function applyDateRangeFilter(start, end, filter, filterNavbar) {
	let filterName = filter.getAttribute('filter-name');
	const componentId = filterNavbar.getAttribute('data-component-id');

	if (start && end) {
		var dates = {
			'from': start.format('YYYY-MM-DD HH:mm:ss'),
			'to': end.format('YYYY-MM-DD HH:mm:ss')
		};

		var value = JSON.stringify(dates);
	} else {
		var value = '';
	}

	if (value !== '') {
		filter.classList.add('active');
	} else {
		filter.dispatchEvent(new CustomEvent('backpack:filter:clear'));
	}

	document.dispatchEvent(new CustomEvent('backpack:filter:changed', {
		detail: {
			filterName: filterName,
			filterValue: value,
			shouldUpdateUrl: true,
			debounce: filter.getAttribute('filter-debounce'),
			componentId: componentId,
		}
	}));
}

function initDateRangeFilter(filter, filterNavbar) {
	let filterName = filter.getAttribute('filter-name');
	let filterKey = filter.getAttribute('filter-key');
	let filterDebounce = filter.getAttribute('filter-debounce');
	let filterLocale = filter.getAttribute('filter-locale');
	let dateRangeInput = filter.querySelector('input');
	let filterClearButton = filter.querySelector(`.daterangepicker-${filterKey}-clear-button`);
	let filterOptions = JSON.parse(dateRangeInput.getAttribute('data-bs-daterangepicker'));

	// check if the filter was already initialized
	if (filter.getAttribute('data-filter-initialized') === 'true') {
		return;
	}
	filter.setAttribute('data-filter-initialized', 'true');

	moment.locale(filterLocale);

	let filterRanges = filterOptions.ranges;
	filterOptions.ranges = {};

	//if developer configured ranges we convert it to moment() dates.
	for (var key in filterRanges) {
		if (filterRanges.hasOwnProperty(key)) {
			filterOptions.ranges[key] = [moment(filterRanges[key][0]), moment(filterRanges[key][1])];
		}
	}

	filterOptions.startDate = moment(filterOptions.startDate);
	filterOptions.endDate = moment(filterOptions.endDate);

	$(dateRangeInput).daterangepicker(filterOptions);

	$(dateRangeInput).on('apply.daterangepicker', function(ev, picker) {
		applyDateRangeFilter(picker.startDate, picker.endDate, filter, filterNavbar);
	});

	//focus on input when filter open
	filter.querySelector('a[data-bs-toggle]').addEventListener('click', function(e) {
		setTimeout(() => {
			dateRangeInput.focus();
		}, 50);
	});
	filter.addEventListener('backpack:filter:clear', function() {
		filter.classList.remove('active');
	});
	// datepicker clear button
	filterClearButton.addEventListener('click', function(e) {
		e.preventDefault();
		applyDateRangeFilter(null, null, filter, filterNavbar);
	});
};

