( function () {
	'use strict';
	const account = window.axismundiPublishAccount;
	wp.apiFetch.use( function ( options, next ) {
		const path = options.path || options.url || '';
		const method = ( options.method || 'GET' ).toUpperCase();
		if ( /\/wp\/v2\/posts(?:\/\d+)?(?:\?|$)/.test( path ) && [ 'POST', 'PUT', 'PATCH' ].includes( method ) ) {
			options = { ...options, headers: { ...options.headers, 'X-Axismundi-Account': account } };
		}
		return next( options ).then( function ( result ) {
			if ( result && result.axismundi_publication_error ) {
				wp.data.dispatch( 'core/notices' ).createWarningNotice(
					wp.i18n.__( 'The WordPress source was saved, but federation failed: ', 'axismundi-activities' ) + result.axismundi_publication_error,
					{ id: 'axismundi-publication-error', isDismissible: true }
				);
			}
			return result;
		} );
	} );
} )();
