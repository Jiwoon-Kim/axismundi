/**
 * axismundi/actor-biography editor registration (no build step).
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-biography', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'ax-actor-biography is-editor-preview' } ),
				el( 'div', { className: 'ax-actor-biography__summary' }, __( 'A short profile summary appears here, carrying the Actor\'s voice across the network.', 'axismundi-actors' ) )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
