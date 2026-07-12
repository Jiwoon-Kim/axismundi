( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'axismundi/media-pagination', {
		edit: function ( props ) {
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Pagination', 'axismundi-media-library' ), initialOpen: true },
						[ 'showPrevious', 'showNumbers', 'showNext' ].map( function ( key ) {
							var labels = {
								showPrevious: __( 'Show previous', 'axismundi-media-library' ),
								showNumbers: __( 'Show page numbers', 'axismundi-media-library' ),
								showNext: __( 'Show next', 'axismundi-media-library' )
							};
							return el( components.ToggleControl, {
								key: key,
								label: labels[ key ],
								checked: props.attributes[ key ],
								onChange: function ( value ) {
									var update = {};
									update[ key ] = value;
									props.setAttributes( update );
								}
							} );
						} )
					)
				),
				el( 'nav', blockEditor.useBlockProps(), __( 'Previous  1  2  3  Next', 'axismundi-media-library' ) )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
