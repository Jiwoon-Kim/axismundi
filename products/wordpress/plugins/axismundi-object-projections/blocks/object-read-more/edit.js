/**
 * axismundi/object-read-more editor registration (no build step).
 *
 * The initial words follow the FEP example: "Read more". An editor can replace them with the
 * publication's own idiom. Empty content falls back to that visible route at render time.
 *
 * The destination is not editable and is not shown here. It belongs to whichever Object the card
 * is rendering, which is known at render time and not while editing a template.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;

	blocks.registerBlockType( 'axismundi/object-read-more', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var align = attributes.textAlign || '';
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.BlockControls,
					{ group: 'block' },
					el( blockEditor.AlignmentToolbar, {
						value: align,
						onChange: function ( value ) {
							props.setAttributes( { textAlign: value } );
						}
					} )
				),
				el(
					'p',
					blockEditor.useBlockProps( {
						className: 'axismundi-object__read-more' + ( align ? ' has-text-align-' + align : '' )
					} ),
					el( RichText, {
						identifier: 'text',
						tagName: 'a',
						className: 'axismundi-object__read-more-link',
						'aria-label': __( '“Read more” link text', 'axismundi-object-projections' ),
						placeholder: __( 'Add "read more" link text', 'axismundi-object-projections' ),
						value: attributes.text || '',
						onChange: function ( value ) {
							props.setAttributes( { text: value } );
						},
						withoutInteractiveFormatting: true
					} )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
