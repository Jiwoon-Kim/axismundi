/**
 * axismundi/feed-item-template editor registration (no build step).
 *
 * What an author edits here is rendered once per Object in the feed — including the Objects that
 * arrive after "Load more", which come from a REST request with no template around it. Both sides
 * read this same saved markup, so an edit here changes every card or none.
 *
 * There is no live preview of a real Object: which blocks produce anything depends on what kind of
 * Object each row holds, and one feed mixes kinds.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	var DENSITY_LABELS = { card: __( 'Card items', 'axismundi-activities' ), compact: __( 'Compact items', 'axismundi-activities' ) };

	var TEMPLATE = [
		[ 'axismundi/object-status' ],
		[ 'axismundi/object-card-body' ],
		[ 'axismundi/object-hashtags', { className: 'is-style-tags' } ],
		[ 'axismundi/reaction-bar' ],
		[ 'axismundi/interactions', {}, [
			[ 'axismundi/interaction', { type: 'reply' } ],
			[ 'axismundi/interaction', { type: 'like' } ],
			[ 'axismundi/interaction', { type: 'announce', announceMenu: true } ],
			[ 'axismundi/interaction', { type: 'reaction' } ]
		] ]
	];

	blocks.registerBlockType( 'axismundi/feed-item-template', {
		edit: function ( props ) {
			var density = ( props.attributes && props.attributes.density ) === 'compact' ? 'compact' : 'card';
			var blockProps = blockEditor.useBlockProps( {
				className: 'axismundi-feed-item-template is-density-' + density,
				// Named in the canvas, because two of these sit in one loop and an author editing
				// the wrong one would be editing a card no reader on this page is looking at.
				'aria-label': DENSITY_LABELS[ density ]
			} );
			var innerProps = useInnerBlocksProps
				? useInnerBlocksProps( blockProps, { template: TEMPLATE, templateLock: false } )
				: null;
			return innerProps
				? el( 'div', innerProps )
				: el( 'div', blockProps, el( blockEditor.InnerBlocks, { template: TEMPLATE, templateLock: false } ) );
		},
		/*
		 * Dynamic, like Core's Post Template: nothing renders the children here, because the loop
		 * renders one copy of them per Object. They must still be written out, though — the
		 * serializer emits only what `save` returns, and returning nothing serializes the block
		 * self-closing, taking the whole card definition with it on the first editor save.
		 */
		save: function () {
			return element.createElement( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
