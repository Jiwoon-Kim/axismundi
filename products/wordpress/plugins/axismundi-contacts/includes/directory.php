<?php
/**
 * Finding the contacts the current person is allowed to keep.
 *
 * These queries read the projections maintained by the Card writer. They never fetch a remote
 * profile, search the public Actor registry, or write back to the document.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * A prepared predicate shared by the rows and their count. Empty means access was refused.
 *
 * @param int    $owner Actor identity.
 * @param int    $group Same-owner group, or zero for every saved contact.
 * @param string $search Text to find in names or endpoints.
 * @return string
 */
function axismundi_contacts_directory_where( int $owner, int $group = 0, string $search = '' ) : string {
	global $wpdb;
	if ( ! axismundi_contacts_can_use_book( $owner, get_current_user_id() ) ) {
		return '';
	}
	if ( $group > 0 ) {
		$book = axismundi_contacts_get_book( $group );
		if ( (int) ( $book['owner_actor_id'] ?? 0 ) !== $owner ) {
			return '';
		}
	}
	$where = $wpdb->prepare( 'c.owner_actor_id = %d AND c.id <> %d', $owner, axismundi_contacts_profile_card( $owner ) );
	if ( $group > 0 ) {
		$memberships = axismundi_contacts_memberships_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table name.
		$where .= $wpdb->prepare( " AND EXISTS (SELECT 1 FROM {$memberships} m WHERE m.card_id = c.id AND m.address_book_id = %d)", $group );
	}
	$search = trim( $search );
	if ( '' !== $search ) {
		$values = axismundi_contacts_values_table();
		$like   = '%' . $wpdb->esc_like( mb_strtolower( $search ) ) . '%';
		// Digits inside an email or URL must not also search every phone ending in those digits.
		$phone  = 1 === preg_match( '/^(?:tel:)?[+0-9().\s-]+$/i', $search )
			? axismundi_contacts_normalize_value( 'phones', $search ) : '';
		$dial   = '%' . $wpdb->esc_like( $phone ) . '%';
		// EXISTS keeps a Card with several matching endpoints on one row, including across pages.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table name.
		$where .= $wpdb->prepare(
			" AND (c.sort_key LIKE %s OR c.linked_actor_uri LIKE %s OR EXISTS (
				SELECT 1 FROM {$values} v WHERE v.card_id = c.id AND (
					(v.field <> 'phones' AND v.normalized_value LIKE %s)
					OR (v.field = 'phones' AND %s <> '' AND v.normalized_value LIKE %s)
				)))",
			$like, $like, $like, $phone, $dial
		);
	}
	return $where;
}

/** Count saved contacts, excluding the separate profile Card, under the same permission gate. */
function axismundi_contacts_directory_count( int $owner, int $group = 0 ) : int {
	global $wpdb;
	$where = axismundi_contacts_directory_where( $owner, $group );
	if ( '' === $where ) {
		return 0;
	}
	$table = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- prepared predicate and plugin-owned table.
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} c WHERE {$where}" );
}

/**
 * A complete result count and one bounded page, with stable ordering for equal names.
 *
 * @return array{cards:array,total:int,page:int,pages:int,per_page:int}
 */
function axismundi_contacts_directory_page( int $owner, int $group = 0, string $search = '', int $page = 1, int $per_page = 50 ) : array {
	global $wpdb;
	$per_page = max( 1, min( 100, $per_page ) );
	$result   = array( 'cards' => array(), 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $per_page );
	$where    = axismundi_contacts_directory_where( $owner, $group, $search );
	if ( '' === $where ) {
		return $result;
	}
	$table = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- prepared predicate and plugin-owned table.
	$result['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} c WHERE {$where}" );
	$result['pages'] = max( 1, (int) ceil( $result['total'] / $per_page ) );
	$result['page']  = max( 1, min( $page, $result['pages'] ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- prepared predicate and plugin-owned table.
	$result['cards'] = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT c.* FROM {$table} c WHERE {$where} ORDER BY c.sort_key ASC, c.id ASC LIMIT %d OFFSET %d", $per_page, ( $result['page'] - 1 ) * $per_page ),
		ARRAY_A
	);
	return $result;
}

/** Read only the directory's navigation values; arrays from malformed URLs are not strings. */
function axismundi_contacts_directory_context() : array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	return array(
		'search'       => isset( $_GET['search'] ) && is_string( $_GET['search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['search'] ) ) ) : '',
		'contact_page' => isset( $_GET['contact_page'] ) && is_scalar( $_GET['contact_page'] ) ? max( 1, (int) $_GET['contact_page'] ) : 1,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/** Carry the current local search through detail/edit links, without trusting a return URL. */
function axismundi_contacts_directory_return_url( string $url ) : string {
	$context = axismundi_contacts_directory_context();
	$args    = array();
	if ( '' !== $context['search'] ) {
		$args['search'] = $context['search'];
	}
	if ( $context['contact_page'] > 1 ) {
		$args['contact_page'] = $context['contact_page'];
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/** A local result list, distinct from the action that looks up a new remote contact. */
function axismundi_contacts_directory( int $owner, int $book_id, int $self_id, string $title, int $group ) : void {
	$context = axismundi_contacts_directory_context();
	$result  = axismundi_contacts_directory_page( $owner, $group, $context['search'], $context['contact_page'] );
	?>
	<form class="ax-contacts-search" method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" role="search" aria-label="<?php esc_attr_e( 'Saved contacts', 'axismundi-contacts' ); ?>">
		<input type="hidden" name="page" value="axismundi-contacts">
		<?php if ( $group > 0 ) : ?>
			<input type="hidden" name="group" value="<?php echo esc_attr( (string) $group ); ?>">
		<?php endif; ?>
		<label for="ax-contacts-search"><?php esc_html_e( 'Search saved contacts', 'axismundi-contacts' ); ?></label>
		<input id="ax-contacts-search" name="search" type="search" value="<?php echo esc_attr( $context['search'] ); ?>" aria-describedby="ax-contacts-search-help">
		<button type="submit" class="button"><?php esc_html_e( 'Search contacts', 'axismundi-contacts' ); ?></button>
		<?php if ( '' !== $context['search'] ) : ?>
			<a href="<?php echo esc_url( axismundi_contacts_screen_url( -1, $group ) ); ?>"><?php esc_html_e( 'Clear search', 'axismundi-contacts' ); ?></a>
		<?php endif; ?>
		<p id="ax-contacts-search-help" class="description"><?php esc_html_e( 'Find a saved name, email address, phone number, or URL in this list.', 'axismundi-contacts' ); ?></p>
	</form>
	<?php
	axismundi_contacts_card_list( $book_id, $self_id, $result['cards'], $title, $group, $context['search'] );
	if ( $result['total'] > 0 ) {
		printf(
			'<p class="ax-contacts-result-count">%s</p>',
			esc_html( sprintf(
				/* translators: 1: first contact shown, 2: last contact shown, 3: total matching contacts. */
				_n( '%1$s–%2$s of %3$s contact', '%1$s–%2$s of %3$s contacts', $result['total'], 'axismundi-contacts' ),
				number_format_i18n( ( $result['page'] - 1 ) * $result['per_page'] + 1 ),
				number_format_i18n( min( $result['page'] * $result['per_page'], $result['total'] ) ),
				number_format_i18n( $result['total'] )
			) )
		);
	}
	if ( $result['pages'] <= 1 ) {
		return;
	}
	?>
	<nav class="ax-contacts-pages" aria-label="<?php esc_attr_e( 'Contact pages', 'axismundi-contacts' ); ?>">
		<?php foreach ( array( -1 => __( 'Previous contacts', 'axismundi-contacts' ), 1 => __( 'Next contacts', 'axismundi-contacts' ) ) as $step => $label ) : ?>
			<?php $target = $result['page'] + $step; ?>
			<?php if ( $target >= 1 && $target <= $result['pages'] ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'search' => $context['search'], 'contact_page' => $target ), axismundi_contacts_screen_url( -1, $group ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
		<span><?php
			/* translators: 1: current page, 2: total pages. */
			echo esc_html( sprintf( __( 'Page %1$s of %2$s', 'axismundi-contacts' ), number_format_i18n( $result['page'] ), number_format_i18n( $result['pages'] ) ) );
		?></span>
	</nav>
	<?php
}
