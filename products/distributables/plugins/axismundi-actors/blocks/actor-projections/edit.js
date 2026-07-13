/**
 * axismundi/actor-projections editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-projections', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( components.Placeholder, {
					icon: 'networking',
					label: __( 'Actor Projections', 'axismundi-actors' ),
					instructions: __( 'Displays links supplied by readable actor projections.', 'axismundi-actors' ),
				} )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
