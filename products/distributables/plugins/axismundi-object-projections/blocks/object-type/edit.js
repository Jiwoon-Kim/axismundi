/** Dynamic Object-aware preview for axismundi/object-type. */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;

	blocks.registerBlockType( 'axismundi/object-type', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var level = Number( attributes.level );
			level = level >= 1 && level <= 6 ? level : 0;
			var tag = level ? 'h' + level : 'span';

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
							label: __( 'Display as', 'axismundi-object-projections' ),
							value: String( level ),
							options: [
								{ label: __( 'Inline text', 'axismundi-object-projections' ), value: '0' },
								{ label: 'H1', value: '1' },
								{ label: 'H2', value: '2' },
								{ label: 'H3', value: '3' },
								{ label: 'H4', value: '4' },
								{ label: 'H5', value: '5' },
								{ label: 'H6', value: '6' }
							],
							onChange: function ( value ) { setAttributes( { level: Number( value ) } ); }
						} )
					)
				),
				el( tag, blockEditor.useBlockProps( { className: 'wp-block-query-title axismundi-object__type' } ), __( 'Article', 'axismundi-object-projections' ) )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
