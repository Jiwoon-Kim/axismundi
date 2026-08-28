<?php
/**
 * The address book screen.
 *
 * One book at a time: the one belonging to whichever Actor somebody is currently acting as. There is
 * no book picker here, because choosing which identity you are speaking as is already a decision the
 * Actors switch owns, and offering it twice would let the two disagree.
 *
 * One editor, too. The card describing the owner is edited by the same form as everybody else's,
 * because it is the same kind of thing -- a JSContact Card that happens to be pointed at. A separate
 * "my details" form would drift from the general one and eventually support different fields.
 *
 * Nothing here edits an Actor. A person's public name, summary and avatar belong to the Actor
 * profile screen; this screen edits contact facts that person keeps. Putting both on one screen is
 * how a private phone number ends up published because the save button looked the same.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;


/** Add the address book to the Users menu, beside the Actor profile it belongs to. */
function axismundi_contacts_admin_menu() : void {
	add_users_page(
		__( 'Contacts', 'axismundi-contacts' ),
		__( 'Contacts', 'axismundi-contacts' ),
		'read',
		'axismundi-contacts',
		'axismundi_contacts_render_screen'
	);
}
add_action( 'admin_menu', 'axismundi_contacts_admin_menu' );

/** The row-adding script, on this screen only. */
function axismundi_contacts_admin_assets( string $hook ) : void {
	if ( 'users_page_axismundi-contacts' !== $hook ) {
		return;
	}
	$file = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/card-editor.js';
	wp_enqueue_script(
		'axismundi-contacts-card-editor',
		plugins_url( 'assets/card-editor.js', dirname( __FILE__ ) . '/axismundi-contacts.php' ),
		array(),
		// Versioned by the file itself, so an edited asset is never served from a stale cache.
		(string) ( file_exists( $file ) ? filemtime( $file ) : AXISMUNDI_CONTACTS_VERSION ),
		true
	);
	$css = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/contacts.css';
	wp_enqueue_style(
		'axismundi-contacts-admin',
		plugins_url( 'assets/contacts.css', dirname( __FILE__ ) . '/axismundi-contacts.php' ),
		array(),
		file_exists( $css ) ? (string) filemtime( $css ) : AXISMUNDI_CONTACTS_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_contacts_admin_assets' );

/**
 * The address book of whoever is acting right now, or an honest explanation.
 *
 * @return array{actor:?Axismundi_Actor,book:array<string,mixed>,default_book:array<string,mixed>,all:bool,error:string}
 */
function axismundi_contacts_current_book() : array {
	$none = array( 'actor' => null, 'book' => array(), 'default_book' => array(), 'all' => true, 'error' => '' );
	if ( ! axismundi_contacts_ready() ) {
		$unmet = axismundi_contacts_unmet_dependencies();
		return array_merge(
			$none,
			array(
				'error' => array() === $unmet
					? __( 'Contacts is still setting up its storage.', 'axismundi-contacts' )
					/* translators: %s: comma-separated plugin names. */
					: sprintf( __( 'Contacts needs %s.', 'axismundi-contacts' ), implode( ', ', $unmet ) ),
			)
		);
	}
	$actor = function_exists( 'axismundi_actors_acting_actor' ) ? axismundi_actors_acting_actor() : null;
	if ( ! $actor instanceof Axismundi_Actor ) {
		return array_merge( $none, array( 'error' => __( 'You are not acting as any Actor, so there is no address book to open.', 'axismundi-contacts' ) ) );
	}
	// Asked again here rather than trusted from the switch: losing a manager role closes the book.
	if ( ! axismundi_contacts_can_use_book( (int) $actor->get_identity_id(), get_current_user_id() ) ) {
		return array_merge( $none, array( 'error' => __( 'You cannot open this Actor&#8217;s address book.', 'axismundi-contacts' ) ) );
	}
	$default = axismundi_contacts_book_for_actor( (int) $actor->get_identity_id() );
	if ( is_wp_error( $default ) ) {
		return array_merge( $none, array( 'error' => $default->get_error_message() ) );
	}
	// A missing group means the virtual "All contacts" view, not the default book.
	// The default book remains the filing target for a new Card made from that view.
	$group_id = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	if ( $group_id <= 0 ) {
		return array( 'actor' => $actor, 'book' => $default, 'default_book' => $default, 'all' => true, 'error' => '' );
	}
	$group = axismundi_contacts_get_book( $group_id );
	if ( array() === $group || (int) $group['owner_actor_id'] !== (int) $actor->get_identity_id() || (int) $group['is_default'] === 1 ) {
		return array_merge( $none, array( 'error' => __( 'That contact group is not available.', 'axismundi-contacts' ) ) );
	}
	return array( 'actor' => $actor, 'book' => $group, 'default_book' => $default, 'all' => false, 'error' => '' );
}

/** The screen's own URL, optionally opening one card in one contact group. */
function axismundi_contacts_screen_url( int $card_id = -1, int $group_id = 0 ) : string {
	$url = admin_url( 'users.php?page=axismundi-contacts' );
	$args = array();
	if ( $card_id >= 0 ) {
		$args['item'] = $card_id;
	}
	if ( $group_id > 0 ) {
		$args['group'] = $group_id;
	}
	return array() === $args ? $url : add_query_arg( $args, $url );
}

/**
 * Where a card is changed, which is not where it is read.
 *
 * The same `item` as the record, plus `action=edit`. One Card has one name in the address whatever
 * is being done to it: two names would mean two ways to build a link, two things to check a
 * permission against, and a back button that returned to a different screen than the one somebody
 * left.
 *
 * Editing says so. A screen that looks like a form is a screen where somebody may already have
 * typed into a field they only meant to read, and the action is the difference between opening a
 * record and opening it for changes.
 *
 * @param int $card_id  Card, or 0 for a new one.
 * @param int $group_id Group to file a new card into, and to return to.
 * @return string
 */
function axismundi_contacts_edit_url( int $card_id = 0, int $group_id = 0 ) : string {
	$args = array( 'item' => $card_id, 'action' => 'edit' );
	if ( $group_id > 0 ) {
		$args['group'] = $group_id;
	}
	return add_query_arg( $args, admin_url( 'users.php?page=axismundi-contacts' ) );
}


/**
 * Render the address book: a list, or one card being edited.
 *
 * @return void
 */
function axismundi_contacts_render_screen() : void {
	$current = axismundi_contacts_current_book();
	echo '<div class="wrap">';
	if ( '' !== $current['error'] ) {
		echo '<h1>' . esc_html__( 'Contacts', 'axismundi-contacts' ) . '</h1>';
		echo '<p>' . esc_html( $current['error'] ) . '</p></div>';
		return;
	}
	$actor   = $current['actor'];
	$book    = $current['book'];
	$default = $current['default_book'];
	$all     = $current['all'];
	$book_id = (int) $book['id'];
	$self_id = axismundi_contacts_profile_card( (int) $book['owner_actor_id'] );
	/*
	 * One Card, one name for it, and the action says what is being done. Reading is the common case
	 * and gets the bare id; changing says so, so nobody arrives at a form they meant to arrive at a
	 * record.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to show, not writing.
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to show, not writing.
	$item = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : -1;
	$editing = 'edit' === $action && $item >= 0 ? $item : -1;
	$reading = 'edit' === $action ? -1 : $item;

	/*
	 * The profile is opened as the profile. It is not a contact somebody keeps -- it is the document
	 * this Actor publishes about itself, with a public projection, a forced identity account and a
	 * lifetime tied to the Actor rather than to an address book -- so it has its own address here,
	 * and an old link naming it by card id arrives at the same place rather than at a contact record.
	 *
	 * The editor is the same editor. What differs is which ledger it is opened against, which is what
	 * `mode` tells it.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to show, not writing.
	$profile = isset( $_GET['profile'] ) || ( $self_id > 0 && $self_id === $item );
	if ( $profile ) {
		if ( 'edit' === $action && $self_id > 0 ) {
			axismundi_contacts_card_editor_screen( $self_id, 0 );
		} else {
			axismundi_contacts_profile_editor( $book_id, $actor );
		}
		echo '</div>';
		return;
	}

	if ( $editing > 0 ) {
		axismundi_contacts_card_editor_screen( $editing, $all ? 0 : $book_id );
		echo '</div>';
		return;
	}
	if ( $reading > 0 ) {
		axismundi_contacts_card_detail( $reading, $all ? 0 : $book_id, $self_id, (int) $actor->get_identity_id() );
		echo '</div>';
		return;
	}
	?>
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Contacts', 'axismundi-contacts' ); ?></h1>
	<?php
	/*
	 * A contact is made and then edited, rather than typed into a form and then made. The editor works
	 * on a record with a revision to save against, and a screen that collected a Card first would be a
	 * second way to write one -- with its own rules about what a Card must contain.
	 */
	?>
	<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'axismundi_contacts_create_card', 'book_id' => $book_id, 'group' => $all ? 0 : $book_id ), admin_url( 'admin-post.php' ) ), 'ax_contacts_create_card_' . $book_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add contact', 'axismundi-contacts' ); ?></a>
	<hr class="wp-header-end">
	<div class="ax-contacts-browser">
		<?php axismundi_contacts_groups_sidebar( $actor, $default, $all ? 0 : $book_id ); ?>
		<main class="ax-contacts-browser__content">
			<p class="description">
				<?php
				printf(
					/* translators: %s: Actor display name. */
					esc_html__( 'The contacts kept by %s are private to this Actor and are never published.', 'axismundi-contacts' ),
					'<strong>' . esc_html( $actor->get_display_name() ) . '</strong>'
				);
				?>
			</p>
			<?php axismundi_contacts_profile_band( $actor, $self_id ); ?>
			<?php axismundi_contacts_card_list( $book_id, $self_id, $all ? axismundi_contacts_cards_for_owner( (int) $actor->get_identity_id() ) : axismundi_contacts_cards_in_book( $book_id ), $all ? __( 'All contacts', 'axismundi-contacts' ) : (string) $book['name'], $all ? 0 : $book_id ); ?>
		</main>
	</div>
	<?php
	echo '</div>';
}

/**
 * The private grouping browser for one Contacts account.
 *
 * These are AddressBooks rendered as the groups people expect in a personal
 * address book. They are not JSContact group Cards (mailing/distribution lists)
 * and they are not ActivityPub Group Actors.
 *
 * @param Axismundi_Actor     $actor        Acting Actor who owns the account.
 * @param array<string,mixed> $default_book Default AddressBook, kept as the filing target.
 * @param int                 $selected_id  Selected non-default group, or 0 for all contacts.
 * @return void
 */
function axismundi_contacts_groups_sidebar( Axismundi_Actor $actor, array $default_book, int $selected_id ) : void {
	$books = array_filter(
		axismundi_contacts_books_for_actor( (int) $actor->get_identity_id() ),
		static fn( array $book ) : bool => 1 !== (int) $book['is_default']
	);
	?>
	<aside class="ax-contacts-browser__sidebar" aria-label="<?php esc_attr_e( 'Contact groups', 'axismundi-contacts' ); ?>">
		<nav class="ax-contacts-groups">
			<a class="ax-contacts-groups__item<?php echo 0 === $selected_id ? ' is-current' : ''; ?>" href="<?php echo esc_url( axismundi_contacts_screen_url() ); ?>">
				<span><?php esc_html_e( 'All contacts', 'axismundi-contacts' ); ?></span>
				<span class="count"><?php echo esc_html( (string) axismundi_contacts_card_count_for_owner( (int) $actor->get_identity_id() ) ); ?></span>
			</a>
			<h2><?php esc_html_e( 'Groups', 'axismundi-contacts' ); ?></h2>
			<?php foreach ( $books as $group ) : ?>
				<?php $group_id = (int) $group['id']; ?>
				<a class="ax-contacts-groups__item<?php echo $group_id === $selected_id ? ' is-current' : ''; ?>" href="<?php echo esc_url( axismundi_contacts_screen_url( -1, $group_id ) ); ?>">
					<span><?php echo esc_html( (string) $group['name'] ); ?></span>
					<span class="count"><?php echo esc_html( (string) axismundi_contacts_card_count_in_book( $group_id ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<form class="ax-contacts-groups__create" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_contacts_create_group">
			<?php wp_nonce_field( 'ax_contacts_create_group_' . (int) $default_book['id'] ); ?>
			<label class="screen-reader-text" for="ax-contacts-group-name"><?php esc_html_e( 'New group name', 'axismundi-contacts' ); ?></label>
			<input id="ax-contacts-group-name" name="group_name" type="text" placeholder="<?php esc_attr_e( 'New group', 'axismundi-contacts' ); ?>" required>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Add group', 'axismundi-contacts' ); ?></button>
		</form>
	</aside>
	<?php
}

/**
 * The cards in this book.
 *
 * @param int                            $book_id Address book id.
 * @param int                            $self_id Card marked as the owner's.
 * @param array<int,array<string,mixed>> $cards   Cards in the current view.
 * @param string                          $title   Current view title.
 * @param int                             $group_id Group query value, or 0 for all contacts.
 * @return void
 */
function axismundi_contacts_card_list( int $book_id, int $self_id, array $cards, string $title, int $group_id ) : void {
	/*
	 * The Actor's own profile is not a contact and is not listed as one. It is read and written from
	 * My profile, above -- listing it here would offer every action a contact has, and the ones it
	 * must not have are exactly the ones a list is for: filing it, and deleting it.
	 */
	$cards = array_values(
		array_filter(
			$cards,
			static function ( array $card ) use ( $self_id ) : bool {
				return $self_id <= 0 || (int) ( $card['id'] ?? 0 ) !== $self_id;
			}
		)
	);
	echo '<h2>' . esc_html( $title ) . '</h2>';
	if ( array() === $cards ) {
		echo '<p>' . esc_html__( 'Nothing saved yet.', 'axismundi-contacts' ) . '</p>';
		return;
	}
	?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'axismundi-contacts' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Linked Actor', 'axismundi-contacts' ); ?></th>
				<th scope="col" style="width:12em"><?php esc_html_e( 'Mine', 'axismundi-contacts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cards as $card ) : ?>
				<?php $card_id = (int) $card['id']; ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( axismundi_contacts_screen_url( $card_id, $group_id ) ); ?>">
							<?php echo esc_html( '' !== (string) $card['display_name'] ? (string) $card['display_name'] : __( 'Untitled card', 'axismundi-contacts' ) ); ?>
						</a>
					</td>
					<td><?php echo '' !== (string) ( $card['linked_actor_uri'] ?? '' ) ? '<code>' . esc_html( (string) $card['linked_actor_uri'] ) . '</code>' : '&#8212;'; ?></td>
					<td>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}



/**
 * Whether the person submitting may write to this book, asked without trusting the form.
 *
 * @param int $book_id Address book id.
 * @return array<string,mixed>|WP_Error The book.
 */
function axismundi_contacts_authorize_book( int $book_id ) {
	$book = axismundi_contacts_get_book( $book_id );
	if ( array() === $book ) {
		return new WP_Error( 'ax_contacts_book_missing', __( 'That address book does not exist.', 'axismundi-contacts' ) );
	}
	if ( ! axismundi_contacts_can_use_book( (int) $book['owner_actor_id'], get_current_user_id() ) ) {
		return new WP_Error( 'ax_contacts_book_denied', __( 'You cannot use this address book.', 'axismundi-contacts' ) );
	}
	return $book;
}



/**
 * Make a contact, then open it.
 *
 * An empty Card rather than a form: `@type` and a kind, which is the least a JSContact document can
 * say and still be one. Everything else -- whether there is a name, whether it is written out or
 * given in parts -- is answered in the editor, on a record that exists and has a revision to save
 * against.
 *
 * Nothing is guessed about what kind of thing this is either. `individual` is what an address book
 * is mostly full of, and it is changed in the editor like any other value.
 *
 * @return void
 */
function axismundi_contacts_handle_create_card() : void {
	$book_id = isset( $_GET['book_id'] ) ? absint( $_GET['book_id'] ) : 0;
	check_admin_referer( 'ax_contacts_create_card_' . $book_id );
	$book = axismundi_contacts_authorize_book( $book_id );
	if ( is_wp_error( $book ) ) {
		wp_die( esc_html( $book->get_error_message() ), '', array( 'response' => 403 ) );
	}
	$group = isset( $_GET['group'] ) ? absint( $_GET['group'] ) : 0;
	$made  = axismundi_contacts_save_card(
		$book_id,
		array( '@type' => 'Card', 'version' => AXISMUNDI_CONTACTS_JSCONTACT_VERSION, 'kind' => 'individual' )
	);
	if ( is_wp_error( $made ) ) {
		axismundi_contacts_redirect_result( $made );
		return;
	}
	wp_safe_redirect( axismundi_contacts_edit_url( (int) $made, $group ) );
	exit;
}
add_action( 'admin_post_axismundi_contacts_create_card', 'axismundi_contacts_handle_create_card' );

/**
 * Create one contact group for the acting Actor's Contacts account.
 *
 * A group is a named AddressBook in the personal UI. It does not create an
 * ActivityPub Group Actor or a JSContact `kind: group` distribution-list Card.
 *
 * @return void
 */
function axismundi_contacts_handle_create_group() : void {
	$current = axismundi_contacts_current_book();
	if ( '' !== $current['error'] || ! $current['actor'] instanceof Axismundi_Actor ) {
		wp_die( esc_html( '' !== $current['error'] ? $current['error'] : __( 'You cannot create a contact group.', 'axismundi-contacts' ) ), '', array( 'response' => 403 ) );
	}
	$default_book = $current['default_book'];
	check_admin_referer( 'ax_contacts_create_group_' . (int) $default_book['id'] );
	$name   = isset( $_POST['group_name'] ) ? sanitize_text_field( wp_unslash( $_POST['group_name'] ) ) : '';
	$created = axismundi_contacts_create_book( (int) $current['actor']->get_identity_id(), $name );
	if ( is_wp_error( $created ) ) {
		axismundi_contacts_redirect_result( $created );
	}
	wp_safe_redirect( axismundi_contacts_screen_url( -1, (int) $created ) );
	exit;
}
add_action( 'admin_post_axismundi_contacts_create_group', 'axismundi_contacts_handle_create_group' );

/**
 * Remove one card from this book.
 *
 * @return void
 */
function axismundi_contacts_handle_delete_card() : void {
	$card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
	check_admin_referer( 'ax_contacts_delete_' . $card_id );
	$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
	$book    = axismundi_contacts_authorize_book( $book_id );
	if ( is_wp_error( $book ) ) {
		wp_die( esc_html( $book->get_error_message() ), '', array( 'response' => 403 ) );
	}
	/*
	 * The one the Actor publishes about itself is refused before anything else is asked, because the
	 * answer does not depend on where it is filed. That document is a singleton with a public address,
	 * an identity account this site guarantees, and readers elsewhere holding its uid -- and deleting
	 * it here would take the Actor's binding with it, so the Actor would quietly stop answering at its
	 * own address. Emptying a profile is a different act from deleting a contact and wants a command
	 * that says so.
	 *
	 * Asked first rather than after the book check, which today refuses it for the incidental reason
	 * that a profile is filed in no book. That is true and not the reason, and it would stop being
	 * true the moment somebody filed it.
	 */
	if ( $card_id > 0 && $card_id === axismundi_contacts_profile_card( (int) $book['owner_actor_id'] ) ) {
		axismundi_contacts_redirect_result(
			new WP_Error(
				'ax_contacts_profile_delete',
				__( 'This is the card this Actor publishes about itself, so it cannot be deleted here.', 'axismundi-contacts' )
			)
		);
	}
	$card = axismundi_contacts_get_card( $card_id );
	if ( array() === $card || ! in_array( $book_id, axismundi_contacts_card_books( $card_id ), true ) ) {
		// Deleting by id has to be checked against the book, or a stray id deletes somebody else's card.
		axismundi_contacts_redirect_result( new WP_Error( 'ax_contacts_card_book', __( 'That card is not in this address book.', 'axismundi-contacts' ) ) );
	}
	axismundi_contacts_delete_card( $card_id );
	axismundi_contacts_redirect_result( true );
}
add_action( 'admin_post_axismundi_contacts_delete_card', 'axismundi_contacts_handle_delete_card' );

/**
 * Back to the address book, saying what happened.
 *
 * @param mixed $result  Outcome of the write.
 * @param int   $card_id Card to reopen, or -1 for the list.
 * @return void
 */
function axismundi_contacts_redirect_result( $result, int $card_id = -1 ) : void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the handler verified the nonce.
	$profile = isset( $_POST['return'] ) && 'profile' === sanitize_key( wp_unslash( $_POST['return'] ) );
	$group_id = isset( $_POST['return_group'] ) ? absint( $_POST['return_group'] ) : 0;
	$url      = $profile ? axismundi_contacts_profile_url() : axismundi_contacts_screen_url( $card_id, $group_id );
	if ( is_wp_error( $result ) ) {
		$url = add_query_arg( 'ax_contacts_error', rawurlencode( $result->get_error_message() ), $url );
	}
	wp_safe_redirect( $url );
	exit;
}

/** Say what went wrong, where the person is looking. */
function axismundi_contacts_admin_notice() : void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a message this plugin put in the URL.
	$message = isset( $_GET['ax_contacts_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ax_contacts_error'] ) ) : '';
	if ( '' === $message || ! $screen instanceof WP_Screen || 'users_page_axismundi-contacts' !== $screen->id ) {
		return;
	}
	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
}
add_action( 'admin_notices', 'axismundi_contacts_admin_notice' );
