/**
 * Axismundi Navigation Icons — front-end icon click delegation.
 *
 * In the li-level structure the icon box is a sibling of the link, not a child of
 * it, so a pointer click on the icon would otherwise do nothing. This forwards an
 * icon click to the item's own link, restoring the M3 expectation that the whole
 * item — icon included — activates the destination.
 *
 * The icon stays aria-hidden / decorative, so keyboard and assistive-tech users
 * still reach the destination through the focusable label link; this only adds the
 * pointer affordance. Clicks that land on the disclosure button are untouched.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var icon = event.target.closest( '.ax-nav-item-icon' );
		if ( ! icon ) {
			return;
		}

		var item = icon.closest( '.wp-block-navigation-item' );
		if ( ! item ) {
			return;
		}

		// The item's own link is the first content anchor within it; nested
		// popover links live deeper and are never the first match.
		var link = item.querySelector( 'a.wp-block-navigation-item__content' );
		if ( link ) {
			link.click();
		}
	} );
}() );
