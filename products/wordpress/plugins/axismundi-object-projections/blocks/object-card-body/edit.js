/**
 * axismundi/object-card-body editor registration (no build step).
 *
 * There is nothing to compose here: which blocks this renders depends on the kind of Object the
 * card is showing, and one feed mixes them. The preview names both shapes so an author can see
 * why the region is not editable rather than assuming it is empty.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/object-card-body', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-object-card__body-placeholder' } ),
				el( 'p', {}, __( 'Object body — chosen per card at render time.', 'axismundi-object-projections' ) ),
				el( 'p', {}, __( 'Note: content warning wrapping body, quote, poll and attachments.', 'axismundi-object-projections' ) ),
				el( 'p', {}, __( 'Article: lead image, title, summary and Read more.', 'axismundi-object-projections' ) )
			);
		},
		save: function () { return null; }
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
