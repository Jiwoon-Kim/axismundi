import { getContext, store } from '@wordpress/interactivity';

store( 'axismundi/like-button', {
	state: {
		get isDisabled() {
			const context = getContext();
			return ! context.canLike || context.isPending;
		},
	},
	/*
	 * `isDisabled` is also kept on the context, not only derived here.
	 *
	 * The Interactivity API evaluates directives on the server as well, and a `state` getter
	 * defined in this module does not exist there — so binding an attribute to one makes the
	 * server resolve it to nothing and strip the attribute it was asked to control. A control
	 * that ships disabled would arrive enabled. Context is serialized into the markup and
	 * therefore means the same thing in both places.
	 */
	actions: {
		*toggleLike() {
			const context = getContext();
			if ( ! context.canLike || context.isPending ) {
				return;
			}
			const previousLiked = context.isLiked;
			const previousLikes = context.likes;
			context.isPending = true;
			context.isDisabled = true;
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
				context.isDisabled = ! context.canLike;
			}
		},
	},
} );
