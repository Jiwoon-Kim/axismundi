/**
 * axismundi/dialog-close — editor registration (no build / vanilla).
 *
 * Preview-only close button; render.php emits the real button with the
 * Interactivity close directive. save() returns null.
 */
( function ( blocks, blockEditor, element, components, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/dialog-close', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'ax-dialog-close ax-icon-button is-standard' } );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Close button', 'axismundi-dialogs' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Icon', 'axismundi-dialogs' ),
							value: a.icon,
							onChange: function ( v ) { set( { icon: v } ); },
							help: __( 'Material Symbols name, e.g. close, arrow_back.', 'axismundi-dialogs' ),
						} ),
						el( TextControl, {
							label: __( 'Accessible label', 'axismundi-dialogs' ),
							value: a.label,
							onChange: function ( v ) { set( { label: v } ); },
						} )
					)
				),
				el( 'span', blockProps,
					el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': true }, a.icon || 'close' )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n );
