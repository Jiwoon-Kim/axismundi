/**
 * axismundi/object-media-dialog editor registration (no build step).
 *
 * The hub renders nothing until a reader opens it, so the editor shows a placeholder
 * describing its role rather than a fake dialog. Metadata stays in block.json.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;

	blocks.registerBlockType( 'axismundi/object-media-dialog', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var variant = 'fullscreen' === attributes.variant ? 'fullscreen' : 'basic';

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Settings', 'axismundi-dialogs' ) },
					el( SelectControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Variant', 'axismundi-dialogs' ),
						value: variant,
						options: [
							{ label: __( 'Contained', 'axismundi-dialogs' ), value: 'basic' },
							{ label: __( 'Full screen', 'axismundi-dialogs' ), value: 'fullscreen' }
						],
						help: 'fullscreen' === variant
							? __( 'Fills the viewport, giving the media the most room.', 'axismundi-dialogs' )
							: __( 'A centred dialog wide enough to show the media beside the post it came from.', 'axismundi-dialogs' ),
						onChange: function ( value ) { props.setAttributes( { variant: value } ); }
					} )
				)
			);

			return el(
				element.Fragment,
				null,
				inspector,
				el(
					'div',
					blockEditor.useBlockProps( { className: 'ax-object-media-dialog-placeholder' } ),
					el( 'strong', null, __( 'Object Media Dialog', 'axismundi-dialogs' ) ),
					el(
						'p',
						null,
						__(
							'One per page. Attached media opens here full size, with the post and the post it replies to beside it.',
							'axismundi-dialogs'
						)
					),
					el(
						'p',
						null,
						'fullscreen' === variant
							? __( 'Opens full screen.', 'axismundi-dialogs' )
							: __( 'Opens as a contained dialog.', 'axismundi-dialogs' )
					)
				)
			);
		},
		save: function () { return null; }
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
