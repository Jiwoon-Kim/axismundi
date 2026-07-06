/**
 * axismundi/sheet — editor registration (no build / vanilla).
 *
 * The editor never opens a real modal dialog: it shows the trigger preview plus
 * the chosen Sheet template part's name, and exposes the host settings in the
 * inspector. The content itself is edited by opening that Sheet template part in
 * the Site Editor. save() returns null — render.php is authoritative.
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
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/sheet', {
		transforms: {
			to: [ {
				type: 'block',
				blocks: [ 'axismundi/dialog' ],
				transform: function ( a ) {
					// Preserve the open button + naming + block style; reset the
					// sheet-only geometry / part so the Dialog uses its defaults.
					return blocks.createBlock( 'axismundi/dialog', {
						triggerLabel: a.triggerLabel,
						triggerIcon: a.triggerIcon,
						title: a.title,
						label: a.label,
						className: a.className,
					} );
				},
			} ],
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var parts = useSelect( function ( select ) {
				var recs = select( 'core' ).getEntityRecords( 'postType', 'wp_template_part', { per_page: -1 } );
				return ( recs || [] ).filter( function ( r ) { return r.area === 'sheet'; } );
			}, [] );

			var partOptions = [ { label: __( 'Select a Sheet part…', 'axismundi-dialogs' ), value: '' } ].concat(
				parts.map( function ( p ) {
					return {
						label: ( p.title && p.title.rendered ) || p.slug,
						value: p.theme + '//' + p.slug,
					};
				} )
			);

			var controls = [
				el( SelectControl, {
					key: 'part',
					label: __( 'Content (Sheet template part)', 'axismundi-dialogs' ),
					value: a.templatePart,
					options: partOptions,
					onChange: function ( v ) { set( { templatePart: v } ); },
					help: __( 'Edit the sheet content by opening this part in the Site Editor.', 'axismundi-dialogs' ),
				} ),
				el( SelectControl, {
					key: 'variant',
					label: __( 'Variant', 'axismundi-dialogs' ),
					value: a.variant,
					options: [ { label: __( 'Side', 'axismundi-dialogs' ), value: 'side' }, { label: __( 'Bottom', 'axismundi-dialogs' ), value: 'bottom' } ],
					onChange: function ( v ) { set( { variant: v } ); },
				} ),
			];
			if ( a.variant === 'side' ) {
				if ( a.modal !== false ) {
					controls.push( el( SelectControl, {
						key: 'attachment',
						label: __( 'Attachment', 'axismundi-dialogs' ),
						value: a.attachment || 'docked',
						options: [
							{ label: __( 'Docked', 'axismundi-dialogs' ), value: 'docked' },
							{ label: __( 'Detached', 'axismundi-dialogs' ), value: 'detached' },
						],
						onChange: function ( v ) { set( { attachment: v } ); },
						help: __( 'Detached modal sheets float 16px from the viewport edges.', 'axismundi-dialogs' ),
					} ) );
				}
				controls.push( el( SelectControl, {
					key: 'edge',
					label: __( 'Edge', 'axismundi-dialogs' ),
					value: a.edge,
					options: [ { label: __( 'Start (inline-start)', 'axismundi-dialogs' ), value: 'start' }, { label: __( 'End (inline-end)', 'axismundi-dialogs' ), value: 'end' } ],
					onChange: function ( v ) { set( { edge: v } ); },
				} ) );
			}
			if ( a.variant === 'side' ) {
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
				label: __( 'Trigger icon', 'axismundi-dialogs' ),
				value: a.triggerIcon,
				onChange: function ( v ) { set( { triggerIcon: v } ); },
				help: __( 'Material Symbols name, e.g. menu, toc, filter_list, more_vert.', 'axismundi-dialogs' ),
			} ) );
			controls.push( el( TextControl, {
				key: 'label',
				label: __( 'Sheet accessible name', 'axismundi-dialogs' ),
				value: a.label,
				onChange: function ( v ) { set( { label: v } ); },
				help: __( 'Names the dialog for assistive tech. Falls back to the trigger label.', 'axismundi-dialogs' ),
			} ) );
			controls.push( el( ToggleControl, {
				key: 'modal',
				label: __( 'Modal', 'axismundi-dialogs' ),
				checked: a.modal !== false,
				onChange: function ( v ) { set( v ? { modal: true } : { modal: false, attachment: 'docked' } ); },
				help: __( 'Modal overlays the page; standard docks beside and resizes the site on larger screens.', 'axismundi-dialogs' ),
			} ) );
			controls.push( el( SelectControl, {
				key: 'scroll',
				label: __( 'Scroll', 'axismundi-dialogs' ),
				value: a.scrollMode || 'body',
				options: [ { label: __( 'Body only', 'axismundi-dialogs' ), value: 'body' }, { label: __( 'Whole sheet', 'axismundi-dialogs' ), value: 'sheet' } ],
				onChange: function ( v ) { set( { scrollMode: v } ); },
				help: __( 'Body keeps the header and footer fixed; whole sheet scrolls everything together.', 'axismundi-dialogs' ),
			} ) );
			if ( a.modal !== false ) {
				controls.push( el( ToggleControl, {
					key: 'backdrop',
					label: __( 'Close on backdrop click', 'axismundi-dialogs' ),
					checked: a.closeOnBackdrop,
					onChange: function ( v ) { set( { closeOnBackdrop: v } ); },
				} ) );
			}
			if ( a.variant === 'bottom' ) {
				controls.push( el( ToggleControl, {
					key: 'handle',
					label: __( 'Show drag handle', 'axismundi-dialogs' ),
					checked: a.showDragHandle,
					onChange: function ( v ) { set( { showDragHandle: v } ); },
					help: __( 'Decorative grabber; the sheet is not drag-dismissed.', 'axismundi-dialogs' ),
				} ) );
			}

			var blockProps = useBlockProps( { className: 'ax-dialog-host-edit' } );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Sheet', 'axismundi-dialogs' ), initialOpen: true }, controls )
				),
				el( 'div', blockProps,
				el( 'div', { className: 'ax-dialog__open-button ax-icon-button' },
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': true }, a.triggerIcon || 'menu' ),
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
