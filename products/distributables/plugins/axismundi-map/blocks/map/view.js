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
	function enableLeafletVisitorLocation( map, L ) {
		var marker = null;
		var circle = null;
		var LocateControl = L.Control.extend( {
			options: { position: 'topright' },
			onAdd: function () {
				var wrap = L.DomUtil.create( 'div', 'leaflet-bar leaflet-control axismundi-map__locate-control' );
				var button = L.DomUtil.create( 'button', 'axismundi-map__locate', wrap );
				button.type = 'button';
				button.title = 'Show your location';
				button.setAttribute( 'aria-label', 'Show your location' );
				button.textContent = '◎';
				L.DomEvent.disableClickPropagation( wrap );
				L.DomEvent.on( button, 'click', function ( event ) {
					L.DomEvent.preventDefault( event );
					map.locate( { setView: true, maxZoom: 15, enableHighAccuracy: true } );
				} );
				return wrap;
			}
		} );
		map.addControl( new LocateControl() );
		map.on( 'locationfound', function ( e ) {
			if ( marker ) {
				marker.setLatLng( e.latlng );
			} else {
				marker = L.circleMarker( e.latlng, {
					radius: 7,
					color: '#1565c0',
					weight: 2,
					fillColor: '#42a5f5',
					fillOpacity: 0.9
				} ).addTo( map );
			}
			if ( circle ) {
				circle.setLatLng( e.latlng ).setRadius( e.accuracy || 0 );
			} else {
				circle = L.circle( e.latlng, {
					radius: e.accuracy || 0,
					color: '#1565c0',
					weight: 1,
					fillColor: '#42a5f5',
					fillOpacity: 0.12
				} ).addTo( map );
			}
		} );
		map.on( 'locationerror', function ( e ) {
			if ( window.console && window.console.warn ) {
				window.console.warn( e.message );
			}
		} );
	}

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
		if ( cfg.showVisitorLocation ) {
			enableLeafletVisitorLocation( map, L );
		}

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
					if ( b.getNorthEast().equals( b.getSouthWest() ) ) {
						map.setView( b.getCenter(), cfg.zoom > 0 ? cfg.zoom : 14 );
					} else {
						map.fitBounds( b, { padding: [ 24, 24 ], maxZoom: 15 } );
					}
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
		var sources = {
			protomaps: { type: 'vector', url: 'pmtiles://' + cfg.packUrl, attribution: cfg.attribution || '' }
		};
		var layers = window.basemaps.layers( 'protomaps', window.basemaps.namedFlavor( dark ? 'dark' : 'light' ), { lang: cfg.lang || 'en' } );
		return {
			version: 8,
			glyphs: cfg.glyphs,
			sprite: dark && cfg.sprite ? cfg.sprite.replace( /\/light$/, '/dark' ) : cfg.sprite,
			sources: sources,
			layers: layers
		};
	}

	function transparentPng() {
		if ( window.axismundiMapTransparentPng ) {
			return window.axismundiMapTransparentPng;
		}
		var binary = window.atob( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=' );
		var bytes = new Uint8Array( binary.length );
		for ( var i = 0; i < binary.length; i++ ) {
			bytes[ i ] = binary.charCodeAt( i );
		}
		window.axismundiMapTransparentPng = bytes.buffer;
		return window.axismundiMapTransparentPng;
	}

	function tileBounds( z, x, y ) {
		var n = Math.pow( 2, z );
		function lon( tileX ) {
			return tileX / n * 360 - 180;
		}
		function lat( tileY ) {
			var rad = Math.atan( Math.sinh( Math.PI * ( 1 - 2 * tileY / n ) ) );
			return rad * 180 / Math.PI;
		}
		return [ lon( x ), lat( y + 1 ), lon( x + 1 ), lat( y ) ]; // W, S, E, N.
	}

	function containsBounds( outer, inner ) {
		return outer && 4 === outer.length &&
			inner[ 0 ] >= outer[ 0 ] && inner[ 2 ] <= outer[ 2 ] &&
			inner[ 1 ] >= outer[ 1 ] && inner[ 3 ] <= outer[ 3 ];
	}

	function boundsArray( bounds ) {
		return bounds ? [ bounds[ 0 ][ 0 ], bounds[ 0 ][ 1 ], bounds[ 1 ][ 0 ], bounds[ 1 ][ 1 ] ] : null;
	}

	function mapBoundsArray( map ) {
		var b = map.getBounds();
		return [ b.getWest(), b.getSouth(), b.getEast(), b.getNorth() ];
	}

	function tileUrl( template, z, x, y ) {
		var subs = [ 'a', 'b', 'c', 'd' ];
		return template
			.replace( /\{s\}/g, subs[ Math.abs( x + y ) % subs.length ] )
			.replace( /\{z\}/g, z )
			.replace( /\{x\}/g, x )
			.replace( /\{y\}/g, y );
	}

	function registerRasterFallbackProtocol( maplibregl ) {
		if ( window.axismundiMapRasterFallbackProtocol ) {
			return;
		}
		window.axismundiMapRasterFallbacks = window.axismundiMapRasterFallbacks || {};
		maplibregl.addProtocol( 'axrasterfallback', function ( params ) {
			var match = String( params.url || '' ).match( /^axrasterfallback:\/\/([^/]+)\/(\d+)\/(\d+)\/(\d+)$/ );
			if ( ! match ) {
				return Promise.resolve( { data: transparentPng() } );
			}
			var cfg = window.axismundiMapRasterFallbacks[ match[ 1 ] ];
			var z = parseInt( match[ 2 ], 10 );
			var x = parseInt( match[ 3 ], 10 );
			var y = parseInt( match[ 4 ], 10 );
			if ( ! cfg || ! cfg.tileUrl ) {
				return Promise.resolve( { data: transparentPng() } );
			}
			if ( containsBounds( cfg.bounds, tileBounds( z, x, y ) ) ) {
				return Promise.resolve( { data: transparentPng() } );
			}
			return window.fetch( tileUrl( cfg.tileUrl, z, x, y ) )
				.then( function ( response ) {
					if ( ! response.ok ) {
						return transparentPng();
					}
					return response.arrayBuffer();
				} )
				.then( function ( data ) {
					return { data: data };
				} );
		} );
		window.axismundiMapRasterFallbackProtocol = true;
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
		if ( cfg.rasterTileUrl ) {
			registerRasterFallbackProtocol( maplibregl );
			window.axismundiMapRasterFallbackSeq = ( window.axismundiMapRasterFallbackSeq || 0 ) + 1;
			cfg.rasterFallbackId = 'm' + window.axismundiMapRasterFallbackSeq;
			window.axismundiMapRasterFallbacks[ cfg.rasterFallbackId ] = {
				tileUrl: cfg.rasterTileUrl,
				bounds: cfg.bounds || []
			};
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
		if ( cfg.showVisitorLocation && maplibregl.GeolocateControl ) {
			map.addControl( new maplibregl.GeolocateControl( {
				positionOptions: { enableHighAccuracy: true },
				trackUserLocation: true,
				showUserHeading: true,
				showAccuracyCircle: true
			} ), 'top-right' );
		}

		var cachedGj = null;
		var bound    = false;

		function addRasterFallback() {
			if ( ! cfg.rasterFallbackActive || ! cfg.rasterTileUrl || ! map.isStyleLoaded() ) {
				return;
			}
			if ( ! map.getSource( 'fallback-raster' ) ) {
				map.addSource( 'fallback-raster', {
					type: 'raster',
					tiles: [ cfg.rasterFallbackId ? 'axrasterfallback://' + cfg.rasterFallbackId + '/{z}/{x}/{y}' : cfg.rasterTileUrl ],
					tileSize: 256,
					attribution: cfg.rasterAttribution || ''
				} );
			}
			if ( map.getLayer( 'fallback-raster' ) ) {
				return;
			}
			var firstNonBackground = ( map.getStyle().layers || [] ).find( function ( layer ) {
				return 'background' !== layer.type;
			} );
			map.addLayer( {
				id: 'fallback-raster',
				type: 'raster',
				source: 'fallback-raster',
				minzoom: cfg.minZoom || 0,
				maxzoom: cfg.maxZoom || 22
			}, firstNonBackground ? firstNonBackground.id : undefined );
		}

		function updateRasterFallbackForViewport() {
			if ( ! cfg.rasterTileUrl || ! map.isStyleLoaded() ) {
				return;
			}
			if ( ! containsBounds( cfg.bounds, mapBoundsArray( map ) ) ) {
				cfg.rasterFallbackActive = true;
				addRasterFallback();
			}
		}

		function addLayers() {
			if ( ! cachedGj || ! map.isStyleLoaded() ) {
				return;
			}
			// Idempotent: setStyle (theme switch) drops these, and the initial add
			// must not collide — remove any leftovers (layers before their sources).
			[ 'axgeo-points', 'axgeo-lines-hit', 'axgeo-lines', 'axgeo-track-endpoints' ].forEach( function ( id ) {
				if ( map.getLayer( id ) ) {
					map.removeLayer( id );
				}
			} );
			[ 'axgeo', 'axgeo-track-endpoints' ].forEach( function ( id ) {
				if ( map.getSource( id ) ) {
					map.removeSource( id );
				}
			} );

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
			// 'style.load' isn't reliably re-emitted by setStyle in MapLibre 5; 'idle'
			// fires once the new basemap has settled, when re-adding is safe.
			map.once( 'idle', function () {
				updateRasterFallbackForViewport();
				addRasterFallback();
				addLayers();
			} );
		} );

		if ( ! cfg.geojson ) {
			return;
		}
		map.on( 'load', function () {
			fetchGeojson( cfg.geojson ).then( function ( gj ) {
				cachedGj = gj;
				bindOnce();
				var b = geojsonBounds( gj );
				if ( b ) {
					if ( b[ 0 ][ 0 ] === b[ 1 ][ 0 ] && b[ 0 ][ 1 ] === b[ 1 ][ 1 ] ) {
						// A single point (e.g. one geotag archive) — fitBounds can't pick a
						// zoom from a zero-size box, so place it directly.
						map.jumpTo( { center: b[ 0 ], zoom: cfg.zoom > 0 ? cfg.zoom : 14 } );
					} else {
						map.fitBounds( b, { padding: 24, maxZoom: 15, duration: 0 } );
					}
				}
				cfg.rasterFallbackActive = !! ( cfg.rasterTileUrl && ( ! containsBounds( cfg.bounds, boundsArray( b ) ) || ! containsBounds( cfg.bounds, mapBoundsArray( map ) ) ) );
				updateRasterFallbackForViewport();
				addLayers();
			} ).catch( function () {} );
		} );
		map.on( 'moveend', updateRasterFallbackForViewport );
		map.on( 'zoomend', updateRasterFallbackForViewport );
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
