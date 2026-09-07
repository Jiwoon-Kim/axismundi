import { getContext, store } from '@wordpress/interactivity';

/**
 * The control sends the direction the reader asked for and lets the server settle exclusivity.
 * Sending "add a downvote" from a page that no longer knows the reader's current vote would let
 * a stale tab contradict the server; a direction is always unambiguous.
 *
 * Everything the markup binds is a context value rather than a `state` getter, because directives
 * are also processed in PHP: a getter that exists only here evaluates to nothing during server
 * rendering, which would blank the score and icons and drop `disabled` before hydration. So the
 * derived values are written into context on every change instead.
 */
const syncDerived = ( context ) => {
	context.score = context.up - context.down;
	context.isUpvoted = context.viewer === 'up';
	context.isDownvoted = context.viewer === 'down';
	context.upIcon = context.isUpvoted ? 'thumb_up' : 'thumb_up_off_alt';
	context.downIcon = context.isDownvoted ? 'thumb_down' : 'thumb_down_off_alt';
	context.formattedScore = context.score.toLocaleString();
	context.isDisabled = ! context.canVote || context.isPending;
	context.tally = context.tallyTemplate
		.replace( '%1$s', context.up.toLocaleString() )
		.replace( '%2$s', context.down.toLocaleString() );
};

const { actions } = store( 'axismundi/vote-buttons', {
	actions: {
		*vote( direction ) {
			const context = getContext();
			if ( ! context.canVote || context.isPending ) {
				return;
			}
			const previous = {
				viewer: context.viewer,
				up: context.up,
				down: context.down,
			};
			// Pressing the side already held is a withdrawal, matching the server rule.
			const next = previous.viewer === direction ? 'none' : direction;
			context.isPending = true;
			context.error = '';
			context.up = previous.up + ( next === 'up' ? 1 : 0 ) - ( previous.viewer === 'up' ? 1 : 0 );
			context.down = previous.down + ( next === 'down' ? 1 : 0 ) - ( previous.viewer === 'down' ? 1 : 0 );
			context.viewer = next;
			syncDerived( context );
			try {
				const response = yield fetch( context.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': context.nonce },
					body: JSON.stringify( { object_uri: context.objectUri, direction: next } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				context.up = Number( result.up ) || 0;
				context.down = Number( result.down ) || 0;
				context.viewer = result.viewer || 'none';
			} catch ( error ) {
				context.viewer = previous.viewer;
				context.up = previous.up;
				context.down = previous.down;
				context.error =
					error instanceof Error && error.message !== 'request_failed'
						? error.message
						: context.errorFallback;
			} finally {
				context.isPending = false;
				syncDerived( context );
			}
		},
		*voteUp() {
			yield* actions.vote( 'up' );
		},
		*voteDown() {
			yield* actions.vote( 'down' );
		},
	},
} );
