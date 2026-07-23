/**
 * axismundi/account-header editor registration (no build step).
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;

	var TEMPLATE = [
		[ 'core/group', { layout: { type: 'constrained' } }, [
			[ 'axismundi/object-featured-image', { showPlaceholder: true, style: { dimensions: { height: '200px' } } } ],
			[ 'core/group', {
				className: 'ax-account-header__head',
				style: { spacing: { margin: { top: '-36px' }, padding: { right: 'var:preset|spacing|100', left: 'var:preset|spacing|100' }, blockGap: 'var:preset|spacing|100' } },
				layout: { type: 'flex', flexWrap: 'nowrap', justifyContent: 'space-between', orientation: 'horizontal', verticalAlignment: 'bottom' },
			}, [
				[ 'axismundi/actor-avatar', { size: 128, style: { shadow: 'var:preset|shadow|elevation-2' } } ],
				[ 'core/group', {
					style: { layout: { selfStretch: 'fill', flexSize: null } },
					layout: { type: 'flex', flexWrap: 'nowrap', justifyContent: 'center', verticalAlignment: 'center' },
				}, [
					[ 'core/group', {
						style: { layout: { selfStretch: 'fill', flexSize: null }, spacing: { blockGap: 'var:preset|spacing|0' } },
						layout: { type: 'constrained', justifyContent: 'left' },
					}, [
						[ 'axismundi/actor-identity', {} ],
					] ],
					[ 'axismundi/follow-button', {} ],
				] ],
			] ],
		] ],
		[ 'axismundi/actor-biography', {} ],
		[ 'axismundi/actor-profile-fields', {} ],
	];

	var ALLOWED_BLOCKS = [
		'axismundi/object-featured-image',
		'axismundi/actor-avatar',
		'axismundi/actor-identity',
		'axismundi/actor-biography',
		'axismundi/actor-profile-fields',
		'axismundi/follow-button',
		'core/group',
		'core/columns',
		'core/buttons',
		'core/social-links',
		'core/separator',
		'core/spacer',
	];

	blocks.registerBlockType( 'axismundi/account-header', {
		edit: function () {
			var blockProps = useBlockProps( { className: 'ax-account-header' } );
			var innerBlocksProps = useInnerBlocksProps( blockProps, {
				template: TEMPLATE,
				allowedBlocks: ALLOWED_BLOCKS,
			} );
			return el( 'div', innerBlocksProps );
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
