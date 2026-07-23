/**
 * axismundi/actor-handle editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-handle', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Handle', 'axismundi-actors' ) },
						el( components.ToggleControl, {
							label: __( 'Username only', 'axismundi-actors' ),
							help: __( 'The same username exists on many hosts; the full address is unambiguous.', 'axismundi-actors' ),
							checked: !! attributes.shortForm,
							onChange: function ( value ) { setAttributes( { shortForm: value } ); },
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
					'span',
					blockEditor.useBlockProps( { className: 'ax-actor-handle is-editor-preview' } ),
					attributes.shortForm ? '@actor' : '@actor@example.test'
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
