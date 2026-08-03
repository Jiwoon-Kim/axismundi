/**
 * axismundi/interactions editor registration (no build step).
 *
 * A Buttons-style container: the action buttons are real inner blocks, so an
 * author can reorder or remove them and use the same alignment and gap controls
 * Core's Buttons block provides. Metadata stays in block.json.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var InnerBlocks = blockEditor.InnerBlocks;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;
	var TEMPLATE = [
		[ 'axismundi/interaction', { type: 'reply' } ],
		[ 'axismundi/interaction', { type: 'like' } ],
		[ 'axismundi/interaction', { type: 'announce', announceMenu: true } ],
		[ 'axismundi/interaction', { type: 'reaction' } ]
	];
	blocks.registerBlockType( 'axismundi/interactions', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-object__interactions' } );
			// The layout classes land on the block wrapper, so the buttons have to be
			// that wrapper's own children. Plain `InnerBlocks` inserts an editor-only
			// element in between, which leaves justification applying to that single
			// wrapper instead of to the buttons.
			if ( ! useInnerBlocksProps ) {
				return el( 'div', blockProps, el( InnerBlocks, { template: TEMPLATE, templateLock: false, orientation: 'horizontal' } ) );
			}
			return el( 'div', useInnerBlocksProps( blockProps, {
				template: TEMPLATE,
				templateLock: false,
				orientation: 'horizontal'
			} ) );
		},
		// Dynamic block: inner blocks are still serialized between the delimiters
		/*
		 * The children have to reach the saved markup, even though the server renders them.
		 *
		 * `return null` writes the block out self-closing, which discards every child the moment
		 * the template is saved — the controls were in the editor and were gone from the file, and
		 * a container with no children renders nothing at all. Being dynamic decides what happens
		 * at render time; it does not excuse the block from serializing what it contains.
		 */
		save: function () { return el( InnerBlocks.Content ); }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
