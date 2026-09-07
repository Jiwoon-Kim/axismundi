( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/media-folders', {
		edit: function ( props ) {
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Folder region', 'axismundi-media-library' ), initialOpen: true },
						el( components.TextControl, {
							label: __( 'Heading', 'axismundi-media-library' ),
							value: props.attributes.heading,
							onChange: function ( value ) { props.setAttributes( { heading: value } ); }
						} ),
						el( components.ToggleControl, {
							label: __( 'Show folder counts', 'axismundi-media-library' ),
							checked: props.attributes.showCounts,
							onChange: function ( value ) { props.setAttributes( { showCounts: value } ); }
						} ),
						el( components.ToggleControl, {
							label: __( 'Show parent action', 'axismundi-media-library' ),
							checked: props.attributes.showUp,
							onChange: function ( value ) { props.setAttributes( { showUp: value } ); }
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'ax-media-folders-placeholder' } ),
					el( 'strong', null, props.attributes.heading || __( 'Folders', 'axismundi-media-library' ) ),
					el( 'p', null, __( 'Child folders are rendered from the current media collection.', 'axismundi-media-library' ) )
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
