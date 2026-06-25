/**
 * Axismundi Geodata — MapLibre + PMTiles map field.
 *
 * Drop-in alternative to the Leaflet map-field.js for the `pmtiles` admin preview
 * provider: same window.axismundiGeodataInitMapField( { container, latInput,
 * lngInput, onChange } ) contract and the same draggable / click-to-place marker,
 * but renders a self-hosted PMTiles vector basemap via MapLibre GL + the Protomaps
 * light theme. Reads config from window.axismundiGeodataMap (kind === 'pmtiles').
 */
( function ( window ) {
	function num( v ) {
		var n = parseFloat( v );
		return isFinite( n ) ? n : null;
	}

	function round7( n ) {
		return Math.round( n * 1e7 ) / 1e7;
	}

	function initMapField( opts ) {
		var cfg = window.axismundiGeodataMap || {};
		if (
			'pmtiles' !== cfg.kind || ! window.maplibregl || ! window.pmtiles || ! window.basemaps ||
			! opts || ! opts.container || ! opts.latInput || ! opts.lngInput || ! cfg.packUrl
		) {
			return null;
		}

		var maplibregl = window.maplibregl;

		// Register the pmtiles:// protocol once per page.
		if ( ! window.axismundiGeodataPmtilesProtocol ) {
			var protocol = new window.pmtiles.Protocol();
			maplibregl.addProtocol( 'pmtiles', protocol.tile );
			window.axismundiGeodataPmtilesProtocol = protocol;
		}

		var lat       = num( opts.latInput.value );
		var lng       = num( opts.lngInput.value );
		var hasCoords = null !== lat && null !== lng;
		var center    = hasCoords ? [ lng, lat ] : ( cfg.center && 2 === cfg.center.length ? cfg.center : [ 0, 20 ] );

		var style = {
			version: 8,
			glyphs: cfg.glyphs,
			sprite: cfg.sprite,
			sources: {
				protomaps: {
					type: 'vector',
					url: 'pmtiles://' + cfg.packUrl,
					attribution: cfg.attribution || ''
				}
			},
			layers: window.basemaps.layers( 'protomaps', window.basemaps.namedFlavor( 'light' ), { lang: cfg.lang || 'en' } )
		};

		var map = new maplibregl.Map( {
			container: opts.container,
			style: style,
			center: center,
			zoom: hasCoords ? 13 : ( cfg.minZoom || 4 ),
			minZoom: cfg.minZoom || 0,
			maxZoom: cfg.maxZoom || 18
		} );
		map.addControl( new maplibregl.NavigationControl( { showCompass: false } ), 'top-left' );

		var marker = null;

		function writeInputs( la, ln ) {
			opts.latInput.value = round7( la );
			opts.lngInput.value = round7( ln );
			if ( opts.onChange ) {
				opts.onChange( la, ln );
			}
		}

		function place( la, ln, write ) {
			if ( marker ) {
				marker.setLngLat( [ ln, la ] );
			} else {
				marker = new maplibregl.Marker( { draggable: true, color: '#d32f2f' } ).setLngLat( [ ln, la ] ).addTo( map );
				marker.on( 'dragend', function () {
					var ll = marker.getLngLat();
					writeInputs( ll.lat, ll.lng );
				} );
			}
			if ( write ) {
				writeInputs( la, ln );
			}
		}

		if ( hasCoords ) {
			place( lat, lng, false );
		}

		map.on( 'click', function ( e ) {
			place( e.lngLat.lat, e.lngLat.lng, true );
		} );

		function syncFromInputs() {
			var la = num( opts.latInput.value );
			var ln = num( opts.lngInput.value );
			if ( null !== la && null !== ln ) {
				place( la, ln, false );
				map.panTo( [ ln, la ] );
			}
		}
		opts.latInput.addEventListener( 'change', syncFromInputs );
		opts.lngInput.addEventListener( 'change', syncFromInputs );

		return {
			setLatLng: function ( la, ln ) {
				place( la, ln, true );
				map.flyTo( { center: [ ln, la ], zoom: 14 } );
			},
			clear: function () {
				if ( marker ) {
					marker.remove();
					marker = null;
				}
			}
		};
	}

	window.axismundiGeodataInitMapField = initMapField;
} )( window );
