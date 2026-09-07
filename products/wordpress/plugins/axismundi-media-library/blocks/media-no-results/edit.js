( function ( blocks, blockEditor, element, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/media-no-results', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'ax-media-no-results-placeholder' } ),
				el( blockEditor.InnerBlocks, {
					template: [ [ 'core/paragraph', { content: __( 'No media is available in this collection.', 'axismundi-media-library' ) } ] ],
					templateLock: false
				} )
			);
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
