/** Static editor preview for axismundi/object-replies; the thread is resolved on the server. */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/object-replies', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Settings', 'axismundi-object-projections' ) },
						el( components.RangeControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Replies to show', 'axismundi-object-projections' ),
							value: attributes.perPage || 20,
							min: 1,
							max: 50,
							onChange: function ( value ) { props.setAttributes( { perPage: value } ); }
						} )
					)
				),
				el(
					'section',
					blockEditor.useBlockProps( { className: 'axismundi-object-replies' } ),
					el( 'h2', { className: 'axismundi-object-replies__heading' }, __( 'Replies', 'axismundi-object-projections' ) ),
					el( 'p', null, __( 'Replies to this object, including ones received from other servers, are listed here when the page is viewed.', 'axismundi-object-projections' ) )
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
