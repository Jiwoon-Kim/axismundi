/**
 * Table of Contents — editor registration (no build / vanilla).
 *
 * save() returns null: this is a dynamic block rendered by render.php from the
 * post's live headings. The editor canvas can't cheaply read the sibling
 * post-content render, so the MVP shows a placeholder plus the inspector
 * controls; a real heading preview is a later pass.
 */
( function ( blocks, blockEditor, element, components, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/toc', {
		// Brand-tinted inserter icon so this theme-owned block reads distinctly
		// from core. A literal hex is required: editor chrome has no theme tokens.
		icon: { src: 'list-view', foreground: '#6750A4' },
		edit: function ( props ) {
			var a = props.attributes;
			var setA = props.setAttributes;
			var isDisclosure = a.variant === 'disclosure';
			var blockProps = useBlockProps( {
				className: 'ax-toc ax-toc--' + ( a.variant || 'rail' ),
			} );
			var title = a.title || __( 'On this page', 'axismundi-table-of-contents' );
			var placeholder =
				__( 'Headings', 'axismundi-table-of-contents' ) +
				' H' + a.minLevel + '–H' + a.maxLevel + ' ' +
				__( 'from this post appear here on the published page.', 'axismundi-table-of-contents' );

			var preview;
			if ( isDisclosure ) {
				preview = el(
					'details',
					Object.assign( {}, blockProps, { open: !! a.openByDefault } ),
					el(
						'summary',
						{ className: 'ax-toc__summary' },
						el( 'span', { className: 'ax-toc__summary-label' }, title )
					),
					el( 'div', { className: 'ax-toc__panel' },
						el( 'p', { className: 'ax-toc__placeholder' }, placeholder )
					)
				);
			} else {
				preview = el(
					'nav',
					blockProps,
					a.showTitle ? el( 'div', { className: 'ax-toc__title' }, title ) : null,
					el( 'p', { className: 'ax-toc__placeholder' }, placeholder )
				);
			}

			var panelChildren = [
				el( SelectControl, {
					key: 'variant',
					label: __( 'Placement', 'axismundi-table-of-contents' ),
					value: a.variant || 'rail',
					options: [
						{ label: __( 'Sidebar rail', 'axismundi-table-of-contents' ), value: 'rail' },
						{ label: __( 'Above content (collapsible)', 'axismundi-table-of-contents' ), value: 'disclosure' },
					],
					onChange: function ( v ) {
						setA( { variant: v } );
					},
				} ),
				isDisclosure
					? el( ToggleControl, {
							key: 'open',
							label: __( 'Open by default', 'axismundi-table-of-contents' ),
							checked: !! a.openByDefault,
							onChange: function ( v ) {
								setA( { openByDefault: v } );
							},
					  } )
					: null,
				isDisclosure
					? el( SelectControl, {
							key: 'summary',
							label: __( 'Summary', 'axismundi-table-of-contents' ),
							value: a.summaryMode || 'current',
							options: [
								{ label: __( 'Title only', 'axismundi-table-of-contents' ), value: 'title' },
								{ label: __( 'Current section', 'axismundi-table-of-contents' ), value: 'current' },
								{ label: __( 'Title + current section', 'axismundi-table-of-contents' ), value: 'title-current' },
							],
							onChange: function ( v ) {
								setA( { summaryMode: v } );
							},
					  } )
					: null,
				! isDisclosure
					? el( ToggleControl, {
							key: 'showTitle',
							label: __( 'Show title', 'axismundi-table-of-contents' ),
							checked: !! a.showTitle,
							onChange: function ( v ) {
								setA( { showTitle: v } );
							},
					  } )
					: null,
				el( TextControl, {
					key: 'title',
					label: __( 'Title', 'axismundi-table-of-contents' ),
					value: a.title,
					onChange: function ( v ) {
						setA( { title: v } );
					},
				} ),
				el( ToggleControl, {
					key: 'ordered',
					label: __( 'Numbered (ordered) list', 'axismundi-table-of-contents' ),
					checked: !! a.ordered,
					onChange: function ( v ) {
						setA( { ordered: v } );
					},
				} ),
				el( RangeControl, {
					key: 'min',
					label: __( 'Minimum heading level', 'axismundi-table-of-contents' ),
					min: 2,
					max: 4,
					value: a.minLevel,
					onChange: function ( v ) {
						setA( { minLevel: v } );
					},
				} ),
				el( RangeControl, {
					key: 'max',
					label: __( 'Maximum heading level', 'axismundi-table-of-contents' ),
					min: 2,
					max: 6,
					value: a.maxLevel,
					onChange: function ( v ) {
						setA( { maxLevel: v } );
					},
				} ),
			];

			var controls = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Table of contents', 'axismundi-table-of-contents' ), initialOpen: true },
					panelChildren
				)
			);

			return el( Fragment, null, controls, preview );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n );
