/**
 * axismundi/object-title editor registration (no build step).
 *
 * Metadata stays in block.json so the server remains the canonical block
 * definition. This file supplies only the dynamic Object-aware preview and
 * the contextual title controls Core's Post Title exposes.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'axismundi/object-title', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var level = Number( attributes.level );
			level = level >= 1 && level <= 6 ? level : 1;
			var tag = 'h' + level;
			var title = __( 'Object title', 'axismundi-object-projections' );
			var titleContent = attributes.isLink
				? el( 'a', { href: '#', onClick: function ( event ) { event.preventDefault(); } }, title )
				: title;

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
							label: __( 'Heading level', 'axismundi-object-projections' ),
							value: String( level ),
							options: [ 1, 2, 3, 4, 5, 6 ].map( function ( value ) {
								return { label: 'H' + value, value: String( value ) };
							} ),
							onChange: function ( value ) { setAttributes( { level: Number( value ) } ); }
						} ),
						el( ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Make title a link', 'axismundi-object-projections' ),
							checked: !! attributes.isLink,
							onChange: function ( value ) { setAttributes( { isLink: value } ); }
						} ),
						attributes.isLink ? el( ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Open in new tab', 'axismundi-object-projections' ),
							checked: '_blank' === attributes.linkTarget,
							onChange: function ( value ) { setAttributes( { linkTarget: value ? '_blank' : '_self' } ); }
						} ) : null
					)
				),
				el( tag, blockEditor.useBlockProps( { className: 'wp-block-post-title axismundi-object__title' } ), titleContent )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
