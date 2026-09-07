/**
 * axismundi/object-hashtags editor registration (no build step).
 *
 * The preview mirrors what the front end renders — real term links carrying the
 * ActivityStreams "#" marker — using Core's own `wp-block-post-terms` classes so
 * the theme's Tags style variation applies to the preview exactly as it does to
 * `core/post-terms`. Metadata stays in block.json.
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var sample = [
		__( 'busan', 'axismundi-object-projections' ),
		__( 'gwangalli', 'axismundi-object-projections' ),
		__( 'nightview', 'axismundi-object-projections' )
	];

	blocks.registerBlockType( 'axismundi/object-hashtags', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var children = [];

			if ( attributes.prefix ) {
				children.push( el( 'span', { className: 'wp-block-post-terms__prefix', key: 'prefix' }, attributes.prefix ) );
			}
			sample.forEach( function ( name, index ) {
				children.push( el( 'a', { href: '#', onClick: function ( e ) { e.preventDefault(); }, key: 'term-' + index }, '#' + name ) );
			} );
			if ( attributes.suffix ) {
				children.push( el( 'span', { className: 'wp-block-post-terms__suffix', key: 'suffix' }, attributes.suffix ) );
			}

			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						wp.components.PanelBody,
						{ title: __( 'Hashtags', 'axismundi-object-projections' ) },
						el( wp.components.TextControl, {
							label: __( 'Prefix', 'axismundi-object-projections' ),
							value: attributes.prefix || '',
							onChange: function ( value ) { setAttributes( { prefix: value } ); },
							__nextHasNoMarginBottom: true,
						} ),
						el( wp.components.TextControl, {
							label: __( 'Suffix', 'axismundi-object-projections' ),
							value: attributes.suffix || '',
							onChange: function ( value ) { setAttributes( { suffix: value } ); },
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'wp-block-post-terms axismundi-object__hashtags' } ),
					children
				)
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
