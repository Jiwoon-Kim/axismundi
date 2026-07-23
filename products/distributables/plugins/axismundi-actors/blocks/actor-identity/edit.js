/**
 * axismundi/actor-identity editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/actor-identity', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var compact = 'compact' === attributes.variant;
			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Identity', 'axismundi-actors' ) },
						el( components.ToggleControl, {
							label: __( 'Show federated handle', 'axismundi-actors' ),
							checked: attributes.showHandle,
							onChange: function ( value ) { setAttributes( { showHandle: value } ); },
							__nextHasNoMarginBottom: true,
						} ),
						el( components.ToggleControl, {
							label: __( 'Show Actor type badge', 'axismundi-actors' ),
							checked: attributes.showTypeBadge,
							onChange: function ( value ) { setAttributes( { showTypeBadge: value } ); },
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'ax-actor-identity is-editor-preview' + ( compact ? ' is-compact' : '' ) } ),
					compact ? el( 'span', { className: 'ax-actor-identity__name' }, __( 'Actor display name', 'axismundi-actors' ) ) : el( 'h1', { className: 'wp-block-heading ax-actor-identity__name' }, __( 'Actor display name', 'axismundi-actors' ) ),
					compact && el( 'span', { className: 'ax-actor-identity__preferred-username' }, '@actor' ),
					! compact && ( attributes.showHandle || attributes.showTypeBadge ) && el(
						'p',
						{ className: 'ax-actor-identity__meta' },
						attributes.showHandle && el( 'span', { className: 'ax-actor-identity__handle' }, '@actor@example.test' ),
						attributes.showTypeBadge && el( 'span', { className: 'ax-actor-identity__type' }, __( 'Person', 'axismundi-actors' ) )
					)
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
