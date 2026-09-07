import { getContext, store } from '@wordpress/interactivity';

store( 'axismundi/dislike-button', {
	state: {
		get isDisabled() {
			const context = getContext();
			return ! context.canDislike || context.isPending;
		},
	},
	actions: {
		*toggleDislike() {
			const context = getContext();
			if ( ! context.canDislike || context.isPending ) {
				return;
			}
			const previousDisliked = context.isDisliked;
			const previousDislikes = context.dislikes;
			context.isPending = true;
			context.isDisabled = true;
			context.error = '';
			context.isDisliked = ! previousDisliked;
			context.dislikes = Math.max( 0, previousDislikes + ( context.isDisliked ? 1 : -1 ) );
			try {
				const response = yield fetch( context.endpoint, {
					method: context.isDisliked ? 'POST' : 'DELETE',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': context.nonce },
					body: JSON.stringify( { object_uri: context.objectUri } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				context.isDisliked = Boolean( result.is_disliked );
				context.dislikes = Number( result.dislike_count ) || 0;
			} catch ( error ) {
				context.isDisliked = previousDisliked;
				context.dislikes = previousDislikes;
				context.error = error instanceof Error && error.message !== 'request_failed' ? error.message : context.errorFallback;
			} finally {
				context.isPending = false;
				context.isDisabled = ! context.canDislike;
			}
		},
	},
} );
