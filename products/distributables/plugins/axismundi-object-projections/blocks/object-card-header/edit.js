/**
 * axismundi/object-card-header editor registration (no build step).
 *
 * A shell with a default arrangement rather than fixed markup: who posted this, what it is, and
 * when. The children stay editable because those three are not equally interesting on every
 * surface — an archive of one Actor's own work has little use for their name on every row.
 */
( function ( blocks, blockEditor, element ) {
	'use strict';
	var el = element.createElement;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps || blockEditor.__experimentalUseInnerBlocksProps;

	/*
	 * `published`, not `updated`: most federated Objects carry no `updated` time at all, so a
	 * header asking for it renders an empty string and the card silently loses its timestamp.
	 */
	var TEMPLATE = [
		[ 'axismundi/actor-avatar', { size: 48 } ],
		[ 'core/group', {
			layout: { type: 'flex', orientation: 'vertical', flexWrap: 'nowrap' },
			style: { spacing: { blockGap: '0' }, layout: { selfStretch: 'fill', flexSize: null } }
		}, [
			[ 'axismundi/actor-name' ],
			[ 'axismundi/actor-handle' ]
		] ],
		[ 'core/group', {
			layout: { type: 'flex', orientation: 'vertical', justifyContent: 'right' },
			style: { spacing: { blockGap: '0' } }
		}, [
			[ 'axismundi/object-type' ],
			[ 'axismundi/object-date' ]
		] ]
	];

	blocks.registerBlockType( 'axismundi/object-card-header', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'axismundi-object-card__header' } );
			var settings = { template: TEMPLATE };
			var innerProps = useInnerBlocksProps ? useInnerBlocksProps( blockProps, settings ) : null;
			return innerProps
				? el( 'div', innerProps )
				: el( 'div', blockProps, el( blockEditor.InnerBlocks, settings ) );
		},
		/*
		 * Required: the serializer writes only what `save` returns, and an empty one serializes
		 * self-closing — which would drop every child the first time a template was saved.
		 */
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element ) );
