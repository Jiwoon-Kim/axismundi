/**
 * axismundi/feed-item-templates editor registration (no build step).
 *
 * A wrapper so the variants read as one thing. Left as siblings of the filters and the pager they
 * were just a run of blocks that happened to be next to each other; grouped, it is visible that
 * they are alternatives, that their order decides the default, and where a new one would go.
 */
( function ( blocks, blockEditor, element, i18n, data ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	var DENSITIES = [ 'card', 'compact' ];

	// Card first, so a fresh loop defaults to the fuller reading. Order is the contract: the first
	// child is the default density, and moving them changes it.
	var TEMPLATE = [
		[ 'axismundi/feed-item-template', { density: 'card' } ],
		[ 'axismundi/feed-item-template', { density: 'compact' } ]
	];

	/*
	 * Named insertions, so adding a card means choosing which one — rather than adding a second of
	 * whatever the attribute happens to default to, which is how a duplicate gets made by accident.
	 */
	blocks.registerBlockVariation( 'axismundi/feed-item-template', {
		name: 'card',
		title: __( 'Card items', 'axismundi-activities' ),
		description: __( 'The full entry: author, body, media, tags and every control.', 'axismundi-activities' ),
		attributes: { density: 'card' },
		scope: [ 'inserter' ],
		isActive: function ( attributes ) {
			return 'compact' !== attributes.density;
		}
	} );
	blocks.registerBlockVariation( 'axismundi/feed-item-template', {
		name: 'compact',
		title: __( 'Compact items', 'axismundi-activities' ),
		description: __( 'One line per entry, for readers who want more of them at once.', 'axismundi-activities' ),
		attributes: { density: 'compact' },
		scope: [ 'inserter' ],
		isActive: function ( attributes ) {
			return 'compact' === attributes.density;
		}
	} );

	blocks.registerBlockType( 'axismundi/feed-item-templates', {
		edit: function ( props ) {
			/*
			 * Insertion closes once every density is spoken for.
			 *
			 * The audit refuses a duplicate, but that is the defence for a hand-edited template or
			 * one that arrived from elsewhere. It should not be how an author finds out: there is
			 * one card per density, so once both exist there is nothing left to add, and the
			 * editor says so by not offering it.
			 */
			var taken = data.useSelect(
				function ( select ) {
					var children = select( 'core/block-editor' ).getBlocks( props.clientId ) || [];
					return children
						.filter( function ( child ) {
							return 'axismundi/feed-item-template' === child.name;
						} )
						.map( function ( child ) {
							return 'compact' === child.attributes.density ? 'compact' : 'card';
						} );
				},
				[ props.clientId ]
			);
			var remaining = DENSITIES.filter( function ( density ) {
				return -1 === taken.indexOf( density );
			} );

			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-feed-item-templates' } );
			var settings = {
				template: TEMPLATE,
				// `insert` rather than `all`: the cards stay editable and re-orderable, because
				// re-ordering is how the default density gets chosen.
				templateLock: 0 === remaining.length ? 'insert' : false,
				allowedBlocks: [ 'axismundi/feed-item-template' ]
			};
			var innerProps = useInnerBlocksProps ? useInnerBlocksProps( blockProps, settings ) : null;
			return innerProps
				? el( 'div', innerProps )
				: el( 'div', blockProps, el( blockEditor.InnerBlocks, settings ) );
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n, window.wp.data ) );
