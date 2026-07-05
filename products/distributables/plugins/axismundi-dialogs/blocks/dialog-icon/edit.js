/**
 * axismundi/dialog-icon — editor registration (no build / vanilla).
 */
( function ( blocks, blockEditor, element, components, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/dialog-icon', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'ax-dialog-icon material-symbols-outlined' } );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Dialog icon', 'axismundi-dialogs' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Icon', 'axismundi-dialogs' ),
							value: a.icon,
							onChange: function ( v ) { set( { icon: v } ); },
							help: __( 'Material Symbols name, e.g. info, warning, delete.', 'axismundi-dialogs' ),
						} )
					)
				),
				el( 'span', blockProps, a.icon || 'info' )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n );
