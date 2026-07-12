/**
 * axismundi/media-preview editor registration (no build step).
 */
( function ( blocks, blockEditor, element, components, i18n, ServerSideRender ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/media-preview', {
		edit: function ( props ) {
			var attributes = props.attributes;
			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Media preview', 'axismundi-media-library' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Image size', 'axismundi-media-library' ),
							value: attributes.sizeSlug,
							options: [
								{ label: __( 'Thumbnail', 'axismundi-media-library' ), value: 'thumbnail' },
								{ label: __( 'Medium', 'axismundi-media-library' ), value: 'medium' },
								{ label: __( 'Medium large', 'axismundi-media-library' ), value: 'medium_large' },
								{ label: __( 'Large', 'axismundi-media-library' ), value: 'large' },
								{ label: __( 'Full', 'axismundi-media-library' ), value: 'full' },
							],
							onChange: function ( value ) { props.setAttributes( { sizeSlug: value } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Link to Attachment page', 'axismundi-media-library' ),
							checked: attributes.linkToAttachment,
							onChange: function ( value ) { props.setAttributes( { linkToAttachment: value } ); },
						} )
					)
				),
				el( ServerSideRender, {
					block: 'axismundi/media-preview',
					attributes: attributes,
					urlQueryArgs: { post_id: props.context.postId || 0 },
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
