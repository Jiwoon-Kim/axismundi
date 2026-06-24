/**
 * Axismundi Geodata — reusable Leaflet map field.
 *
 * Binds a Leaflet map (draggable + click-to-place marker) to a pair of
 * latitude / longitude text inputs. Used by the attachment GPS editor and, later,
 * the geo_area / geotag term screens. Reads the tile provider from the localized
 * window.axismundiGeodataMap. Plain JS — no build, no editor dependency.
 *
 * window.axismundiGeodataInitMapField( {
 *   container, latInput, lngInput, onChange
 * } ) -> { setLatLng(lat,lng) } | null
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
		if ( ! cfg.mapEnabled || ! window.L || ! opts || ! opts.container || ! opts.latInput || ! opts.lngInput ) {
			return null;
		}

		var L = window.L;
		L.Icon.Default.mergeOptions( {
			iconRetinaUrl: cfg.imagePath + 'marker-icon-2x.png',
			iconUrl: cfg.imagePath + 'marker-icon.png',
			shadowUrl: cfg.imagePath + 'marker-shadow.png'
		} );

		var lat       = num( opts.latInput.value );
		var lng       = num( opts.lngInput.value );
		var hasCoords = null !== lat && null !== lng;

		var map = L.map( opts.container ).setView( hasCoords ? [ lat, lng ] : [ 20, 0 ], hasCoords ? 13 : 2 );
		L.tileLayer( cfg.tileUrl, {
			attribution: cfg.attribution || '',
			minZoom: cfg.minZoom || 1,
			maxZoom: cfg.maxZoom || 19
		} ).addTo( map );

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
				marker.setLatLng( [ la, ln ] );
			} else {
				marker = L.marker( [ la, ln ], { draggable: true } ).addTo( map );
				marker.on( 'dragend', function () {
					var ll = marker.getLatLng();
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
			place( e.latlng.lat, e.latlng.lng, true );
		} );

		function syncFromInputs() {
			var la = num( opts.latInput.value );
			var ln = num( opts.lngInput.value );
			if ( null !== la && null !== ln ) {
				place( la, ln, false );
				map.panTo( [ la, ln ] );
			}
		}
		opts.latInput.addEventListener( 'change', syncFromInputs );
		opts.lngInput.addEventListener( 'change', syncFromInputs );

		window.setTimeout( function () {
			map.invalidateSize();
		}, 60 );

		return {
			setLatLng: function ( la, ln ) {
				place( la, ln, true );
				map.setView( [ la, ln ], 14 );
			}
		};
	}

	window.axismundiGeodataInitMapField = initMapField;
} )( window );
