/**
 * axismundi/actor-activity-feed editor registration (no build step).
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'axismundi/actor-activity-feed', {
		edit: function () {
			return el(
				'section',
				blockEditor.useBlockProps( { className: 'axismundi-activity-feed' } ),
				el( 'h2', { className: 'axismundi-activity-feed__heading' }, i18n.__( 'Timeline', 'axismundi-activities' ) ),
				el(
					'nav',
					{ className: 'axismundi-activity-feed__surfaces', 'aria-label': i18n.__( 'Profile surfaces', 'axismundi-activities' ) },
					el( 'span', { className: 'axismundi-activity-feed__surface is-current', 'aria-current': 'page' }, i18n.__( 'Activity', 'axismundi-activities' ) ),
					el( 'span', { className: 'axismundi-activity-feed__surface' }, i18n.__( 'Community', 'axismundi-activities' ) )
				),
				el(
					'div',
					{ className: 'axismundi-activity-feed__filters' },
					el(
						'button',
						{ type: 'button', className: 'axismundi-activity-feed__filters-trigger', disabled: true, 'aria-haspopup': 'dialog', 'aria-expanded': 'false' },
						el( 'span', {}, i18n.__( 'Posts and boosts', 'axismundi-activities' ) ),
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'unfold_more' )
					)
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
