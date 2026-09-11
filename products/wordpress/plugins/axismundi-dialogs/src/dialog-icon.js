import {
	flipHorizontal as flipHorizontalIcon,
	flipVertical as flipVerticalIcon,
	rotateRight,
} from '@wordpress/icons';
import * as blocks from '@wordpress/blocks';
import * as blockEditor from '@wordpress/block-editor';
import * as element from '@wordpress/element';
import * as components from '@wordpress/components';
import * as i18n from '@wordpress/i18n';
import * as primitives from '@wordpress/primitives';
import {
	IconElement,
	IconPlaceholder,
	flipClasses,
	glyphName,
	rotationStyle,
	useIconRecord,
} from './shared/icon';
import {
	DEFAULT_ICONS,
	IconLibraryModal,
	IconReferenceControls,
	fontFamilyOptions,
} from './shared/icon-controls';

/**
 * axismundi/dialog-icon — editor registration.
 *
 * The icon has an explicit source, and each source keeps its own value format:
 * a Material Symbols ligature for `font`, a WordPress Icon Registry reference
 * for `registry`. See render.php for why the source is stored rather than
 * inferred.
 */
var el = element.createElement;
var Fragment = element.Fragment;
var useBlockProps = blockEditor.useBlockProps;
var useSettings = blockEditor.useSettings;
var useDimensionsProps = blockEditor.getDimensionsClassesAndStyles;
// The same three core/icon uses. Colour, border and padding skip serialisation
// in block.json, so useBlockProps no longer paints them and the preview has to
// apply them itself - or the editor shows none of them, on either source.
var useColorProps = blockEditor.__experimentalUseColorProps;
var useBorderProps = blockEditor.__experimentalUseBorderProps;
var useSpacingProps = blockEditor.__experimentalGetSpacingClassesAndStyles;
var InspectorControls = blockEditor.InspectorControls;
var BlockControls = blockEditor.BlockControls;
var PanelBody = components.PanelBody;
var SelectControl = components.SelectControl;
var TextControl = components.TextControl;
var RangeControl = components.RangeControl;
var ToggleControl = components.ToggleControl;
var ToolbarButton = components.ToolbarButton;
var DropdownMenu = components.DropdownMenu;
var ToolsPanel = components.__experimentalToolsPanel;
var ToolsPanelItem = components.__experimentalToolsPanelItem;
var __ = i18n.__;

var AXIS_DEFAULTS = { FILL: '0', wght: '400', GRAD: '0', opsz: '24' };

function clampWeight( value ) {
	var weight = Number( value );
	return Number.isFinite( weight ) ? Math.min( 700, Math.max( 100, weight ) ) : 400;
}

function clampGrade( value ) {
	var grade = Number( value );
	return Number.isFinite( grade ) ? Math.min( 200, Math.max( -25, grade ) ) : 0;
}

function opticalSize( value ) {
	var size = Number( value );
	return [ 20, 24, 40, 48 ].indexOf( size ) !== -1 ? size : 24;
}

function cssFontSize( value ) {
	var preset = typeof value === 'string' && value.match( /^var:preset\|font-size\|(.+)$/ );
	if ( ! preset ) {
		return value || '24px';
	}
	var slug = preset[ 1 ]
		.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
		.replace( /[^A-Za-z0-9]+/g, '-' )
		.toLowerCase();
	return 'var(--wp--preset--font-size--' + slug + ')';
}

// This is deliberately the proposed block-style shape, rather than a custom
// top-level attribute. Gutenberg currently has no Typography support key for
// arbitrary OpenType axes, so dialog-icon owns this adapter for now.
function variationAxes( typography ) {
	var settings = typography.fontVariationSettings;
	if ( Array.isArray( settings ) ) {
		return settings.reduce( function ( axes, setting ) {
			return setting && typeof setting === 'object'
				? Object.assign( axes, setting )
				: axes;
		}, {} );
	}
	return settings && typeof settings === 'object' ? settings : {};
}

function setVariationAxis( typography, axis, value ) {
	var settings = Array.isArray( typography.fontVariationSettings )
		? typography.fontVariationSettings.map( function ( setting ) {
			return setting && typeof setting === 'object' ? Object.assign( {}, setting ) : setting;
		} )
		: [];
	var updated = false;

	settings = settings.map( function ( setting ) {
		if ( setting && typeof setting === 'object' && Object.prototype.hasOwnProperty.call( setting, axis ) ) {
			updated = true;
			return Object.assign( {}, setting, { [ axis ]: String( value ) } );
		}
		return setting;
	} );

	if ( ! updated ) {
		settings.push( { [ axis ]: String( value ) } );
	}

	return Object.assign( {}, typography, { fontVariationSettings: settings } );
}

function resetVariationSettings( style ) {
	var nextStyle = Object.assign( {}, style || {} );
	var nextTypography = Object.assign( {}, nextStyle.typography || {} );
	nextTypography.fontVariationSettings = Object.keys( AXIS_DEFAULTS ).map( function ( axis ) {
		return { [ axis ]: AXIS_DEFAULTS[ axis ] };
	} );
	nextStyle.typography = nextTypography;

	return nextStyle;
}

function hasOwn( object, property ) {
	return !! object && Object.prototype.hasOwnProperty.call( object, property );
}

// icons.css owns the Material Symbols defaults. The block writes a custom
// property only when the author has actually overridden its matching axis.
function fontAxisOverrides( attributes, typography, axes, size, values ) {
	var style = {};
	if ( attributes.fontSize || typography.fontSize ) {
		style[ '--md-icon-size' ] = size;
	}
	if ( hasOwn( axes, 'FILL' ) ) {
		style[ '--md-icon-fill' ] = values.fill ? 1 : 0;
	}
	if ( hasOwn( axes, 'wght' ) || hasOwn( typography, 'wght' ) || hasOwn( attributes, 'iconWeight' ) || hasOwn( typography, 'fontWeight' ) ) {
		style[ '--md-icon-wght' ] = values.weight;
	}
	if ( hasOwn( axes, 'GRAD' ) ) {
		style[ '--md-icon-grad' ] = values.grade;
	}
	if ( hasOwn( axes, 'opsz' ) ) {
		style[ '--md-icon-opsz' ] = values.opticalSize;
	}
	return style;
}

// core/icon's block icon (icon.js).
var blockIcon = el( primitives.SVG, { xmlns: 'http://www.w3.org/2000/svg', width: '24', height: '24', fill: 'none' },
	el( primitives.Path, { d: 'M6 9.5h3.5V6H6v3.5Zm5 .5a1 1 0 0 1-.898.995L10 11H5.5l-.103-.005a1 1 0 0 1-.892-.893L4.5 10V5.5a1 1 0 0 1 1-1H10a1 1 0 0 1 1 1V10ZM18.25 7.75a2 2 0 1 0-4 0 2 2 0 0 0 4 0Zm1.5 0a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM6.88 13.535a1 1 0 0 1 1.74 0l2.534 4.472a1 1 0 0 1-.87 1.493H5.216a1 1 0 0 1-.87-1.493l2.534-4.472ZM6.074 18h3.352L7.75 15.041l-1.676 2.96ZM14.952 13h2.596a1 1 0 0 1 .866.5l1.298 2.25a1 1 0 0 1 0 1L18.414 19l-.074.11a1 1 0 0 1-.792.39h-2.596a1 1 0 0 1-.792-.39l-.074-.11-1.298-2.25a1.001 1.001 0 0 1 0-1l1.298-2.25a1 1 0 0 1 .866-.5Zm-.72 3.25 1.01 1.75h2.017l1.009-1.75-1.01-1.75h-2.017l-1.01 1.75Z' } )
);

// core/icon keeps its default in variations.js rather than block.json, and so
// does this block. The default is written in full so every inserted icon
// stores its source and axes - see render.php for why the source is explicit.
var DEFAULT_ATTRIBUTES = {
	iconSource: 'font',
	icon: 'info',
	iconClass: 'material-symbols-outlined',
	tagName: 'span',
	style: {
		typography: {
			fontVariationSettings: [ { FILL: '0' }, { wght: '400' }, { GRAD: '0' }, { opsz: '24' } ],
		},
	},
};

blocks.registerBlockType( 'axismundi/dialog-icon', {
	icon: blockIcon,
	example: {
		attributes: DEFAULT_ATTRIBUTES,
	},
	variations: [
		{
			name: 'default',
			isDefault: true,
			attributes: DEFAULT_ATTRIBUTES,
		},
	],
		edit: function ( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		var libraryState = element.useState( false );
		var isLibraryOpen = libraryState[ 0 ];
		var setLibraryOpen = libraryState[ 1 ];
		var source = a.iconSource === 'registry' ? 'registry' : 'font';
		var isRegistry = source === 'registry';
		var isContentOnlyMode = blockEditor.useBlockEditingMode() === 'contentOnly';
		// An absent attribute is legacy content and receives the variation's
		// default. An explicitly empty string is a deliberate "no icon" value
		// and must stay empty in the controlled input.
		var icon = Object.prototype.hasOwnProperty.call( a, 'icon' )
			? a.icon
			: DEFAULT_ICONS[ source ];
		// Empty as the page sees it: a glyph name with nothing renderable left
		// after sanitising draws nothing there, so it is empty here too.
		var isEmptyIcon = isRegistry ? icon === '' : glyphName( icon ) === '';
		var tagName = a.tagName === 'div' ? 'div' : 'span';
		var iconClass = a.iconClass || 'material-symbols-outlined';
		var typography = ( a.style && a.style.typography ) || {};
		var axes = variationAxes( typography );
		var iconWeight = clampWeight( axes.wght || typography.wght || a.iconWeight || typography.fontWeight );
		var iconFill = String( axes.FILL || '0' ) === '1';
		var iconGrade = clampGrade( axes.GRAD );
		var iconOpticalSize = opticalSize( axes.opsz );
		var fontOptions = fontFamilyOptions( useSettings( 'typography.fontFamilies' )[ 0 ] );
		// Core Typography stores a preset in the top-level `fontSize` attribute
		// and only stores a custom value under style.typography.fontSize.
		var iconFontSize = cssFontSize(
			a.fontSize ? 'var:preset|font-size|' + a.fontSize : typography.fontSize
		);
		var dimensionsProps = useDimensionsProps( a );
		var colorProps = useColorProps( a );
		var borderProps = useBorderProps( a );
		// Padding only: margin is still serialised onto the wrapper by
		// useBlockProps, as it is by the server.
		var spacingProps = useSpacingProps( {
			style: { spacing: { padding: a.style && a.style.spacing ? a.style.spacing.padding : undefined } },
		} );
		// Painted on whichever element the front end paints: the glyph wrapper
		// for a font icon, the box around the SVG for a registry one. Width is
		// registry only, as in render.php.
		var boxClassName = [ colorProps.className, borderProps.className, spacingProps.className ]
			.filter( Boolean ).join( ' ' );
		var boxStyle = Object.assign( {}, colorProps.style, borderProps.style, spacingProps.style );
		var fontAxisStyle = fontAxisOverrides( a, typography, axes, iconFontSize, {
			fill: iconFill,
			weight: iconWeight,
			grade: iconGrade,
			opticalSize: iconOpticalSize,
		} );
		// The one size token, for either source (style.css). Written only when
		// the author chose a size; otherwise the container's value or 24px.
		var sizeStyle = a.fontSize || typography.fontSize ? { '--md-icon-size': iconFontSize } : {};
		var hasRegistryWidth = !!( dimensionsProps.style && dimensionsProps.style.width );

		// The icon reference, as the server renderer takes it (includes/icon.php).
		var iconRef = {
			source: source,
			name: icon,
			'class': iconClass,
			flipHorizontal: !! a.flipHorizontal,
			flipVertical: !! a.flipVertical,
			rotation: Number( a.rotation ) || 0,
		};
		var record = useIconRecord( iconRef );

		var isRegistryPlaceholder = isRegistry && !record.content;
		// The block box, as render.php outputs it: margin and alignment, and the
		// element a parent layout sizes. The painted element sits inside it.
		// No aria here, as core/icon's editor wrapper has none: the editor's
		// block wrapper is a focusable region with a name of its own ("Block:
		// Dialog Icon"), which aria-label replaced and aria-hidden took out of
		// the accessibility tree. The icon's semantics sit on the painted
		// element, as on the page.
		var blockProps = useBlockProps( {
			className: 'ax-dialog-icon ' +
				( isRegistry ? 'ax-dialog-icon--registry' : 'ax-dialog-icon--font' ) +
				( isEmptyIcon || isRegistryPlaceholder ? ' ax-dialog-icon--empty' : '' ),
		} );

		function joinClasses() {
			return Array.prototype.filter.call( arguments, Boolean ).join( ' ' );
		}

		// The painted element comes from the shared renderer (shared/icon.js),
		// with this block's paint: colour, border and padding on both sources,
		// the Width on a registry icon, the font axes on a glyph. Null means
		// nothing to draw, and the placeholder takes its place.
		var painted = el( IconElement, {
			icon: iconRef,
			record: record,
			label: a.ariaLabel,
			className: isRegistry
				? joinClasses( boxClassName, dimensionsProps.className, hasRegistryWidth && 'ax-icon--has-width' )
				: boxClassName,
			style: isRegistry
				? Object.assign( {}, boxStyle, sizeStyle, dimensionsProps.style || {} )
				: Object.assign( {}, boxStyle, fontAxisStyle ),
		} );
		if ( isEmptyIcon || isRegistryPlaceholder ) {
			// As core/icon: the placeholder takes the icon's place directly, with
			// border, padding, width and the transforms, and not its colours -
			// an empty box painted in a chosen background would read as an icon.
			// Width is a registry setting, as it is for a drawn icon here; the
			// transforms belong to both sources.
			painted = el( IconPlaceholder, {
				className: joinClasses(
					borderProps.className,
					spacingProps.className,
					isRegistry && dimensionsProps.className,
					flipClasses( iconRef )
				),
				style: Object.assign(
					{},
					borderProps.style,
					spacingProps.style,
					sizeStyle,
					isRegistry ? dimensionsProps.style : {},
					rotationStyle( iconRef ),
					{ height: 'auto' }
				),
			} );
		}

		var preview = el( tagName, blockProps, painted );

			// Offered whenever there is an icon, on either source, as core/icon
			// offers them whenever `icon` is set.
			var transformControls = ! isEmptyIcon
				? el( BlockControls, { group: 'block' },
					el( ToolbarButton, {
						icon: flipHorizontalIcon,
						label: __( 'Flip horizontal', 'axismundi-dialogs' ),
						isPressed: !!a.flipHorizontal,
						onClick: function () { set( { flipHorizontal: !a.flipHorizontal } ); },
					} ),
					el( ToolbarButton, {
						icon: flipVerticalIcon,
						label: __( 'Flip vertical', 'axismundi-dialogs' ),
						isPressed: !!a.flipVertical,
						onClick: function () { set( { flipVertical: !a.flipVertical } ); },
					} ),
					el( ToolbarButton, {
						icon: rotateRight,
						label: __( 'Rotate', 'axismundi-dialogs' ),
						onClick: function () { set( { rotation: ( ( Number( a.rotation ) || 0 ) + 90 ) % 360 } ); },
					} )
				)
				: null;

			var libraryControl = isRegistry
				? el( BlockControls, { group: 'other' },
					el( ToolbarButton, {
						onClick: function () { setLibraryOpen( true ); },
					}, icon ? __( 'Replace', 'axismundi-dialogs' ) : __( 'Choose icon', 'axismundi-dialogs' ) )
				)
				: null;

		var weightControl = ! isRegistry
			? el( RangeControl, {
				label: __( 'Weight', 'axismundi-dialogs' ),
				value: iconWeight,
				min: 100,
				max: 700,
				step: 1,
				onChange: function ( value ) {
					set( {
						style: Object.assign( {}, a.style || {}, {
						typography: setVariationAxis( typography, 'wght', clampWeight( value ) ),
						} ),
					} );
				},
				__nextHasNoMarginBottom: true,
			} )
			: null;

		var fillControl = ! isRegistry
			? el( ToggleControl, {
				label: __( 'Fill', 'axismundi-dialogs' ),
				checked: iconFill,
				onChange: function ( value ) {
					set( {
						style: Object.assign( {}, a.style || {}, {
							typography: setVariationAxis( typography, 'FILL', value ? 1 : 0 ),
						} ),
					} );
				},
				__nextHasNoMarginBottom: true,
			} )
			: null;

		var gradeControl = ! isRegistry
			? el( RangeControl, {
				label: __( 'Grade', 'axismundi-dialogs' ),
				value: iconGrade,
				min: -25,
				max: 200,
				step: 1,
				onChange: function ( value ) {
					set( {
						style: Object.assign( {}, a.style || {}, {
							typography: setVariationAxis( typography, 'GRAD', clampGrade( value ) ),
						} ),
					} );
				},
				__nextHasNoMarginBottom: true,
			} )
			: null;

		var opticalSizeControl = ! isRegistry
			? el( SelectControl, {
				label: __( 'Optical size', 'axismundi-dialogs' ),
				value: String( iconOpticalSize ),
				options: [ 20, 24, 40, 48 ].map( function ( value ) {
					return { label: String( value ), value: String( value ) };
				} ),
				onChange: function ( value ) {
					set( {
						style: Object.assign( {}, a.style || {}, {
							typography: setVariationAxis( typography, 'opsz', opticalSize( value ) ),
						} ),
					} );
				},
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			} )
			: null;

		// Source, font and name, shared with dialog-icon-button.
		var referenceControls = el( IconReferenceControls, {
			source: source,
			icon: icon,
			iconClass: iconClass,
			fontOptions: fontOptions,
			record: record,
			onChange: set,
			onSourceChange: function () { setLibraryOpen( false ); },
		} );

		// Both sources: a labelled glyph is an image with a name, as a labelled
		// SVG is (includes/icon.php). core/icon's wording.
		var labelHelp = __( 'Briefly describe the icon to help screen reader users. Leave blank for decorative icons.', 'axismundi-dialogs' );
		var labelControl = el( TextControl, {
			label: __( 'Label', 'axismundi-dialogs' ),
			help: labelHelp,
			value: a.ariaLabel || '',
			onChange: function ( value ) { set( { ariaLabel: value || undefined } ); },
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		} );

		// core/icon: with content-only editing the inspector is hidden, so the
		// label - content, not design - is offered from the toolbar instead.
		var contentOnlyLabelControl = isContentOnlyMode && ! isEmptyIcon
			? el( BlockControls, { group: 'other' },
				el( DropdownMenu, {
					icon: '',
					toggleProps: { as: ToolbarButton },
					popoverProps: { className: 'is-alternate' },
					text: __( 'Label', 'axismundi-dialogs' ),
				}, function () {
					return el( TextControl, {
						className: 'ax-dialog-icon__toolbar-content',
						label: __( 'Label', 'axismundi-dialogs' ),
						value: a.ariaLabel || '',
						onChange: function ( value ) { set( { ariaLabel: value || undefined } ); },
						help: labelHelp,
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
					} );
				} )
			)
			: null;

		var resetAxis = function ( axis ) {
			set( {
				style: Object.assign( {}, a.style || {}, {
					typography: setVariationAxis( typography, axis, AXIS_DEFAULTS[ axis ] ),
				} ),
			} );
		};
		var fontAxesPanel = ! isRegistry
			? el( InspectorControls, null,
				el( ToolsPanel, {
					label: __( 'Icon font variations', 'axismundi-dialogs' ),
					resetAll: function () {
						set( { style: resetVariationSettings( a.style ) } );
					},
				}, el( ToolsPanelItem, {
					label: __( 'Fill', 'axismundi-dialogs' ),
					isShownByDefault: true,
					hasValue: function () {
						return String( axes.FILL || AXIS_DEFAULTS.FILL ) !== AXIS_DEFAULTS.FILL;
					},
					onDeselect: function () {
						resetAxis( 'FILL' );
					},
				}, fillControl ),
				el( ToolsPanelItem, {
					label: __( 'Weight', 'axismundi-dialogs' ),
					isShownByDefault: true,
					hasValue: function () {
						return String( axes.wght || AXIS_DEFAULTS.wght ) !== AXIS_DEFAULTS.wght;
					},
					onDeselect: function () {
						resetAxis( 'wght' );
					},
				}, weightControl ),
				el( ToolsPanelItem, {
					label: __( 'Grade', 'axismundi-dialogs' ),
					isShownByDefault: true,
					hasValue: function () {
						return String( axes.GRAD || AXIS_DEFAULTS.GRAD ) !== AXIS_DEFAULTS.GRAD;
					},
					onDeselect: function () {
						resetAxis( 'GRAD' );
					},
				}, gradeControl ),
				el( ToolsPanelItem, {
					label: __( 'Optical size', 'axismundi-dialogs' ),
					isShownByDefault: true,
					hasValue: function () {
						return String( axes.opsz || AXIS_DEFAULTS.opsz ) !== AXIS_DEFAULTS.opsz;
					},
					onDeselect: function () {
						resetAxis( 'opsz' );
					},
				}, opticalSizeControl ) )
			)
			: null;

		return el( Fragment, null,
			transformControls,
			libraryControl,
			contentOnlyLabelControl,
			el( InspectorControls, null,
				el( PanelBody, { title: __( 'Dialog icon', 'axismundi-dialogs' ), initialOpen: true },
					el( 'div', { style: { display: 'grid', gap: '16px' } }, referenceControls, labelControl )
				)
			),
			fontAxesPanel,
			el( InspectorControls, { group: 'advanced' },
				el( SelectControl, {
					label: __( 'HTML element', 'axismundi-dialogs' ),
					value: tagName,
					options: [
						{ label: __( 'Default (<span>)', 'axismundi-dialogs' ), value: 'span' },
						{ label: '<div>', value: 'div' },
					],
					onChange: function ( value ) { set( { tagName: value } ); },
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
				} )
			),
			preview,
			isLibraryOpen && isRegistry
				? el( IconLibraryModal, {
					value: icon,
					onClose: function () { setLibraryOpen( false ); },
					onChange: function ( nextIcon ) {
						set( { icon: nextIcon } );
						setLibraryOpen( false );
					},
				} )
				: null
			);
		},
		save: function () { return null; },
	} );
