<?php
/**
 * My profile, which is not one row in a list (dev screen).
 *
 * A phone's address book puts it at the top, on its own, and edits it on its own screen. That is not
 * decoration: the card an Actor publishes about itself answers different questions from a card about
 * somebody else. It has an audience, it is what a stranger fetching `/@handle.jscontact` receives,
 * and its name is kept in step with the Actor. A card about a friend has none of that and is
 * entirely its owner's to write.
 *
 * Editing them on one form meant every one of those controls had to be either shown to both or shown
 * to neither. So they are two screens over one store, sharing the field editors and nothing else.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Send somebody back where they were, saying what went wrong if anything did.
 *
 * @param true|WP_Error $result Outcome.
 * @param string        $url    Where to return to.
 * @return void
 */
function axismundi_contacts_redirect_to( $result, string $url ) : void {
	if ( is_wp_error( $result ) ) {
		$url = add_query_arg( 'ax_contacts_error', rawurlencode( $result->get_error_message() ), $url );
	}
	wp_safe_redirect( $url );
	exit;
}

/** @return string The profile screen's URL. */
function axismundi_contacts_profile_url() : string {
	return add_query_arg( 'profile', '1', admin_url( 'users.php?page=axismundi-contacts' ) );
}

/**
 * The band at the top of the list: who this book belongs to, and the card they publish.
 *
 * @param Axismundi_Actor $actor   Owner.
 * @param int             $card_id Profile card, or 0.
 * @return void
 */
function axismundi_contacts_profile_band( Axismundi_Actor $actor, int $card_id ) : void {
	$sharing = axismundi_contacts_profile_sharing( (int) $actor->get_identity_id() );
	$labels  = array(
		'off'      => __( 'Shared with nobody', 'axismundi-contacts' ),
		'contacts' => __( 'Shared with people you have saved', 'axismundi-contacts' ),
		'public'   => __( 'Public', 'axismundi-contacts' ),
	);
	?>
	<h2 class="screen-reader-text"><?php esc_html_e( 'My profile', 'axismundi-contacts' ); ?></h2>
	<div class="ax-contacts-band card" style="max-width:none;display:flex;align-items:center;gap:1rem;padding:1rem;">
		<?php echo get_avatar( (int) $actor->get_local_user_id(), 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?>
		<div style="flex:1;">
			<p style="margin:0;font-size:1.1em;"><strong><?php echo esc_html( $actor->get_display_name() ); ?></strong></p>
			<p style="margin:0;" class="description">
				<?php esc_html_e( 'My profile', 'axismundi-contacts' ); ?>
				&middot;
				<?php echo esc_html( $labels[ $sharing ] ?? $labels['off'] ); ?>
			</p>
		</div>
		<?php if ( $card_id > 0 ) : ?>
			<a href="<?php echo esc_url( axismundi_contacts_profile_url() ); ?>" class="button"><?php esc_html_e( 'Edit profile', 'axismundi-contacts' ); ?></a>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="axismundi_contacts_create_profile">
				<?php wp_nonce_field( 'ax_contacts_create_profile' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Create a profile card', 'axismundi-contacts' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The screen for the card an Actor publishes about itself.
 *
 * @param int             $book_id Address book id.
 * @param Axismundi_Actor $actor   Owner.
 * @return void
 */
function axismundi_contacts_profile_editor( int $book_id, Axismundi_Actor $actor ) : void {
	$actor_id = (int) $actor->get_identity_id();
	$card_id  = axismundi_contacts_profile_card( $actor_id );
	if ( $card_id <= 0 ) {
		echo '<h1>' . esc_html__( 'My profile', 'axismundi-contacts' ) . '</h1>';
		echo '<p>' . esc_html__( 'This Actor publishes no contact card yet.', 'axismundi-contacts' ) . '</p>';
		return;
	}
	$row     = axismundi_contacts_get_card( $card_id );
	$card    = axismundi_contacts_card_document( $card_id );
	$prov    = axismundi_contacts_card_provenance( $card_id );
	$sharing = axismundi_contacts_profile_sharing( $actor_id );
	?>
	<h1><?php esc_html_e( 'My profile', 'axismundi-contacts' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'The card this Actor publishes about itself. Everything on it may be read by whoever the audience below allows.', 'axismundi-contacts' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_contacts_save_card">
		<input type="hidden" name="book_id" value="<?php echo esc_attr( (string) $book_id ); ?>">
		<input type="hidden" name="card_id" value="<?php echo esc_attr( (string) $card_id ); ?>">
		<input type="hidden" name="return" value="profile">
		<input type="hidden" name="revision" value="<?php echo esc_attr( (string) ( $row['revision'] ?? 0 ) ); ?>">
		<?php wp_nonce_field( 'ax_contacts_card_' . $book_id ); ?>

		<h2><?php esc_html_e( 'Name', 'axismundi-contacts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-contacts-name"><?php esc_html_e( 'Full name', 'axismundi-contacts' ); ?></label></th>
				<td>
					<input id="ax-contacts-name" name="primary_name[full]" value="<?php echo esc_attr( (string) ( $card['name']['full'] ?? '' ) ); ?>" class="regular-text">
					<?php
					/*
					 * Open on this screen and folded away on the other. Somebody editing their own card came
					 * to write their name properly; somebody saving a number for a friend did not.
					 */
					axismundi_contacts_name_details( 'primary_name', (array) ( $card['name'] ?? array() ), true );
					?>
				</td>
			</tr>
			<?php axismundi_contacts_localized_name_rows( $card ); ?>
		</table>

		<h2><?php esc_html_e( 'Contact information', 'axismundi-contacts' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			axismundi_contacts_entry_rows( 'emails', __( 'Email', 'axismundi-contacts' ), $card, $prov );
			axismundi_contacts_entry_rows( 'phones', __( 'Phone', 'axismundi-contacts' ), $card, $prov );
			axismundi_contacts_entry_rows( 'addresses', __( 'Address', 'axismundi-contacts' ), $card, $prov );
			?>
		</table>

		<?php axismundi_contacts_publish_fields( (int) $actor->get_identity_id(), $card ); ?>
		<?php submit_button( __( 'Save profile', 'axismundi-contacts' ) ); ?>
	</form>

	<?php axismundi_contacts_binding_rows( $actor_id ); ?>

	<h2><?php esc_html_e( 'Who may read it', 'axismundi-contacts' ); ?></h2>
	<?php
	/*
	 * Its own form, because it is its own decision. Publishing a card is not a side effect of
	 * correcting a telephone number, and a single Save covering both would make it one.
	 */
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_contacts_set_sharing">
		<input type="hidden" name="book_id" value="<?php echo esc_attr( (string) $book_id ); ?>">
		<?php wp_nonce_field( 'ax_contacts_sharing_' . $book_id ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Share this card', 'axismundi-contacts' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sharing_enabled" value="1"<?php checked( axismundi_contacts_profile_sharing_enabled( $actor_id ) ); ?>>
						<?php esc_html_e( 'Share it', 'axismundi-contacts' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Turning this off stops sharing and keeps the audience below, so switching it back on does not ask you to choose again.', 'axismundi-contacts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Shared with', 'axismundi-contacts' ); ?></th>
				<td>
					<?php $audience = axismundi_contacts_profile_audience( $actor_id ); ?>
					<label><input type="radio" name="audience" value="contacts"<?php checked( 'contacts', $audience ); ?>> <?php esc_html_e( 'People I have saved', 'axismundi-contacts' ); ?></label><br>
					<label><input type="radio" name="audience" value="public"<?php checked( 'public', $audience ); ?>> <?php esc_html_e( 'Anyone', 'axismundi-contacts' ); ?></label>
					<p class="description">
						<?php esc_html_e( 'Saved people are decided from this address book, which only this site can answer, so that audience is never served to another server. Only a public card is.', 'axismundi-contacts' ); ?>
					</p>
					<?php if ( 'public' === $sharing ) : ?>
						<p><code><?php echo esc_html( home_url( '/@' . $actor->get_preferred_username() . '.jscontact' ) ); ?></code></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save audience', 'axismundi-contacts' ) ); ?>
	</form>
	<?php
}

/**
 * Choose who may read the profile card.
 *
 * @return void
 */
function axismundi_contacts_handle_set_sharing() : void {
	$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
	check_admin_referer( 'ax_contacts_sharing_' . $book_id );
	$book = axismundi_contacts_authorize_book( $book_id );
	if ( is_wp_error( $book ) ) {
		wp_die( esc_html( $book->get_error_message() ), '', array( 'response' => 403 ) );
	}
	$actor_id = (int) $book['owner_actor_id'];
	$audience = isset( $_POST['audience'] ) ? sanitize_key( wp_unslash( $_POST['audience'] ) ) : 'contacts';
	// The audience is written whether or not sharing is on, which is what makes turning it off safe.
	$saved = axismundi_contacts_set_profile_audience( $actor_id, $audience );
	if ( ! is_wp_error( $saved ) ) {
		$saved = axismundi_contacts_set_profile_sharing_enabled( $actor_id, isset( $_POST['sharing_enabled'] ) );
	}
	axismundi_contacts_redirect_to( $saved, axismundi_contacts_profile_url() );
}
add_action( 'admin_post_axismundi_contacts_set_sharing', 'axismundi_contacts_handle_set_sharing' );

/**
 * Make the profile card for an Actor that has none.
 *
 * @return void
 */
function axismundi_contacts_handle_create_profile() : void {
	check_admin_referer( 'ax_contacts_create_profile' );
	$current = axismundi_contacts_current_book();
	if ( '' !== $current['error'] ) {
		wp_die( esc_html( $current['error'] ), '', array( 'response' => 403 ) );
	}
	$made = axismundi_contacts_create_profile_card( (int) $current['actor']->get_identity_id() );
	axismundi_contacts_redirect_to( is_wp_error( $made ) ? $made : true, axismundi_contacts_profile_url() );
}
add_action( 'admin_post_axismundi_contacts_create_profile', 'axismundi_contacts_handle_create_profile' );
