/**
 * axismundi/feed-density-switch editor registration (no build step).
 *
 * A placeholder, not a working control. Density is a reader's URL state and the editor is not a
 * reader — a switch that appeared to work here would be changing an address nobody is at.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/feed-density-switch', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-feed-slot' } ),
				__( 'Card / Compact', 'axismundi-activities' )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
