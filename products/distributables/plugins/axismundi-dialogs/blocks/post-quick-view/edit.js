/**
 * axismundi/post-quick-view — editor registration (no build / vanilla).
 *
 * The hub renders an empty, hidden dialog on the front end; in the editor it
 * shows a static placeholder so authors can see the one-per-page host without a
 * live dialog. No settings in v0.2.
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/post-quick-view', {
		edit: function () {
			var blockProps = useBlockProps( { className: 'ax-post-quick-view-placeholder' } );
			return el( 'div', blockProps,
				el( 'span', { className: 'ax-post-quick-view-placeholder__icon material-symbols-outlined', 'aria-hidden': true }, 'preview' ),
				el( 'span', null,
					el( 'strong', null, __( 'Post Quick View', 'axismundi-dialogs' ) ),
					' — ',
					__( 'one hidden dialog per page; feed triggers open it.', 'axismundi-dialogs' )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
