/** Dynamic Object-aware preview for axismundi/object-date. */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'axismundi/object-date', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var isUpdated = 'updated' === attributes.field;
			var dateText = isUpdated
				? __( 'July 27, 2026 (updated)', 'axismundi-object-projections' )
				: __( 'July 20, 2026', 'axismundi-object-projections' );
			var content = attributes.isLink
				? el( 'a', { href: '#', onClick: function ( event ) { event.preventDefault(); } }, dateText )
				: dateText;
			var className = 'wp-block-post-date axismundi-object__date' + ( isUpdated ? ' wp-block-post-date__modified-date' : '' );

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'axismundi-object-projections' ) },
						el( SelectControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Date to display', 'axismundi-object-projections' ),
							value: isUpdated ? 'updated' : 'published',
							options: [
								{ label: __( 'Published', 'axismundi-object-projections' ), value: 'published' },
								{ label: __( 'Last modified', 'axismundi-object-projections' ), value: 'updated' }
							],
							onChange: function ( value ) { setAttributes( { field: value } ); }
						} ),
						el( TextControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Date format', 'axismundi-object-projections' ),
							help: __( 'Optional WordPress date format used on the published page.', 'axismundi-object-projections' ),
							value: attributes.format || '',
							onChange: function ( value ) { setAttributes( { format: value } ); }
						} ),
						el( ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Link to object', 'axismundi-object-projections' ),
							checked: !! attributes.isLink,
							onChange: function ( value ) { setAttributes( { isLink: value } ); }
						} )
					)
				),
				el( 'time', blockEditor.useBlockProps( { className: className, dateTime: '2026-07-27T00:00:00+09:00' } ), content )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
