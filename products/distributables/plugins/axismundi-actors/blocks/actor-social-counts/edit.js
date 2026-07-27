/**
 * axismundi/actor-social-counts editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-social-counts', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'ax-actor-social-counts is-editor-preview' } ),
				el( components.Placeholder, {
					icon: 'groups',
					label: __( 'Actor Social Counts', 'axismundi-actors' ),
					instructions: __( 'Displays the current Actor\'s public follower and following counts.', 'axismundi-actors' ),
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
