/* global wp */
( function () {
	const input = document.getElementById( 'axismundi-forum-topic-community-search' );
	const value = document.getElementById( 'axismundi-forum-topic-group' );
	const results = document.getElementById( 'axismundi-forum-topic-community-results' );
	if ( ! input || ! value || ! results || ! window.wp || ! wp.apiFetch || ! wp.url ) {
		return;
	}

	let timer = 0;
	let request = 0;
	const clear = () => {
		results.replaceChildren();
		results.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
	};
	const render = ( items ) => {
		clear();
		items.forEach( ( item ) => {
			const option = document.createElement( 'button' );
			option.type = 'button';
			option.className = 'button-link';
			option.role = 'option';
			const reasons = Array.isArray( item.reasons ) && item.reasons.length ? ` - ${ item.reasons.join( ' · ' ) }` : '';
			option.textContent = `${ item.name } (${ item.handle })${ reasons }`;
			option.addEventListener( 'click', () => {
				value.value = item.value;
				input.value = `${ item.name } (${ item.handle })`;
				clear();
			} );
			const row = document.createElement( 'li' );
			row.appendChild( option );
			results.appendChild( row );
		} );
		if ( items.length ) {
			results.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}
	};

	const load = ( search ) => {
		const id = ++request;
		wp.apiFetch( { path: wp.url.addQueryArgs( '/axismundi/v1/forum/community-search', { search, post_id: results.dataset.postId } ) } )
			.then( ( items ) => { if ( id === request ) { render( items ); } } )
			.catch( () => { if ( id === request ) { clear(); } } );
	};

	input.addEventListener( 'focus', () => {
		if ( '' === input.value.trim() ) {
			load( '' );
		}
	} );
	input.addEventListener( 'input', () => {
		value.value = '';
		window.clearTimeout( timer );
		const search = input.value.trim();
		if ( '' === search ) {
			load( '' );
			return;
		}
		if ( search.length < 2 ) {
			clear();
			return;
		}
		timer = window.setTimeout( () => load( search ), 200 );
	} );
}() );
