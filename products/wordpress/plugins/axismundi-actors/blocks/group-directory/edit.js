( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/group-directory', {
		edit: function () {
			return el( 'div', blockEditor.useBlockProps( { className: 'is-editor-preview' } ), el( components.Placeholder, {
				icon: 'groups',
				label: __( 'Group Directory', 'axismundi-actors' ),
				instructions: __( 'Displays public Group Actors known to this server.', 'axismundi-actors' ),
			} ) );
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
