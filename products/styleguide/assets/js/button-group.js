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
