/**
 * Axismundi Geodata — term editor map.
 *
 * Binds the reusable map field to the Latitude / Longitude inputs on the
 * geo_area / geotag Add New and Edit term screens. Plain JS.
 */
( function ( window, document ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		var lat       = document.getElementById( 'geo_latitude' );
		var lng       = document.getElementById( 'geo_longitude' );
		var container = document.getElementById( 'axgeo-term-map' );
		if ( ! lat || ! lng || ! container || ! window.axismundiGeodataInitMapField ) {
			return;
		}

		// Map zoom / bounds inputs exist on the geo_area editor only; pass them so the
		// field can sync the zoom and draw the bounds preview rectangle.
		var zoomInput   = document.getElementById( 'ax_geo_zoom' );
		var boundsInput = document.getElementById( 'ax_geo_bounds' );

		// Expose the handle so the place-lookup UI (lookup.js) can drop candidate
		// markers and focus / zoom the map when a candidate is chosen.
		window.axismundiGeodataTermMap = window.axismundiGeodataInitMapField( {
			container: container,
			latInput: lat,
			lngInput: lng,
			zoomInput: zoomInput || null,
			boundsInput: boundsInput || null
		} );

		// "Use current map view" button: fill Map bounds from the current viewport.
		var capture = document.getElementById( 'axgeo-capture-bounds' );
		var handle  = window.axismundiGeodataTermMap;
		if ( capture && boundsInput && handle && handle.getViewBounds ) {
			capture.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				boundsInput.value = handle.getViewBounds();
				boundsInput.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
			} );
		}
	} );
} )( window, document );
