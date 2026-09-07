/**
 * Reveal a sensitive-media block on click (Phase 4c). Delegated so it also
 * covers blocks added after load. Revealing only removes the local blur; it
 * changes no server state.
 */
( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.ax-media-sensitive__reveal' );
		if ( ! button ) {
			return;
		}
		var wrapper = button.closest( '.ax-media-sensitive' );
		if ( wrapper ) {
			wrapper.classList.remove( 'is-hidden' );
		}
	} );
}() );
