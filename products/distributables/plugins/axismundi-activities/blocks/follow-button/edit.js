( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	blocks.registerBlockType( 'axismundi/follow-button', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-follow-button is-editor-preview' } ),
				el( components.Button, { variant: 'primary', disabled: true }, i18n.__( 'Follow', 'axismundi-activities' ) )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
