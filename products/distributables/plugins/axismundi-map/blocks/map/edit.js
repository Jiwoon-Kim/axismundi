/**
 * axismundi/map editor. v0.1 is inspector-driven: a placeholder in the canvas and
 * the source / display controls in the sidebar; the real map renders on the front
 * end (render.php + view.js). No build step — plain wp.element.createElement.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	var el                = element.createElement;
	var __                = i18n.__;
	var useBlockProps     = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var MediaUpload       = blockEditor.MediaUpload;
	var MediaUploadCheck  = blockEditor.MediaUploadCheck;
	var PanelBody         = components.PanelBody;
	var BaseControl       = components.BaseControl;
	var Button            = components.Button;
	var SelectControl     = components.SelectControl;
	var RangeControl      = components.RangeControl;
	var ToggleControl     = components.ToggleControl;
	var TextControl       = components.TextControl;
	var Placeholder       = components.Placeholder;

	blocks.registerBlockType( 'axismundi/map', {
		edit: function ( props ) {
			var a   = props.attributes;
			var set = props.setAttributes;

			var summary = __( 'Source: ', 'axismundi-map' ) + a.source;
			if ( 'track' === a.source && a.trackId ) {
				summary += ' #' + a.trackId;
			}
			if ( 'media' === a.source && a.mediaIds && a.mediaIds.length ) {
				summary += ' (' + a.mediaIds.length + ' media)';
			}
			function setMedia( media ) {
				var items = Array.isArray( media ) ? media : [ media ];
				set( { mediaIds: items.map( function ( item ) { return item && item.id ? parseInt( item.id, 10 ) : 0; } ).filter( Boolean ) } );
			}

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Map source', 'axismundi-map' ) },
						el( SelectControl, {
							label: __( 'Source', 'axismundi-map' ),
							value: a.source,
							options: [
								{ label: __( 'None (basemap only)', 'axismundi-map' ), value: 'none' },
								{ label: __( 'Current archive (geo term)', 'axismundi-map' ), value: 'current' },
								{ label: __( 'Geotags', 'axismundi-map' ), value: 'geotags' },
								{ label: __( 'Selected media', 'axismundi-map' ), value: 'media' },
								{ label: __( 'Track', 'axismundi-map' ), value: 'track' }
							],
							onChange: function ( v ) { set( { source: v } ); }
						} ),
						'geotags' === a.source && el( TextControl, {
							label: __( 'BBox — w,s,e,n (optional)', 'axismundi-map' ),
							value: a.bbox,
							onChange: function ( v ) { set( { bbox: v } ); }
						} ),
						'media' === a.source && el(
							BaseControl,
							{
								label: __( 'Media attachments', 'axismundi-map' ),
								help: __( 'Choose public GPS photos and GPX/KML tracks from the Media Library.', 'axismundi-map' )
							},
							el(
								'div',
								{},
								el( 'p', {}, a.mediaIds && a.mediaIds.length ? a.mediaIds.join( ', ' ) : __( 'No media selected.', 'axismundi-map' ) ),
								el(
									MediaUploadCheck,
									{},
									el( MediaUpload, {
										multiple: true,
										gallery: false,
										value: a.mediaIds || [],
										onSelect: setMedia,
										render: function ( obj ) {
											return el(
												Button,
												{ variant: 'secondary', onClick: obj.open },
												a.mediaIds && a.mediaIds.length ? __( 'Edit selected media', 'axismundi-map' ) : __( 'Select media', 'axismundi-map' )
											);
										}
									} )
								),
								a.mediaIds && a.mediaIds.length ? el(
									Button,
									{ variant: 'link', isDestructive: true, onClick: function () { set( { mediaIds: [] } ); } },
									__( 'Clear selection', 'axismundi-map' )
								) : null
							)
						),
						'track' === a.source && el( TextControl, {
							label: __( 'Track attachment ID', 'axismundi-map' ),
							type: 'number',
							value: a.trackId || 0,
							onChange: function ( v ) { set( { trackId: parseInt( v, 10 ) || 0 } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Display', 'axismundi-map' ) },
						el( RangeControl, {
							label: __( 'Height (px)', 'axismundi-map' ),
							value: a.height,
							min: 160,
							max: 800,
							onChange: function ( v ) { set( { height: v } ); }
						} ),
						el( RangeControl, {
							label: __( 'Zoom (0 = auto-fit)', 'axismundi-map' ),
							value: a.zoom,
							min: 0,
							max: 18,
							onChange: function ( v ) { set( { zoom: v } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Show popups', 'axismundi-map' ),
							checked: a.showPopups,
							onChange: function ( v ) { set( { showPopups: v } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Show visitor location control', 'axismundi-map' ),
							help: __( 'Visitors can opt in from the map control; location is not requested on page load.', 'axismundi-map' ),
							checked: a.showVisitorLocation,
							onChange: function ( v ) { set( { showVisitorLocation: v } ); }
						} )
					)
				),
				el( Placeholder, {
					icon: 'location-alt',
					label: __( 'Axismundi Map', 'axismundi-map' ),
					instructions: summary
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
