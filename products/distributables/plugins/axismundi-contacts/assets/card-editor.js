/**
 * Repeating rows in the contact editor.
 *
 * A contact has as many email addresses as it has, so the form grows rather than offering a fixed
 * two. Without this script the form still works: every entry already on the card is rendered, plus
 * one blank row per field, so a contact can always gain one more thing per save.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-ax-contacts-add]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var list = document.getElementById( button.getAttribute( 'data-ax-contacts-add' ) );
		if ( ! list ) {
			return;
		}
		var rows = list.querySelectorAll( '[data-ax-contacts-row]' );
		var last = rows[ rows.length - 1 ];
		if ( ! last ) {
			return;
		}
		var row = last.cloneNode( true );
		row.querySelectorAll( 'input' ).forEach( function ( input ) {
			// A cloned row is a new entry: it carries no key, so the writer gives it one.
			input.value = '';
		} );
		// A note about where a value came from belongs to the value that was there, not to a blank.
		row.querySelectorAll( '[data-ax-contacts-source]' ).forEach( function ( note ) {
			note.remove();
		} );
		list.appendChild( row );
		var field = row.querySelector( 'input:not([type="hidden"])' );
		if ( field ) {
			field.focus();
		}
	} );
}() );
