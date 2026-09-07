/** Dynamic Object-aware preview for axismundi/object-visibility. */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/object-visibility', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-object__visibility' } );

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Settings', 'axismundi-object-projections' ) },
						el( components.ToggleControl, {
							label: __( 'Show the audience name', 'axismundi-object-projections' ),
							help: __( 'The name is always available to screen readers. This puts it on screen as well.', 'axismundi-object-projections' ),
							checked: !! attributes.showLabel,
							onChange: function ( value ) {
								props.setAttributes( { showLabel: !! value } );
							}
						} )
					)
				),
				// One representative value: the editor is not rendering an Object, and a preview
				// that cycled through audiences would suggest the block is a control.
				el(
					'span',
					blockProps,
					el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'public' ),
					attributes.showLabel ? el( 'span', null, __( 'Public', 'axismundi-object-projections' ) ) : null
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
