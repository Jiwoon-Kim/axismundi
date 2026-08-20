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

/**
 * The repeating fields the editor offers, and where each entry's value lives.
 *
 * A contact has as many numbers as it has. Fixing the form at two of each would make the model's
 * multiple values into a limit nobody chose.
 */
const AXISMUNDI_CONTACTS_EDITABLE_FIELDS = array(
	'emails'    => array( 'value_key' => 'address', 'type' => 'email' ),
	'phones'    => array( 'value_key' => 'number', 'type' => 'tel' ),
	'addresses' => array( 'value_key' => 'full', 'type' => 'text' ),
);

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
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing what to show, not writing.
	if ( isset( $_GET['profile'] ) ) {
		/*
		 * Its own screen. The card an Actor publishes about itself has an audience, is what a stranger
		 * fetching it receives, and keeps its name in step with the Actor; a card about a friend has
		 * none of that. One form for both meant every such control was shown to both or to neither.
		 */
		axismundi_contacts_profile_editor( $book_id, $actor );
		echo '</div>';
		return;
	}
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

	if ( $editing >= 0 ) {
		axismundi_contacts_card_editor( $book_id, $editing, $self_id, $all, $all ? 0 : $book_id );
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
	<a href="<?php echo esc_url( axismundi_contacts_edit_url( 0, $all ? 0 : $book_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add contact', 'axismundi-contacts' ); ?></a>
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
						<?php if ( $self_id === $card_id ) : ?>
							<strong><?php esc_html_e( 'My card', 'axismundi-contacts' ); ?></strong>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="axismundi_contacts_set_profile_card">
								<input type="hidden" name="book_id" value="<?php echo esc_attr( (string) $book_id ); ?>">
								<input type="hidden" name="card_id" value="<?php echo esc_attr( (string) $card_id ); ?>">
								<input type="hidden" name="return_group" value="<?php echo esc_attr( (string) $group_id ); ?>">
								<?php wp_nonce_field( 'ax_contacts_self_pointer_' . $book_id ); ?>
								<button type="submit" class="button button-small"><?php esc_html_e( 'This is me', 'axismundi-contacts' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Create or edit one card.
 *
 * The same form for the owner's card and everybody else's, because they are the same kind of thing.
 * What differs is only a line of explanation and whether deleting it is offered.
 *
 * @param int $book_id Address book id.
 * @param int $card_id Card id, or 0 for a new one.
 * @param int  $self_id  Card marked as the owner's.
 * @param bool $all      Whether this is the virtual all-contacts view.
 * @param int  $group_id Group query value, or 0 for all contacts.
 * @return void
 */
function axismundi_contacts_card_editor( int $book_id, int $card_id, int $self_id, bool $all = false, int $group_id = 0 ) : void {
	$row  = $card_id > 0 ? axismundi_contacts_get_card( $card_id ) : array();
	$book = axismundi_contacts_get_book( $book_id );
	if ( $card_id > 0 && ( array() === $row || array() === $book || (int) $row['owner_actor_id'] !== (int) $book['owner_actor_id'] || ( ! $all && ! in_array( $book_id, axismundi_contacts_card_books( $card_id ), true ) ) ) ) {
		// A card id from another book is not this book's business, whatever the URL says.
		echo '<h1>' . esc_html__( 'Contacts', 'axismundi-contacts' ) . '</h1>';
		echo '<p>' . esc_html__( 'That card is not in this address book.', 'axismundi-contacts' ) . '</p>';
		return;
	}
	$card    = $card_id > 0 ? axismundi_contacts_card_document( $card_id ) : array();
	$prov    = $card_id > 0 ? axismundi_contacts_card_provenance( $card_id ) : array();
	$is_self = $card_id > 0 && $card_id === $self_id;
	?>
	<h1><?php echo esc_html( $card_id > 0 ? __( 'Edit contact', 'axismundi-contacts' ) : __( 'Add contact', 'axismundi-contacts' ) ); ?></h1>
	<?php if ( $is_self ) : ?>
		<p class="description"><?php esc_html_e( 'This is the card that describes you. Your public name and profile are edited on the Actor profile screen; nothing here is published.', 'axismundi-contacts' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_contacts_save_card">
		<input type="hidden" name="book_id" value="<?php echo esc_attr( (string) $book_id ); ?>">
		<input type="hidden" name="return_group" value="<?php echo esc_attr( (string) $group_id ); ?>">
		<input type="hidden" name="card_id" value="<?php echo esc_attr( (string) $card_id ); ?>">
		<?php /* The revision this form was drawn from, so a save written against a stale view is refused. */ ?>
		<input type="hidden" name="revision" value="<?php echo esc_attr( (string) ( $row['revision'] ?? 0 ) ); ?>">
		<?php wp_nonce_field( 'ax_contacts_card_' . $book_id ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-contacts-name"><?php esc_html_e( 'Name', 'axismundi-contacts' ); ?></label></th>
				<td>
					<input id="ax-contacts-name" name="primary_name[full]" value="<?php echo esc_attr( (string) ( $card['name']['full'] ?? '' ) ); ?>" class="regular-text">
					<?php axismundi_contacts_name_details( 'primary_name', (array) ( $card['name'] ?? array() ) ); ?>
				</td>
			</tr>
			<?php
			/*
			 * No row for the Card's own language here. The primary name above is that language's name,
			 * and offering a localization for it as well would put one fact in two editable places.
			 */
			axismundi_contacts_localized_name_rows( $card );
			?>
			<?php
			axismundi_contacts_entry_rows( 'emails', __( 'Email', 'axismundi-contacts' ), $card, $prov );
			axismundi_contacts_entry_rows( 'phones', __( 'Phone', 'axismundi-contacts' ), $card, $prov );
			axismundi_contacts_entry_rows( 'addresses', __( 'Address', 'axismundi-contacts' ), $card, $prov );
			?>
			<tr>
				<th scope="row"><label for="ax-contacts-note"><?php esc_html_e( 'Note', 'axismundi-contacts' ); ?></label></th>
				<td>
					<textarea id="ax-contacts-note" name="note" rows="4" class="large-text"><?php echo esc_textarea( (string) ( $card['notes']['note']['note'] ?? '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Kept in this address book and never published.', 'axismundi-contacts' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( $card_id > 0 ? __( 'Save contact', 'axismundi-contacts' ) : __( 'Add contact', 'axismundi-contacts' ) ); ?>
	</form>
	<p><a href="<?php echo esc_url( axismundi_contacts_screen_url( -1, $group_id ) ); ?>">&larr; <?php esc_html_e( 'Back to contacts', 'axismundi-contacts' ); ?></a></p>
	<?php if ( $card_id > 0 ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this contact?', 'axismundi-contacts' ) ); ?>' );">
			<input type="hidden" name="action" value="axismundi_contacts_delete_card">
			<input type="hidden" name="book_id" value="<?php echo esc_attr( (string) $book_id ); ?>">
			<input type="hidden" name="return_group" value="<?php echo esc_attr( (string) $group_id ); ?>">
			<input type="hidden" name="card_id" value="<?php echo esc_attr( (string) $card_id ); ?>">
			<?php wp_nonce_field( 'ax_contacts_delete_' . $card_id ); ?>
			<?php submit_button( __( 'Delete contact', 'axismundi-contacts' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>
	<?php
}

/**
 * One repeating field, with a row per entry and a way to add another.
 *
 * The key each entry is addressed by travels in a hidden field rather than being regenerated on
 * save, because provenance is recorded against it: renumbering entries on every edit would detach
 * last year's record of where a value came from from the value it was written for.
 *
 * One blank row is always rendered, so the form still grows without the script.
 *
 * @param string                            $field      JSContact field name.
 * @param string                            $label      What to call it on screen.
 * @param array<string,mixed>               $card       Card document.
 * @param array<string,array<string,mixed>> $provenance Provenance by pointer.
 * @return void
 */
function axismundi_contacts_entry_rows( string $field, string $label, array $card, array $provenance ) : void {
	$spec      = AXISMUNDI_CONTACTS_EDITABLE_FIELDS[ $field ] ?? array( 'value_key' => 'value', 'type' => 'text' );
	$value_key = (string) $spec['value_key'];
	$presets   = axismundi_contacts_presets_for( $field );
	$default   = (string) ( array_key_first( $presets ) ?? 'custom' );
	$rows      = array();
	foreach ( (array) ( $card[ $field ] ?? array() ) as $entry_id => $entry ) {
		$entry   = is_array( $entry ) ? $entry : array();
		$rows[] = array(
			'key'    => (string) $entry_id,
			'value'  => (string) ( $entry[ $value_key ] ?? '' ),
			// Which label this reads as, asked of the stored values rather than kept beside them.
			'preset' => axismundi_contacts_entry_preset( $field, $entry ),
			'label'  => (string) ( $entry['label'] ?? '' ),
		);
	}
	// Always one empty row, so a card can gain another value with or without JavaScript.
	$rows[] = array( 'key' => '', 'value' => '', 'preset' => $default, 'label' => '' );
	$list   = 'ax-contacts-' . $field;
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<div id="<?php echo esc_attr( $list ); ?>">
				<?php foreach ( $rows as $entry ) : ?>
					<?php
					$pointer = '' !== $entry['key'] ? $field . '/' . $entry['key'] : '';
					$source  = '' !== $pointer ? (string) ( $provenance[ $pointer ]['source'] ?? '' ) : '';
					?>
					<p data-ax-contacts-row>
						<input type="hidden" name="<?php echo esc_attr( $field ); ?>_key[]" value="<?php echo esc_attr( $entry['key'] ); ?>">
						<input
							type="<?php echo esc_attr( (string) $spec['type'] ); ?>"
							name="<?php echo esc_attr( $field ); ?>_value[]"
							value="<?php echo esc_attr( $entry['value'] ); ?>"
							class="regular-text"
							aria-label="<?php echo esc_attr( $label ); ?>">
						<?php
						/*
						 * What this row is for. A phone book shows the label before the number, because
						 * which number to ring matters more than the digits -- and what is stored is the
						 * standard pair behind the word, so an export means the same thing elsewhere.
						 */
						?>
						<select name="<?php echo esc_attr( $field ); ?>_preset[]" aria-label="<?php esc_attr_e( 'Label', 'axismundi-contacts' ); ?>">
							<?php foreach ( $presets as $ax_key => $ax_preset ) : ?>
								<option value="<?php echo esc_attr( (string) $ax_key ); ?>" <?php selected( $entry['preset'], (string) $ax_key ); ?>><?php echo esc_html( (string) $ax_preset['label'] ); ?></option>
							<?php endforeach; ?>
							<option value="custom" <?php selected( $entry['preset'], 'custom' ); ?>><?php esc_html_e( 'Custom', 'axismundi-contacts' ); ?></option>
						</select>
						<input
							name="<?php echo esc_attr( $field ); ?>_label[]"
							value="<?php echo esc_attr( $entry['label'] ); ?>"
							class="small-text"
							placeholder="<?php esc_attr_e( 'Custom label', 'axismundi-contacts' ); ?>"
							aria-label="<?php esc_attr_e( 'Custom label', 'axismundi-contacts' ); ?>">
						<?php if ( '' !== $source && AXISMUNDI_CONTACTS_SOURCE_LOCAL !== $source ) : ?>
							<span class="description" data-ax-contacts-source>
								<?php
								printf(
									/* translators: %s: name of the source this value was imported from. */
									esc_html__( 'from %s — editing it makes it yours', 'axismundi-contacts' ),
									esc_html( $source )
								);
								?>
							</span>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button button-small" data-ax-contacts-add="<?php echo esc_attr( $list ); ?>">
					<?php
					printf(
						/* translators: %s: field label, such as Email. */
						esc_html__( '+ %s', 'axismundi-contacts' ),
						esc_html( $label )
					);
					?>
				</button>
				<span class="description"><?php esc_html_e( 'Clearing a row removes that entry.', 'axismundi-contacts' ); ?></span>
			</p>
		</td>
	</tr>
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
 * Save one card, new or existing.
 *
 * The document is rebuilt from the form, stored, and reindexed by one call, so the index cannot
 * describe a Card that was never written.
 *
 * @return void
 */
function axismundi_contacts_handle_save_card() : void {
	$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
	check_admin_referer( 'ax_contacts_card_' . $book_id );
	$book = axismundi_contacts_authorize_book( $book_id );
	if ( is_wp_error( $book ) ) {
		wp_die( esc_html( $book->get_error_message() ), '', array( 'response' => 403 ) );
	}
	$card_id  = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
	$revision = isset( $_POST['revision'] ) ? absint( $_POST['revision'] ) : 0;
	$before   = $card_id > 0 ? axismundi_contacts_card_document( $card_id ) : array();

	// Everything the form did not show is carried through untouched, including fields it cannot edit.
	$card          = $before;
	$card['@type'] = 'Card';
	$name = axismundi_contacts_name_from_request( 'primary_name', (array) ( $card['name'] ?? array() ) );
	if ( array() !== $name ) {
		$card['name'] = $name;
	} else {
		unset( $card['name'] );
	}
	$card = axismundi_contacts_localized_names_from_request( $card );
	/*
	 * One note, written as JSContact writes them: a keyed entry rather than a bare string, so a Card
	 * that arrives with several keeps them all and this form edits the one it owns.
	 */
	$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
	if ( '' !== trim( $note ) ) {
		$card['notes']['note'] = array_merge(
			(array) ( $card['notes']['note'] ?? array() ),
			array( '@type' => 'Note', 'note' => $note )
		);
	} else {
		unset( $card['notes']['note'] );
		if ( array() === (array) ( $card['notes'] ?? array() ) ) {
			unset( $card['notes'] );
		}
	}
	foreach ( AXISMUNDI_CONTACTS_EDITABLE_FIELDS as $field => $spec ) {
		$card[ $field ] = axismundi_contacts_entries_from_request( $field, (string) $spec['value_key'], $card[ $field ] ?? array() );
		if ( array() === $card[ $field ] ) {
			unset( $card[ $field ] );
		}
	}

	$saved = axismundi_contacts_save_card( $book_id, $card, $card_id, $card_id > 0 ? $revision : null );
	if ( ! is_wp_error( $saved ) ) {
		axismundi_contacts_record_local_edits( (int) $saved, $before, $card );
		/*
	 * What may be published, from the screen that showed the values it names. Only the profile screen
	 * draws these, and only that screen sends them: a form that did not is not saying "publish
	 * nothing", it is saying nothing, and clearing the selection because a different form was
	 * submitted would unpublish somebody's card as a side effect of editing a friend's phone number.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce was verified above.
	if ( isset( $_POST['return'] ) && 'profile' === sanitize_key( wp_unslash( $_POST['return'] ) ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce was verified above.
		$sent = isset( $_POST['published'] ) && is_array( $_POST['published'] ) ? wp_unslash( $_POST['published'] ) : array();
		axismundi_contacts_set_published_pointers( (int) $book['owner_actor_id'], array_map( 'sanitize_text_field', $sent ) );
	}

	axismundi_contacts_redirect_result( $saved, (int) $saved );
	}
	axismundi_contacts_redirect_result( $saved, $card_id );
}
add_action( 'admin_post_axismundi_contacts_save_card', 'axismundi_contacts_handle_save_card' );

/**
 * Rebuild one repeating field from the submitted rows.
 *
 * A row whose value was cleared is dropped, which is how an entry is removed. A row that arrived
 * with no key is new and gets one derived from the field, so the pointer provenance is recorded
 * against stays put for the life of that entry.
 *
 * @param string              $field     JSContact field name.
 * @param string              $value_key Key holding the value inside an entry.
 * @param array<string,mixed> $existing  Entries already on the Card.
 * @return array<string,mixed>
 */
function axismundi_contacts_entries_from_request( string $field, string $value_key, array $existing ) : array {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is sanitized below.
	$keys = isset( $_POST[ $field . '_key' ] ) ? (array) wp_unslash( $_POST[ $field . '_key' ] ) : array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is sanitized below.
	$values = isset( $_POST[ $field . '_value' ] ) ? (array) wp_unslash( $_POST[ $field . '_value' ] ) : array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is sanitized below.
	$presets = isset( $_POST[ $field . '_preset' ] ) ? array_values( (array) wp_unslash( $_POST[ $field . '_preset' ] ) ) : array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is sanitized below.
	$labels = isset( $_POST[ $field . '_label' ] ) ? array_values( (array) wp_unslash( $_POST[ $field . '_label' ] ) ) : array();
	$out    = array();
	$next   = 1;
	foreach ( array_values( $values ) as $index => $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === trim( $value ) ) {
			continue;
		}
		$key = sanitize_key( (string) ( $keys[ $index ] ?? '' ) );
		while ( '' === $key || isset( $out[ $key ] ) ) {
			$key = $field . '-' . $next;
			++$next;
		}
		// Whatever else the entry carried is kept: a preference, a type this form never showed.
		$entry               = is_array( $existing[ $key ] ?? null ) ? $existing[ $key ] : array();
		$entry[ $value_key ] = $value;
		/*
		 * The label is written as the standard pair behind it, so the word on screen stays a rendering
		 * of what is stored. A custom label is the exception the vocabulary makes for itself: somebody
		 * typing their own word is saying the enumeration did not have what they meant.
		 */
		$entry       = axismundi_contacts_apply_preset(
			$field,
			$entry,
			sanitize_text_field( (string) ( $presets[ $index ] ?? '' ) ),
			sanitize_text_field( (string) ( $labels[ $index ] ?? '' ) )
		);
		$out[ $key ] = $entry;
	}
	return $out;
}

/**
 * Record that a person wrote these values themselves.
 *
 * Only where something changed. An imported value somebody edits by hand becomes theirs, which is
 * what stops the next sync from putting the old one back; an imported value they left alone keeps
 * its source, so that sync may still update it.
 *
 * @param int                 $card_id Card id.
 * @param array<string,mixed> $before  Document before the save.
 * @param array<string,mixed> $after   Document after it.
 * @return void
 */
function axismundi_contacts_record_local_edits( int $card_id, array $before, array $after ) : void {
	foreach ( AXISMUNDI_CONTACTS_INDEXED_FIELDS as $field => $value_key ) {
		foreach ( (array) ( $after[ $field ] ?? array() ) as $entry_id => $entry ) {
			$was = (string) ( $before[ $field ][ $entry_id ][ $value_key ] ?? '' );
			$now = (string) ( $entry[ $value_key ] ?? '' );
			if ( $was === $now ) {
				continue;
			}
			axismundi_contacts_set_provenance( $card_id, $field . '/' . $entry_id, AXISMUNDI_CONTACTS_SOURCE_LOCAL );
		}
	}
	$before_name = (string) ( $before['name']['full'] ?? '' );
	$after_name  = (string) ( $after['name']['full'] ?? '' );
	if ( $before_name !== $after_name && '' !== $after_name ) {
		axismundi_contacts_set_provenance( $card_id, 'name', AXISMUNDI_CONTACTS_SOURCE_LOCAL );
	}
}

/**
 * Say which card describes the Actor whose book this is.
 *
 * Authorised against the book because that is what the screen is showing, then applied to the Actor,
 * because a profile belongs to the Actor rather than to whichever book it was chosen from.
 *
 * @return void
 */
function axismundi_contacts_handle_set_profile_card() : void {
	$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
	check_admin_referer( 'ax_contacts_self_pointer_' . $book_id );
	$book = axismundi_contacts_authorize_book( $book_id );
	if ( is_wp_error( $book ) ) {
		wp_die( esc_html( $book->get_error_message() ), '', array( 'response' => 403 ) );
	}
	$card_id = isset( $_POST['card_id'] ) ? absint( $_POST['card_id'] ) : 0;
	axismundi_contacts_redirect_result( axismundi_contacts_set_profile_card( (int) $book['owner_actor_id'], $card_id ) );
}
add_action( 'admin_post_axismundi_contacts_set_profile_card', 'axismundi_contacts_handle_set_profile_card' );

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
