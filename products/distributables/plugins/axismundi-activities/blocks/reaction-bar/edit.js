/**
 * axismundi/reaction-bar editor registration (no build step).
 *
 * A preview, not an editor. Which reactions exist is a fact about the Activity ledger at
 * render time, so there is nothing here for an author to set — the block's only job in the
 * editor is to occupy the right amount of space and be selectable.
 *
 * The specimen shows one chip in each state, because the pair is the point: a reaction the
 * reader has sent is the theme's tonal button, one they have not is the outline button.
 * A single chip would say nothing about how "mine" reads.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	function chip( variation, label, count ) {
		return el(
			'div',
			{ className: [ 'wp-block-button', variation, 'axismundi-reaction-bar__item' ].filter( Boolean ).join( ' ' ) },
			el(
				'button',
				{ type: 'button', className: 'wp-block-button__link wp-element-button axismundi-reaction-bar__chip' },
				el( 'span', { className: 'axismundi-reaction-bar__shortcode' }, label ),
				el( 'span', { className: 'axismundi-reaction-bar__count' }, count )
			)
		);
	}

	blocks.registerBlockType( 'axismundi/reaction-bar', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-reaction-bar is-editor-preview' } ),
				chip( 'is-style-tonal', ':emoji:', '2' ),
				chip( '', ':other:', '1' ),
				el(
					'span',
					{ className: 'axismundi-reaction-bar__hint' },
					__( 'Reactions appear here once readers add them.', 'axismundi-activities' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
