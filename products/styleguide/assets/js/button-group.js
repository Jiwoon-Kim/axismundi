/**
 * Button group — selection behaviour for the specimens on this site.
 *
 * The page argues that a group owns the arrangement of selection while the
 * control that uses it owns what selection means. A static specimen cannot show
 * that: three segments frozen at aria-pressed="true" read as a row of
 * checkboxes whatever the prose says. This script makes the three modes M3
 * publishes actually behave differently, so the shape morph is something a
 * reader triggers rather than something they are told about.
 *
 * The invariants it holds, and they are the definition of the three modes:
 *
 *   single + required   exactly one pressed, always
 *   single              nothing or one pressed
 *   multiple            any number pressed
 *
 * Only aria-pressed is driven here. aria-current="page" belongs to the other
 * shipping consumer - the feed density switch, whose segments are links to a
 * URL - and a link's current state is the server's answer, not a click
 * handler's. Those specimens stay static on purpose.
 */
( function () {
	var SEGMENT = ".wp-block-axismundi-button-group__item[aria-pressed]";

	function segments( group ) {
		return Array.prototype.slice.call( group.querySelectorAll( SEGMENT ) );
	}

	function bind( group ) {
		var mode = group.dataset.selection;
		var required = group.dataset.required === "true";

		group.addEventListener( "click", function ( event ) {
			var target = event.target.closest( SEGMENT );
			if ( ! target || ! group.contains( target ) ) {
				return;
			}

			if ( target.disabled || target.getAttribute( "aria-disabled" ) === "true" ) {
				return;
			}

			var pressed = target.getAttribute( "aria-pressed" ) === "true";

			if ( mode === "multiple" ) {
				target.setAttribute( "aria-pressed", pressed ? "false" : "true" );
				return;
			}

			// Single-select. Turning the last one off is what `required`
			// forbids, and it is the only difference between the two modes.
			if ( pressed && required ) {
				return;
			}

			segments( group ).forEach( function ( segment ) {
				segment.setAttribute( "aria-pressed", "false" );
			} );

			if ( ! pressed ) {
				target.setAttribute( "aria-pressed", "true" );
			}
		} );
	}

	document
		.querySelectorAll( ".wp-block-axismundi-button-group[data-selection]" )
		.forEach( bind );
} )();

/**
 * The playground.
 *
 * Every control here writes one of the data-* attributes the adapter already
 * reads, which is the point: this is not a simulator, it is the block's
 * Inspector expressed as HTML. A reader changing "Color" is doing what an
 * author would do in the sidebar, and the specimen under it is the markup the
 * block would save.
 *
 * No iframe. The specimens are already the real DOM under the real stylesheet,
 * so there is nothing to isolate; an iframe would only add a second copy of the
 * token layers to keep in step. The one thing it would buy is a viewport of its
 * own, which no control here needs.
 */
( function () {
	var GROUP = ".wp-block-axismundi-button-group";

	function normalise( group ) {
		var mode = group.dataset.selection;
		var required = group.dataset.required === "true";
		var segments = Array.prototype.slice.call(
			group.querySelectorAll( ".wp-block-axismundi-button-group__item[aria-pressed]" )
		);
		if ( ! segments.length ) {
			return;
		}
		var on = segments.filter( function ( s ) {
			return s.getAttribute( "aria-pressed" ) === "true";
		} );

		// Switching modes can leave a state the new mode forbids: two pressed
		// under single, none pressed under required. Fix it on the way in
		// rather than letting the invariant break silently.
		if ( mode === "multiple" ) {
			return;
		}
		if ( on.length > 1 ) {
			on.slice( 1 ).forEach( function ( s ) {
				s.setAttribute( "aria-pressed", "false" );
			} );
			on = on.slice( 0, 1 );
		}
		if ( required && on.length === 0 ) {
			segments[ 0 ].setAttribute( "aria-pressed", "true" );
		}
	}

	document.querySelectorAll( ".sg-bg-playground" ).forEach( function ( host ) {
		var group = host.querySelector( GROUP );
		if ( ! group ) {
			return;
		}

		host.addEventListener( "change", function ( event ) {
			var control = event.target.closest( "[data-controls]" );
			if ( ! control ) {
				return;
			}
			var key = control.dataset.controls;
			var value = control.type === "checkbox"
				? ( control.checked ? control.value : "" )
				: control.value;

			if ( value === "" ) {
				delete group.dataset[ key ];
			} else {
				group.dataset[ key ] = value;
			}

			// required is a second attribute, not a fourth selection value.
			if ( key === "selection" ) {
				if ( value === "single-required" ) {
					group.dataset.selection = "single";
					group.dataset.required = "true";
				} else {
					delete group.dataset.required;
				}
				normalise( group );
			}

			var out = host.querySelector( "[data-playground-markup]" );
			if ( out ) {
				out.textContent = Object.keys( group.dataset )
					.map( function ( k ) {
						return "data-" + k.replace( /[A-Z]/g, function ( m ) {
							return "-" + m.toLowerCase();
						} ) + '="' + group.dataset[ k ] + '"';
					} )
					.join( String.fromCharCode( 10 ) );
			}
		} );

		host.dispatchEvent( new Event( "change", { bubbles: false } ) );
	} );
} )();
