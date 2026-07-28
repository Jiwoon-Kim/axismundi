( function ( wp ) {
	'use strict';

	const el = wp.element.createElement;

	function optionLabel( actor ) {
		return el(
			wp.element.Fragment,
			null,
			actor.avatar
				? el( 'img', { className: 'editor-autocompleters__user-avatar', src: actor.avatar, alt: '' } )
				: el( 'span', { className: 'editor-autocompleters__no-avatar' } ),
			el( 'span', { className: 'editor-autocompleters__user-name' }, actor.name ),
			el( 'span', { className: 'editor-autocompleters__user-slug' }, actor.handle )
		);
	}

	const actorCompleter = {
		name: 'axismundi-actors',
		className: 'editor-autocompleters__user',
		triggerPrefix: '@',
		useItems: function ( search ) {
			const [ options, setOptions ] = wp.element.useState( [] );
			wp.element.useEffect( function () {
				let current = true;
				wp.apiFetch( {
					path: wp.url.addQueryArgs( '/axismundi/v1/actors/mention-search', { search: search || '' } ),
				} ).then( function ( actors ) {
					if ( current ) {
						setOptions( actors.map( function ( actor ) {
							return { key: actor.uri, value: actor, label: optionLabel( actor ) };
						} ) );
					}
				} ).catch( function () {
					if ( current ) {
						setOptions( [] );
					}
				} );
				return function () {
					current = false;
				};
			}, [ search ] );
			return [ options ];
		},
		getOptionCompletion: function ( actor ) {
			/*
			 * Gutenberg replaces the active `@query` but leaves the caret immediately
			 * after this value. Without a separator, choosing a second person starts
			 * `@first@second`, which is one invalid plaintext token and therefore emits
			 * neither Mention declaration. Keep the authored representation plain text,
			 * but make consecutive choices compose as separate tokens.
			 */
			return actor.token + ' ';
		},
	};

	wp.hooks.addFilter(
		'editor.Autocomplete.completers',
		'axismundi/actor-mentions',
		function ( completers ) {
			return ( completers || [] ).filter( function ( completer ) {
				return 'users' !== completer.name && 'axismundi-actors' !== completer.name;
			} ).concat( actorCompleter );
		}
	);
}( window.wp ) );
