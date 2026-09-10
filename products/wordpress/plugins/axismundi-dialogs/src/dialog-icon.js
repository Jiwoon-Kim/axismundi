import {
	flipHorizontal as flipHorizontalIcon,
	flipVertical as flipVerticalIcon,
	rotateRight,
} from '@wordpress/icons';
import * as blocks from '@wordpress/blocks';
import * as blockEditor from '@wordpress/block-editor';
import * as element from '@wordpress/element';
import * as components from '@wordpress/components';
import * as data from '@wordpress/data';
import * as coreData from '@wordpress/core-data';
import * as dom from '@wordpress/dom';
import * as i18n from '@wordpress/i18n';

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
var Modal = components.Modal;
var SearchControl = components.SearchControl;
var Spinner = components.Spinner;
var Button = components.Button;
var ToolsPanel = components.__experimentalToolsPanel;
var ToolsPanelItem = components.__experimentalToolsPanelItem;
var useSelect = data.useSelect;
var coreStore = coreData.store;
var __ = i18n.__;

// Each source's default is the same "info" icon, so switching source keeps
// what the dialog is saying and only changes where the drawing comes from.
// Carrying the old value across would always be wrong: the two value spaces
// do not overlap, and "info" is not a registry name any more than
// "core/info" is a glyph.
var DEFAULTS = { font: 'info', registry: 'core/info' };
var AXIS_DEFAULTS = { FILL: '0', wght: '400', GRAD: '0', opsz: '24' };

// `typography.fontFamilies` comes back from useSettings keyed by origin -
// `{ theme: [...], custom: [...] }` - not as one array. Unlike fontSizes and
// the colour palette it is not in PATHS_WITH_OVERRIDE, so getBlockSettings
// does not collapse the origins for it. Treating it as an array threw on the
// first render and took the whole block down. Core flattens it the same way in
// global-styles/typography-utils.js; the array branch covers a filter or an
// older editor that hands back a flat list.
//
// De-duplicated by slug because a Font Library font and a theme font can
// share one, and a select needs one option per stored value. The later origin
// wins, which is the order global styles resolves them in.
function fontFamilyOptions( setting ) {
	var families = Array.isArray( setting )
		? setting
		: [ 'default', 'theme', 'custom' ].reduce( function ( all, origin ) {
			return all.concat( ( setting && setting[ origin ] ) || [] );
		}, [] );
	var bySlug = {};
	families.forEach( function ( font ) {
		if ( font && font.slug ) {
			bySlug[ font.slug ] = { label: font.name || font.slug, value: font.slug };
		}
	} );
	return Object.keys( bySlug ).map( function ( slug ) { return bySlug[ slug ]; } );
}

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

function normalizeIconSearch( value ) {
	return String( value || '' ).toLowerCase().replace( /[\s_-]+/g, '' );
}

// core/icon's Icon Library modal is internal to block-library, but the two
// entities it reads are public. Keep this implementation on those entities so
// dialog-icon can browse the same registry without importing a private module.
function IconLibraryModal( props ) {
	var searchState = element.useState( '' );
	var searchInput = searchState[ 0 ];
	var setSearchInput = searchState[ 1 ];
	var collectionState = element.useState( null );
	var currentCollection = collectionState[ 0 ];
	var setCurrentCollection = collectionState[ 1 ];
	var collections = useSelect( function ( select ) {
		return select( coreStore ).getEntityRecords( 'root', 'iconCollection' );
	}, [] );
	var selectedCollection = props.value ? props.value.split( '/' )[ 0 ] : '';
	var collectionSlug = currentCollection !== null
		? currentCollection
		: ( collections && collections.some( function ( collection ) {
			return collection.slug === selectedCollection;
		} ) ? selectedCollection : ( collections && collections[ 0 ] ? collections[ 0 ].slug : null ) );
	var iconResults = useSelect( function ( select ) {
		if ( collectionSlug === null ) {
			return { icons: null, resolved: false };
		}
		var query = collectionSlug === '' ? {} : { collection: collectionSlug };
		var store = select( coreStore );
		return {
			icons: store.getEntityRecords( 'root', 'icon', query ),
			resolved: store.hasFinishedResolution( 'getEntityRecords', [ 'root', 'icon', query ] ),
		};
	}, [ collectionSlug ] );
	var search = normalizeIconSearch( searchInput );
	var icons = ( iconResults.icons || [] ).filter( function ( registryIcon ) {
		return ! search || normalizeIconSearch( registryIcon.name ).indexOf( search ) !== -1 ||
			normalizeIconSearch( registryIcon.label ).indexOf( search ) !== -1;
	} );

	return el( Modal, {
		className: 'ax-dialog-icon__library',
		title: __( 'Icon library', 'axismundi-dialogs' ),
		onRequestClose: props.onClose,
		isFullScreen: true,
	}, el( 'div', { className: 'ax-dialog-icon__library-layout' },
		el( 'aside', { className: 'ax-dialog-icon__library-sidebar' },
			el( SearchControl, {
				label: __( 'Search icons', 'axismundi-dialogs' ),
				value: searchInput,
				onChange: setSearchInput,
			} ),
			el( 'div', { className: 'ax-dialog-icon__library-collections', role: 'tablist', 'aria-label': __( 'Icon collections', 'axismundi-dialogs' ) },
				[ { slug: '', label: __( 'All', 'axismundi-dialogs' ) } ].concat( collections || [] ).map( function ( collection ) {
					return el( 'button', {
						key: collection.slug,
						type: 'button',
						role: 'tab',
						'aria-selected': collection.slug === collectionSlug,
						className: 'ax-dialog-icon__library-collection' + ( collection.slug === collectionSlug ? ' is-active' : '' ),
						onClick: function () { setCurrentCollection( collection.slug ); },
					}, collection.label );
				} )
			)
		),
		el( 'section', { className: 'ax-dialog-icon__library-panel', role: 'tabpanel' },
			! iconResults.resolved
				? el( 'div', { className: 'ax-dialog-icon__library-loading', role: 'status', 'aria-label': __( 'Loading icons', 'axismundi-dialogs' ) }, el( Spinner ) )
				: ! icons.length
					? el( 'p', { className: 'ax-dialog-icon__library-empty' }, __( 'No results found.', 'axismundi-dialogs' ) )
					: el( 'div', { className: 'ax-dialog-icon__library-grid', 'aria-label': __( 'Icon library', 'axismundi-dialogs' ) },
						icons.map( function ( registryIcon ) {
							var isSelected = registryIcon.name === props.value;
							return el( Button, {
								key: registryIcon.name,
								className: 'ax-dialog-icon__library-item',
								variant: isSelected ? 'primary' : undefined,
								label: registryIcon.label || registryIcon.name,
								onClick: function () { props.onChange( registryIcon.name ); },
								__next40pxDefaultSize: true,
							},
								el( 'span', {
									className: 'ax-dialog-icon__library-item-icon',
									dangerouslySetInnerHTML: { __html: dom.safeHTML( registryIcon.content || '' ) },
								} ),
								el( 'span', { className: 'ax-dialog-icon__library-item-label' }, registryIcon.label || registryIcon.name )
							);
						} )
					)
		)
	) );
}

// Mirrors core/icon's editor-only empty state. This is deliberately distinct
// from a non-empty, unregistered registry name, which remains an error state.
function IconPlaceholder( props ) {
	return el( 'svg', Object.assign( {
		xmlns: 'http://www.w3.org/2000/svg',
		viewBox: '0 0 60 60',
		preserveAspectRatio: 'none',
		fill: 'none',
		'aria-hidden': 'true',
		className: 'ax-dialog-icon__placeholder',
	}, props ),
		el( 'rect', { width: '60', height: '60', fill: 'currentColor', fillOpacity: '0.1' } ),
		el( 'path', {
			vectorEffect: 'non-scaling-stroke',
			stroke: 'currentColor',
			strokeOpacity: '0.25',
			d: 'M60 60 0 0',
		} )
	);
}

blocks.registerBlockType( 'axismundi/dialog-icon', {
		edit: function ( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		var libraryState = element.useState( false );
		var isLibraryOpen = libraryState[ 0 ];
		var setLibraryOpen = libraryState[ 1 ];
		var source = a.iconSource === 'registry' ? 'registry' : 'font';
		var isRegistry = source === 'registry';
		// An absent attribute is legacy content and receives the variation's
		// default. An explicitly empty string is a deliberate "no icon" value
		// and must stay empty in the controlled input.
		var icon = Object.prototype.hasOwnProperty.call( a, 'icon' )
			? a.icon
			: DEFAULTS[ source ];
		var isEmptyIcon = icon === '';
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
		if ( ! fontOptions.some( function ( option ) { return option.value === iconClass; } ) ) {
			fontOptions.unshift( { label: iconClass, value: iconClass } );
		}
		var rotationStyle = a.rotation ? { rotate: String( a.rotation ) + 'deg' } : {};
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
		var registryStyle = Object.assign( {}, boxStyle, dimensionsProps.style || {}, rotationStyle );
		var hasRegistryWidth = !!( dimensionsProps.style && dimensionsProps.style.width );

			// The same entity core/icon previews from, so the editor draws exactly
			// the SVG the server will render for that name.
			var record = useSelect( function ( select ) {
			if ( ! isRegistry || ! icon ) {
				return { content: '', resolved: true };
			}
			var store = select( coreStore );
			var registryIcon = store.getEntityRecord( 'root', 'icon', icon );
			return {
				content: registryIcon && registryIcon.content ? registryIcon.content : '',
				resolved: store.hasFinishedResolution( 'getEntityRecord', [ 'root', 'icon', icon ] ),
			};
		}, [ isRegistry, icon ] );

		var isRegistryPlaceholder = isRegistry && !record.content;
		var className = isRegistry
			? 'ax-dialog-icon ax-dialog-icon--registry'
			: 'ax-dialog-icon ' + iconClass;
			var blockProps = useBlockProps( Object.assign(
				{ className: className },
				isRegistry && a.ariaLabel
					? { 'aria-label': a.ariaLabel }
					: { 'aria-hidden': 'true' }
			) );

			var preview = isEmptyIcon || isRegistryPlaceholder
				? el( tagName, Object.assign( {}, blockProps, {
					className: blockProps.className +
						( boxClassName ? ' ' + boxClassName : '' ) +
						( isRegistry && dimensionsProps.className ? ' ' + dimensionsProps.className : '' ) +
						' ax-dialog-icon--empty',
					style: isRegistry
						? registryStyle
						: Object.assign( {}, blockProps.style, boxStyle, fontAxisStyle ),
				} ), el( IconPlaceholder, {
					style: isRegistry && hasRegistryWidth ? { inlineSize: '100%', blockSize: 'auto' } : undefined,
				} ) )
				: isRegistry
		? el( tagName, Object.assign( {}, blockProps, {
				className: blockProps.className +
					( boxClassName ? ' ' + boxClassName : '' ) +
					( dimensionsProps.className ? ' ' + dimensionsProps.className : '' ) +
					( hasRegistryWidth ? ' ax-dialog-icon--has-width' : '' ) +
					( a.flipHorizontal ? ' is-flip-horizontal' : '' ) +
					( a.flipVertical ? ' is-flip-vertical' : '' ),
				style: registryStyle,
					// The REST record is sanitised by the registry; safeHTML is the
					// same second pass core/icon applies before rendering it.
					dangerouslySetInnerHTML: { __html: dom.safeHTML( record.content ) },
				} ) )
		: el( tagName, Object.assign( {}, blockProps, {
				className: blockProps.className + ( boxClassName ? ' ' + boxClassName : '' ),
				style: Object.assign( {}, blockProps.style, boxStyle, fontAxisStyle ),
			} ), icon );

			var transformControls = isRegistry && record.content
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

			var sourceControl = el( SelectControl, {
				label: __( 'Icon source', 'axismundi-dialogs' ),
				value: source,
				options: [
					{ label: __( 'Icon font', 'axismundi-dialogs' ), value: 'font' },
					{ label: __( 'Icon library', 'axismundi-dialogs' ), value: 'registry' },
				],
				onChange: function ( next ) {
					setLibraryOpen( false );
					set( { iconSource: next, icon: DEFAULTS[ next ] } );
				},
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			} );

		var fontControl = ! isRegistry
			? el( SelectControl, {
				label: __( 'Icon font', 'axismundi-dialogs' ),
				value: iconClass,
				options: fontOptions,
				onChange: function ( value ) { set( { iconClass: value } ); },
				help: __( 'Lists active Font Library families. Until WordPress identifies icon fonts, text fonts may appear here but cannot render icon names.', 'axismundi-dialogs' ),
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			} )
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

		var valueControl = isRegistry
			? el( TextControl, {
					label: __( 'Icon', 'axismundi-dialogs' ),
					value: icon,
					onChange: function ( v ) { set( { icon: v } ); },
					help: record.resolved && ! record.content && icon
						? __( 'No registered icon has this name. Nothing will render on the front end.', 'axismundi-dialogs' )
						: __( 'A registered icon name in collection/icon form, e.g. core/info.', 'axismundi-dialogs' ),
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
				} )
				: el( TextControl, {
					label: __( 'Icon', 'axismundi-dialogs' ),
					value: icon,
					onChange: function ( v ) { set( { icon: v } ); },
					help: __( 'Material Symbols name, e.g. info, warning, delete.', 'axismundi-dialogs' ),
					__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			} );

		var labelControl = isRegistry
			? el( TextControl, {
				label: __( 'Label', 'axismundi-dialogs' ),
				help: __( 'Briefly describe the icon for screen reader users. Leave blank for a decorative icon.', 'axismundi-dialogs' ),
				value: a.ariaLabel || '',
				onChange: function ( value ) { set( { ariaLabel: value || undefined } ); },
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			} )
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
			el( InspectorControls, null,
				el( PanelBody, { title: __( 'Dialog icon', 'axismundi-dialogs' ), initialOpen: true },
					el( 'div', { style: { display: 'grid', gap: '16px' } }, sourceControl, fontControl, valueControl, labelControl )
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
