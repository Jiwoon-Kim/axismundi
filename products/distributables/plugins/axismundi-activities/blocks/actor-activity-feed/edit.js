/**
 * axismundi/actor-activity-feed editor registration (no build step).
 *
 * The preview drew the chrome and stopped, so the block that actually renders the profile's cards
 * showed none of them — while a neighbouring block that renders nothing showed four. An author
 * comparing the two would pick the wrong one, and did.
 *
 * So the rows are here, as a skeleton rather than as content. They name the parts a card is made
 * of and nothing else: a real Object's title, body and counts are not known while a template is
 * being edited, and drawing plausible ones invites exactly the confusion this is fixing.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	/** One skeleton row, showing the parts a card is assembled from. */
	function card( key, boosted ) {
		var parts = [];
		if ( boosted ) {
			parts.push(
				el(
					'p',
					{ className: 'axismundi-activity-feed__boost', key: 'boost' },
					el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'sync' ),
					' ',
					__( 'Boosted', 'axismundi-activities' )
				)
			);
		}
		parts.push(
			el(
				'article',
				{ className: 'axismundi-object-card', key: 'card' },
				el( 'p', { className: 'axismundi-object-card__header' }, __( 'Actor · handle · time', 'axismundi-activities' ) ),
				el( 'p', {}, __( 'Object body — a Note’s text, or an Article’s image, title and summary.', 'axismundi-activities' ) ),
				el( 'p', {}, __( 'Hashtags · reactions · reply, like, repost, react', 'axismundi-activities' ) )
			)
		);
		return el( 'li', { className: 'axismundi-activity-feed__item', key: key }, parts );
	}

	blocks.registerBlockType( 'axismundi/actor-activity-feed', {
		edit: function () {
			return el(
				'section',
				blockEditor.useBlockProps( { className: 'axismundi-activity-feed' } ),
				el( 'h2', { className: 'axismundi-activity-feed__heading' }, __( 'Timeline', 'axismundi-activities' ) ),
				el(
					'nav',
					{ className: 'axismundi-activity-feed__surfaces', 'aria-label': __( 'Profile surfaces', 'axismundi-activities' ) },
					el( 'span', { className: 'axismundi-activity-feed__surface is-current', 'aria-current': 'page' }, __( 'Activity', 'axismundi-activities' ) ),
					el( 'span', { className: 'axismundi-activity-feed__surface' }, __( 'Community', 'axismundi-activities' ) )
				),
				el(
					'div',
					{ className: 'axismundi-activity-feed__filters' },
					el(
						'button',
						{ type: 'button', className: 'axismundi-activity-feed__filters-trigger', disabled: true, 'aria-haspopup': 'dialog', 'aria-expanded': 'false' },
						el( 'span', {}, __( 'Posts and boosts', 'axismundi-activities' ) ),
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'unfold_more' )
					)
				),
				el(
					'ul',
					{ className: 'axismundi-activity-feed__list' },
					card( 'a', false ),
					card( 'b', true )
				),
				el(
					'p',
					{ className: 'axismundi-activity-feed__pagination' },
					el( 'span', { className: 'axismundi-activity-feed__more-link' }, __( 'Load more', 'axismundi-activities' ) )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
