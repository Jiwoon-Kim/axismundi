/**
 * axismundi/media-collection editor registration (no build step).
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var TextControl = components.TextControl;
	var collectionTemplate = [
		[ 'axismundi/media-folders', { showCounts: false, showUp: false } ],
		[ 'axismundi/media-post-template', {}, [
			[ 'axismundi/media-preview', {} ],
			[ 'core/post-title', { level: 3, isLink: true } ],
			[ 'core/post-date', {} ]
		] ],
		[ 'axismundi/media-no-results', {}, [
			[ 'core/paragraph', { content: __( 'No media is available in this collection.', 'axismundi-media-library' ) } ]
		] ],
		[ 'axismundi/media-pagination', {} ]
	];
	var allowedBlocks = [
		'axismundi/media-folders',
		'axismundi/media-post-template',
		'axismundi/media-no-results',
		'axismundi/media-pagination'
	];

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
					el( 'p', null, __( 'Edit folder, item template, empty state, and pagination blocks directly in the canvas.', 'axismundi-media-library' ) )
				)
			);

			return el(
				'div',
				blockEditor.useBlockProps(),
				controls,
				el( blockEditor.InnerBlocks, {
					allowedBlocks: allowedBlocks,
					template: collectionTemplate,
					templateLock: false
				} )
			);
		},
		save: function () { return el( blockEditor.InnerBlocks.Content ); }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
