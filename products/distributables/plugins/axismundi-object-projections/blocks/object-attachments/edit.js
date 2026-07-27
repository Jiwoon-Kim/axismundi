/**
 * axismundi/object-attachments editor registration (no build step).
 *
 * Gallery and Carousel are two renderings of one block, not two blocks: the attachments
 * are the same set either way, so the choice is a view of them. It lives in the toolbar
 * as icon buttons and is deliberately not repeated in the sidebar — the same placement
 * Core's Latest Posts uses for its list/grid switch, and the one
 * `axismundi/actor-profile-fields` already follows here.
 *
 * Gallery view is a real Core Gallery block preview. Keeping its nested Image block tree
 * means Core, rather than a lookalike CSS grid, owns columns and cropping in the editor.
 *
 * The gallery/carousel icons are Core's own `gallery` / `swatch` style glyphs, inlined
 * so this file has no script dependency on `@wordpress/icons`.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var useRef = element.useRef;
	var useEffect = element.useEffect;
	var useState = element.useState;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var BlockPreview = blockEditor.BlockPreview;
	var useSettings = blockEditor.useSettings;
	var InspectorControls = blockEditor.InspectorControls;
	var BlockControls = blockEditor.BlockControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var ToolbarGroup = components.ToolbarGroup;
	var ToolbarButton = components.ToolbarButton;

	var ICON_PROPS = { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true, focusable: false };
	var GALLERY_ICON = el( 'svg', ICON_PROPS, el( 'path', {
		d: 'M4 4h6v6H4V4zm0 10h6v6H4v-6zM14 4h6v6h-6V4zm0 10h6v6h-6v-6z'
	} ) );
	var CAROUSEL_ICON = el( 'svg', ICON_PROPS, el( 'path', {
		d: 'M7 6h10v12H7V6zM3 8h2v8H3V8zm16 0h2v8h-2V8z'
	} ) );

	var PREVIEW_PALETTES = [
		[ '#83b7dd', '#253758' ],
		[ '#f0bc5e', '#915a35' ],
		[ '#eca29a', '#82495e' ],
		[ '#9ecbaf', '#315b60' ],
		[ '#a497d1', '#47385e' ],
		[ '#d7b4da', '#6d4269' ]
	];
	var GALLERY_PREVIEW_WIDTH = 760;

	function previewImageUrl( index ) {
		var palette = PREVIEW_PALETTES[ index % PREVIEW_PALETTES.length ];
		var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600">'
			+ '<rect width="800" height="600" fill="' + palette[ 0 ] + '"/>'
			+ '<circle cx="620" cy="155" r="92" fill="' + palette[ 1 ] + '" opacity=".72"/>'
			+ '<path d="M0 480 220 265l155 145 130-116 295 286H0z" fill="' + palette[ 1 ] + '" opacity=".88"/>'
			+ '</svg>';

		return 'data:image/svg+xml,' + encodeURIComponent( svg );
	}

	function previewImageBlock( index, aspectRatio ) {
		var attributes = {
			url: previewImageUrl( index ),
			alt: '',
			linkDestination: 'none',
			sizeSlug: 'large'
		};
		if ( 'auto' !== aspectRatio ) {
			attributes.aspectRatio = aspectRatio;
		}

		return blocks.createBlock( 'core/image', attributes );
	}

	function previewImageFigure( index, aspectRatio, crop ) {
		var imageStyle = {
			display: 'block',
			width: '100%',
			height: '100%',
			objectFit: crop ? 'cover' : 'contain'
		};
		if ( 'auto' !== aspectRatio ) {
			imageStyle.aspectRatio = aspectRatio;
		}
		return el(
			'figure',
			{ className: 'wp-block-image', key: 'item-' + index },
			el( 'img', {
				src: previewImageUrl( index ),
				alt: '',
				style: imageStyle
			} )
		);
	}

	blocks.registerBlockType( 'axismundi/object-attachments', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var previewRef = useRef( null );
			var previewAvailableWidthState = useState( GALLERY_PREVIEW_WIDTH );
			var previewAvailableWidth = previewAvailableWidthState[ 0 ];
			var setPreviewAvailableWidth = previewAvailableWidthState[ 1 ];
			useEffect( function () {
				var node = previewRef.current;
				if ( ! node ) {
					return undefined;
				}
				var updateWidth = function () {
					var width = Math.floor( node.getBoundingClientRect().width );
					if ( width > 0 ) {
						setPreviewAvailableWidth( function ( currentWidth ) {
							return currentWidth === width ? currentWidth : width;
						} );
					}
				};
				updateWidth();
				if ( 'undefined' === typeof ResizeObserver ) {
					return undefined;
				}
				var observer = new ResizeObserver( updateWidth );
				observer.observe( node );
				return function () { observer.disconnect(); };
			}, [] );
			var ratioSettings = useSettings(
				'dimensions.aspectRatios.default',
				'dimensions.aspectRatios.theme',
				'dimensions.defaultAspectRatios'
			);
			var defaultRatios = Array.isArray( ratioSettings[ 0 ] ) ? ratioSettings[ 0 ] : [];
			var themeRatios = Array.isArray( ratioSettings[ 1 ] ) ? ratioSettings[ 1 ] : [];
			var showDefaultRatios = Boolean( ratioSettings[ 2 ] );
			var aspectRatioOptions = [ {
				label: __( 'Original', 'axismundi-object-projections' ),
				value: 'auto'
			} ];
			if ( showDefaultRatios ) {
				defaultRatios.forEach( function ( ratio ) {
					aspectRatioOptions.push( { label: ratio.name, value: ratio.ratio } );
				} );
			}
			themeRatios.forEach( function ( ratio ) {
				aspectRatioOptions.push( { label: ratio.name, value: ratio.ratio } );
			} );
			var mode = 'carousel' === attributes.displayMode ? 'carousel' : 'gallery';
			var columns = attributes.columns;
			var crop = attributes.imageCrop !== false;
			var previewCount = Number( attributes.previewCount );
			previewCount = Number.isFinite( previewCount ) ? Math.max( 0, Math.min( 20, Math.round( previewCount ) ) ) : 4;
			var previewColumns = Number( columns );
			previewColumns = Number.isFinite( previewColumns ) && previewColumns > 0 ? Math.min( 8, Math.round( previewColumns ) ) : 2;
			// The editor has no Object data. "All" has no real total here, so six
			// representative tiles make the selected composition visible.
			var placeholderCount = 0 === previewCount ? 6 : previewCount;

			var toolbar = el(
				BlockControls,
				null,
				el(
					ToolbarGroup,
					null,
					el( ToolbarButton, {
						icon: GALLERY_ICON,
						label: __( 'Gallery view', 'axismundi-object-projections' ),
						isPressed: 'gallery' === mode,
						onClick: function () { setAttributes( { displayMode: 'gallery' } ); }
					} ),
					el( ToolbarButton, {
						icon: CAROUSEL_ICON,
						label: __( 'Carousel view', 'axismundi-object-projections' ),
						isPressed: 'carousel' === mode,
						onClick: function () { setAttributes( { displayMode: 'carousel' } ); }
					} )
				)
			);

			var selectedAspectRatio = attributes.aspectRatio || '5/3';
			var carouselItems = 'carousel' === mode
				? Array.from( { length: placeholderCount }, function ( _, index ) {
					return previewImageFigure( index, selectedAspectRatio, crop );
				} )
				: [];

			var preview;
			if ( 'carousel' === mode ) {
				preview = el(
					'figure',
					useBlockProps( { className: 'axismundi-object__attachments is-mode-carousel' } ),
					el(
						'div',
						{ className: 'axismundi-object__carousel' },
						el(
							'div',
							{ className: 'axismundi-object__carousel-viewport' },
							el(
								'div',
								{ className: 'axismundi-object__carousel-track' },
								carouselItems.map( function ( item, index ) {
									return el( 'div', { className: 'axismundi-object__carousel-slide', key: 'slide-' + index }, item );
								} )
							)
						),
						el( 'div', { className: 'axismundi-object__carousel-dots' },
							carouselItems.map( function ( _, index ) {
								return el( 'button', {
									type: 'button',
									key: 'dot-' + index,
									className: 'axismundi-object__carousel-dot' + ( 0 === index ? ' is-active' : '' ),
									'aria-label': __( 'Slide', 'axismundi-object-projections' ) + ' ' + ( index + 1 )
								} );
							} )
						)
					)
				);
			} else {
				// This is a nested, editor-only Core Gallery. Without an explicit zero
				// bottom margin, the preview iframe's flow layout gives it a trailing
				// block gap and leaves an empty strip below the final tile.
				var galleryAttributes = {
					columns: previewColumns,
					imageCrop: crop,
					linkTo: 'none',
					style: {
						spacing: {
							margin: { bottom: '0' }
						}
					}
				};
				if ( selectedAspectRatio !== 'auto' ) {
					galleryAttributes.aspectRatio = selectedAspectRatio;
				}
				if ( attributes.style && attributes.style.spacing && attributes.style.spacing.blockGap ) {
					galleryAttributes.style.spacing.blockGap = attributes.style.spacing.blockGap;
				}
				var galleryPreviewKey = [
					previewColumns,
					placeholderCount,
					crop,
					selectedAspectRatio,
					JSON.stringify( galleryAttributes.style || {} )
				].join( ':' );
				preview = el(
					'div',
					useBlockProps( {
						className: 'axismundi-object__attachments is-mode-gallery',
						ref: previewRef
					} ),
					el( BlockPreview, {
						key: galleryPreviewKey,
						blocks: [ blocks.createBlock( 'core/gallery', galleryAttributes, Array.from( { length: placeholderCount }, function ( _, index ) {
							return previewImageBlock( index, selectedAspectRatio );
						} ) ) ],
						// Match the nested Gallery viewport to this block's measured width so
						// Gutenberg's selection outline and the rendered grid share bounds.
						viewportWidth: previewAvailableWidth
					} )
				);
			}

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Settings', 'axismundi-object-projections' ) },
					'gallery' === mode
						? el( RangeControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Columns', 'axismundi-object-projections' ),
							value: columns,
							onChange: function ( value ) { setAttributes( { columns: value } ); },
							min: 1,
							max: 8,
							allowReset: true
						} )
						: null,
					'gallery' === mode
						? el( RangeControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Preview items', 'axismundi-object-projections' ),
							help: __( 'How many attachments the gallery shows. The rest stay reachable in the media dialog, counted on the last tile. 0 shows all.', 'axismundi-object-projections' ),
							value: previewCount,
							onChange: function ( value ) { setAttributes( { previewCount: value || 0 } ); },
							min: 0,
							max: 20,
							allowReset: true
						} )
						: null,
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Crop images to fit', 'axismundi-object-projections' ),
						checked: crop,
						onChange: function ( value ) { setAttributes( { imageCrop: value } ); }
					} ),
					el( SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Aspect ratio', 'axismundi-object-projections' ),
						value: selectedAspectRatio,
						options: aspectRatioOptions,
						onChange: function ( value ) { setAttributes( { aspectRatio: value } ); }
					} ),
					el( SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Link to', 'axismundi-object-projections' ),
						help: __( 'Linking to the media file replaces the media dialog.', 'axismundi-object-projections' ),
						value: attributes.linkTo || 'none',
						options: [
							{ label: __( 'None', 'axismundi-object-projections' ), value: 'none' },
							{ label: __( 'Media file', 'axismundi-object-projections' ), value: 'media' }
						],
						onChange: function ( value ) { setAttributes( { linkTo: value } ); }
					} )
				)
			);

			return el( element.Fragment, null, toolbar, inspector, preview );
		},
		save: function () { return null; }
	} );
}(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n
) );
