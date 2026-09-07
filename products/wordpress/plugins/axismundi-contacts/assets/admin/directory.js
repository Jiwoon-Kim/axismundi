/** Progressive page-local selection. Filing itself remains a native POST form. */
( function () {
	'use strict';
	document.querySelectorAll( '.ax-contacts-selection' ).forEach( function ( form ) {
		var rows = Array.from( form.querySelectorAll( 'input[name="cards[]"]' ) );
		var all = form.querySelector( '[data-ax-select-all]' );
		var status = form.querySelector( '[data-ax-selection-status]' );
		var apply = form.querySelector( '[data-ax-label-apply]' );
		var label = form.querySelector( 'select[name="label_id"]' );
		function update() {
			var selected = rows.filter( function ( row ) { return row.checked; } ).length;
			all.checked = selected > 0 && selected === rows.length;
			all.indeterminate = selected > 0 && selected < rows.length;
			rows.forEach( function ( row ) { row.closest( 'tr' ).classList.toggle( 'is-selected', row.checked ); } );
			status.textContent = wp.i18n.sprintf( wp.i18n.__( '%1$d of %2$d selected on this page. Selection clears when you navigate.', 'axismundi-contacts' ), selected, rows.length );
			if ( apply ) { apply.disabled = selected === 0 || ! label.value; }
		}
		all.hidden = false;
		all.addEventListener( 'change', function () { rows.forEach( function ( row ) { row.checked = all.checked; } ); update(); } );
		form.addEventListener( 'change', update );
		// Browsers may restore form values on Back; never silently revive an old selection.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) { rows.forEach( function ( row ) { row.checked = false; } ); update(); }
		} );
		rows.forEach( function ( row ) { row.checked = false; } );
		update();
	} );
}() );
