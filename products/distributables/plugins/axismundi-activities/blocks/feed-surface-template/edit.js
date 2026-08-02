/** axismundi/feed-surface-template editor registration (no build step). */
( function ( blocks, blockEditor, element ) {
	'use strict';
	var el = element.createElement;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	blocks.registerBlockType( 'axismundi/feed-surface-template', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-feed-surface-template' } );
			var innerProps = useInnerBlocksProps ? useInnerBlocksProps( blockProps, {} ) : null;
			return innerProps
				? el( 'div', innerProps )
				: el( 'div', blockProps, el( blockEditor.InnerBlocks, {} ) );
		},
		// A container: the serializer writes only what save returns, so a block returning nothing
		// is written self-closing and its layouts would be discarded on the first editor save.
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element ) );
