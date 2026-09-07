<?php
/** Private label commands. AddressBooks are filing metadata, never Card documents. */
defined( 'ABSPATH' ) || exit;

/** Same-owner non-default AddressBooks, using the existing private access policy. */
function axismundi_contacts_labels( int $owner ) : array {
	if ( ! axismundi_contacts_can_use_book( $owner, get_current_user_id() ) ) {
		return array();
	}
	return array_values( array_filter( axismundi_contacts_books_for_actor( $owner ), static fn( array $book ) : bool => 1 !== (int) $book['is_default'] ) );
}

/** Reject malformed input rather than coercing it into a different object's id. */
function axismundi_contacts_label_id( $value ) : int {
	return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/D', (string) $value ) ? (int) $value : 0;
}

/**
 * Execute one bounded metadata command. All members are checked before any write.
 * The nonce is also checked here so direct dispatch and tests exercise the same gate.
 *
 * @return int|WP_Error Created label id, or number of changed rows.
 */
function axismundi_contacts_label_command( array $input ) {
	global $wpdb;
	$owner = axismundi_contacts_label_id( $input['owner'] ?? 0 );
	$actor = function_exists( 'axismundi_actors_acting_actor' ) ? axismundi_actors_acting_actor() : null;
	$nonce = is_string( $input['_wpnonce'] ?? null ) ? $input['_wpnonce'] : '';
	if ( ! $actor instanceof Axismundi_Actor || (int) $actor->get_identity_id() !== $owner
		|| ! axismundi_contacts_can_use_book( $owner, get_current_user_id() )
		|| ! wp_verify_nonce( $nonce, 'ax_contacts_labels_' . $owner ) ) {
		return new WP_Error( 'ax_contacts_labels_denied', __( 'This contact action is no longer available. Return to Contacts and try again.', 'axismundi-contacts' ) );
	}
	$operation = $input['operation'] ?? '';
	if ( ! in_array( $operation, array( 'create', 'rename', 'delete', 'add', 'remove' ), true ) ) {
		return new WP_Error( 'ax_contacts_labels_action', __( 'Choose a label action.', 'axismundi-contacts' ) );
	}
	$name = is_string( $input['label_name'] ?? null ) ? trim( sanitize_text_field( $input['label_name'] ) ) : '';
	if ( in_array( $operation, array( 'create', 'rename' ), true ) && ( '' === $name || mb_strlen( $name ) > 191 ) ) {
		return new WP_Error( 'ax_contacts_labels_name', __( 'Use a label name between 1 and 191 characters.', 'axismundi-contacts' ) );
	}
	if ( 'create' === $operation ) {
		return axismundi_contacts_create_book( $owner, $name );
	}
	$label = axismundi_contacts_label_id( $input['label_id'] ?? 0 );
	$ids = $input['cards'] ?? array();
	if ( in_array( $operation, array( 'add', 'remove' ), true ) ) {
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 50 ) {
			return new WP_Error( 'ax_contacts_labels_selection', __( 'Select between 1 and 50 contacts on this page.', 'axismundi-contacts' ) );
		}
		$ids = array_values( array_unique( array_map( 'axismundi_contacts_label_id', $ids ) ) );
		if ( in_array( 0, $ids, true ) ) {
			return new WP_Error( 'ax_contacts_labels_selection', __( 'That selection is not available.', 'axismundi-contacts' ) );
		}
		sort( $ids, SORT_NUMERIC );
	}
	$books = axismundi_contacts_books_table();
	$members = axismundi_contacts_memberships_table();
	$cards = axismundi_contacts_cards_table();
	// All SQL below uses plugin table names and prepared values. Locks serialize label commands.
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		return new WP_Error( 'ax_contacts_labels_storage', __( 'The labels could not be saved. Try again.', 'axismundi-contacts' ) );
	}
	try {
		$book = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$books} WHERE id = %d FOR UPDATE", $label ), ARRAY_A );
		if ( ! $book || (int) $book['owner_actor_id'] !== $owner || 1 === (int) $book['is_default'] ) {
			throw new RuntimeException( __( 'That label is not available.', 'axismundi-contacts' ) );
		}
		if ( in_array( $operation, array( 'rename', 'delete' ), true ) ) {
			$revision = $input['label_revision'] ?? null;
			if ( ! is_scalar( $revision ) || ! preg_match( '/^[0-9]+$/D', (string) $revision ) || (int) $revision !== (int) $book['revision'] ) {
				throw new RuntimeException( __( 'This label changed. Reload Contacts before trying again.', 'axismundi-contacts' ) );
			}
		}
		$changed = 0;
		if ( 'rename' === $operation ) {
			$changed = $name === $book['name'] ? 0 : $wpdb->update( $books, array( 'name' => $name ), array( 'id' => $label ), array( '%s' ), array( '%d' ) );
		} elseif ( 'delete' === $operation ) {
			if ( ! is_scalar( $input['confirm_remove'] ?? null ) || '1' !== (string) $input['confirm_remove'] ) {
				throw new RuntimeException( __( 'Confirm removal of the label. Its contacts will be kept.', 'axismundi-contacts' ) );
			}
			if ( false === $wpdb->delete( $members, array( 'address_book_id' => $label ), array( '%d' ) ) ) {
				throw new RuntimeException( __( 'The label could not be removed. Try again.', 'axismundi-contacts' ) );
			}
			$changed = $wpdb->delete( $books, array( 'id' => $label ), array( '%d' ) );
		} else {
			foreach ( $ids as $id ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, owner_actor_id FROM {$cards} WHERE id = %d FOR UPDATE", $id ), ARRAY_A );
				if ( ! $row || (int) $row['owner_actor_id'] !== $owner || $id === axismundi_contacts_profile_card( $owner ) ) {
					throw new RuntimeException( __( 'That selection is not available.', 'axismundi-contacts' ) );
				}
			}
			foreach ( $ids as $id ) {
				// The book lock serializes the existence check; duplicates are honest no-ops.
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT card_id FROM {$members} WHERE address_book_id = %d AND card_id = %d", $label, $id ) );
				if ( $wpdb->last_error ) {
					throw new RuntimeException( __( 'The labels could not be saved. Try again.', 'axismundi-contacts' ) );
				}
				if ( 'add' === $operation ) {
					$result = $exists ? 0 : $wpdb->insert( $members, array( 'address_book_id' => $label, 'card_id' => $id, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s' ) );
				} else {
					$result = $wpdb->delete( $members, array( 'address_book_id' => $label, 'card_id' => $id ), array( '%d', '%d' ) );
				}
				if ( false === $result ) {
					throw new RuntimeException( __( 'The labels could not be saved. Try again.', 'axismundi-contacts' ) );
				}
				$changed += $result;
			}
		}
		if ( false === $changed || ( $changed > 0 && 'delete' !== $operation && false === $wpdb->query( $wpdb->prepare( "UPDATE {$books} SET revision = revision + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $label ) ) ) ) {
			throw new RuntimeException( __( 'The labels could not be saved. Try again.', 'axismundi-contacts' ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( __( 'The labels could not be saved. Try again.', 'axismundi-contacts' ) );
		}
		return (int) $changed;
	} catch ( RuntimeException $error ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_contacts_labels_failed', $error->getMessage() );
	}
}

/** Allowlisted local navigation, including after the selected label disappears. */
function axismundi_contacts_label_return_url( array $input ) : string {
	$owner = axismundi_contacts_label_id( $input['owner'] ?? 0 );
	$group = axismundi_contacts_label_id( $input['return_group'] ?? 0 );
	$book = axismundi_contacts_get_book( $group );
	if ( (int) ( $book['owner_actor_id'] ?? 0 ) !== $owner || 1 === (int) ( $book['is_default'] ?? 0 ) ) {
		$group = 0;
	}
	$item = axismundi_contacts_label_id( $input['return_item'] ?? 0 );
	$row = axismundi_contacts_get_card( $item );
	if ( (int) ( $row['owner_actor_id'] ?? 0 ) !== $owner ) {
		$item = 0;
	}
	return add_query_arg( array(
		'search' => is_string( $input['return_search'] ?? null ) ? sanitize_text_field( $input['return_search'] ) : '',
		'contact_page' => is_scalar( $input['return_page'] ?? null ) ? max( 1, (int) $input['return_page'] ) : 1,
	), axismundi_contacts_screen_url( $item > 0 ? $item : -1, $group ) );
}

/** Native POST/redirect/get; only Contacts metadata commands reach this handler. */
function axismundi_contacts_handle_labels() : void {
	$input = wp_unslash( $_POST );
	$result = axismundi_contacts_label_command( $input );
	$url = axismundi_contacts_label_return_url( $input );
	if ( is_wp_error( $result ) ) {
		$url = add_query_arg( 'ax_contacts_error', $result->get_error_message(), $url );
	} else {
		if ( 'create' === ( $input['operation'] ?? '' ) ) {
			$url = axismundi_contacts_screen_url( -1, $result );
		}
		$url = add_query_arg( 'ax_contacts_labels_saved', $result > 0 ? 'changed' : 'unchanged', $url );
	}
	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_axismundi_contacts_labels', 'axismundi_contacts_handle_labels' );

/** Hidden fields shared by list, label administration and contact detail. */
function axismundi_contacts_label_form_context( int $owner, int $group, int $item = 0 ) : void {
	$context = axismundi_contacts_directory_context();
	$fields = array( 'action' => 'axismundi_contacts_labels', 'owner' => $owner, 'return_group' => $group, 'return_item' => $item, 'return_search' => $context['search'], 'return_page' => $context['contact_page'] );
	foreach ( $fields as $name => $value ) {
		printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( (string) $value ) );
	}
	wp_nonce_field( 'ax_contacts_labels_' . $owner );
}

/** Label links reveal only labels already authorized for this owner. */
function axismundi_contacts_card_label_links( int $card, array $labels ) : void {
	$memberships = axismundi_contacts_card_books( $card );
	foreach ( $labels as $label ) {
		if ( in_array( (int) $label['id'], $memberships, true ) ) {
			printf( '<a class="ax-contacts-label" href="%s">%s</a> ', esc_url( axismundi_contacts_screen_url( -1, (int) $label['id'] ) ), esc_html( $label['name'] ) );
		}
	}
}

/** Add/remove one label without replacing other memberships or writing a Card. */
function axismundi_contacts_label_picker( array $labels ) : void {
	?>
	<label><?php esc_html_e( 'Label action', 'axismundi-contacts' ); ?>
		<select name="operation"><option value="add"><?php esc_html_e( 'Add label', 'axismundi-contacts' ); ?></option><option value="remove"><?php esc_html_e( 'Remove label', 'axismundi-contacts' ); ?></option></select>
	</label>
	<label><?php esc_html_e( 'Label', 'axismundi-contacts' ); ?>
		<select name="label_id" required><option value=""><?php esc_html_e( 'Choose a label', 'axismundi-contacts' ); ?></option>
		<?php foreach ( $labels as $label ) : ?><option value="<?php echo esc_attr( (string) $label['id'] ); ?>"><?php echo esc_html( $label['name'] ); ?></option><?php endforeach; ?>
		</select>
	</label>
	<button class="button" type="submit" data-ax-label-apply><?php esc_html_e( 'Apply label action', 'axismundi-contacts' ); ?></button>
	<?php
}

/** Readable detail filing, separate from the document editor's Save. */
function axismundi_contacts_detail_labels( int $card, int $owner, int $group ) : void {
	$labels = axismundi_contacts_labels( $owner );
	?>
	<section class="ax-contacts-detail__labels" aria-label="<?php esc_attr_e( 'Labels', 'axismundi-contacts' ); ?>">
		<h2><?php esc_html_e( 'Labels', 'axismundi-contacts' ); ?></h2>
		<p><?php axismundi_contacts_card_label_links( $card, $labels ); ?></p>
		<?php if ( $labels ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ax-contacts-label-actions">
			<?php axismundi_contacts_label_form_context( $owner, $group, $card ); ?>
			<input type="hidden" name="cards[]" value="<?php echo esc_attr( (string) $card ); ?>">
			<?php axismundi_contacts_label_picker( $labels ); ?>
		</form>
		<?php else : ?>
		<p><?php esc_html_e( 'No labels yet. Create a label in Contacts to organize this contact.', 'axismundi-contacts' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}
