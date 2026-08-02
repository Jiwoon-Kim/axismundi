/**
 * axismundi/feed-tabs editor registration (no build step).
 *
 * The editing shell for per-surface layouts. Every tab's blocks stay mounted and saved; this only
 * decides which one is visible while editing. Unmounting the others would be the same mistake as
 * an empty `save` — the blocks an author cannot see are still the blocks the template is made of.
 */
( function ( blocks, blockEditor, components, element, i18n, data ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	// Activity first: it is the surface every profile has, and the one a reader lands on.
	var TEMPLATE = [
		[ 'axismundi/feed-tab', { surface: 'activity' } ],
		[ 'axismundi/feed-tab', { surface: 'community' } ]
	];

	blocks.registerBlockType( 'axismundi/feed-tabs', {
		edit: function ( props ) {
			var editing = element.useState( 0 );
			var active = editing[ 0 ];
			var setActive = editing[ 1 ];

			var surfaces = data.useSelect(
				function ( select ) {
					return select( 'core/block-editor' )
						.getBlocks( props.clientId )
						.map( function ( child ) {
							return child.attributes.surface || 'activity';
						} );
				},
				[ props.clientId ]
			);

			var blockProps = blockEditor.useBlockProps( {
				className: 'axismundi-feed-tabs is-editing-' + ( surfaces[ active ] || 'activity' )
			} );
			var settings = { template: TEMPLATE, allowedBlocks: [ 'axismundi/feed-tab' ] };
			var innerProps = useInnerBlocksProps ? useInnerBlocksProps( blockProps, settings ) : null;

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.BlockControls,
					null,
					el(
						components.ToolbarGroup,
						null,
						surfaces.map( function ( surface, index ) {
							return el(
								components.ToolbarButton,
								{
									key: surface + index,
									isPressed: index === active,
									onClick: function () {
										setActive( index );
									}
								},
								surface
							);
						} )
					)
				),
				innerProps
					? el( 'div', innerProps )
					: el( 'div', blockProps, el( blockEditor.InnerBlocks, settings ) )
			);
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.data ) );
