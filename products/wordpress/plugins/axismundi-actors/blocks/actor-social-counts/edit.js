/**
 * axismundi/actor-social-counts editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	blocks.registerBlockType( 'axismundi/actor-social-counts', {
		edit: function () {
			return el(
				'ul',
				blockEditor.useBlockProps( { className: 'is-editor-preview' } ),
				el(
					'li',
					{ className: 'ax-actor-social-counts__item' },
					el( 'a', { href: '#', onClick: function ( event ) { event.preventDefault(); } },
						el( 'span', {}, i18n.__( 'Followers', 'axismundi-actors' ) ),
						el( 'strong', {}, '0' )
					)
				),
				el(
					'li',
					{ className: 'ax-actor-social-counts__item' },
					el( 'a', { href: '#', onClick: function ( event ) { event.preventDefault(); } },
						el( 'span', {}, i18n.__( 'Following', 'axismundi-actors' ) ),
						el( 'strong', {}, '0' )
					)
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
