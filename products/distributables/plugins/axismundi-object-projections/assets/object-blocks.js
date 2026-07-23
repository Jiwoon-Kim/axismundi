/**
 * Editor registration for the server-rendered Axismundi Object blocks.
 *
 * The block list comes from the server (`window.axismundiOpObjectBlocks`) rather
 * than a copy kept here, so registering a block in PHP is enough for the Site
 * Editor to accept it. A hardcoded list silently desynchronized and surfaced as
 * "your site doesn't include support for this block".
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InnerBlocks = blockEditor.InnerBlocks;
	var registry = window.axismundiOpObjectBlocks || {};

	Object.keys( registry ).forEach( function ( name ) {
		var definition = registry[ name ] || {};
		var label = definition.label || name;
		var settings = {
			apiVersion: definition.apiVersion || 3,
			title: label,
			category: definition.category || 'theme',
			attributes: definition.attributes || {},
			supports: definition.supports || {},
		};

		if ( 'axismundi/object-interactions' === name ) {
			settings.edit = function () {
				return el(
					'div',
					blockEditor.useBlockProps( { className: 'axismundi-object__interactions' } ),
					el( InnerBlocks, {
						template: [ [ 'axismundi/reply-button' ], [ 'axismundi/like-button' ], [ 'axismundi/announce-button' ] ],
						templateLock: false,
						orientation: 'horizontal'
					} )
				);
			};
			settings.save = function () { return null; };
			blocks.registerBlockType( name, settings );
			return;
		}

		settings.edit = function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-object-block-placeholder' } ),
				label
			);
		};
		settings.save = function () { return null; };
		blocks.registerBlockType( name, settings );
	} );

	if ( ! Object.keys( registry ).length && window.console ) {
		// Registration data missing means the editor script loaded without its
		// server-provided list; every Object block would report as unsupported.
		window.console.warn( __( 'Axismundi Object block registration data was not provided by the server.', 'axismundi-object-projections' ) );
	}
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
