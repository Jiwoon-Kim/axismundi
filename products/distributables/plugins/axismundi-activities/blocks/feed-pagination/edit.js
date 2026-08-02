/**
 * axismundi/feed-pagination editor registration (no build step).
 *
 * A placeholder rather than a preview: whether there is a next page, and what cursor addresses it,
 * are answers to a query the editor has not run.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/feed-pagination', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-feed-slot' } ),
				__( 'Load more', 'axismundi-activities' )
			);
		},
		// Dynamic: the loop renders the control, this only records where it belongs.
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
