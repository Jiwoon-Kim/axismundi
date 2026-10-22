<?php
/** Real-database write failures and interleaved CAS audit. Development only. @package AxismundiContacts */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$user = 0;
$owner = 0;
$book_id = 0;
$card_id = 0;
$filter = null;
$other = null;
$notifications = 0;
$initial_user = get_current_user_id();
$assert = static function ( bool $pass, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $pass ) {
		throw new RuntimeException( $label );
	}
};
$notify = static function ( int $id ) use ( &$notifications ) : void { ++$notifications; };
try {
	global $wpdb;
	$user = wp_insert_user( array( 'user_login' => 'axwrite' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( is_wp_error( $user ) ) {
		throw new RuntimeException( $user->get_error_message() );
	}
	wp_set_current_user( $user );
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $user ) );
	if ( ! $actor instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Actor fixture failed' );
	}
	$owner = $actor->get_identity_id();
	$book = axismundi_contacts_book_for_actor( $owner );
	if ( is_wp_error( $book ) ) {
		throw new RuntimeException( $book->get_error_message() );
	}
	$book_id = (int) $book['id'];
	$doc = array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( 'full' => 'Original' ), 'emails' => array( 'e1' => array( 'address' => 'original@example.test' ) ) );
	$card_id = axismundi_contacts_save_card( $book_id, $doc );
	if ( is_wp_error( $card_id ) ) {
		throw new RuntimeException( $card_id->get_error_message() );
	}
	$doc = axismundi_contacts_card_document( $card_id );
	$edited = $doc;
	$edited['name']['full'] = 'Edited';
	$edited['emails']['e1']['address'] = 'edited@example.test';
	$cards = axismundi_contacts_cards_table();
	$values = axismundi_contacts_values_table();
	$books = axismundi_contacts_books_table();
	$members = axismundi_contacts_memberships_table();
	$snapshot = static function () use ( $wpdb, &$card_id, $book_id, $values, $members ) : array {
		return array( axismundi_contacts_get_card( $card_id ), axismundi_contacts_get_book( $book_id ),
			$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$values} WHERE card_id = %d ORDER BY id", $card_id ), ARRAY_A ),
			$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$members} WHERE card_id = %d ORDER BY id", $card_id ), ARRAY_A ) );
	};
	add_action( 'axismundi_contacts_card_saved', $notify );
	$fault = static function ( callable $matches, callable $operation, string $label ) use ( $wpdb, $assert, $snapshot, &$filter, &$notifications ) : void {
		$before = $snapshot();
		$before_hooks = $notifications;
		$hit = false;
		$filter = static function ( string $sql ) use ( $matches, &$hit ) : string {
			if ( ! $hit && $matches( $sql ) ) {
				$hit = true;
				return 'SELECT * FROM ax_contacts_deliberately_missing_table';
			}
			return $sql;
		};
		add_filter( 'query', $filter );
		$prior = $wpdb->suppress_errors( true );
		try {
			$result = $operation();
		} finally {
			remove_filter( 'query', $filter );
			$filter = null;
			$wpdb->suppress_errors( $prior );
		}
		$assert( $hit && is_wp_error( $result ), $label . ': failure propagated' );
		$assert( $before === $snapshot() && $before_hooks === $notifications, $label . ': all rows restored, no save notification' );
	};
	$save = static fn() => axismundi_contacts_save_card( $book_id, $edited, $card_id, (int) axismundi_contacts_get_card( $card_id )['revision'] );
	$fault( static fn( $sql ) => 'START TRANSACTION' === $sql, $save, 'START' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'SAVEPOINT ' ), $save, 'SAVEPOINT' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'UPDATE ' ) && str_contains( $sql, $cards ), $save, 'Card UPDATE' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'DELETE ' ) && str_contains( $sql, $values ), $save, 'Index DELETE' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'INSERT ' ) && str_contains( $sql, $values ), $save, 'Index INSERT' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'UPDATE ' ) && str_contains( $sql, $books ), $save, 'Book revision' );
	$fault( static fn( $sql ) => 'COMMIT' === $sql, $save, 'COMMIT' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'RELEASE SAVEPOINT ' ), $save, 'Savepoint release' );
	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$cards} WHERE owner_actor_id = %d", $owner ) );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'INSERT ' ) && str_contains( $sql, $cards ), static fn() => axismundi_contacts_save_card( $book_id, $edited ), 'Card INSERT' );
	$fault( static fn( $sql ) => str_starts_with( $sql, 'INSERT IGNORE ' ) && str_contains( $sql, $members ), static fn() => axismundi_contacts_save_card( $book_id, $edited ), 'Initial membership' );
	$assert( $count === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$cards} WHERE owner_actor_id = %d", $owner ) ), 'Failed initial membership leaves no new Card' );
	$before = $snapshot();
	$result = Axismundi_Contacts_Write::run( static function () use ( $save, $assert, &$notifications ) {
		$saved = $save();
		$assert( ! is_wp_error( $saved ) && 0 === $notifications, 'Nested save queues notification until outer commit' );
		return new WP_Error( 'forced_outer_failure' );
	} );
	$assert( is_wp_error( $result ) && $before === $snapshot() && 0 === $notifications, 'Outer failure rolls back nested save and discards notification' );
	$caught = false;
	try {
		Axismundi_Contacts_Write::run( static function () use ( $save ) {
			$save();
			throw new RuntimeException( 'Fixture exception' );
		} );
	} catch ( RuntimeException $error ) {
		$caught = 'Fixture exception' === $error->getMessage();
	}
	$assert( $caught && $before === $snapshot() && 0 === $notifications, 'Thrown operation rolls back and clears transaction frame' );
	$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$other->suppress_errors( true );
	$committed = false;
	$observer = static function ( int $id ) use ( $other, $cards, &$committed ) : void {
		$committed = 'Edited' === $other->get_var( $other->prepare( "SELECT display_name FROM {$cards} WHERE id = %d", $id ) );
	};
	add_action( 'axismundi_contacts_card_saved', $observer );
	$assert( $card_id === $save() && 1 === $notifications && $committed, 'Notification observes committed rows from a separate connection' );
	remove_action( 'axismundi_contacts_card_saved', $observer );
	$before = $snapshot();
	$wpdb->query( 'START TRANSACTION' );
	$wpdb->update( $cards, array( 'display_name' => 'Uncommitted caller' ), array( 'id' => $card_id ) );
	$result = $save();
	$wpdb->query( 'ROLLBACK' );
	$assert( is_wp_error( $result ) && $before === $snapshot(), 'Raw enclosing transaction rejected without implicit commit' );
	$assert( ! is_wp_error( $save() ), 'Runner remains usable after rejected raw caller' );
	$before = axismundi_contacts_get_card( $card_id );
	$hit = false;
	$filter = static function ( string $sql ) use ( $wpdb, $other, $cards, $card_id, &$hit, &$filter ) : string {
		if ( ! $hit && str_starts_with( $sql, 'UPDATE ' ) && str_contains( $sql, $cards ) ) {
			$hit = true;
			remove_filter( 'query', $filter );
			// Commit a competing revision after the first connection read, before its conditional UPDATE.
			if ( 1 !== $other->query( $other->prepare( "UPDATE {$cards} SET revision = revision + 1 WHERE id = %d", $card_id ) ) ) {
				throw new RuntimeException( 'Competing connection failed: ' . $other->last_error );
			}
		}
		return $sql;
	};
	add_filter( 'query', $filter );
	$prior_errors = $wpdb->suppress_errors( true );
	try {
		$result = axismundi_contacts_save_card_for_owner( $owner, $doc, $card_id, (int) $before['revision'] );
	} finally {
		remove_filter( 'query', $filter );
		$filter = null;
		$wpdb->suppress_errors( $prior_errors );
	}
	$after = axismundi_contacts_get_card( $card_id );
	$assert( $hit && is_wp_error( $result ) && 'ax_contacts_card_conflict' === $result->get_error_code(), 'Interleaved two-connection edit rejected by conditional revision UPDATE' );
	$assert( (int) $before['revision'] + 1 === (int) $after['revision'] && $before['card_json'] === $after['card_json'], 'Losing edit preserves competing revision and committed document' );
	$before = $snapshot();
	$prior_hooks = $notifications;
	$result = Axismundi_Contacts_Write::run( static function () use ( $save ) {
		$saved = Axismundi_Contacts_Write::run( static function () use ( $save ) {
			$result = $save();
			return is_wp_error( $result ) ? $result : new WP_Error( 'nested_failure' );
		} );
		return is_wp_error( $saved ) ? true : new WP_Error( 'expected_failure' );
	} );
	$assert( true === $result && $before === $snapshot() && $prior_hooks === $notifications, 'Handled nested failure rolls back only its savepoint and notification' );
	$assert( is_wp_error( axismundi_contacts_write_card( $owner, $doc, $card_id, null ) ), 'Internal write body refuses unowned writes' );
	$throwing = static function () : void { throw new RuntimeException( 'Notification exception' ); };
	add_action( 'axismundi_contacts_card_saved', $throwing );
	$caught = false;
	try {
		$save();
	} catch ( RuntimeException $error ) {
		$caught = 'Notification exception' === $error->getMessage();
	} finally {
		remove_action( 'axismundi_contacts_card_saved', $throwing );
	}
	$assert( $caught && ! Axismundi_Contacts_Write::active(), 'Post-commit exception does not leave an open PHP transaction frame' );
	$prior_hooks = $notifications;
	$assert( ! is_wp_error( $save() ) && $notifications === $prior_hooks + 1, 'Notification re-entry guard resets after an exception' );
	printf( "PASS: %d Card write checks\n", $checks );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter );
	}
	remove_action( 'axismundi_contacts_card_saved', $notify );
	if ( isset( $observer ) ) {
		remove_action( 'axismundi_contacts_card_saved', $observer );
	}
	if ( $other instanceof wpdb ) {
		$other->close();
	}
	wp_set_current_user( $initial_user );
	if ( $owner > 0 ) {
		axismundi_contacts_purge_actor( $owner );
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $owner ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $owner ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $owner ) );
	}
	if ( is_int( $user ) && $user > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user );
	}
}
