( function ( blocks, blockEditor, element ) {
	'use strict';

	var el = element.createElement;
	var template = [
		[ 'axismundi/media-preview', {} ],
		[ 'core/post-title', { level: 3, isLink: true } ],
		[ 'core/post-date', {} ]
	];

	blocks.registerBlockType( 'axismundi/media-post-template', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'ax-media-post-template-placeholder' } ),
				el( blockEditor.InnerBlocks, { template: template, templateLock: false } )
			);
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element );
