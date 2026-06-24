/**
 * Axismundi Geodata — block-editor Location panel.
 *
 * A PluginDocumentSettingPanel that edits the post's geo_* coordinate meta plus
 * the ax_geo_public_precision privacy select. Manual coordinates, "use current
 * location" (browser geolocation, user-gesture), public toggle, precision, a
 * read-only provider address, and remove. Map preview, geocoding, and Plus Code
 * decoding arrive with the geocoding adapter.
 *
 * Hand-written (no JSX / build) to match the other Axismundi companion plugins.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data ) {
		return;
	}

	var el            = wp.element.createElement;
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

		function setMeta( changes ) {
			editPost( { meta: changes } );
		}

		function useCurrentLocation() {
			if ( ! navigator.geolocation ) {
				window.alert( __( 'Geolocation is not available in this browser.', 'axismundi-geodata' ) );
				return;
			}
			navigator.geolocation.getCurrentPosition(
				function ( pos ) {
					setMeta( {
						geo_latitude: Math.round( pos.coords.latitude * 1e7 ) / 1e7,
						geo_longitude: Math.round( pos.coords.longitude * 1e7 ) / 1e7,
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

		var hasPoint = !! meta.geo_latitude || !! meta.geo_longitude;

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
