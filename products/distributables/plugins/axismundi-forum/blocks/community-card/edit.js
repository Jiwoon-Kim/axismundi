/** Static editor preview for axismundi/community-card; the Group is resolved on the server. */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/community-card', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Settings', 'axismundi-forum' ) },
						el( components.ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Show member count', 'axismundi-forum' ),
							checked: false !== attributes.showMemberCount,
							onChange: function ( value ) { props.setAttributes( { showMemberCount: value } ); }
						} )
					)
				),
				el(
					'aside',
					blockEditor.useBlockProps( { className: 'axismundi-forum-community-card' } ),
					el( 'p', { className: 'axismundi-forum-community-card__name' }, __( 'Community', 'axismundi-forum' ) ),
					el( 'p', { className: 'axismundi-forum-community-card__handle' }, '@community' ),
					el( 'p', null, __( "The Group this Topic belongs to, with its Follow control, is resolved when the page is viewed.", 'axismundi-forum' ) )
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
