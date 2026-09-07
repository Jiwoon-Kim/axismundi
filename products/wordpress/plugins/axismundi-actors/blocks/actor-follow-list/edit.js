/**
 * axismundi/actor-follow-list editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-follow-list', {
		edit: function ( props ) {
			var collection = 'following' === props.attributes.collection ? 'following' : 'followers';
			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Follow list', 'axismundi-actors' ) },
						el( components.SelectControl, {
							label: __( 'Collection', 'axismundi-actors' ),
							value: collection,
							options: [
								{ label: __( 'Followers', 'axismundi-actors' ), value: 'followers' },
								{ label: __( 'Following', 'axismundi-actors' ), value: 'following' },
							],
							onChange: function ( value ) { props.setAttributes( { collection: value } ); },
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'is-editor-preview' } ),
					el( components.Placeholder, {
						icon: 'groups',
						label: 'following' === collection ? __( 'Following', 'axismundi-actors' ) : __( 'Followers', 'axismundi-actors' ),
						instructions: __( 'Displays the current Actor\'s public follow list.', 'axismundi-actors' ),
					} )
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
