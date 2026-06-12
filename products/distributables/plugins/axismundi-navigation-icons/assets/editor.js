/**
 * Axismundi Navigation Icons — editor registration (no build / vanilla wp.*).
 *
 * Three filters, all on the core navigation blocks:
 *   1. blocks.registerBlockType — declare the icon attribute client-side so the
 *      editor stores/serialises it (the PHP twin makes the server aware).
 *   2. editor.BlockEdit — the InspectorControls "Navigation Icon" panel.
 *   3. editor.BlockListBlock — feed a --ax-nav-icon custom property so editor.css
 *      can render the glyph in the canvas (these blocks render via core JS in the
 *      editor, so the PHP render_block splice only reaches the front end).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	var TEXT_BLOCKS = [ 'core/navigation-link', 'core/navigation-submenu' ];
	var HOME_BLOCK = 'core/home-link';

	// Kept in sync with axismundi_navigation_icons_sanitize() in the PHP plugin:
	// spaces/hyphens -> underscore, strip the rest, collapse and trim underscores.
	function sanitize( value ) {
		return String( value || '' )
			.toLowerCase()
			.trim()
			.replace( /[\s-]+/g, '_' )
			.replace( /[^a-z0-9_]/g, '' )
			.replace( /_+/g, '_' )
			.replace( /^_+|_+$/g, '' );
	}

	/* 1. Attributes ------------------------------------------------------------ */
	addFilter(
		'blocks.registerBlockType',
		'axismundi/navigation-icons/attributes',
		function ( settings, name ) {
			if ( TEXT_BLOCKS.indexOf( name ) !== -1 ) {
				settings.attributes = Object.assign( {}, settings.attributes, {
					axismundiNavIcon: { type: 'string', default: '' },
				} );
			} else if ( name === HOME_BLOCK ) {
				settings.attributes = Object.assign( {}, settings.attributes, {
					axismundiHomeIcon: { type: 'boolean', default: false },
				} );
			}
			return settings;
		}
	);

	/* 2. Inspector control ----------------------------------------------------- */
	var withInspector = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var name = props.name;
			var isText = TEXT_BLOCKS.indexOf( name ) !== -1;
			var isHome = name === HOME_BLOCK;

			if ( ! props.isSelected || ( ! isText && ! isHome ) ) {
				return el( BlockEdit, props );
			}

			var control;
			if ( isText ) {
				control = el( TextControl, {
					label: __( 'Icon name', 'axismundi-navigation-icons' ),
					help: __(
						'Material Symbols name, e.g. home, article, category, sell, tag, folder. See fonts.google.com/icons.',
						'axismundi-navigation-icons'
					),
					value: props.attributes.axismundiNavIcon || '',
					onChange: function ( value ) {
						props.setAttributes( { axismundiNavIcon: value } );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
				} );
			} else {
				control = el( ToggleControl, {
					label: __( 'Show home icon', 'axismundi-navigation-icons' ),
					checked: !! props.attributes.axismundiHomeIcon,
					onChange: function ( value ) {
						props.setAttributes( { axismundiHomeIcon: !! value } );
					},
					__nextHasNoMarginBottom: true,
				} );
			}

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Navigation Icon', 'axismundi-navigation-icons' ),
							initialOpen: true,
						},
						control
					)
				)
			);
		};
	}, 'withAxismundiNavIconInspector' );
	addFilter( 'editor.BlockEdit', 'axismundi/navigation-icons/inspector', withInspector );

	/* 3. Canvas live preview --------------------------------------------------- */
	var withPreview = createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var name = props.name;
			var glyph = '';

			if ( TEXT_BLOCKS.indexOf( name ) !== -1 ) {
				glyph = sanitize( props.attributes.axismundiNavIcon );
			} else if ( name === HOME_BLOCK && props.attributes.axismundiHomeIcon ) {
				glyph = 'home';
			}

			if ( ! glyph ) {
				return el( BlockListBlock, props );
			}

			var wrapperProps = Object.assign( {}, props.wrapperProps );
			wrapperProps.style = Object.assign( {}, wrapperProps.style, {
				'--ax-nav-icon': '"' + glyph + '"',
			} );
			var className = ( props.className ? props.className + ' ' : '' ) + 'has-axismundi-nav-icon';

			return el(
				BlockListBlock,
				Object.assign( {}, props, { className: className, wrapperProps: wrapperProps } )
			);
		};
	}, 'withAxismundiNavIconPreview' );
	addFilter( 'editor.BlockListBlock', 'axismundi/navigation-icons/preview', withPreview );
} )( window.wp );
