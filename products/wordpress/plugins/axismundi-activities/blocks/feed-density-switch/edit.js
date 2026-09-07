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
	var previewSegment = function ( icon, label, current ) {
		return el(
			'div',
			{ className: 'wp-block-button is-style-outline' + ( current ? ' is-current' : '' ) },
			el(
				'span',
				{
					className: 'wp-block-button__link wp-element-button axismundi-feed-density-switch__link',
					'aria-hidden': 'true'
				},
				el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, icon )
			)
		);
	};

	blocks.registerBlockType( 'axismundi/feed-density-switch', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-feed-density-switch axismundi-feed-slot' } ),
				el(
					'div',
					{ className: 'wp-block-buttons is-style-connected', 'aria-hidden': 'true' },
					previewSegment( 'view_stream', __( 'Card view', 'axismundi-activities' ), true ),
					previewSegment( 'view_list', __( 'List view', 'axismundi-activities' ), false )
				),
				el(
					'span',
					{ className: 'screen-reader-text' },
					__( 'Entry density selector preview. Card view is selected.', 'axismundi-activities' )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
