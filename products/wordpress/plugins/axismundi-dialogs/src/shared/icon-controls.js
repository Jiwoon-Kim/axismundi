/**
 * The icon picker, shared by the blocks that take an icon reference
 * (dialog-icon, dialog-icon-button): the source, font and name controls, and
 * the Icon library modal. Moved here unchanged from dialog-icon.
 */
import * as element from '@wordpress/element';
import * as components from '@wordpress/components';
import * as data from '@wordpress/data';
import * as coreData from '@wordpress/core-data';
import * as dom from '@wordpress/dom';
import * as i18n from '@wordpress/i18n';

var el = element.createElement;
var Fragment = element.Fragment;
var Modal = components.Modal;
var SearchControl = components.SearchControl;
var Spinner = components.Spinner;
var Button = components.Button;
var SelectControl = components.SelectControl;
var TextControl = components.TextControl;
var useSelect = data.useSelect;
var coreStore = coreData.store;
var __ = i18n.__;

// Each source's default is the same "info" icon, so switching source keeps
// what the icon is saying and only changes where the drawing comes from.
// Carrying the old value across would always be wrong: the two value spaces
// do not overlap, and "info" is not a registry name any more than
// "core/info" is a glyph.
export var DEFAULT_ICONS = { font: 'info', registry: 'core/info' };

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
export function fontFamilyOptions( setting ) {
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

function normalizeIconSearch( value ) {
	return String( value || '' ).toLowerCase().replace( /[\s_-]+/g, '' );
}

// core/icon's Icon Library modal is internal to block-library, but the two
// entities it reads are public. Keep this implementation on those entities so
// dialog-icon can browse the same registry without importing a private module.
export function IconLibraryModal( props ) {
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


/**
 * Source, font and name - the three fields an icon reference is made of.
 *
 * @param {Object}   props
 * @param {string}   props.source         'font' or 'registry'.
 * @param {string}   props.icon           Stored name.
 * @param {string}   props.iconClass      Font provider class.
 * @param {Array}    props.fontOptions    fontFamilyOptions() result.
 * @param {Object}   props.record         useIconRecord() result.
 * @param {Function} props.onChange       Receives the attribute changes.
 * @param {Function} props.onSourceChange Optional, called before a source change.
 */
export function IconReferenceControls( props ) {
	var isRegistry = props.source === 'registry';
	var fontOptions = props.fontOptions.slice();
	if ( ! fontOptions.some( function ( option ) { return option.value === props.iconClass; } ) ) {
		fontOptions.unshift( { label: props.iconClass, value: props.iconClass } );
	}
	return el( Fragment, null,
		el( SelectControl, {
			label: __( 'Icon source', 'axismundi-dialogs' ),
			value: props.source,
			options: [
				{ label: __( 'Icon font', 'axismundi-dialogs' ), value: 'font' },
				{ label: __( 'Icon library', 'axismundi-dialogs' ), value: 'registry' },
			],
			onChange: function ( next ) {
				if ( props.onSourceChange ) {
					props.onSourceChange( next );
				}
				props.onChange( { iconSource: next, icon: DEFAULT_ICONS[ next ] } );
			},
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		} ),
		! isRegistry
			? el( SelectControl, {
				label: __( 'Icon font', 'axismundi-dialogs' ),
				value: props.iconClass,
				options: fontOptions,
				onChange: function ( value ) { props.onChange( { iconClass: value } ); },
				help: __( 'Lists active Font Library families. Until WordPress identifies icon fonts, text fonts may appear here but cannot render icon names.', 'axismundi-dialogs' ),
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			} )
			: null,
		el( TextControl, {
			label: __( 'Icon', 'axismundi-dialogs' ),
			value: props.icon,
			onChange: function ( v ) { props.onChange( { icon: v } ); },
			help: isRegistry
				? ( props.record.resolved && ! props.record.content && props.icon
					? __( 'No registered icon has this name. Nothing will render on the front end.', 'axismundi-dialogs' )
					: __( 'A registered icon name in collection/icon form, e.g. core/info.', 'axismundi-dialogs' ) )
				: __( 'Material Symbols name, e.g. info, warning, delete.', 'axismundi-dialogs' ),
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		} )
	);
}
