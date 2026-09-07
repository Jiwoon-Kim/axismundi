/**
 * axismundi/post-quick-view-trigger — editor registration (no build / vanilla).
 *
 * The live count and open/closed icon are resolved server-side (render.php); the
 * editor shows a representative preview (a sample count with the open-comments
 * icon) plus the display and closed-state controls.
 */
( function ( blocks, blockEditor, element, components, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/post-quick-view-trigger', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'ax-comment-action' } );
			var sample = '3';
			var label = a.displayMode === 'count-and-label'
				? sample + ' ' + __( 'comments', 'axismundi-dialogs' )
				: sample;

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Comments action', 'axismundi-dialogs' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Display', 'axismundi-dialogs' ),
							value: a.displayMode,
							options: [
								{ label: __( 'Count only', 'axismundi-dialogs' ), value: 'count' },
								{ label: __( 'Count and label', 'axismundi-dialogs' ), value: 'count-and-label' }
							],
							onChange: function ( v ) { set( { displayMode: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'When comments are closed', 'axismundi-dialogs' ),
							value: a.closedBehavior,
							options: [
								{ label: __( 'Show disabled icon', 'axismundi-dialogs' ), value: 'disabled' },
								{ label: __( 'Still link to comments', 'axismundi-dialogs' ), value: 'link' },
								{ label: __( 'Hide', 'axismundi-dialogs' ), value: 'hidden' }
							],
							onChange: function ( v ) { set( { closedBehavior: v } ); }
						} )
					)
				),
				el( 'span', blockProps,
					el( 'span', { className: 'ax-comment-action__icon material-symbols-outlined', 'aria-hidden': true }, 'comment' ),
					el( 'span', { className: 'ax-comment-action__count' }, label )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.i18n );
