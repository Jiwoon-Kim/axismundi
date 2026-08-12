/**
 * Selection controls for the system-calendar holiday review table.
 *
 * @package AxismundiCalendar
 */
( function () {
	function selections( form ) {
		return form ? Array.prototype.slice.call( form.querySelectorAll( '.ax-cal-holiday-selection' ) ) : [];
	}

	function syncAll( form ) {
		var all = document.getElementById( 'ax-cal-select-all-holidays' );
		var inputs = selections( form );
		var checked;

		if ( ! all ) {
			return;
		}

		checked = inputs.filter( function ( input ) {
			return input.checked;
		} ).length;
		all.checked = inputs.length > 0 && checked === inputs.length;
		all.indeterminate = checked > 0 && checked < inputs.length;
	}

	window.axismundiCalendarSystemItems = {
		toggleAll: function ( all, form ) {
			selections( form ).forEach( function ( input ) {
				input.checked = all.checked;
			} );
		},
		selectDrafts: function ( form ) {
			selections( form ).forEach( function ( input ) {
				input.checked = '1' === input.getAttribute( 'data-draft' );
			} );
			syncAll( form );
		},
		invert: function ( form ) {
			selections( form ).forEach( function ( input ) {
				input.checked = ! input.checked;
			} );
			syncAll( form );
		},
		syncAll: syncAll,
	};
}() );
