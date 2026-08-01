/**
 * axismundi/group-moderators editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'axismundi/group-moderators', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( components.Placeholder, {
					icon: 'shield',
					label: i18n.__( 'Community Moderators', 'axismundi-forum' ),
					instructions: i18n.__( 'Displays the moderators for the current Group profile.', 'axismundi-forum' ),
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
