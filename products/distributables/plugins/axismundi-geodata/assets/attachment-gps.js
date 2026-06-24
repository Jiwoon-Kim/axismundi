/**
 * Axismundi Geodata — attachment Location meta box behaviour.
 *
 * Wires the lat/lng inputs to the reusable map field, the "Import from EXIF"
 * button (AJAX read of the original file's GPS), and the Clear button. Plain JS.
 */
( function ( window, document ) {
	var __ = ( window.wp && window.wp.i18n && window.wp.i18n.__ ) || function ( s ) { return s; };

	document.addEventListener( 'DOMContentLoaded', function () {
		var lat = document.getElementById( 'axgeo-lat' );
		var lng = document.getElementById( 'axgeo-lng' );
		var alt = document.getElementById( 'axgeo-alt' );
		if ( ! lat || ! lng ) {
			return;
		}

		var controller = null;
		var container  = document.getElementById( 'axgeo-map' );
		if ( container && window.axismundiGeodataInitMapField ) {
			controller = window.axismundiGeodataInitMapField( {
				container: container,
				latInput: lat,
				lngInput: lng
			} );
		}

		var importBtn = document.getElementById( 'axgeo-import-exif' );
		if ( importBtn ) {
			importBtn.addEventListener( 'click', function () {
				var ajax = window.axismundiGeodataAjax || {};
				var id   = importBtn.getAttribute( 'data-id' );
				importBtn.disabled = true;

				var url = ajax.url + '?action=axismundi_geodata_exif&_ajax_nonce=' +
					encodeURIComponent( ajax.nonce ) + '&id=' + encodeURIComponent( id );

				window.fetch( url, { credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						importBtn.disabled = false;
						if ( ! res || ! res.success || ! res.data ) {
							window.alert( __( 'No GPS found in this file.', 'axismundi-geodata' ) );
							return;
						}
						lat.value = res.data.latitude;
						lng.value = res.data.longitude;
						if ( alt && null !== res.data.altitude && undefined !== res.data.altitude ) {
							alt.value = res.data.altitude;
						}
						if ( controller ) {
							controller.setLatLng( res.data.latitude, res.data.longitude );
						}
					} )
					.catch( function () {
						importBtn.disabled = false;
						window.alert( __( 'Import failed.', 'axismundi-geodata' ) );
					} );
			} );
		}

		var clearBtn = document.getElementById( 'axgeo-clear' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				lat.value = '';
				lng.value = '';
				if ( alt ) {
					alt.value = '';
				}
			} );
		}
	} );
} )( window, document );
