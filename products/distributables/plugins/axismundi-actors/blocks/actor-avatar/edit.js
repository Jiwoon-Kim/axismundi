/**
 * axismundi/actor-avatar editor registration (no build step).
 *
 * Attributes come from block.json, which WordPress bootstraps into the editor;
 * re-declaring them here would override that server definition. Border and
 * shadow are real block supports, but this block skips their automatic
 * serialization onto the block's own wrapper (block.json
 * `__experimentalSkipSerialization`) and applies them to the inner preview
 * element by hand instead, using the same `__experimentalGetBorderClassesAndStyles`
 * / `__experimentalGetShadowClassesAndStyles` helpers Core's own supports use.
 *
 * The reason is the outer element: WordPress draws the editor's selection
 * outline as a pseudo-element sized to the block's own wrapper. A wrapper that is
 * itself round and clipped (`overflow: hidden`, `border-radius: 50%`) clips that
 * selection outline into a circle too, which is why the outer element here stays
 * a plain, unrounded box -- exactly the split Core's own Avatar block uses -- and
 * the round shape lives one level in, on the placeholder.
 *
 * Sizing is a private attribute rather than a support: one custom property drives
 * every variant, so the size control is authoritative in the profile header, in a
 * compact feed row, and in the editor preview alike.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var getBorderStyles = blockEditor.__experimentalGetBorderClassesAndStyles;
	var getShadowStyles = blockEditor.__experimentalGetShadowClassesAndStyles;

	blocks.registerBlockType( 'axismundi/actor-avatar', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var compact = 'compact' === attributes.variant;

			var blockProps = blockEditor.useBlockProps( {
				className: 'ax-actor-avatar is-editor-preview' + ( compact ? ' is-compact' : '' ),
				style: { '--axismundi-actor-avatar-size': ( attributes.size || 128 ) + 'px' },
			} );

			var border = getBorderStyles ? getBorderStyles( attributes ) : { className: '', style: {} };
			var shadow = getShadowStyles ? getShadowStyles( attributes ) : { className: '', style: {} };
			var previewClassName = 'ax-actor-avatar__preview'
				+ ( border.className ? ' ' + border.className : '' )
				+ ( shadow.className ? ' ' + shadow.className : '' );
			var previewStyle = Object.assign( {}, border.style, shadow.style );

			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Avatar', 'axismundi-actors' ) },
						el( components.RangeControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Size (px)', 'axismundi-actors' ),
							value: attributes.size,
							min: 24,
							max: 256,
							onChange: function ( value ) { setAttributes( { size: value || 128 } ); },
						} ),
						el( components.ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Link to the actor profile', 'axismundi-actors' ),
							checked: !! attributes.isLink,
							onChange: function ( value ) { setAttributes( { isLink: value } ); },
						} ),
						attributes.isLink
							? el( components.ToggleControl, {
								__nextHasNoMarginBottom: true,
								label: __( 'Open in a new tab', 'axismundi-actors' ),
								checked: '_blank' === attributes.linkTarget,
								onChange: function ( value ) { setAttributes( { linkTarget: value ? '_blank' : '_self' } ); },
							} )
							: null
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: previewClassName, style: previewStyle },
						el( 'span', { className: 'ax-actor-avatar__editor-initial', 'aria-label': __( 'Actor avatar preview', 'axismundi-actors' ) }, 'A' )
					)
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
