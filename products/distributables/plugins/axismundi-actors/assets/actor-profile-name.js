/* global document */
( function () {
	'use strict';

	function value( form, id ) {
		var input = form.querySelector( '#' + id );
		return input ? input.value.trim() : '';
	}

	function assembledName( first, middle, last, secondSurname, order ) {
		var familyFirst = order === 'family-given' || order === 'family-given-compact';
		var parts = familyFirst ? [ last, secondSurname, first, middle ] : [ first, middle, last, secondSurname ];
		parts = parts.filter( function ( part ) { return part !== ''; } );
		if ( ! parts.length ) {
			return '';
		}
		return parts.join( /-compact$/.test( order ) || ( familyFirst && ! /[A-Za-z]/.test( first + last ) ) ? '' : ' ' );
	}

	function refresh( form ) {
		var select = form.querySelector( '#ax-actor-display-order' );
		if ( ! select ) {
			return;
		}
		var first = value( form, 'ax-actor-given' );
		var middle = value( form, 'ax-actor-given2' );
		var last = value( form, 'ax-actor-surname' );
		var secondSurname = value( form, 'ax-actor-surname2' );
		var names = {
			'given-family': assembledName( first, middle, last, secondSurname, 'given-family' ),
			'given-family-compact': assembledName( first, middle, last, secondSurname, 'given-family-compact' ),
			'family-given': assembledName( first, middle, last, secondSurname, 'family-given' ),
			'family-given-compact': assembledName( first, middle, last, secondSurname, 'family-given-compact' )
		};
		[ 'given-family', 'given-family-compact', 'family-given', 'family-given-compact' ].forEach( function ( order ) {
			var option = select.querySelector( 'option[value="' + order + '"]' );
			if ( option ) {
				option.textContent = names[ order ] || option.getAttribute( 'data-empty-label' ) || '';
			}
		} );

		var preview = form.querySelector( '#ax-actor-name[readonly]' );
		if ( preview ) {
			preview.value = select.value === 'custom'
				? value( form, 'ax-actor-custom-display-name' )
				: ( names[ select.value ] || '' );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( 'form' ), function ( form ) {
			if ( ! form.querySelector( '#ax-actor-display-order' ) ) {
				return;
			}
			[ 'ax-actor-given', 'ax-actor-given2', 'ax-actor-surname', 'ax-actor-surname2', 'ax-actor-custom-display-name' ].forEach( function ( id ) {
				var input = form.querySelector( '#' + id );
				if ( input ) {
					input.addEventListener( 'input', function () { refresh( form ); } );
				}
			} );
			form.querySelector( '#ax-actor-display-order' ).addEventListener( 'change', function () { refresh( form ); } );
			refresh( form );
		} );
	} );
}() );
