/**
 * axismundi/feed-item-templates editor registration (no build step).
 *
 * A wrapper so the variants read as one thing. Left as siblings of the filters and the pager they
 * were just a run of blocks that happened to be next to each other; grouped, it is visible that
 * they are alternatives, that their order decides the default, and where a new one would go.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	// Card first, so a fresh loop defaults to the fuller reading. Order is the contract: the first
	// child is the default density, and moving them changes it.
	var TEMPLATE = [
		[ 'axismundi/feed-item-template', { density: 'card' } ],
		[ 'axismundi/feed-item-template', { density: 'compact' } ]
	];

	blocks.registerBlockType( 'axismundi/feed-item-templates', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-feed-item-templates' } );
			var innerProps = useInnerBlocksProps
				? useInnerBlocksProps( blockProps, { template: TEMPLATE, templateLock: false } )
				: null;
			return innerProps
				? el( 'div', innerProps )
				: el( 'div', blockProps, el( blockEditor.InnerBlocks, { template: TEMPLATE, templateLock: false } ) );
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
