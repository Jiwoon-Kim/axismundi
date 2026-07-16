import { getContext, store } from '@wordpress/interactivity';

store( 'axismundi/like-button', {
	state: {
		get isDisabled() {
			const context = getContext();
			return ! context.canLike || context.isPending;
		},
	},
	actions: {
		*toggleLike() {
			const context = getContext();
			if ( ! context.canLike || context.isPending ) {
				return;
			}
			const previousLiked = context.isLiked;
			const previousLikes = context.likes;
			context.isPending = true;
			context.error = '';
			context.isLiked = ! previousLiked;
			context.likes = Math.max( 0, previousLikes + ( context.isLiked ? 1 : -1 ) );
			try {
				const response = yield fetch( context.endpoint, {
					method: context.isLiked ? 'POST' : 'DELETE',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': context.nonce },
					body: JSON.stringify( { object_uri: context.objectUri } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				context.isLiked = Boolean( result.is_liked );
				context.likes = Number( result.like_count ) || 0;
			} catch ( error ) {
				context.isLiked = previousLiked;
				context.likes = previousLikes;
				context.error = error instanceof Error && error.message !== 'request_failed' ? error.message : context.errorFallback;
			} finally {
				context.isPending = false;
			}
		},
	},
} );
