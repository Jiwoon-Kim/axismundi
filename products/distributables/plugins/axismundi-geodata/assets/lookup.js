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

	function lookupMode() {
		return document.querySelector( 'input[name="axgeo_lookup_mode"]:checked' )?.value || 'text';
	}

	function updateLookupMode() {
		const mode = lookupMode();
		const queryInput = document.getElementById( 'axgeo-lookup-query' );
		if ( queryInput ) {
			queryInput.disabled = 'map' === mode;
		}
		setStatus( 'map' === mode
			? ( cfg.i18n?.mapMode || 'Click the map to set a point, then choose a lookup provider.' )
			: ( cfg.i18n?.textMode || 'Search by place name or address.' )
		);
	}

	function applyFacts( data, candidate ) {
		setInputValue( 'ax_geo_place_id', data.canonical || data.place_id || '' );
		setInputValue( 'geo_address', data.facts?.geo_address || candidate.address || '' );
		setInputValue( 'geo_latitude', data.facts?.geo_latitude ?? candidate.latitude ?? '' );
		setInputValue( 'geo_longitude', data.facts?.geo_longitude ?? candidate.longitude ?? '' );

		const type = data.facts?.ax_geo_place_type || candidate.place_type || '';
		const select = document.getElementById( 'ax_geo_place_type' );
		if ( select && ! select.value && type && select.querySelector( `option[value="${ window.CSS.escape( type ) }"]` ) ) {
			setInputValue( 'ax_geo_place_type', type );
		}
	}

	// Both the Add New and Edit screens behave the same: a candidate only fills the
	// form inputs. Nothing is written until the term form is submitted (Add New /
	// Update), so a lookup never triggers an unexpected save.
	function useCandidate( provider, candidate ) {
		applyFacts( {
			canonical: `${ provider }:${ candidate.place_id || '' }`,
			facts: {
				geo_address: candidate.address || '',
				geo_latitude: candidate.latitude ?? '',
				geo_longitude: candidate.longitude ?? '',
				ax_geo_place_type: candidate.place_type || '',
			},
		}, candidate );
		setStatus( cfg.i18n?.selected || 'Candidate selected. Save the term to keep it.' );
	}

	// Choosing a candidate (from the list or a map marker): fill the fields, then
	// recentre / zoom the term map on the corrected point and drop the result pins.
	function pickCandidate( provider, candidate ) {
		useCandidate( provider, candidate );
		const map = window.axismundiGeodataTermMap;
		if ( map ) {
			if ( candidate.latitude && candidate.longitude ) {
				map.focus( candidate.latitude, candidate.longitude );
			}
			map.clearCandidates();
		}
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

			if ( candidate.address && candidate.name && candidate.address !== candidate.name ) {
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

			const use = document.createElement( 'button' );
			use.type = 'button';
			use.className = 'button button-secondary';
			use.textContent = cfg.i18n?.use || 'Use this place';
			use.addEventListener( 'click', () => pickCandidate( provider, candidate ) );
			card.appendChild( use );

			root.appendChild( card );
		} );
		setStatus( cfg.i18n?.choose || 'Choose a candidate from the list or the map.' );

		// Mirror the candidates as clickable pins on the term map.
		if ( window.axismundiGeodataTermMap ) {
			window.axismundiGeodataTermMap.showCandidates( candidates, ( c ) => pickCandidate( provider, c ) );
		}
	}

	function init() {
		const unbind = document.getElementById( 'axgeo-unbind-btn' );
		if ( unbind ) {
			unbind.addEventListener( 'click', () => {
				// Clear the place identity in the form only; it is removed from the
				// database when the term is saved (Update), like any other field edit.
				const placeId = document.getElementById( 'ax_geo_place_id' );
				if ( placeId ) {
					placeId.value = '';
					placeId.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
				}
				setStatus( cfg.i18n?.unbound || 'Place id cleared. Save the term to apply.' );
			} );
		}

		// The search box lives inside WordPress's Add New term <form>, so a bare
		// Enter would submit that form (with an empty Name → "A name is required").
		// Intercept it: Enter runs the first provider's lookup, never a form submit.
		const queryInput = document.getElementById( 'axgeo-lookup-query' );
		if ( queryInput ) {
			queryInput.addEventListener( 'keydown', ( event ) => {
				if ( 'Enter' === event.key && 'text' === lookupMode() ) {
					event.preventDefault();
					document.querySelector( '.axgeo-lookup-btn' )?.click();
				}
			} );
		}
		document.querySelectorAll( 'input[name="axgeo_lookup_mode"]' ).forEach( ( radio ) => {
			radio.addEventListener( 'change', updateLookupMode );
		} );

		const buttons = document.querySelectorAll( '.axgeo-lookup-btn' );
		if ( ! buttons.length ) {
			return;
		}

		buttons.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const provider = button.getAttribute( 'data-provider' ) || '';
				const mode = lookupMode();
				const queryInput = document.getElementById( 'axgeo-lookup-query' );
				const query = queryInput ? queryInput.value.trim() : '';
				const latitude = document.getElementById( 'geo_latitude' )?.value || '';
				const longitude = document.getElementById( 'geo_longitude' )?.value || '';
				if ( 'text' === mode && ! query ) {
					setStatus( cfg.i18n?.enterQuery || 'Enter a name or address before searching.' );
					queryInput?.focus();
					return;
				}
				if ( 'map' === mode && ( ! latitude || ! longitude ) ) {
					setStatus( cfg.i18n?.enterPoint || 'Click the map to set a valid point before searching.' );
					return;
				}
				buttons.forEach( ( other ) => { other.disabled = true; } );
				setStatus( cfg.i18n?.searching || 'Searching…' );

				post( 'axismundi_geodata_lookup', provider, {
					mode,
					query,
					taxonomy: cfg.taxonomy || '',
					latitude,
					longitude,
					place_type: document.getElementById( 'ax_geo_place_type' )?.value || '',
				} ).then( ( result ) => {
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
		updateLookupMode();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
