( function () {
	'use strict';

	let savedFolder = 0;

	async function updateUploadTarget() {
		const select = document.getElementById( 'ax-media-upload-folder' );
		if ( ! select ) {
			return;
		}

		const folder = Number.parseInt( select.value, 10 ) || 0;
		const button = document.getElementById( 'plupload-browse-button' );
		const status = document.querySelector( '.ax-media-upload-folder-status' );
		select.disabled = true;
		if ( button ) {
			button.disabled = true;
		}

		try {
			const body = new URLSearchParams( {
				action: 'axismundi_media_set_upload_folder',
				nonce: window.axMediaNewFolder.nonce,
				folder: String( folder ),
			} );
			const response = await window.fetch( window.axMediaNewFolder.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			} );
			const result = await response.json();
			if ( ! response.ok || ! result.success ) {
				throw new Error( result.data?.message || window.axMediaNewFolder.error );
			}
			savedFolder = folder;
			if ( status ) {
				status.textContent = '';
			}
		} catch ( error ) {
			select.value = String( savedFolder );
			if ( status ) {
				status.textContent = error.message || window.axMediaNewFolder.error;
			}
		} finally {
			select.disabled = false;
			if ( button ) {
				button.disabled = false;
			}
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const select = document.getElementById( 'ax-media-upload-folder' );
		if ( ! select ) {
			return;
		}
		savedFolder = Number.parseInt( select.value, 10 ) || 0;
		select.addEventListener( 'change', updateUploadTarget );
	} );
}() );
