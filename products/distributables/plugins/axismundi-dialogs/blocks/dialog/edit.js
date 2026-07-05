/**
 * axismundi/dialog — editor registration (no build / vanilla).
 *
 * Basic / full-screen modal dialog. Shares the open button, surface, and title /
 * close blocks with the Sheet; transforms to a modal Side Sheet. save() returns
 * null — render.php is authoritative.
 */
( function ( blocks, blockEditor, element, components, data, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var RichText = blockEditor.RichText;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var useSelect = data.useSelect;
	var createBlock = blocks.createBlock;
	var __ = i18n.__;

	function preserved( a ) {
		return {
			triggerLabel: a.triggerLabel,
			triggerIcon: a.triggerIcon,
			title: a.title,
			label: a.label,
			className: a.className,
		};
	}

	blocks.registerBlockType( 'axismundi/dialog', {
		transforms: {
			to: [ {
				type: 'block',
				blocks: [ 'axismundi/sheet' ],
				transform: function ( a ) {
					return createBlock( 'axismundi/sheet', preserved( a ) );
				},
			} ],
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var parts = useSelect( function ( select ) {
				var recs = select( 'core' ).getEntityRecords( 'postType', 'wp_template_part', { per_page: -1 } );
				return ( recs || [] ).filter( function ( r ) { return r.area === 'dialog'; } );
			}, [] );

			var partOptions = [ { label: __( 'Select a Dialog part…', 'axismundi-dialogs' ), value: '' } ].concat(
				parts.map( function ( p ) {
					return { label: ( p.title && p.title.rendered ) || p.slug, value: p.theme + '//' + p.slug };
				} )
			);

			var controls = [
				el( SelectControl, {
					key: 'part',
					label: __( 'Content (Dialog template part)', 'axismundi-dialogs' ),
					value: a.templatePart,
					options: partOptions,
					onChange: function ( v ) { set( { templatePart: v } ); },
					help: __( 'Edit the dialog content by opening this part in the Site Editor.', 'axismundi-dialogs' ),
				} ),
				el( SelectControl, {
					key: 'variant',
					label: __( 'Variant', 'axismundi-dialogs' ),
					value: a.variant,
					options: [ { label: __( 'Basic', 'axismundi-dialogs' ), value: 'basic' }, { label: __( 'Full-screen', 'axismundi-dialogs' ), value: 'fullscreen' } ],
					onChange: function ( v ) { set( { variant: v } ); },
				} ),
			];
			if ( a.variant !== 'fullscreen' ) {
				controls.push( el( SelectControl, {
					key: 'width',
					label: __( 'Width', 'axismundi-dialogs' ),
					value: a.width,
					options: [ { label: __( 'Narrow', 'axismundi-dialogs' ), value: 'narrow' }, { label: __( 'Medium', 'axismundi-dialogs' ), value: 'medium' }, { label: __( 'Wide', 'axismundi-dialogs' ), value: 'wide' } ],
					onChange: function ( v ) { set( { width: v } ); },
				} ) );
			}
			controls.push( el( TextControl, {
				key: 'icon',
				label: __( 'Open button icon', 'axismundi-dialogs' ),
				value: a.triggerIcon,
				onChange: function ( v ) { set( { triggerIcon: v } ); },
				help: __( 'Material Symbols name, e.g. open_in_new, info, edit.', 'axismundi-dialogs' ),
			} ) );
			controls.push( el( TextControl, {
				key: 'label',
				label: __( 'Dialog accessible name', 'axismundi-dialogs' ),
				value: a.label,
				onChange: function ( v ) { set( { label: v } ); },
				help: __( 'Names the dialog for assistive tech. Falls back to the open button label.', 'axismundi-dialogs' ),
			} ) );
			if ( a.variant !== 'fullscreen' ) {
				controls.push( el( ToggleControl, {
					key: 'backdrop',
					label: __( 'Close on backdrop click', 'axismundi-dialogs' ),
					checked: a.closeOnBackdrop,
					onChange: function ( v ) { set( { closeOnBackdrop: v } ); },
				} ) );
			}

			var blockProps = useBlockProps( { className: 'ax-dialog-host-edit' } );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Dialog', 'axismundi-dialogs' ), initialOpen: true }, controls )
				),
				el( 'div', blockProps,
					el( 'div', { className: 'ax-dialog__open-button ax-icon-button' },
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': true }, a.triggerIcon || 'open_in_new' ),
						el( RichText, {
							tagName: 'span',
							className: 'ax-dialog__open-button-label',
							value: a.triggerLabel,
							placeholder: __( 'Add text…', 'axismundi-dialogs' ),
							allowedFormats: [],
							onChange: function ( v ) { set( { triggerLabel: v } ); },
						} )
					)
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.data, window.wp.i18n );
