/**
 * axismundi/reaction-button editor registration (no build step).
 *
 * The picker itself is not previewed: it is a reader's control over live data, and opening
 * it in the editor would fetch a catalogue nobody is about to pick from.
 */
( function ( blocks, blockEditor, element ) {
	'use strict';
	var el = element.createElement;
	blocks.registerBlockType( 'axismundi/reaction-button', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-reaction-button is-editor-preview' } ),
				el(
					'span',
					{ className: 'axismundi-reaction-button__trigger' },
					el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'add_reaction' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element );
