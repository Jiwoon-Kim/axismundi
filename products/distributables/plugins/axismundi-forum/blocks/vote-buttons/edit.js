/**
 * axismundi/vote-buttons editor registration (no build step).
 *
 * The preview shows the resting state with a zero score. Both the score and the reader's own vote
 * come from the Activity ledger at render time, so there is nothing here for an author to edit —
 * only where the control sits.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/vote-buttons', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el(
					'div',
					{ className: 'axismundi-vote-buttons__group', role: 'group', 'aria-label': __( 'Community vote', 'axismundi-forum' ) },
					el(
						'span',
						{ className: 'axismundi-vote-buttons__button axismundi-vote-buttons__button--up', key: 'up' },
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'thumb_up_off_alt' )
					),
					el( 'span', { className: 'axismundi-vote-buttons__score', key: 'score' }, '0' ),
					el(
						'span',
						{ className: 'axismundi-vote-buttons__button axismundi-vote-buttons__button--down', key: 'down' },
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'thumb_down_off_alt' )
					)
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
