<?php
/**
 * The screen where somebody is looked up, and the one place that saves what it found.
 *
 * Two requests, and the split is deliberate. The first reads a public document and shows it; the
 * second writes it down, and only because a person pressed the button that says so. Between them
 * nothing is stored -- not the card, not the address, not the fact that anybody looked -- so a
 * lookup that goes nowhere goes nowhere.
 *
 * What is shown is a reading of the card, not a form. Somebody deciding whether to keep a contact is
 * answering "is this the right person", and a screen full of editable fields answers it while
 * inviting an edit to a document that has not been accepted yet. Corrections happen afterwards, in
 * the editor, against a saved record with a revision to save against.
 *
 * The result reaches the save request in the form itself, signed. `axismundi_contacts_lookup_seal()`
 * says why.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** Where somebody is looked up. */
function axismundi_contacts_lookup_url() : string {
	return add_query_arg( 'action', 'lookup', admin_url( 'users.php?page=axismundi-contacts' ) );
}

/**
 * Ask for somebody, and show whoever answers.
 *
 * The lookup runs on this request rather than through `admin-post.php`, because the result is the
 * screen. A handler elsewhere would have to hand a whole card back through a redirect, and the only
 * places to put it are a URL too short to hold it or a server-side store this screen exists in order
 * not to write to.
 *
 * @param Axismundi_Actor $actor Acting Actor, whose book a save would go into.
 * @return void
 */
function axismundi_contacts_lookup_screen( Axismundi_Actor $actor ) : void {
	$input  = '';
	$found  = null;
	$failed = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line, before anything is read.
	if ( isset( $_POST['ax_contacts_lookup'] ) ) {
		check_admin_referer( 'ax_contacts_lookup' );
		$input  = sanitize_text_field( wp_unslash( $_POST['ax_contacts_lookup'] ) );
		$result = axismundi_contacts_lookup( $input );
		if ( is_wp_error( $result ) ) {
			$failed = $result->get_error_message();
		} else {
			$found = $result;
		}
	}
	?>
	<p><a href="<?php echo esc_url( axismundi_contacts_screen_url() ); ?>">&larr; <?php esc_html_e( 'Back to contacts', 'axismundi-contacts' ); ?></a></p>
	<h1><?php esc_html_e( 'Look somebody up', 'axismundi-contacts' ); ?></h1>

	<form class="ax-contacts-lookup" method="post" action="<?php echo esc_url( axismundi_contacts_lookup_url() ); ?>">
		<?php wp_nonce_field( 'ax_contacts_lookup' ); ?>
		<label class="ax-contacts-lookup__label" for="ax-contacts-lookup-input"><?php esc_html_e( 'Address, profile page, or contact card', 'axismundi-contacts' ); ?></label>
		<input
			id="ax-contacts-lookup-input"
			class="regular-text"
			name="ax_contacts_lookup"
			type="text"
			value="<?php echo esc_attr( $input ); ?>"
			placeholder="alice@example.com"
			spellcheck="false"
			autocapitalize="none"
			required>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Look up', 'axismundi-contacts' ); ?></button>
		<p class="description">
			<?php esc_html_e( 'Nothing is saved until you ask for it. Looking somebody up reads what they publish and shows it here.', 'axismundi-contacts' ); ?>
		</p>
	</form>

	<?php
	if ( '' !== $failed ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $failed ) );
		return;
	}
	if ( null === $found ) {
		return;
	}
	axismundi_contacts_lookup_result( $found, $actor );
}

/**
 * What was read, and the one button that keeps it.
 *
 * @param array{card:array<string,mixed>,card_url:string,profile_url:string,actor_uri:string} $found What the lookup read.
 * @param Axismundi_Actor                                                                     $actor Acting Actor.
 * @return void
 */
function axismundi_contacts_lookup_result( array $found, Axismundi_Actor $actor ) : void {
	$card = $found['card'];
	$name = axismundi_contacts_name_text( (array) ( $card['name'] ?? array() ) );
	$uid  = trim( (string) ( $card['uid'] ?? '' ) );
	$held = '' !== $uid ? axismundi_contacts_find_by_uid( (int) $actor->get_identity_id(), $uid ) : 0;
	?>
	<div class="ax-contacts-lookup__result">
		<h2><?php echo esc_html( '' !== $name ? $name : __( '(no name)', 'axismundi-contacts' ) ); ?></h2>

		<?php if ( $held > 0 ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'This person is already in your contacts. Saving again would not add a second copy or change what you have.', 'axismundi-contacts' ); ?>
					<a href="<?php echo esc_url( axismundi_contacts_screen_url( $held ) ); ?>"><?php esc_html_e( 'Open the contact you have', 'axismundi-contacts' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<div class="ax-contacts-detail">
			<section class="ax-contacts-detail__facts">
				<dl class="ax-contacts-detail__values">
				<?php foreach ( axismundi_contacts_detail_sections() as $property => $label ) : ?>
					<?php
					$entries = (array) ( $card[ $property ] ?? array() );
					if ( array() === $entries ) {
						continue;
					}
					?>
					<dt><?php echo esc_html( $label ); ?></dt>
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						if ( is_array( $entry ) ) {
							axismundi_contacts_detail_row( $entry, $property, false );
						}
						?>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</dl>
			</section>
		</div>

		<?php axismundi_contacts_lookup_reached( $found ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_contacts_save_lookup">
			<?php wp_nonce_field( 'ax_contacts_save_lookup' ); ?>
			<input type="hidden" name="sealed" value="<?php echo esc_attr( axismundi_contacts_lookup_seal( $found, (int) $actor->get_identity_id() ) ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save contact', 'axismundi-contacts' ); ?></button>
			<p class="description">
				<?php esc_html_e( 'Saves this card as it is published, into your contacts. It is not kept up to date afterwards.', 'axismundi-contacts' ); ?>
			</p>
		</form>
	</div>
	<?php
}

/**
 * How this card was reached, shown because it is what gets written down.
 *
 * Saving records these addresses on the contact, and somebody agreeing to that should be able to see
 * what they are agreeing to. They also answer the question the card itself cannot: a document says
 * who it describes, not where it was found or whether the site that served it is the one asked.
 *
 * @param array{card_url:string,profile_url:string,actor_uri:string} $found What the lookup read.
 * @return void
 */
function axismundi_contacts_lookup_reached( array $found ) : void {
	$reached = array_filter(
		array(
			__( 'Contact card', 'axismundi-contacts' ) => (string) $found['card_url'],
			__( 'Profile page', 'axismundi-contacts' ) => (string) $found['profile_url'],
			__( 'Actor', 'axismundi-contacts' )        => (string) $found['actor_uri'],
		),
		static fn( string $value ) : bool => '' !== $value
	);
	if ( array() === $reached ) {
		return;
	}
	?>
	<h3><?php esc_html_e( 'Reached through', 'axismundi-contacts' ); ?></h3>
	<dl class="ax-contacts-detail__values">
		<?php foreach ( $reached as $label => $value ) : ?>
			<dt><?php echo esc_html( $label ); ?></dt>
			<dd class="ax-contacts-detail__value">
				<code class="ax-contacts-detail__text"><?php echo esc_html( $value ); ?></code>
			</dd>
		<?php endforeach; ?>
	</dl>
	<?php
}

/**
 * Keep the card that was shown, and open it.
 *
 * The book is resolved from who is acting now rather than from the form, and the seal is checked
 * against that same Actor. Somebody who switched identities between reading a card and pressing Save
 * is refused instead of quietly filing a stranger into a book they were not looking at.
 *
 * @return void
 */
function axismundi_contacts_handle_save_lookup() : void {
	check_admin_referer( 'ax_contacts_save_lookup' );
	$current = axismundi_contacts_current_book();
	if ( '' !== $current['error'] || ! $current['actor'] instanceof Axismundi_Actor ) {
		wp_die(
			esc_html( '' !== $current['error'] ? $current['error'] : __( 'You cannot save a contact.', 'axismundi-contacts' ) ),
			'',
			array( 'response' => 403 )
		);
	}
	$owner = (int) $current['actor']->get_identity_id();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the signature is what validates this, and sanitising it would break the signature.
	$sealed = isset( $_POST['sealed'] ) ? (string) wp_unslash( $_POST['sealed'] ) : '';
	$found  = axismundi_contacts_lookup_unseal( $sealed, $owner );
	if ( is_wp_error( $found ) ) {
		axismundi_contacts_redirect_result( $found );
		return;
	}
	$saved = axismundi_contacts_save_looked_up( $owner, $found );
	if ( is_wp_error( $saved ) ) {
		axismundi_contacts_redirect_result( $saved );
		return;
	}
	wp_safe_redirect( axismundi_contacts_screen_url( (int) $saved['card_id'] ) );
	exit;
}
add_action( 'admin_post_axismundi_contacts_save_lookup', 'axismundi_contacts_handle_save_lookup' );
