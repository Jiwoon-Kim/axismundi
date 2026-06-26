/**
 * axismundi/map front-end renderer. Reads the per-block config from the canvas
 * data-config, draws the basemap (Leaflet for raster, MapLibre for PMTiles), and
 * overlays the geotags / track GeoJSON from Geo Data's REST endpoints.
 */
( function () {
	function centerZoom( cfg ) {
		var center = ( cfg.center && 2 === cfg.center.length ) ? cfg.center : [ 0, 20 ]; // [lon, lat]
		var zoom   = cfg.zoom > 0 ? cfg.zoom : Math.max( cfg.minZoom || 2, 4 );
		return { center: center, zoom: zoom };
	}

	function geojsonBounds( geojson ) {
		var minX = 180, minY = 90, maxX = -180, maxY = -90, has = false;
		function eat( c ) {
			if ( 'number' === typeof c[ 0 ] ) {
				has = true;
				minX = Math.min( minX, c[ 0 ] );
				maxX = Math.max( maxX, c[ 0 ] );
				minY = Math.min( minY, c[ 1 ] );
				maxY = Math.max( maxY, c[ 1 ] );
			} else {
				c.forEach( eat );
			}
		}
		( geojson.features || [] ).forEach( function ( f ) {
			if ( f.geometry && f.geometry.coordinates ) {
				eat( f.geometry.coordinates );
			}
		} );
		return has ? [ [ minX, minY ], [ maxX, maxY ] ] : null;
	}

	function fetchGeojson( url ) {
		return window.fetch( url, { credentials: 'same-origin' } ).then( function ( r ) {
			return r.json();
		} );
	}

	function popupTitle( props ) {
		props = props || {};
		return props.title || props.name || '';
	}

	/* ---- Leaflet (raster tiles) ---- */
	function initLeaflet( canvas, cfg ) {
		if ( ! window.L ) {
			return;
		}
		var L  = window.L;
		var cz = centerZoom( cfg );
		var map = L.map( canvas ).setView( [ cz.center[ 1 ], cz.center[ 0 ] ], cz.zoom );
		L.tileLayer( cfg.tileUrl, {
			attribution: cfg.attribution || '',
			minZoom: cfg.minZoom || 0,
			maxZoom: cfg.maxZoom || 19
		} ).addTo( map );

		if ( ! cfg.geojson ) {
			return;
		}
		fetchGeojson( cfg.geojson ).then( function ( gj ) {
			var layer = L.geoJSON( gj, {
				pointToLayer: function ( feature, latlng ) {
					return L.circleMarker( latlng, { radius: 6, color: '#d32f2f', weight: 2, fillColor: '#d32f2f', fillOpacity: 0.7 } );
				},
				style: function () {
					return { color: '#d32f2f', weight: 3 };
				},
				onEachFeature: function ( feature, lyr ) {
					var title = popupTitle( feature.properties );
					if ( cfg.showPopups && title ) {
						lyr.bindPopup( title );
					}
				}
			} ).addTo( map );
			try {
				var b = layer.getBounds();
				if ( b.isValid() ) {
					map.fitBounds( b, { padding: [ 24, 24 ], maxZoom: 15 } );
				}
			} catch ( e ) {}
		} ).catch( function () {} );
	}

	/* ---- MapLibre (PMTiles vector) ---- */
	function initMaplibre( canvas, cfg ) {
		if ( ! window.maplibregl || ! window.pmtiles || ! window.basemaps || ! cfg.packUrl ) {
			return;
		}
		var maplibregl = window.maplibregl;
		if ( ! window.axismundiMapPmtilesProtocol ) {
			var protocol = new window.pmtiles.Protocol();
			maplibregl.addProtocol( 'pmtiles', protocol.tile );
			window.axismundiMapPmtilesProtocol = protocol;
		}

		var cz    = centerZoom( cfg );
		var style = {
			version: 8,
			glyphs: cfg.glyphs,
			sprite: cfg.sprite,
			sources: { protomaps: { type: 'vector', url: 'pmtiles://' + cfg.packUrl, attribution: cfg.attribution || '' } },
			layers: window.basemaps.layers( 'protomaps', window.basemaps.namedFlavor( 'light' ), { lang: cfg.lang || 'en' } )
		};
		var map = new maplibregl.Map( {
			container: canvas,
			style: style,
			center: cz.center,
			zoom: cz.zoom,
			minZoom: cfg.minZoom || 0,
			maxZoom: cfg.maxZoom || 18
		} );
		map.addControl( new maplibregl.NavigationControl( { showCompass: false } ), 'top-right' );

		if ( ! cfg.geojson ) {
			return;
		}
		map.on( 'load', function () {
			fetchGeojson( cfg.geojson ).then( function ( gj ) {
				map.addSource( 'axgeo', { type: 'geojson', data: gj } );
				map.addLayer( {
					id: 'axgeo-lines',
					type: 'line',
					source: 'axgeo',
					filter: [ 'in', [ 'geometry-type' ], [ 'literal', [ 'LineString', 'MultiLineString' ] ] ],
					paint: { 'line-color': '#d32f2f', 'line-width': 3 }
				} );
				map.addLayer( {
					id: 'axgeo-points',
					type: 'circle',
					source: 'axgeo',
					filter: [ '==', [ 'geometry-type' ], 'Point' ],
					paint: { 'circle-radius': 6, 'circle-color': '#d32f2f', 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 }
				} );

				var b = geojsonBounds( gj );
				if ( b ) {
					map.fitBounds( b, { padding: 24, maxZoom: 15, duration: 0 } );
				}

				if ( cfg.showPopups ) {
					map.on( 'click', 'axgeo-points', function ( e ) {
						var feature = e.features[ 0 ];
						var title   = popupTitle( feature.properties );
						if ( title ) {
							new maplibregl.Popup().setLngLat( feature.geometry.coordinates ).setText( title ).addTo( map );
						}
					} );
					map.on( 'mouseenter', 'axgeo-points', function () { map.getCanvas().style.cursor = 'pointer'; } );
					map.on( 'mouseleave', 'axgeo-points', function () { map.getCanvas().style.cursor = ''; } );
				}
			} ).catch( function () {} );
		} );
	}

	function initOne( canvas ) {
		var cfg;
		try {
			cfg = JSON.parse( canvas.getAttribute( 'data-config' ) );
		} catch ( e ) {
			return;
		}
		if ( ! cfg ) {
			return;
		}
		if ( 'pmtiles' === cfg.kind ) {
			initMaplibre( canvas, cfg );
		} else {
			initLeaflet( canvas, cfg );
		}
	}

	function ready( fn ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}
	ready( function () {
		document.querySelectorAll( '.axismundi-map__canvas' ).forEach( initOne );
	} );
}() );
