/* global document */
( function () {
	'use strict';

	function rowTemplate() {
		var row = document.createElement( 'tr' );
		row.className = 'ax-actor-profile-fields__row';
		row.draggable = true;
		row.innerHTML =
			'<td class="ax-actor-profile-fields__order">' +
				'<button type="button" class="button-link ax-actor-profile-fields__drag" aria-label="Drag to reorder"><span class="dashicons dashicons-menu"></span></button>' +
				'<button type="button" class="button-link ax-actor-profile-fields__move-up" aria-label="Move up">&#8593;</button>' +
				'<button type="button" class="button-link ax-actor-profile-fields__move-down" aria-label="Move down">&#8595;</button>' +
			'</td>' +
			'<td><input class="regular-text" name="profile_field_name[]" maxlength="191"></td>' +
			'<td><input class="large-text" type="url" name="profile_field_url[]" placeholder="https://example.com/"></td>' +
			'<td class="ax-actor-profile-fields__verification"></td>' +
			'<td><button type="button" class="button-link-delete ax-actor-profile-fields__remove" aria-label="Remove profile link">&times;</button></td>';
		return row;
	}

	function refresh( table ) {
		var rows = Array.prototype.slice.call( table.querySelectorAll( '.ax-actor-profile-fields__row' ) );
		var max = parseInt( table.getAttribute( 'data-max-fields' ), 10 ) || 8;
		var add = table.parentNode.querySelector( '.ax-actor-profile-fields__add' );
		rows.forEach( function ( row, index ) {
			row.querySelector( '.ax-actor-profile-fields__move-up' ).disabled = index === 0;
			row.querySelector( '.ax-actor-profile-fields__move-down' ).disabled = index === rows.length - 1;
			row.querySelector( '.ax-actor-profile-fields__remove' ).disabled = rows.length <= 1;
		} );
		if ( add ) {
			add.disabled = rows.length >= max;
		}
	}

	function move( row, direction ) {
		var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
		if ( sibling ) {
			row.parentNode.insertBefore( direction < 0 ? row : sibling, direction < 0 ? sibling : row );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.ax-actor-profile-fields' ), function ( table ) {
			var body = table.querySelector( '.ax-actor-profile-fields__rows' );
			var scope = table.closest( 'form' ) || table.parentNode;
			var dragRow = null;
			refresh( table );

			scope.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( 'button' );
				if ( ! button ) { return; }
				var row = button.closest( '.ax-actor-profile-fields__row' );
				if ( button.classList.contains( 'ax-actor-profile-fields__add' ) ) {
					event.preventDefault();
					if ( body.querySelectorAll( '.ax-actor-profile-fields__row' ).length < ( parseInt( table.getAttribute( 'data-max-fields' ), 10 ) || 8 ) ) {
						body.appendChild( rowTemplate() );
						body.lastElementChild.querySelector( 'input' ).focus();
						refresh( table );
					}
					return;
				}
				if ( ! row ) { return; }
				if ( button.classList.contains( 'ax-actor-profile-fields__verify' ) ) {
					button.value = row.querySelector( 'input[name="profile_field_url[]"]' ).value;
					return;
				}
				if ( button.classList.contains( 'ax-actor-profile-fields__move-up' ) ) { event.preventDefault(); move( row, -1 ); refresh( table ); }
				if ( button.classList.contains( 'ax-actor-profile-fields__move-down' ) ) { event.preventDefault(); move( row, 1 ); refresh( table ); }
				if ( button.classList.contains( 'ax-actor-profile-fields__remove' ) ) { event.preventDefault(); row.remove(); refresh( table ); }
			} );

			body.addEventListener( 'dragstart', function ( event ) {
				dragRow = event.target.closest( '.ax-actor-profile-fields__row' );
				if ( dragRow ) { dragRow.classList.add( 'is-dragging' ); event.dataTransfer.effectAllowed = 'move'; }
			} );
			body.addEventListener( 'dragover', function ( event ) {
				if ( ! dragRow ) { return; }
				event.preventDefault();
				var target = event.target.closest( '.ax-actor-profile-fields__row' );
				if ( target && target !== dragRow ) {
					var before = event.clientY < target.getBoundingClientRect().top + ( target.offsetHeight / 2 );
					body.insertBefore( dragRow, before ? target : target.nextSibling );
				}
			} );
			body.addEventListener( 'dragend', function () {
				if ( dragRow ) { dragRow.classList.remove( 'is-dragging' ); }
				dragRow = null;
				refresh( table );
			} );
		} );
	} );
} )();
