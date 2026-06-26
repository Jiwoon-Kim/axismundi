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
	var PanelBody         = components.PanelBody;
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
			if ( 'geotags' === a.source && a.areaId ) {
				summary += ' (area ' + a.areaId + ')';
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
								{ label: __( 'Geotags', 'axismundi-map' ), value: 'geotags' },
								{ label: __( 'Track', 'axismundi-map' ), value: 'track' }
							],
							onChange: function ( v ) { set( { source: v } ); }
						} ),
						'geotags' === a.source && el( TextControl, {
							label: __( 'Geo area ID (optional)', 'axismundi-map' ),
							type: 'number',
							value: a.areaId || 0,
							onChange: function ( v ) { set( { areaId: parseInt( v, 10 ) || 0 } ); }
						} ),
						'geotags' === a.source && el( TextControl, {
							label: __( 'BBox — w,s,e,n (optional)', 'axismundi-map' ),
							value: a.bbox,
							onChange: function ( v ) { set( { bbox: v } ); }
						} ),
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
