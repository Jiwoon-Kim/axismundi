/**
 * axismundi/object-summary — spoiler reveal (no build step).
 *
 * A sensitive Article's summary is covered rather than removed, so revealing it is a
 * local, client-side act: nothing is fetched and no server state changes, exactly like
 * the Media Library's reveal overlay. The rules are shared with that overlay; the markup
 * is not, because this covers a paragraph, not a figure.
 *
 * Delegated so summaries that arrive later — a feed page, a dialog, an editor preview —
 * work without re-binding. With this script absent the text stays covered and the
 * "Read more" link still leads to the full Article, so nothing becomes unreachable.
 */
( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.axismundi-object__spoiler-reveal' );
		if ( ! button ) {
			return;
		}
		var spoiler = button.closest( '[data-ax-spoiler]' );
		if ( ! spoiler ) {
			return;
		}
		var text = spoiler.querySelector( '.axismundi-object__spoiler-text' );
		spoiler.classList.remove( 'is-obscured' );
		button.setAttribute( 'aria-expanded', 'true' );
		if ( text ) {
			// The warning has been answered, so the summary rejoins the accessibility
			// tree; focus moves to it so a screen reader reads what was just revealed.
			text.removeAttribute( 'aria-hidden' );
			text.setAttribute( 'tabindex', '-1' );
			text.focus();
		}
		// The control has done its one job; leaving it would offer to reveal twice.
		button.hidden = true;
	} );
}() );
