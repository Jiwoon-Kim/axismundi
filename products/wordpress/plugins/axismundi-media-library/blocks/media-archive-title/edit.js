/**
 * axismundi/media-archive-title editor registration (no build step).
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/media-archive-title', {
		edit: function ( props ) {
			var level = Math.max( 1, Math.min( 6, props.attributes.level || 1 ) );
			return el(
				'h' + level,
				blockEditor.useBlockProps(),
				__( 'Media archive title', 'axismundi-media-library' )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
