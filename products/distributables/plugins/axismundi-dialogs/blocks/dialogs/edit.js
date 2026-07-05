/**
 * axismundi/dialogs — collection block registration (no build / vanilla).
 *
 * Each child Sheet marks its trigger label as a content-role attribute
 * (sheet/block.json), so the block inspector shows the native List View tab
 * listing the sheets — the same affordance the core Buttons block provides.
 */
( function ( blocks, blockEditor, element ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;

	var DEFAULT_BLOCK = { name: 'axismundi/sheet' };

	blocks.registerBlockType( 'axismundi/dialogs', {
		edit: function ( props ) {
			var layout = props.attributes.layout || {};
			var blockProps = useBlockProps( { className: 'ax-dialogs' } );
			var innerBlocksProps = useInnerBlocksProps( blockProps, {
				allowedBlocks: [ 'axismundi/sheet', 'axismundi/dialog' ],
				defaultBlock: DEFAULT_BLOCK,
				directInsert: true,
				template: [ [ 'axismundi/sheet' ] ],
				templateInsertUpdatesSelection: true,
				orientation: layout.orientation || 'horizontal',
			} );

			return el( 'div', innerBlocksProps );
		},
		save: function () {
			return el( 'div', useInnerBlocksProps.save( useBlockProps.save( { className: 'ax-dialogs' } ) ) );
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element );
