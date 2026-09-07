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

	function loadGeojson( cfg ) {
		if ( cfg.geojsonData ) {
			return Promise.resolve( cfg.geojsonData );
		}
		return cfg.geojson ? fetchGeojson( cfg.geojson ) : null;
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

	function popupLinkOpen( href, cfg ) {
		var attrs = ' href="' + escapeHtml( href ) + '"';
		if ( cfg && cfg.openInNewTab ) {
			attrs += ' target="_blank" rel="noopener noreferrer"';
		}
		return '<a' + attrs + '>';
	}

	function trimWords( text, max ) {
		var words = String( text || '' ).trim().split( /\s+/ );
		if ( words.length <= max ) {
			return words.join( ' ' );
		}
		return words.slice( 0, max ).join( ' ' ) + '…';
	}

	function popupHtml( props, cfg ) {
		props = props || {};
		cfg = cfg || {};
		var title = popupTitle( props );
		var html  = '';
		if ( props.thumbnail ) {
			html += '<img class="axismundi-map__popup-thumb" src="' + escapeHtml( props.thumbnail ) + '" alt="" loading="lazy" />';
		}
		if ( title ) {
			var titleInner = escapeHtml( title );
			if ( props.url ) {
				titleInner = popupLinkOpen( props.url, cfg ) + titleInner + '</a>';
			}
			html += '<strong class="axismundi-map__popup-title">' + titleInner + '</strong>';
		}
		if ( cfg.displayAuthor ) {
			if ( props.author_name ) {
				var author = escapeHtml( props.author_name );
				if ( props.author_url ) {
					author = popupLinkOpen( props.author_url, cfg ) + author + '</a>';
				}
				html += '<div class="axismundi-map__popup-byline">' + author + '</div>';
			}
		}
		if ( cfg.displayDate && props.published ) {
			html += '<div class="axismundi-map__popup-date">' + escapeHtml( props.published ) + '</div>';
		}
		if ( cfg.displayExcerpt && props.excerpt ) {
			html += '<p class="axismundi-map__popup-excerpt">' + escapeHtml( trimWords( props.excerpt, cfg.excerptLength || 55 ) ) + '</p>';
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

		var layer = L.geoJSON( null, {
				pointToLayer: function ( feature, latlng ) {
					return L.circleMarker( latlng, { radius: 6, color: '#d32f2f', weight: 2, fillColor: '#d32f2f', fillOpacity: 0.7 } );
				},
				style: function () {
					return { color: '#d32f2f', weight: 3 };
				},
				onEachFeature: function ( feature, lyr ) {
					var html = popupHtml( feature.properties, cfg );
					if ( cfg.showPopups && html ) {
						lyr.bindPopup( html );
					}
				}
			} ).addTo( map );

		function setGeojson( gj, fitView ) {
			layer.clearLayers();
			layer.addData( gj );
			if ( ! fitView ) {
				return;
			}
			try {
				var b = layer.getBounds();
				if ( b.isValid() ) {
					if ( b.getNorthEast().equals( b.getSouthWest() ) ) {
						map.setView( b.getCenter(), cfg.singlePointZoom > 0 ? cfg.singlePointZoom : ( cfg.zoom > 0 ? cfg.zoom : 14 ) );
					} else {
						map.fitBounds( b, { padding: [ 24, 24 ], maxZoom: 15 } );
					}
				} else if ( cfg.fallbackBounds && 4 === cfg.fallbackBounds.length ) {
					map.fitBounds(
						[ [ cfg.fallbackBounds[ 1 ], cfg.fallbackBounds[ 0 ] ], [ cfg.fallbackBounds[ 3 ], cfg.fallbackBounds[ 2 ] ] ],
						{ padding: [ 24, 24 ], maxZoom: 15 }
					);
				} else {
					map.setView( [ cz.center[ 1 ], cz.center[ 0 ] ], cz.zoom );
				}
			} catch ( e ) {}
		}

		canvas.axismundiMapController = {
			setGeojson: function ( gj ) {
				setGeojson( gj, !! cfg.fitGeojson );
			}
		};

		var geojson = loadGeojson( cfg );
		if ( geojson ) {
			geojson.then( function ( gj ) {
				setGeojson( gj, true );
			} ).catch( function () {} );
		}
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
		return outer && 4 === outer.length && inner && 4 === inner.length &&
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
				} )
				.catch( function () {
					// A network failure (offline, DNS, CORS) must not reject the tile —
					// fall back to a transparent tile so MapLibre doesn't log an error.
					return { data: transparentPng() };
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
		var viewportFallbackArmed = false;
		function armViewportFallback() {
			viewportFallbackArmed = true;
		}
		// Auto-fit padding can extend a local-data viewport just beyond the pack.
		// Only treat viewport escape as user intent after an actual map gesture.
		map.getContainer().addEventListener( 'pointerdown', armViewportFallback, { passive: true } );
		map.getContainer().addEventListener( 'wheel', armViewportFallback, { passive: true } );
		map.getContainer().addEventListener( 'keydown', armViewportFallback );

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
			if ( cfg.rasterFallbackActive || ( viewportFallbackArmed && ! containsBounds( cfg.bounds, mapBoundsArray( map ) ) ) ) {
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
			[ 'axgeo-points', 'axgeo-lines-hit', 'axgeo-lines', 'axgeo-outline', 'axgeo-fill', 'axgeo-track-endpoints' ].forEach( function ( id ) {
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
			// Polygons (GeoRSS box / polygon) render under the lines and points.
			map.addLayer( { id: 'axgeo-fill', type: 'fill', source: 'axgeo', filter: [ 'in', [ 'geometry-type' ], [ 'literal', [ 'Polygon', 'MultiPolygon' ] ] ], paint: { 'fill-color': '#d32f2f', 'fill-opacity': 0.15 } } );
			map.addLayer( { id: 'axgeo-outline', type: 'line', source: 'axgeo', filter: [ 'in', [ 'geometry-type' ], [ 'literal', [ 'Polygon', 'MultiPolygon' ] ] ], paint: { 'line-color': '#d32f2f', 'line-width': 2 } } );
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
			var html = popupHtml( props, cfg );
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

		function fitGeojsonView( gj ) {
			var b = geojsonBounds( gj );
			if ( b ) {
				if ( b[ 0 ][ 0 ] === b[ 1 ][ 0 ] && b[ 0 ][ 1 ] === b[ 1 ][ 1 ] ) {
					map.jumpTo( { center: b[ 0 ], zoom: cfg.singlePointZoom > 0 ? cfg.singlePointZoom : ( cfg.zoom > 0 ? cfg.zoom : 14 ) } );
				} else {
					map.fitBounds( b, { padding: 24, maxZoom: 15, duration: 0 } );
				}
			} else if ( cfg.fallbackBounds && 4 === cfg.fallbackBounds.length ) {
				map.fitBounds(
					[ [ cfg.fallbackBounds[ 0 ], cfg.fallbackBounds[ 1 ] ], [ cfg.fallbackBounds[ 2 ], cfg.fallbackBounds[ 3 ] ] ],
					{ padding: 24, maxZoom: 15, duration: 0 }
				);
			} else {
				map.jumpTo( { center: cz.center, zoom: cz.zoom } );
			}
		}

		function setGeojson( gj, fitView ) {
			cachedGj = gj;
			if ( ! map.isStyleLoaded() ) {
				return;
			}

			var source = map.getSource( 'axgeo' );
			if ( ! source ) {
				addLayers();
				bindOnce();
				if ( fitView ) {
					fitGeojsonView( gj );
				}
				return;
			}

			source.setData( gj );
			var endpoints       = trackEndpointFeatures( gj );
			var endpointSource  = map.getSource( 'axgeo-track-endpoints' );
			if ( endpointSource ) {
				endpointSource.setData( endpoints );
			} else if ( endpoints.features.length ) {
				addLayers();
			}
			if ( fitView ) {
				fitGeojsonView( gj );
			}
		}

		canvas.axismundiMapController = {
			setGeojson: function ( gj ) {
				setGeojson( gj, !! cfg.fitGeojson );
			}
		};

		// Re-tint the basemap when the theme mode flips; the overlay layers are
		// re-added once the new style finishes loading.
		watchTheme( function ( dark ) {
			map.setStyle( buildStyle( cfg, dark ) );
			// 'style.load' isn't reliably re-emitted by setStyle in MapLibre 5; 'idle'
			// fires once the new basemap has settled, when re-adding is safe.
			map.once( 'idle', function () {
				// Overlay first — see the load handler: addRasterFallback() unloads the
				// style momentarily and would make a later addLayers() bail.
				addLayers();
				updateRasterFallbackForViewport();
				addRasterFallback();
			} );
		} );

		var geojson = loadGeojson( cfg );
		if ( ! geojson ) {
			return;
		}
		map.on( 'load', function () {
			geojson.then( function ( gj ) {
				cachedGj = gj;
				bindOnce();
				var b = geojsonBounds( gj );
				// Add the overlay before moving the camera or adding the raster fallback.
				// A distant fitBounds() starts loading a new viewport and temporarily makes
				// isStyleLoaded() false; adding the overlay afterwards would then bail and
				// leave wide GeoRSS feeds (for example, North America) with no markers.
				addLayers();
				fitGeojsonView( gj );
				// Data outside the pack needs an immediate fallback. A viewport that only
				// exceeds the pack because of automatic fit padding stays self-hosted until
				// the visitor deliberately pans or zooms the map.
				cfg.rasterFallbackActive = !! ( cfg.rasterTileUrl && ! containsBounds( cfg.bounds, boundsArray( b ) ) );
				updateRasterFallbackForViewport();
			} ).catch( function () {} );
		} );
		map.on( 'moveend', updateRasterFallbackForViewport );
		map.on( 'zoomend', updateRasterFallbackForViewport );
		// A long fit can fire moveend while the new viewport is still loading, when
		// the style-loaded guard intentionally defers source changes. Retry at idle;
		// addRasterFallback() is idempotent once the source and layer exist.
		map.on( 'idle', updateRasterFallbackForViewport );
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
