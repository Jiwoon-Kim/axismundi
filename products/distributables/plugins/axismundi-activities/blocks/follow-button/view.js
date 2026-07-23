import { getContext, getElement, store } from '@wordpress/interactivity';

const remoteFollowDialog = ( context ) =>
	context.remoteModalId ? document.getElementById( context.remoteModalId ) : null;

const syncDialogScrollLock = () =>
	document.documentElement.classList.toggle(
		'ax-dialog-scroll-locked',
		Boolean( document.querySelector( 'dialog.ax-dialog[open]:not([data-ax-modal="false"])' ) )
	);

store( 'axismundi/follow-button', {
	state: {
		get isFollowing() {
			const context = getContext();
			return context.relationState === 'accepted';
		},
		get isMutual() {
			const context = getContext();
			return context.relationState === 'accepted' && context.followsYou;
		},
		get isDisabled() {
			const context = getContext();
			return ! context.canFollow || context.isPending;
		},
		get label() {
			const context = getContext();
			if ( context.isLegacy && ( context.relationState === 'accepted' || context.relationState === 'legacy_pending' ) ) {
				return context.labels.reFollow;
			}
			if ( context.relationState === 'pending' ) {
				return context.labels.requested;
			}
			if ( context.relationState === 'accepted' && context.followsYou ) {
				return context.labels.mutual;
			}
			if ( context.relationState === 'accepted' ) {
				return context.labels.following;
			}
			return context.followsYou ? context.labels.followBack : context.labels.follow;
		},
		get actionLabel() {
			const context = getContext();
			if ( context.isLegacy && ( context.relationState === 'accepted' || context.relationState === 'legacy_pending' ) ) {
				return context.labels.reFollow;
			}
			if ( context.relationState === 'pending' ) {
				return context.labels.cancel;
			}
			if ( context.relationState === 'accepted' ) {
				return context.labels.unfollow;
			}
			return context.followsYou ? context.labels.followBack : context.labels.follow;
		},
	},
	actions: {
		openRemoteFollowDialog( event ) {
			event.preventDefault();
			const context = getContext();
			const dialog = remoteFollowDialog( context );
			if ( ! dialog || dialog.open ) {
				return;
			}
			dialog.showModal();
			syncDialogScrollLock();
			window.setTimeout( () => dialog.querySelector( 'input:not([readonly])' )?.focus(), 0 );
		},

		closeRemoteFollowDialog() {
			const dialog = getElement().ref.closest( 'dialog' );
			if ( dialog?.open ) {
				dialog.close();
			}
			syncDialogScrollLock();
		},

		onRemoteFollowDialogCancel( event ) {
			event.preventDefault();
			const dialog = getElement().ref;
			if ( dialog?.open ) {
				dialog.close();
			}
			syncDialogScrollLock();
		},

		onRemoteFollowDialogBackdrop( event ) {
			const dialog = getElement().ref;
			if ( event.target === dialog && dialog.open ) {
				dialog.close();
			}
			syncDialogScrollLock();
		},

		updateRemoteFollowProfile( event ) {
			const context = getContext();
			context.remoteProfile = event.target.value;
			context.remoteError = '';
		},

		copyRemoteFollowTarget() {
			const context = getContext();
			const target = getElement().ref.closest( '.axismundi-remote-follow__target' )?.querySelector( 'input' )?.value || '';
			if ( ! target || ! navigator.clipboard ) {
				return;
			}
			navigator.clipboard.writeText( target ).then( () => {
				context.copyLabel = context.copiedLabel;
				window.setTimeout( () => { context.copyLabel = context.copyDefaultLabel; }, 1000 );
			} );
		},

		*submitRemoteFollow( event ) {
			event.preventDefault();
			const context = getContext();
			const profile = context.remoteProfile.trim();
			if ( ! profile ) {
				context.remoteError = context.emptyProfileError;
				return;
			}
			const parts = profile.replace( /^@/, '' ).split( '@' );
			if ( parts.length !== 2 || ! parts[ 0 ] || ! parts[ 1 ] ) {
				context.remoteError = context.invalidProfileError;
				return;
			}
			context.remoteBusy = true;
			context.remoteError = '';
			try {
				const response = yield fetch( context.remoteEndpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { target_uri: context.targetUri, resource: profile } ),
				} );
				const result = yield response.json();
				if ( ! response.ok || ! result.url ) {
					throw new Error( result.message || 'remote_follow_failed' );
				}
				window.open( result.url, '_blank', 'noopener,noreferrer' );
				const dialog = remoteFollowDialog( context );
				if ( dialog?.open ) {
					dialog.close();
				}
				syncDialogScrollLock();
			} catch ( error ) {
				context.remoteError = error instanceof Error && error.message !== 'remote_follow_failed' ? error.message : context.remoteFollowError;
			} finally {
				context.remoteBusy = false;
			}
		},

		*toggleFollow() {
			const context = getContext();
			if ( ! context.canFollow || context.isPending ) {
				return;
			}
			const previousState = context.relationState;
			const previousLegacy = context.isLegacy;
			context.isPending = true;
			context.error = '';
			const remove = previousState === 'pending' || previousState === 'accepted';
			context.relationState = remove ? 'none' : 'pending';
			context.isLegacy = false;
			try {
				const response = yield fetch( context.endpoint, {
					method: remove ? 'DELETE' : 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': context.nonce },
					body: JSON.stringify( { target_uri: context.targetUri } ),
				} );
				const result = yield response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || 'request_failed' );
				}
				context.relationState = result.state || 'none';
				context.followsYou = Boolean( result.follows_you );
				context.isLegacy = Boolean( result.legacy );
			} catch ( error ) {
				context.relationState = previousState;
				context.isLegacy = previousLegacy;
				context.error = error instanceof Error && error.message !== 'request_failed' ? error.message : context.errorFallback;
			} finally {
				context.isPending = false;
			}
		},
	},
} );
