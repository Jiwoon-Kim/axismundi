<?php
/**
 * axismundi/post-quick-view — server render (singleton hub).
 *
 * Renders ONE fixed-id native <dialog> per page. It ships empty; a feed's
 * post-quick-view-trigger blocks target it by aria-controls and the runtime
 * fetches the selected post's fragment (assets REST route) into the body region
 * via data-wp-html. Static chrome only (close button, loading / error regions);
 * the dynamic body is server-rendered HTML injected at open time.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_pqv_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'ax-post-quick-view-host',
		'data-wp-interactive' => 'axismundi/dialog',
	)
);
?>
<div
	<?php echo $axismundi_dialogs_pqv_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo wp_interactivity_data_wp_context( array( 'isOpen' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
	<dialog
		id="ax-post-quick-view"
		class="ax-dialog ax-post-quick-view"
		aria-label="<?php esc_attr_e( 'Post preview', 'axismundi-dialogs' ); ?>"
		data-ax-modal="true"
		data-ax-close-on-backdrop="true"
		data-wp-on--click="actions.onBackdropClick"
		data-wp-on--cancel="actions.onCancel"
		data-wp-on--close="actions.onQuickViewClose"
	>
		<div class="ax-dialog__surface">
			<button
				type="button"
				class="ax-post-quick-view__close"
				aria-label="<?php esc_attr_e( 'Close', 'axismundi-dialogs' ); ?>"
				data-wp-on--click="actions.close"
			>
				<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span>
			</button>

			<p class="ax-post-quick-view__status" aria-live="polite" data-wp-bind--hidden="!state.quickViewBusy">
				<?php esc_html_e( 'Loading…', 'axismundi-dialogs' ); ?>
			</p>

			<p class="ax-post-quick-view__error" role="alert" data-wp-bind--hidden="!state.quickViewError">
				<?php esc_html_e( 'This post could not be loaded.', 'axismundi-dialogs' ); ?>
				<a class="ax-post-quick-view__error-link" href="#" data-wp-bind--href="state.quickViewHref">
					<?php esc_html_e( 'Open the full post', 'axismundi-dialogs' ); ?>
				</a>
			</p>

			<div class="ax-post-quick-view__body"></div>

			<?php if ( is_user_logged_in() ) : ?>
			<form
				class="ax-composer"
				data-ax-comments-url="<?php echo esc_url( rest_url( 'wp/v2/comments' ) ); ?>"
				data-ax-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				data-wp-on--submit="actions.submitComment"
			>
				<div class="ax-composer__reply-chip" data-wp-bind--hidden="!state.replyParent">
					<span class="ax-composer__reply-to">
						<?php esc_html_e( 'Replying to', 'axismundi-dialogs' ); ?>
						<span class="ax-composer__reply-author" data-wp-text="state.replyAuthor"></span>
					</span>
					<button type="button" class="ax-composer__reply-cancel" data-wp-on--click="actions.cancelReply" aria-label="<?php esc_attr_e( 'Cancel reply', 'axismundi-dialogs' ); ?>">
						<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span>
					</button>
				</div>
				<p class="ax-composer__notice" role="status" data-wp-bind--hidden="!state.composerHeld">
					<?php esc_html_e( 'Your comment is awaiting moderation.', 'axismundi-dialogs' ); ?>
				</p>
				<p class="ax-composer__notice ax-composer__notice--error" role="alert" data-wp-bind--hidden="!state.composerError">
					<?php esc_html_e( 'Your comment could not be posted. Please try again.', 'axismundi-dialogs' ); ?>
				</p>
				<div class="ax-composer__row">
					<input
						type="text"
						class="ax-composer__input"
						name="content"
						required
						placeholder="<?php esc_attr_e( 'Add a comment…', 'axismundi-dialogs' ); ?>"
						aria-label="<?php esc_attr_e( 'Add a comment', 'axismundi-dialogs' ); ?>"
						data-wp-bind--disabled="state.composerBusy"
					/>
					<button type="submit" class="ax-composer__send" aria-label="<?php esc_attr_e( 'Send', 'axismundi-dialogs' ); ?>" data-wp-bind--disabled="state.composerBusy">
						<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">send</span>
					</button>
				</div>
			</form>
			<?php else : ?>
			<p class="ax-composer ax-composer--guest">
				<a class="ax-composer__login" href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in to comment', 'axismundi-dialogs' ); ?></a>
				<a class="ax-composer__open" href="#" data-wp-bind--href="state.quickViewPermalink"><?php esc_html_e( 'Open full post', 'axismundi-dialogs' ); ?></a>
			</p>
			<?php endif; ?>
		</div>
	</dialog>
</div>
