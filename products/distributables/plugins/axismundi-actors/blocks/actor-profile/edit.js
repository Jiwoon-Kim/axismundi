/**
 * axismundi/actor-profile editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-profile', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'ax-actor-profile is-editor-preview' } ),
				el( components.Placeholder, {
					icon: 'admin-users',
					label: __( 'Actor Profile', 'axismundi-actors' ),
					instructions: __( 'Displays the actor resolved from the current profile URL.', 'axismundi-actors' ),
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
