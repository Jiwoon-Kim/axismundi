/**
 * axismundi/actor-name editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-name', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var level = attributes.level || 0;
			var Tag = level >= 1 && level <= 6 ? 'h' + level : 'span';
			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Name', 'axismundi-actors' ) },
						el( components.SelectControl, {
							label: __( 'Heading level', 'axismundi-actors' ),
							value: String( level ),
							options: [
								{ label: __( 'Inline text', 'axismundi-actors' ), value: '0' },
								{ label: 'H1', value: '1' },
								{ label: 'H2', value: '2' },
								{ label: 'H3', value: '3' },
								{ label: 'H4', value: '4' },
								{ label: 'H5', value: '5' },
								{ label: 'H6', value: '6' }
							],
							onChange: function ( value ) { setAttributes( { level: parseInt( value, 10 ) } ); },
							__nextHasNoMarginBottom: true,
						} ),
						el( components.ToggleControl, {
							label: __( 'Link to Actor profile', 'axismundi-actors' ),
							checked: !! attributes.isLink,
							onChange: function ( value ) { setAttributes( { isLink: value } ); },
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					Tag,
					blockEditor.useBlockProps( { className: 'ax-actor-name is-editor-preview' + ( 'span' === Tag ? '' : ' wp-block-heading' ) } ),
					__( 'Actor display name', 'axismundi-actors' )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
