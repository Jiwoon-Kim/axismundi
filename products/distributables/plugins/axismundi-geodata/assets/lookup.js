( function () {
	const cfg = window.axismundiGeodataLookup || {};

	function post( action, provider, payload ) {
		const data = new window.FormData();
		data.append( 'action', action );
		data.append( 'nonce', cfg.nonce || '' );
		data.append( 'term_id', String( cfg.termId || '' ) );
		data.append( 'provider', provider );

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
		const el = document.getElementById( 'axgeo-lookup-status' );
		if ( el ) {
			el.textContent = message;
		}
	}

	function setInputValue( id, value ) {
		const input = document.getElementById( id );
		if ( input && value !== null && value !== undefined && value !== '' ) {
			input.value = String( value );
			input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
			input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		}
	}

	function candidateTitle( candidate ) {
		return candidate.name || candidate.address || candidate.place_id || '';
	}

	function applyFacts( data, candidate ) {
		setInputValue( 'ax_geo_place_id', data.canonical || data.place_id || '' );
		setInputValue( 'geo_address', data.facts?.geo_address || candidate.address || '' );
		setInputValue( 'geo_latitude', data.facts?.geo_latitude ?? candidate.latitude ?? '' );
		setInputValue( 'geo_longitude', data.facts?.geo_longitude ?? candidate.longitude ?? '' );

		const type = data.facts?.ax_geo_place_type || candidate.place_type || '';
		const select = document.getElementById( 'ax_geo_place_type' );
		if ( select && type && select.querySelector( `option[value="${ window.CSS.escape( type ) }"]` ) ) {
			setInputValue( 'ax_geo_place_type', type );
		}
	}

	function bindCandidate( provider, candidate, button ) {
		button.disabled = true;
		setStatus( cfg.i18n?.binding || 'Binding selected place…' );

		post( 'axismundi_geodata_bind', provider, {
			place_id: candidate.place_id || '',
			address: candidate.address || '',
			latitude: candidate.latitude ?? '',
			longitude: candidate.longitude ?? '',
			place_type: candidate.place_type || '',
		} ).then( ( result ) => {
			if ( ! result || ! result.success ) {
				throw new Error( result?.data?.message || cfg.i18n?.error || 'Lookup failed.' );
			}
			applyFacts( result.data, candidate );
			setStatus( cfg.i18n?.bound || 'Place bound.' );
		} ).catch( ( error ) => {
			button.disabled = false;
			setStatus( error.message );
		} );
	}

	function renderCandidates( provider, candidates ) {
		const root = document.getElementById( 'axgeo-lookup-results' );
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
			card.className = 'axgeo-lookup-candidate';

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
				candidate.provider_type || '',
				candidate.latitude && candidate.longitude ? `${ candidate.latitude }, ${ candidate.longitude }` : '',
				candidate.place_id || '',
			].filter( Boolean ).join( ' · ' );
			card.appendChild( meta );

			const bind = document.createElement( 'button' );
			bind.type = 'button';
			bind.className = 'button button-secondary';
			bind.textContent = cfg.i18n?.bind || 'Bind';
			bind.addEventListener( 'click', () => bindCandidate( provider, candidate, bind ) );
			card.appendChild( bind );

			root.appendChild( card );
		} );
	}

	function init() {
		const buttons = document.querySelectorAll( '.axgeo-lookup-btn' );
		if ( ! buttons.length ) {
			return;
		}

		buttons.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const provider = button.getAttribute( 'data-provider' ) || '';
				buttons.forEach( ( other ) => { other.disabled = true; } );
				setStatus( cfg.i18n?.searching || 'Searching…' );

				post( 'axismundi_geodata_lookup', provider, {} ).then( ( result ) => {
					if ( ! result || ! result.success ) {
						throw new Error( result?.data?.message || cfg.i18n?.error || 'Lookup failed.' );
					}
					renderCandidates( provider, result.data.candidates || [] );
				} ).catch( ( error ) => {
					setStatus( error.message );
				} ).finally( () => {
					buttons.forEach( ( other ) => { other.disabled = false; } );
				} );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
