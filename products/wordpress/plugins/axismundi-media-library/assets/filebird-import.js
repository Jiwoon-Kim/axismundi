( () => {
	'use strict';

	const config = window.axMediaFileBirdImport;
	const button = document.getElementById( 'ax-media-filebird-start' );
	if ( ! config || ! button ) {
		return;
	}

	const progress = document.getElementById( 'ax-media-filebird-progress' );
	const status = document.getElementById( 'ax-media-filebird-status' );
	const errors = document.getElementById( 'ax-media-filebird-errors' );

	const update = ( data ) => {
		progress.value = data.progress;
		status.textContent = data.done
			? config.labels.done
			: `${ config.labels.running } ${ data.processed } / ${ data.total }`;

		Object.entries( data.stats ).forEach( ( [ key, value ] ) => {
			const cell = document.querySelector( `[data-ax-media-stat="${ key }"]` );
			if ( cell ) {
				cell.textContent = value;
			}
		} );

		errors.replaceChildren();
		data.errors.forEach( ( message ) => {
			const item = document.createElement( 'li' );
			item.textContent = message;
			errors.appendChild( item );
		} );
	};

	const run = async () => {
		button.disabled = true;
		status.textContent = config.labels.running;
		try {
			let finished = false;
			while ( ! finished ) {
				const body = new URLSearchParams( {
					action: 'axismundi_media_filebird_import_batch',
					token: config.token,
					nonce: config.nonce,
				} );
				const response = await fetch( config.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body,
				} );
				const payload = await response.json();
				if ( ! response.ok || ! payload.success ) {
					throw new Error( payload.data?.message || `HTTP ${ response.status }` );
				}
				update( payload.data );
				finished = payload.data.done;
			}
			button.remove();
		} catch ( error ) {
			status.textContent = error.message;
			button.disabled = false;
			button.textContent = config.labels.resume;
		}
	};

	button.addEventListener( 'click', run );
} )();
