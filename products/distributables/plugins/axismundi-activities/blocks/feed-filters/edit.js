/**
 * axismundi/feed-filters editor registration (no build step).
 *
 * A placeholder rather than a preview. Which switches this renders is decided by the surface the
 * reader is on, and the editor is not on a surface — showing one arbitrary combination would be
 * showing something no reader will necessarily see.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/feed-filters', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-feed-slot' } ),
				__( 'Feed filters', 'axismundi-activities' )
			);
		},
		// Dynamic: the loop renders the controls, this only records where they belong.
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
