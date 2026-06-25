( function () {
	const cfg = window.axismundiGeodataGoogleLookup || {};

	function post( action, payload ) {
		const data = new window.FormData();
		data.append( 'action', action );
		data.append( 'nonce', cfg.nonce || '' );
		data.append( 'term_id', String( cfg.termId || '' ) );

		Object.keys( payload || {} ).forEach( ( key ) => {
			if ( payload[ key ] !== null && payload[ key ] !== undefined ) {
				data.append( key, String( payload[ key ] ) );
			}
		} );

		return window.fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} ).then( ( response ) => response.json() );
	}

	function setStatus( message ) {
		const status = document.getElementById( 'axgeo-google-status' );
		if ( status ) {
			status.textContent = message;
		}
	}

	function setInputValue( id, value ) {
		const input = document.getElementById( id );
		if ( input && value !== null && value !== undefined ) {
			input.value = String( value );
			input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
			input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		}
	}

	function candidateTitle( candidate ) {
		return candidate.name || candidate.address || candidate.place_id || '';
	}

	function renderCandidates( candidates ) {
		const root = document.getElementById( 'axgeo-google-results' );
		if ( ! root ) {
			return;
		}

		root.replaceChildren();
		if ( ! candidates.length ) {
			setStatus( cfg.i18n?.noResults || 'No candidates found.' );
			return;
		}

		candidates.forEach( ( candidate ) => {
			const card = document.createElement( 'div' );
			card.className = 'axgeo-google-candidate';

			const title = document.createElement( 'strong' );
			title.textContent = candidateTitle( candidate );
			card.appendChild( title );

			if ( candidate.address ) {
				const address = document.createElement( 'small' );
				address.textContent = candidate.address;
				card.appendChild( address );
			}

			const meta = document.createElement( 'small' );
			meta.textContent = [
				candidate.google_type || '',
				candidate.latitude && candidate.longitude ? `${ candidate.latitude }, ${ candidate.longitude }` : '',
				candidate.place_id || '',
			].filter( Boolean ).join( ' · ' );
			card.appendChild( meta );

			const bind = document.createElement( 'button' );
			bind.type = 'button';
			bind.className = 'button button-secondary';
			bind.textContent = cfg.i18n?.bind || 'Bind';
			bind.addEventListener( 'click', () => bindCandidate( candidate, bind ) );
			card.appendChild( bind );

			root.appendChild( card );
		} );
	}

	function bindCandidate( candidate, button ) {
		button.disabled = true;
		setStatus( cfg.i18n?.binding || 'Binding selected place…' );

		post( 'axismundi_geodata_google_bind', {
			place_id: candidate.place_id || '',
			address: candidate.address || '',
			latitude: candidate.latitude ?? '',
			longitude: candidate.longitude ?? '',
			google_type: candidate.google_type || '',
			place_type: candidate.place_type || '',
		} ).then( ( result ) => {
			if ( ! result || ! result.success ) {
				throw new Error( result?.data?.message || cfg.i18n?.error || 'Google lookup failed.' );
			}

			setInputValue( 'ax_geo_source', result.data.source || 'google' );
			setInputValue( 'ax_geo_place_id', result.data.place_id || candidate.place_id || '' );
			setInputValue( 'geo_address', result.data.facts?.geo_address || candidate.address || '' );
			setInputValue( 'geo_latitude', result.data.facts?.geo_latitude ?? candidate.latitude ?? '' );
			setInputValue( 'geo_longitude', result.data.facts?.geo_longitude ?? candidate.longitude ?? '' );

			const type = result.data.facts?.ax_geo_place_type || candidate.place_type || '';
			const typeSelect = document.getElementById( 'ax_geo_place_type' );
			if ( typeSelect && type && typeSelect.querySelector( `option[value="${ window.CSS.escape( type ) }"]` ) ) {
				setInputValue( 'ax_geo_place_type', type );
			}

			setStatus( cfg.i18n?.bound || 'Google place bound.' );
		} ).catch( ( error ) => {
			button.disabled = false;
			setStatus( error.message );
		} );
	}

	function init() {
		const button = document.getElementById( 'axgeo-google-lookup' );
		if ( ! button || ! cfg.hasKey ) {
			return;
		}

		button.addEventListener( 'click', () => {
			button.disabled = true;
			setStatus( cfg.i18n?.searching || 'Searching Google Places…' );
			post( 'axismundi_geodata_google_lookup', {} ).then( ( result ) => {
				if ( ! result || ! result.success ) {
					throw new Error( result?.data?.message || cfg.i18n?.error || 'Google lookup failed.' );
				}
				renderCandidates( result.data.candidates || [] );
			} ).catch( ( error ) => {
				setStatus( error.message );
			} ).finally( () => {
				button.disabled = false;
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
