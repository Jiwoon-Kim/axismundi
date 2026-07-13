( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.ax-media-rights__copy' );
		if ( ! button ) {
			return;
		}
		var text = button.getAttribute( 'data-copy-plain' ) || '';
		var html = button.getAttribute( 'data-copy-html' ) || '';
		var format = button.getAttribute( 'data-copy-format' ) || 'plain';
		var status = button.closest( '.ax-media-rights__attribution' ).querySelector( '.ax-media-rights__status' );
		var report = function ( success ) {
			if ( status ) {
				status.textContent = button.getAttribute( success ? 'data-copied-label' : 'data-copy-failed-label' ) || ( success ? 'Copied' : 'Copy failed' );
			}
		};
		if ( 'rich' === format && html && navigator.clipboard && navigator.clipboard.write && window.ClipboardItem ) {
			var item = new window.ClipboardItem( {
				'text/plain': new Blob( [ text ], { type: 'text/plain' } ),
				'text/html': new Blob( [ html ], { type: 'text/html' } )
			} );
			navigator.clipboard.write( [ item ] ).then( function () { report( true ); }, function () { report( false ); } );
			return;
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () { report( true ); }, function () { report( false ); } );
			return;
		}
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();
		try {
			report( document.execCommand( 'copy' ) );
		} catch ( error ) {
			report( false );
		}
		textarea.remove();
	} );
} )();
