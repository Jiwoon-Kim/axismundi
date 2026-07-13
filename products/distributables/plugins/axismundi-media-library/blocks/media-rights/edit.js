/**
 * axismundi/media-rights editor registration (no build step).
 */
( function ( blocks, element, i18n, ServerSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/media-rights', {
		edit: function ( props ) {
			var postId = props.context.postId || 0;
			if ( ( props.context.postType && 'attachment' !== props.context.postType ) || ! postId ) {
				return el( 'p', null, __( 'Media Rights renders for an Attachment.', 'axismundi-media-library' ) );
			}
			return el( ServerSideRender, {
				block: 'axismundi/media-rights',
				attributes: props.attributes,
				urlQueryArgs: { post_id: postId },
			} );
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
