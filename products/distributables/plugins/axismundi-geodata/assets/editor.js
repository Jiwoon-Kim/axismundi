/**
 * Axismundi Geodata — block-editor Location panel.
 *
 * A PluginDocumentSettingPanel ("Location") that edits the post's geo_* coordinate
 * meta plus the ax_geo_public_precision privacy select: manual latitude / longitude,
 * "use current location" (browser geolocation, user-gesture), a draggable Leaflet
 * map preview (when a tile provider is configured), public toggle, precision, a
 * read-only provider address, and remove. Geocoding and Plus Code decoding arrive
 * with the geocoding adapter.
 *
 * Hand-written (no JSX / build) to match the other Axismundi companion plugins.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data ) {
		return;
	}

	var el            = wp.element.createElement;
	var useRef        = wp.element.useRef;
	var useEffect     = wp.element.useEffect;
	var useCallback   = wp.element.useCallback;
	var registerPlugin = wp.plugins.registerPlugin;
	var Panel         =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel ) {
		return; // Not a document editor (e.g. site editor) — nothing to attach to.
	}

	var useSelect      = wp.data.useSelect;
	var useDispatch    = wp.data.useDispatch;
	var __             = wp.i18n.__;
	var c              = wp.components;
	var TextControl    = c.TextControl;
	var ToggleControl  = c.ToggleControl;
	var SelectControl  = c.SelectControl;
	var Button         = c.Button;
	var BaseControl    = c.BaseControl;

	var PRECISION_OPTIONS = [
		{ label: __( 'Hidden', 'axismundi-geodata' ), value: 'hidden' },
		{ label: __( 'City (~11 km)', 'axismundi-geodata' ), value: 'city' },
		{ label: __( 'Coarse (~1 km)', 'axismundi-geodata' ), value: 'coarse' },
		{ label: __( 'Neighborhood (~110 m)', 'axismundi-geodata' ), value: 'neighborhood' },
		{ label: __( 'Exact', 'axismundi-geodata' ), value: 'exact' }
	];

	function round7( n ) {
		return Math.round( n * 1e7 ) / 1e7;
	}

	function isNum( v ) {
		return 'number' === typeof v && isFinite( v );
	}

	// Unset number meta reads back as 0; treat 0 as empty in the inputs so a blank
	// field doesn't show a spurious "0". (True 0,0 / Null Island is not supported.)
	function numToString( v ) {
		return ( v === undefined || v === null || v === '' || v === 0 ) ? '' : String( v );
	}

	function LocationPanel() {
		var meta = useSelect( function ( select ) {
			var ed = select( 'core/editor' );
			return ( ed && ed.getEditedPostAttribute( 'meta' ) ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		var mapObj   = useRef( null );
		var markerObj = useRef( null );

		function setMeta( changes ) {
			editPost( { meta: changes } );
		}

		function setCoords( lat, lng ) {
			setMeta( { geo_latitude: round7( lat ), geo_longitude: round7( lng ) } );
		}

		function ensureMarker( L, map, lat, lng ) {
			if ( markerObj.current ) {
				markerObj.current.setLatLng( [ lat, lng ] );
				return;
			}
			var marker = L.marker( [ lat, lng ], { draggable: true } ).addTo( map );
			marker.on( 'dragend', function () {
				var ll = marker.getLatLng();
				setCoords( ll.lat, ll.lng );
			} );
			markerObj.current = marker;
		}

		function useCurrentLocation() {
			if ( ! navigator.geolocation ) {
				window.alert( __( 'Geolocation is not available in this browser.', 'axismundi-geodata' ) );
				return;
			}
			navigator.geolocation.getCurrentPosition(
				function ( pos ) {
					setMeta( {
						geo_latitude: round7( pos.coords.latitude ),
						geo_longitude: round7( pos.coords.longitude ),
						geo_accuracy: pos.coords.accuracy ? Math.round( pos.coords.accuracy ) : null
					} );
				},
				function () {
					window.alert( __( 'Could not get your location.', 'axismundi-geodata' ) );
				}
			);
		}

		function removeLocation() {
			setMeta( {
				geo_latitude: null,
				geo_longitude: null,
				geo_accuracy: null,
				geo_address: null,
				geo_public: false
			} );
		}

		var cfg = window.axismundiGeodataMap || {};

		// Initialise (or tear down) the Leaflet map as its container mounts. A
		// callback ref handles the panel being collapsed at mount and expanded
		// later: PanelBody unmounts its children when closed, so a useEffect keyed
		// on [] would miss the container appearing.
		var initMapContainer = useCallback( function ( node ) {
			if ( ! node ) {
				if ( mapObj.current ) {
					mapObj.current.remove();
					mapObj.current  = null;
					markerObj.current = null;
				}
				return;
			}
			if ( mapObj.current || ! cfg.mapEnabled || ! window.L ) {
				return;
			}

			var L = window.L;
			L.Icon.Default.mergeOptions( {
				iconRetinaUrl: cfg.imagePath + 'marker-icon-2x.png',
				iconUrl: cfg.imagePath + 'marker-icon.png',
				shadowUrl: cfg.imagePath + 'marker-shadow.png'
			} );

			var current   = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			var hasCoords = isNum( current.geo_latitude ) && isNum( current.geo_longitude );
			var center    = hasCoords ? [ current.geo_latitude, current.geo_longitude ] : [ 20, 0 ];
			var map       = L.map( node ).setView( center, hasCoords ? 13 : 2 );

			L.tileLayer( cfg.tileUrl, {
				attribution: cfg.attribution || '',
				minZoom: cfg.minZoom || 1,
				maxZoom: cfg.maxZoom || 19
			} ).addTo( map );

			mapObj.current = map;
			if ( hasCoords ) {
				ensureMarker( L, map, current.geo_latitude, current.geo_longitude );
			}

			map.on( 'click', function ( e ) {
				ensureMarker( L, map, e.latlng.lat, e.latlng.lng );
				setCoords( e.latlng.lat, e.latlng.lng );
			} );

			window.setTimeout( function () {
				map.invalidateSize();
			}, 60 );
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [] );

		// Sync the marker / view when coordinates are edited by hand.
		useEffect( function () {
			if ( ! mapObj.current || ! window.L ) {
				return;
			}
			if ( isNum( meta.geo_latitude ) && isNum( meta.geo_longitude ) ) {
				ensureMarker( window.L, mapObj.current, meta.geo_latitude, meta.geo_longitude );
				mapObj.current.panTo( [ meta.geo_latitude, meta.geo_longitude ] );
			}
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ meta.geo_latitude, meta.geo_longitude ] );

		var hasPoint = isNum( meta.geo_latitude ) || isNum( meta.geo_longitude );

		var mapNode = cfg.mapEnabled
			? el( 'div', {
				ref: initMapContainer,
				style: { height: '220px', marginBottom: '16px', borderRadius: '4px', overflow: 'hidden' }
			} )
			: el( 'p', { className: 'description', style: { marginTop: '-8px', marginBottom: '16px' } },
				__( 'Map preview is disabled. Configure a map provider in Settings → Geodata.', 'axismundi-geodata' ) );

		return el(
			Panel,
			{ name: 'axismundi-geodata-location', title: __( 'Location', 'axismundi-geodata' ) },
			el( TextControl, {
				label: __( 'Latitude', 'axismundi-geodata' ),
				type: 'number',
				step: 'any',
				value: numToString( meta.geo_latitude ),
				onChange: function ( v ) { setMeta( { geo_latitude: '' === v ? null : parseFloat( v ) } ); }
			} ),
			el( TextControl, {
				label: __( 'Longitude', 'axismundi-geodata' ),
				type: 'number',
				step: 'any',
				value: numToString( meta.geo_longitude ),
				onChange: function ( v ) { setMeta( { geo_longitude: '' === v ? null : parseFloat( v ) } ); }
			} ),
			el(
				Button,
				{ variant: 'secondary', onClick: useCurrentLocation, style: { marginBottom: '16px' } },
				__( 'Use current location', 'axismundi-geodata' )
			),
			mapNode,
			meta.geo_accuracy
				? el( 'p', { className: 'description', style: { marginTop: '-8px' } }, __( 'Accuracy', 'axismundi-geodata' ) + ': ' + meta.geo_accuracy + ' m' )
				: null,
			el( ToggleControl, {
				label: __( 'Public', 'axismundi-geodata' ),
				help: __( 'Allow this coordinate to be exposed on the site and REST.', 'axismundi-geodata' ),
				checked: !! meta.geo_public,
				onChange: function ( v ) { setMeta( { geo_public: v } ); }
			} ),
			el( SelectControl, {
				label: __( 'Public precision', 'axismundi-geodata' ),
				value: meta.ax_geo_public_precision || 'coarse',
				options: PRECISION_OPTIONS,
				onChange: function ( v ) { setMeta( { ax_geo_public_precision: v } ); }
			} ),
			meta.geo_address
				? el( BaseControl, { label: __( 'Address', 'axismundi-geodata' ) }, el( 'p', {}, meta.geo_address ) )
				: null,
			hasPoint
				? el(
					Button,
					{ isDestructive: true, variant: 'tertiary', onClick: removeLocation },
					__( 'Remove location', 'axismundi-geodata' )
				)
				: null
		);
	}

	registerPlugin( 'axismundi-geodata', { render: LocationPanel } );
} )( window.wp );
