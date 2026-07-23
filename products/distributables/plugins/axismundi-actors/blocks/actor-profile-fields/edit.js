/**
 * axismundi/actor-profile-fields editor registration (no build step).
 *
 * The layout toggle group control is only exported from `wp.components` under
 * its `__experimental` name in this WordPress runtime (`ToggleGroupControl`
 * itself is undefined) -- calling `createElement` with an undefined component
 * throws during render and Gutenberg reports the block as unable to preview, so
 * the experimental names are used directly rather than the stable-looking ones.
 *
 * Card styling and the editor UI are a style/interaction sibling of Core's Latest
 * Posts: the list/grid switch lives only in the toolbar as icon buttons (Latest
 * Posts does not duplicate it in the sidebar either), and "Number of items" +
 * "Columns" (grid-only) live together in a "Sorting and filtering" panel, matching
 * Latest Posts' own panel name and control placement even though this block has
 * nothing to sort or filter -- the field order and set are the actor's own, from
 * their profile editor. The list/grid icon paths are Core's own `list` / `grid`
 * icons from `@wordpress/icons`, inlined so this file has no script dependency on
 * that package.
 */
( function ( blocks, components, element, blockEditor, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var BlockControls = blockEditor.BlockControls;
	var ToolbarGroup = components.ToolbarGroup;
	var ToolbarButton = components.ToolbarButton;
	var RangeControl = components.RangeControl;

	var ICON_PROPS = { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true, focusable: false };
	var LIST_ICON = el( 'svg', ICON_PROPS, el( 'path', { d: 'M4 4v1.5h16V4H4zm8 8.5h8V11h-8v1.5zM4 20h16v-1.5H4V20zm4-8c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2z' } ) );
	var GRID_ICON = el( 'svg', ICON_PROPS, el( 'path', {
		fillRule: 'evenodd',
		clipRule: 'evenodd',
		d: 'm3 5c0-1.10457.89543-2 2-2h13.5c1.1046 0 2 .89543 2 2v13.5c0 1.1046-.8954 2-2 2h-13.5c-1.10457 0-2-.8954-2-2zm2-.5h6v6.5h-6.5v-6c0-.27614.22386-.5.5-.5zm-.5 8v6c0 .2761.22386.5.5.5h6v-6.5zm8 0v6.5h6c.2761 0 .5-.2239.5-.5v-6zm0-8v6.5h6.5v-6c0-.27614-.2239-.5-.5-.5z',
	} ) );

	var SAMPLE_FIELDS = [
		{ name: __( 'Website', 'axismundi-actors' ), url: 'designbusan.ai.kr', verified: true },
		{ name: __( 'Fediverse', 'axismundi-actors' ), url: '@axismundi@example.social', verified: false },
	];

	blocks.registerBlockType( 'axismundi/actor-profile-fields', {
		edit: function ( props ) {
			var display = 'grid' === props.attributes.display ? 'grid' : 'list';
			var columns = props.attributes.columns || 2;
			var itemsToShow = props.attributes.itemsToShow || 8;
			var setDisplay = function ( value ) { props.setAttributes( { display: value } ); };

			var toolbar = el(
				BlockControls,
				null,
				el(
					ToolbarGroup,
					null,
					el( ToolbarButton, {
						icon: LIST_ICON,
						label: __( 'List view', 'axismundi-actors' ),
						isPressed: 'list' === display,
						onClick: function () { setDisplay( 'list' ); },
					} ),
					el( ToolbarButton, {
						icon: GRID_ICON,
						label: __( 'Grid view', 'axismundi-actors' ),
						isPressed: 'grid' === display,
						onClick: function () { setDisplay( 'grid' ); },
					} )
				)
			);

			var inspector = el(
				InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: __( 'Sorting and filtering', 'axismundi-actors' ), initialOpen: true },
					el( RangeControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Number of items', 'axismundi-actors' ),
						help: __( 'Profile links beyond this count are hidden here, not removed from the profile.', 'axismundi-actors' ),
						value: itemsToShow,
						min: 1,
						max: 8,
						onChange: function ( value ) { props.setAttributes( { itemsToShow: value || 8 } ); },
					} ),
					'grid' === display
						? el( RangeControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Columns', 'axismundi-actors' ),
							value: columns,
							min: 2,
							max: 4,
							onChange: function ( value ) { props.setAttributes( { columns: value || 2 } ); },
						} )
						: null
				)
			);

			var previewFields = SAMPLE_FIELDS.slice( 0, itemsToShow );
			var previewClass = 'ax-actor-profile-fields-block is-display-' + display + ' is-editor-preview'
				+ ( 'grid' === display ? ' columns-' + columns : '' );

			return el(
				element.Fragment,
				null,
				toolbar,
				inspector,
				el(
					'ul',
					useBlockProps( { className: previewClass } ),
					previewFields.map( function ( field ) {
						return el(
							'li',
							{ className: 'ax-actor-profile-fields-block__item', key: field.name },
							el( 'span', { className: 'ax-actor-profile-fields-block__name' }, field.name ),
							el( 'span', { className: 'ax-actor-profile-fields-block__url' }, field.url ),
							field.verified
								? el( 'span', { className: 'ax-actor-profile-fields-block__verified material-symbols-outlined', title: __( 'Verified reciprocal link', 'axismundi-actors' ), 'aria-label': __( 'Verified reciprocal link', 'axismundi-actors' ) }, 'verified' )
								: null
						);
					} )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.components, window.wp.element, window.wp.blockEditor, window.wp.i18n );
