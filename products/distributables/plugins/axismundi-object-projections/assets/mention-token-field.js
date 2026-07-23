/* global window */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useState = wp.element.useState;

	function indexActors( current, actors ) {
		var next = Object.assign( {}, current );
		( actors || [] ).forEach( function ( actor ) {
			if ( actor && actor.uri && actor.handle ) {
				next[ actor.uri ] = actor;
				next[ actor.handle ] = actor;
			}
		} );
		return next;
	}

	function MentionTokenField( props ) {
		var value = Array.isArray( props.value ) ? props.value : [];
		var state = useState( {} );
		var actors = state[0];
		var setActors = state[1];
		var suggestionsState = useState( [] );
		var suggestions = suggestionsState[0];
		var setSuggestions = suggestionsState[1];

		useEffect( function () {
			if ( ! value.length ) {
				return undefined;
			}
			var active = true;
			wp.apiFetch( { path: wp.url.addQueryArgs( '/axismundi/v1/actors/mention-resolve', { uris: value } ) } ).then( function ( resolved ) {
				if ( active ) {
					setActors( function ( current ) { return indexActors( current, resolved ); } );
				}
			} );
			return function () { active = false; };
		}, [ value.join( '|' ) ] );

		function search( input ) {
			wp.apiFetch( { path: wp.url.addQueryArgs( '/axismundi/v1/actors/mention-search', { search: input || '' } ) } ).then( function ( found ) {
				setActors( function ( current ) { return indexActors( current, found ); } );
				setSuggestions( ( found || [] ).map( function ( actor ) { return actor.handle; } ) );
			} ).catch( function () { setSuggestions( [] ); } );
		}

		var tokenActors = Object.assign( {}, actors );
		var display = value.map( function ( uri, index ) {
			if ( actors[ uri ] ) {
				return actors[ uri ].handle;
			}
			// Legacy unresolved URIs remain editable without exposing their raw
			// authority value in the handle-first authoring surface.
			var placeholder = '@unresolved-' + ( index + 1 );
			tokenActors[ placeholder ] = { uri: uri, handle: placeholder };
			return placeholder;
		} );
		return el( wp.components.FormTokenField, {
			label: props.label,
			help: props.help,
			value: display,
			suggestions: suggestions,
			onInputChange: search,
			onChange: function ( tokens ) {
				var uris = tokens.map( function ( token ) { return tokenActors[ token ] ? tokenActors[ token ].uri : ''; } ).filter( Boolean );
				props.onChange( Array.from( new Set( uris ) ) );
			},
			__experimentalValidateInput: function ( token ) { return !! tokenActors[ token ]; },
			__experimentalShowHowTo: false
		} );
	}

	window.axismundiMentionTokens = { MentionTokenField: MentionTokenField };
}( window.wp ) );
