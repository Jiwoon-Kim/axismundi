( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'axismundi/actor-profile-fields', {
		edit: function () {
			return el(
				'div',
				useBlockProps( { className: 'ax-actor-profile-fields-block' } ),
				__( 'Actor profile links appear here.', 'axismundi-actors' )
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
