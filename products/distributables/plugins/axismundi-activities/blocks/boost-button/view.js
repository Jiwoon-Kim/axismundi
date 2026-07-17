import { getContext, store } from '@wordpress/interactivity';

store( 'axismundi/boost-button', {
	state: {
		get isDisabled() {
			const context = getContext();
			return ! context.canAnnounce || context.isPending;
		},
	},
	actions: {
		*toggleAnnounce() {
			const context = getContext();
			if ( ! context.canAnnounce || context.isPending ) {
				return;
			}
			const previousState = context.isAnnounced;
			const previousCount = context.announces;
			context.isPending = true;
			context.error = '';
			context.isAnnounced = ! previousState;
			context.announces = Math.max( 0, previousCount + ( context.isAnnounced ? 1 : -1 ) );
			try {
				const response = yield fetch( context.endpoint, {
					method: context.isAnnounced ? 'POST' : 'DELETE',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': context.nonce },
					body: JSON.stringify( { object_uri: context.objectUri } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				context.isAnnounced = Boolean( result.is_announced );
				context.announces = Number( result.announce_count ) || 0;
			} catch ( error ) {
				context.isAnnounced = previousState;
				context.announces = previousCount;
				context.error = error instanceof Error && error.message !== 'request_failed' ? error.message : context.errorFallback;
			} finally {
				context.isPending = false;
			}
		},
	},
} );
