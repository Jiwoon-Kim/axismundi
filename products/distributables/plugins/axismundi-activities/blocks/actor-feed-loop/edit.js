/**
 * axismundi/actor-feed-loop editor registration (no build step).
 *
 * The preview used to draw a whole feed — filter control, two skeleton cards, a Load more link —
 * because at the time none of those were blocks and a preview that drew nothing looked broken.
 * They are blocks now, and each one draws itself, so the skeleton had become a picture of the feed
 * sitting on top of the feed: the children were in the saved markup but had nowhere to appear, and
 * an author could neither see nor edit them.
 *
 * What is left here is what the loop still owns and no child block represents — the heading and the
 * surface tabs, both decided by the Actor being rendered rather than by anything an author arranges.
 * Everything between them is the children, in the order the author put them.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	// The arrangement the bundled profile template ships with. Not locked: moving these, or
	// dropping one, is the reason they are blocks.
	var TEMPLATE = [
		[ 'axismundi/feed-filters' ],
		[ 'axismundi/feed-item-template' ],
		[ 'axismundi/feed-pagination' ]
	];

	blocks.registerBlockType( 'axismundi/actor-feed-loop', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-activity-feed' } );
			var inner = useInnerBlocksProps
				? useInnerBlocksProps( {}, { template: TEMPLATE, templateLock: false } )
				: null;
			return el(
				'section',
				blockProps,
				/*
				 * Not editable, and not children. The heading names the surface being read and the
				 * tabs exist only when a product has contributed a second surface — both are
				 * answers to a query, so an author moving them would be moving something the
				 * server puts back.
				 */
				el( 'h2', { className: 'axismundi-activity-feed__heading' }, __( 'Timeline', 'axismundi-activities' ) ),
				el(
					'nav',
					{ className: 'axismundi-activity-feed__surfaces', 'aria-label': __( 'Profile surfaces', 'axismundi-activities' ) },
					el( 'span', { className: 'axismundi-activity-feed__surface is-current', 'aria-current': 'page' }, __( 'Activity', 'axismundi-activities' ) ),
					el( 'span', { className: 'axismundi-activity-feed__surface' }, __( 'Community', 'axismundi-activities' ) )
				),
				inner
					? el( 'div', inner )
					: el( 'div', {}, el( blockEditor.InnerBlocks, { template: TEMPLATE, templateLock: false } ) )
			);
		},
		/*
		 * Dynamic on the front end, but the children still have to reach the saved markup: the
		 * serializer renders only what `save` returns, and a block that saves nothing is written
		 * out self-closing — which would discard the card template and the two placements on the
		 * first save from the Site Editor.
		 */
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
