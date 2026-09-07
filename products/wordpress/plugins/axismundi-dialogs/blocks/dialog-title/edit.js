/**
 * axismundi/dialog-title — editor registration (no build / vanilla).
 *
 * Heading-like editing: authored content is a rich-text field; heading level and
 * text alignment live in the block toolbar. Leaving content empty falls back at
 * render time to the sheet's dynamic title. save() returns null — render.php is
 * authoritative.
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;
	var BlockControls = blockEditor.BlockControls;
	var HeadingLevelDropdown = blockEditor.HeadingLevelDropdown;
	var AlignmentControl = blockEditor.AlignmentControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/dialog-title', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var level = a.level || 2;
			var tag = 'h' + level;
			var className = 'ax-dialog-title' + ( a.textAlign ? ' has-text-align-' + a.textAlign : '' );
			var blockProps = useBlockProps( { className: className } );

			return el( Fragment, null,
				el( BlockControls, { group: 'block' },
					el( HeadingLevelDropdown, {
						value: level,
						onChange: function ( v ) { set( { level: v } ); },
					} ),
					el( AlignmentControl, {
						value: a.textAlign,
						onChange: function ( v ) { set( { textAlign: v } ); },
					} )
				),
				el( RichText, Object.assign( {}, blockProps, {
					tagName: tag,
					value: a.content,
					allowedFormats: [ 'core/bold', 'core/italic' ],
					onChange: function ( v ) { set( { content: v } ); },
					'aria-label': __( 'Sheet title', 'axismundi-dialogs' ),
					placeholder: __( 'Sheet title', 'axismundi-dialogs' ),
				} ) )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
