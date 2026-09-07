/**
 * Fill Core's Quick Edit form with the Note fields for the row being edited.
 *
 * Core copies a row's values into the inline form itself, but only for the fields it knows about.
 * Anything a plugin adds opens empty, and an empty select that saves is not a blank field — it is a
 * silent change to whatever the Note actually had. So the row's current values travel in a hidden
 * element and are read back here.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target instanceof Element ? event.target.closest( '.editinline' ) : null;
		if ( ! trigger ) {
			return;
		}
		var row = trigger.closest( 'tr[id^="post-"]' );
		var id = row ? parseInt( String( row.id ).replace( /^post-/, '' ), 10 ) : 0;
		var data = id ? document.getElementById( 'ax-note-inline-' + id ) : null;
		if ( ! data ) {
			return;
		}
		// Core builds the inline row after this click, so read the form on the next turn.
		window.setTimeout( function () {
			var form = document.getElementById( 'edit-' + id );
			if ( ! form ) {
				return;
			}
			var visibility = form.querySelector( '[name="ax_note_visibility"]' );
			var sensitive = form.querySelector( '[name="ax_note_sensitive"]' );
			var warning = form.querySelector( '[name="ax_note_warning"]' );
			if ( visibility ) {
				visibility.value = data.getAttribute( 'data-visibility' ) || 'public';
			}
			if ( sensitive ) {
				sensitive.checked = '1' === data.getAttribute( 'data-sensitive' );
			}
			if ( warning ) {
				warning.value = data.getAttribute( 'data-warning' ) || '';
			}
		}, 0 );
	} );
}() );
