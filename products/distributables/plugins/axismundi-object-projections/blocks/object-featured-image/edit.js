/**
 * axismundi/object-featured-image editor registration (no build step).
 *
 * Only `edit`/`save` are declared here. Attributes and supports come from
 * block.json, which WordPress bootstraps into the editor; re-declaring them in
 * JavaScript would override that server definition. Sizing, border, and shadow
 * are real block supports, so Core renders those panels -- this file only adds
 * the controls Core has no support for.
 *
 * The preview draws a placeholder rather than a real image: the lead image is
 * resolved per Object at render time, so there is nothing stable to show, and a
 * placeholder that answers the same controls is a truer preview than a borrowed
 * photograph would be.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var FocalPointPicker = blockEditor.FocalPointPicker || components.FocalPointPicker;
	var ColorGradientDropdown = blockEditor.__experimentalColorGradientSettingsDropdown;
	var useMultiOriginColors = blockEditor.__experimentalUseMultipleOriginColorsAndGradients;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;

	/**
	 * The overlay controls, rendered into the Styles tab's Colour panel.
	 *
	 * Opacity without a colour dims nothing, so the two belong together and
	 * belong where an author looks for colour -- not in Settings. Core's colour
	 * dropdown is experimental API, so a plain palette stands in when it is not
	 * exported by the running WordPress.
	 */
	function OverlayControls( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var opacity = el( RangeControl, {
			__nextHasNoMarginBottom: true,
			key: 'opacity',
			label: __( 'Overlay opacity', 'axismundi-object-projections' ),
			value: attributes.dimRatio || 0,
			min: 0,
			max: 100,
			step: 10,
			onChange: function ( value ) { setAttributes( { dimRatio: value } ); },
		} );

		if ( ! ColorGradientDropdown || ! useMultiOriginColors ) {
			return el(
				InspectorControls,
				{ group: 'color' },
				components.ColorPalette
					? el( components.ColorPalette, {
						key: 'palette',
						value: attributes.customOverlayColor,
						onChange: function ( value ) { setAttributes( { customOverlayColor: value, overlayColor: undefined } ); },
					} )
					: null,
				opacity
			);
		}

		var settings = [ {
			label: __( 'Overlay', 'axismundi-object-projections' ),
			colorValue: attributes.overlayColor || attributes.customOverlayColor,
			gradientValue: attributes.gradient || attributes.customGradient,
			onColorChange: function ( value ) { setAttributes( { customOverlayColor: value, overlayColor: undefined } ); },
			onGradientChange: function ( value ) { setAttributes( { customGradient: value, gradient: undefined } ); },
			isShownByDefault: true,
			resetAllFilter: function () {
				return { overlayColor: undefined, customOverlayColor: undefined, gradient: undefined, customGradient: undefined };
			},
		} ];

		return el(
			InspectorControls,
			{ group: 'color' },
			el( ColorGradientDropdown, Object.assign(
				{ key: 'overlay', __experimentalIsRenderedInSidebar: true, settings: settings },
				useMultiOriginColors()
			) ),
			opacity
		);
	}

	blocks.registerBlockType( 'axismundi/object-featured-image', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var focalPoint = attributes.focalPoint || { x: 0.5, y: 0.5 };
			var position = Math.round( focalPoint.x * 100 ) + '% ' + Math.round( focalPoint.y * 100 ) + '%';

			var blockProps = blockEditor.useBlockProps( {
				className: 'axismundi-object__featured-image is-editor-preview',
			} );

			var children = [
				el( 'div', {
					key: 'media',
					className: 'axismundi-object__featured-image-media is-empty'
						+ ( attributes.hasParallax ? ' has-parallax' : '' )
						+ ( attributes.isRepeated ? ' is-repeated' : '' ),
					style: { backgroundPosition: position },
				}, el( 'span', { className: 'axismundi-object__featured-image-label' }, __( 'Object featured image', 'axismundi-object-projections' ) ) ),
			];

			if ( attributes.dimRatio > 0 ) {
				children.push( el( 'span', {
					key: 'overlay',
					'aria-hidden': true,
					className: 'axismundi-object__featured-image-overlay'
						+ ( attributes.overlayColor ? ' has-' + attributes.overlayColor + '-background-color has-background' : '' )
						+ ( attributes.gradient ? ' has-' + attributes.gradient + '-gradient-background has-background' : '' ),
					style: {
						opacity: attributes.dimRatio / 100,
						backgroundColor: ! attributes.overlayColor && attributes.customOverlayColor ? attributes.customOverlayColor : undefined,
						backgroundImage: ! attributes.gradient && attributes.customGradient ? attributes.customGradient : undefined,
					},
				} ) );
			}

			var settings = el(
				InspectorControls,
				{ key: 'settings' },
				el(
					PanelBody,
					{ title: __( 'Settings', 'axismundi-object-projections' ) },
					el( SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Resolution', 'axismundi-object-projections' ),
						help: __( 'How the image fills the space it is given.', 'axismundi-object-projections' ),
						value: attributes.scale || 'cover',
						options: [
							{ label: __( 'Cover', 'axismundi-object-projections' ), value: 'cover' },
							{ label: __( 'Contain', 'axismundi-object-projections' ), value: 'contain' },
							{ label: __( 'Fill', 'axismundi-object-projections' ), value: 'fill' },
						],
						onChange: function ( value ) { setAttributes( { scale: value } ); },
					} ),
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Link to the object', 'axismundi-object-projections' ),
						checked: !! attributes.isLink,
						onChange: function ( value ) { setAttributes( { isLink: value } ); },
					} ),
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Keep the space when there is no image', 'axismundi-object-projections' ),
						help: __( 'Draws a calm placeholder instead of collapsing, for banner slots whose subject often has no image.', 'axismundi-object-projections' ),
						checked: !! attributes.showPlaceholder,
						onChange: function ( value ) { setAttributes( { showPlaceholder: value } ); },
					} ),
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Fixed background', 'axismundi-object-projections' ),
						checked: !! attributes.hasParallax,
						onChange: function ( value ) { setAttributes( { hasParallax: value } ); },
					} ),
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Repeated background', 'axismundi-object-projections' ),
						checked: !! attributes.isRepeated,
						onChange: function ( value ) { setAttributes( { isRepeated: value } ); },
					} ),
					FocalPointPicker
						? el( FocalPointPicker, {
							__nextHasNoMarginBottom: true,
							label: __( 'Focal point', 'axismundi-object-projections' ),
							url: '',
							value: focalPoint,
							onChange: function ( value ) { setAttributes( { focalPoint: value } ); },
						} )
						: null
				)
			);

			return el(
				'figure',
				blockProps,
				settings,
				el( OverlayControls, { key: 'overlay-controls', attributes: attributes, setAttributes: setAttributes } ),
				children
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
