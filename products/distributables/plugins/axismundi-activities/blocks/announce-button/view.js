import { getContext, getElement, store } from '@wordpress/interactivity';

function dialogFor( context ) {
	return context.dialogId ? document.getElementById( context.dialogId ) : null;
}

store( 'axismundi/announce-button', {
	state: {
		get isDisabled() {
			const context = getContext();
			return ! context.canAnnounce || context.isPending;
		},
		get announceLabel() {
			return getContext().isAnnounced ? 'Undo repost' : 'Repost';
		},
	},
	actions: {
		openMenu() {
			const context = getContext();
			const dialog = dialogFor( context );
			if ( dialog && ! dialog.open ) {
				dialog.showModal();
			}
		},
		closeMenu() {
			const dialog = getElement().ref.closest( 'dialog' );
			if ( dialog && dialog.open ) {
				dialog.close();
			}
		},
		onMenuCancel( event ) {
			event.preventDefault();
			const dialog = getElement().ref;
			if ( dialog && dialog.open ) {
				dialog.close();
			}
		},
		onMenuBackdrop( event ) {
			const dialog = getElement().ref;
			if ( event.target === dialog && dialog && dialog.open ) {
				dialog.close();
			}
		},
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
				const dialog = dialogFor( context );
				if ( dialog && dialog.open ) {
					dialog.close();
				}
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
