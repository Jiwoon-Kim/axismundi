/**
 * axismundi/feed-tab editor registration (no build step).
 *
 * One surface's layout. The surface is an attribute rather than the block's identity because
 * which surfaces exist is decided by the products that register them — a forum, an archive — and
 * a block type per surface would mean a plugin could not contribute one without shipping a block.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	/*
	 * The arrangement a surface is born with, which is the one it had before surfaces could be
	 * arranged separately. A new tab that started empty would render a feed with no cards in it
	 * and read as a bug rather than as an invitation.
	 */
	var TEMPLATE = [
		[ 'axismundi/feed-filters' ],
		[ 'axismundi/feed-density-switch' ],
		[ 'axismundi/feed-loop' ],
		[ 'axismundi/feed-pagination' ]
	];

	blocks.registerBlockType( 'axismundi/feed-tab', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps( {
				className: 'axismundi-feed-tab is-surface-' + ( props.attributes.surface || 'activity' )
			} );
			var settings = { template: TEMPLATE };
			var innerProps = useInnerBlocksProps ? useInnerBlocksProps( blockProps, settings ) : null;
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Surface', 'axismundi-activities' ) },
						el( components.TextControl, {
							label: __( 'Surface key', 'axismundi-activities' ),
							help: __( 'The profile surface this layout is for, such as activity or community.', 'axismundi-activities' ),
							value: props.attributes.surface || '',
							onChange: function ( value ) {
								props.setAttributes( { surface: value } );
							}
						} ),
						/*
						 * How this surface is walked, and what its filter control looks like — both here
						 * rather than on the feed, because they differ between surfaces of the same
						 * profile: a timeline is continued by cursor, a community archive is browsed by
						 * number. One value on the feed could only be right for one of them.
						 */
						el( components.SelectControl, {
							label: __( 'Walked by', 'axismundi-activities' ),
							value: props.attributes.navigation || '',
							options: [
								{ label: __( 'Automatic (what the surface offers)', 'axismundi-activities' ), value: '' },
								{ label: __( 'Load more (cursor)', 'axismundi-activities' ), value: 'infinite' },
								{ label: __( 'Numbered pages', 'axismundi-activities' ), value: 'pagination' }
							],
							onChange: function ( value ) {
								props.setAttributes( { navigation: value } );
							},
							__nextHasNoMarginBottom: true
						} ),
						el( components.SelectControl, {
							label: __( 'Filter control', 'axismundi-activities' ),
							help: __( 'Switches are a reading preference kept out of the URL; tabs are addressable collections.', 'axismundi-activities' ),
							value: props.attributes.filterStyle || '',
							options: [
								{ label: __( 'Automatic (what the surface offers)', 'axismundi-activities' ), value: '' },
								{ label: __( 'Switches', 'axismundi-activities' ), value: 'switches' },
								{ label: __( 'Tabs', 'axismundi-activities' ), value: 'tabs' }
							],
							onChange: function ( value ) {
								props.setAttributes( { filterStyle: value } );
							},
							__nextHasNoMarginBottom: true
						} )
					)
				),
				innerProps
					? el( 'div', innerProps )
					: el( 'div', blockProps, el( blockEditor.InnerBlocks, settings ) )
			);
		},
		/*
		 * Required, and not a formality: the serializer writes only what `save` returns, and an
		 * empty one serializes self-closing — which would drop this tab's entire arrangement the
		 * first time an author saved the template.
		 */
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
