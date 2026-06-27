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

		// Expose the handle so the place-lookup UI (lookup.js) can drop candidate
		// markers and focus / zoom the map when a candidate is chosen.
		window.axismundiGeodataTermMap = window.axismundiGeodataInitMapField( {
			container: container,
			latInput: lat,
			lngInput: lng
		} );
	} );
} )( window, document );
