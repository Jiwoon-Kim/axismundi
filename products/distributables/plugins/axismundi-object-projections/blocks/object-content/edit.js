/**
 * axismundi/object-content editor registration (no build step).
 *
 * Only `edit`/`save` are declared here. Attributes, supports, and the rest of the
 * metadata come from block.json, which WordPress bootstraps into the editor for
 * us; re-declaring them in JavaScript would override that server definition.
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/object-content', {
	edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'wp-block-post-content axismundi-object__content' } ),
				el( 'p', null, __( 'This is the Object Content block. It displays the body of the ActivityStreams Object currently being rendered.', 'axismundi-object-projections' ) ),
				el( 'p', null, __( 'That object may be a short Note, a long-form Article, a Question, or another federated Object with paragraphs, media, quotations, polls, and other structured content.', 'axismundi-object-projections' ) ),
				el( 'p', null, __( 'The authored sensitive-content gate is preserved when this block renders an Object on the front end.', 'axismundi-object-projections' ) )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
