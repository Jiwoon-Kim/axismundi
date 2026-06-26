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
		if ( 'track' === props.type && props.name ) {
			return props.name;
		}
		return props.title || props.name || '';
	}

	function escapeHtml( value ) {
		return String( value || '' ).replace( /[&<>"']/g, function ( ch ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ ch ];
		} );
	}

	function popupHtml( props ) {
		props = props || {};
		var title = popupTitle( props );
		var html  = '';
		if ( props.thumbnail ) {
			html += '<img class="axismundi-map__popup-thumb" src="' + escapeHtml( props.thumbnail ) + '" alt="" loading="lazy" />';
		}
		if ( title ) {
			html += '<strong class="axismundi-map__popup-title">' + escapeHtml( title ) + '</strong>';
		}
		return html;
	}

	function trackEndpointFeatures( geojson ) {
		var features = [];
		( geojson.features || [] ).forEach( function ( feature ) {
			if ( ! feature.geometry || ! feature.geometry.coordinates ) {
				return;
			}
			var lines = [];
			if ( 'LineString' === feature.geometry.type ) {
				lines = [ feature.geometry.coordinates ];
			} else if ( 'MultiLineString' === feature.geometry.type ) {
				lines = feature.geometry.coordinates;
			}
			if ( ! lines.length ) {
				return;
			}
			var firstLine = lines.find( function ( line ) { return line && line.length; } );
			var lastLine  = lines.slice().reverse().find( function ( line ) { return line && line.length; } );
			if ( ! firstLine || ! lastLine ) {
				return;
			}
			var base = feature.properties || {};
			features.push( {
				type: 'Feature',
				geometry: { type: 'Point', coordinates: firstLine[ 0 ] },
				properties: Object.assign( {}, base, { endpoint: 'start', title: base.name || base.title || '' } )
			} );
			features.push( {
				type: 'Feature',
				geometry: { type: 'Point', coordinates: lastLine[ lastLine.length - 1 ] },
				properties: Object.assign( {}, base, { endpoint: 'end', title: base.name || base.title || '' } )
			} );
		} );
		return { type: 'FeatureCollection', features: features };
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
					var html = popupHtml( feature.properties );
					if ( cfg.showPopups && html ) {
						lyr.bindPopup( html );
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

	/* ---- Theme light / dark ---- */
	function isDark() {
		var dt = document.documentElement.getAttribute( 'data-theme' );
		if ( 'dark' === dt ) {
			return true;
		}
		if ( 'light' === dt ) {
			return false;
		}
		return !! ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches );
	}

	function buildStyle( cfg, dark ) {
		return {
			version: 8,
			glyphs: cfg.glyphs,
			sprite: dark && cfg.sprite ? cfg.sprite.replace( /\/light$/, '/dark' ) : cfg.sprite,
			sources: { protomaps: { type: 'vector', url: 'pmtiles://' + cfg.packUrl, attribution: cfg.attribution || '' } },
			layers: window.basemaps.layers( 'protomaps', window.basemaps.namedFlavor( dark ? 'dark' : 'light' ), { lang: cfg.lang || 'en' } )
		};
	}

	// Call apply(dark) when the theme's light/dark mode changes (data-theme toggle
	// or, when no explicit mode is set, the OS preference).
	function watchTheme( apply ) {
		var current = isDark();
		function update() {
			var dark = isDark();
			if ( dark !== current ) {
				current = dark;
				apply( dark );
			}
		}
		if ( window.MutationObserver ) {
			new window.MutationObserver( update ).observe( document.documentElement, { attributes: true, attributeFilter: [ 'data-theme' ] } );
		}
		if ( window.matchMedia ) {
			var mq = window.matchMedia( '(prefers-color-scheme: dark)' );
			if ( mq.addEventListener ) {
				mq.addEventListener( 'change', update );
			} else if ( mq.addListener ) {
				mq.addListener( update );
			}
		}
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

		var cz  = centerZoom( cfg );
		var map = new maplibregl.Map( {
			container: canvas,
			style: buildStyle( cfg, isDark() ),
			center: cz.center,
			zoom: cz.zoom,
			minZoom: cfg.minZoom || 0,
			maxZoom: cfg.maxZoom || 18
		} );
		map.addControl( new maplibregl.NavigationControl( { showCompass: false } ), 'top-right' );

		var cachedGj = null;
		var bound    = false;

		function addLayers() {
			if ( ! cachedGj || ! map.isStyleLoaded() || map.getSource( 'axgeo' ) ) {
				return;
			}
			map.addSource( 'axgeo', { type: 'geojson', data: cachedGj } );
			map.addLayer( { id: 'axgeo-lines', type: 'line', source: 'axgeo', filter: [ 'in', [ 'geometry-type' ], [ 'literal', [ 'LineString', 'MultiLineString' ] ] ], paint: { 'line-color': '#d32f2f', 'line-width': 3 } } );
			map.addLayer( { id: 'axgeo-lines-hit', type: 'line', source: 'axgeo', filter: [ 'in', [ 'geometry-type' ], [ 'literal', [ 'LineString', 'MultiLineString' ] ] ], paint: { 'line-color': '#d32f2f', 'line-width': 16, 'line-opacity': 0 } } );
			map.addLayer( { id: 'axgeo-points', type: 'circle', source: 'axgeo', filter: [ '==', [ 'geometry-type' ], 'Point' ], paint: { 'circle-radius': 6, 'circle-color': '#d32f2f', 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 } } );

			var endpoints = trackEndpointFeatures( cachedGj );
			if ( endpoints.features.length ) {
				map.addSource( 'axgeo-track-endpoints', { type: 'geojson', data: endpoints } );
				map.addLayer( {
					id: 'axgeo-track-endpoints',
					type: 'symbol',
					source: 'axgeo-track-endpoints',
					layout: {
						'text-field': [ 'case', [ '==', [ 'get', 'endpoint' ], 'start' ], '▶', '■' ],
						'text-size': 18,
						'text-anchor': 'center',
						'text-allow-overlap': true,
						'text-ignore-placement': true
					},
					paint: {
						'text-color': [ 'case', [ '==', [ 'get', 'endpoint' ], 'start' ], '#2e7d32', '#d32f2f' ],
						'text-halo-color': '#ffffff',
						'text-halo-width': 2
					}
				} );
			}
		}

		function popupAt( lngLat, props ) {
			var html = popupHtml( props );
			if ( html ) {
				new maplibregl.Popup().setLngLat( lngLat ).setHTML( html ).addTo( map );
			}
		}

		function bindOnce() {
			if ( bound ) {
				return;
			}
			bound = true;
			if ( ! cfg.showPopups ) {
				return;
			}
			map.on( 'click', 'axgeo-points', function ( e ) { popupAt( e.features[ 0 ].geometry.coordinates, e.features[ 0 ].properties ); } );
			map.on( 'click', 'axgeo-track-endpoints', function ( e ) { popupAt( e.features[ 0 ].geometry.coordinates, e.features[ 0 ].properties ); } );
			map.on( 'click', 'axgeo-lines-hit', function ( e ) { popupAt( e.lngLat, e.features[ 0 ].properties ); } );
			[ 'axgeo-points', 'axgeo-track-endpoints', 'axgeo-lines-hit' ].forEach( function ( id ) {
				map.on( 'mouseenter', id, function () { map.getCanvas().style.cursor = 'pointer'; } );
				map.on( 'mouseleave', id, function () { map.getCanvas().style.cursor = ''; } );
			} );
		}

		// Re-tint the basemap when the theme mode flips; the overlay layers are
		// re-added once the new style finishes loading.
		watchTheme( function ( dark ) {
			map.setStyle( buildStyle( cfg, dark ) );
			map.once( 'style.load', addLayers );
		} );

		if ( ! cfg.geojson ) {
			return;
		}
		map.on( 'load', function () {
			fetchGeojson( cfg.geojson ).then( function ( gj ) {
				cachedGj = gj;
				bindOnce();
				addLayers();
				var b = geojsonBounds( gj );
				if ( b ) {
					map.fitBounds( b, { padding: 24, maxZoom: 15, duration: 0 } );
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
