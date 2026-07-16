( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	const el = element.createElement;
	blocks.registerBlockType( 'axismundi/like-button', {
		edit: function ( props ) {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( components.Button, { icon: 'heart', variant: 'secondary', disabled: true }, i18n.__( 'Like', 'axismundi-activities' ) ),
				props.context && props.context.postId ? null : el( components.Placeholder, { instructions: i18n.__( 'The front end resolves the current projected object.', 'axismundi-activities' ) } )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
