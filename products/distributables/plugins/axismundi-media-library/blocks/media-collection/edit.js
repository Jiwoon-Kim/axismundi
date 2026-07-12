/**
 * axismundi/media-collection editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'axismundi/media-collection', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var controls = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Collection', 'axismundi-media-library' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Source', 'axismundi-media-library' ),
						value: attrs.source,
						options: [
							{ label: __( 'Current media archive', 'axismundi-media-library' ), value: 'current' },
							{ label: __( 'User media root', 'axismundi-media-library' ), value: 'owner' },
							{ label: __( 'Specific folder', 'axismundi-media-library' ), value: 'folder' }
						],
						onChange: function ( value ) { props.setAttributes( { source: value } ); }
					} ),
					'owner' === attrs.source && el( TextControl, {
						label: __( 'User ID', 'axismundi-media-library' ),
						type: 'number',
						min: 0,
						value: attrs.ownerId || '',
						onChange: function ( value ) { props.setAttributes( { ownerId: parseInt( value, 10 ) || 0 } ); }
					} ),
					'folder' === attrs.source && el( TextControl, {
						label: __( 'Folder term ID', 'axismundi-media-library' ),
						type: 'number',
						min: 0,
						value: attrs.folderId || '',
						onChange: function ( value ) { props.setAttributes( { folderId: parseInt( value, 10 ) || 0 } ); }
					} ),
					el( RangeControl, {
						label: __( 'Columns', 'axismundi-media-library' ), value: attrs.columns, min: 1, max: 6,
						onChange: function ( value ) { props.setAttributes( { columns: value } ); }
					} ),
					el( RangeControl, {
						label: __( 'Items per page', 'axismundi-media-library' ), value: attrs.perPage, min: 1, max: 48,
						onChange: function ( value ) { props.setAttributes( { perPage: value } ); }
					} ),
					el( SelectControl, {
						label: __( 'Image size', 'axismundi-media-library' ), value: attrs.imageSize,
						options: [
							{ label: __( 'Thumbnail', 'axismundi-media-library' ), value: 'thumbnail' },
							{ label: __( 'Medium', 'axismundi-media-library' ), value: 'medium' },
							{ label: __( 'Medium large', 'axismundi-media-library' ), value: 'medium_large' },
							{ label: __( 'Large', 'axismundi-media-library' ), value: 'large' }
						],
						onChange: function ( value ) { props.setAttributes( { imageSize: value } ); }
					} ),
					el( ToggleControl, {
						label: __( 'Show dates', 'axismundi-media-library' ), checked: attrs.showDates,
						onChange: function ( value ) { props.setAttributes( { showDates: value } ); }
					} ),
					el( ToggleControl, {
						label: __( 'Show folder counts', 'axismundi-media-library' ), checked: attrs.showCounts,
						onChange: function ( value ) { props.setAttributes( { showCounts: value } ); }
					} ),
					el( ToggleControl, {
						label: __( 'Show parent item', 'axismundi-media-library' ), checked: attrs.showUp,
						onChange: function ( value ) { props.setAttributes( { showUp: value } ); }
					} )
				)
			);

			return el(
				'div',
				blockEditor.useBlockProps(),
				controls,
				el( ServerSideRender, { block: 'axismundi/media-collection', attributes: attrs } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
