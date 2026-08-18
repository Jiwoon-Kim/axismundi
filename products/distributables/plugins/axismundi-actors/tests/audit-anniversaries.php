<?php
/** Anniversaries are a JSContact collection, not Person-profile birthday columns (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

$ax_an_results = array();
$ax_an_users   = array();
function ax_an_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
}
try {
	$login = 'axan' . strtolower( wp_generate_password( 8, false, false ) );
	$user  = (int) wp_insert_user( array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_an_users[] = $user;
	$actor = axismundi_actors_ensure_for_user( $user );
	$identity_id = (int) $actor->get_identity_id();
	$result = axismundi_actors_replace_anniversaries(
		$identity_id,
		array(
			array( 'kind' => 'birth', 'year' => 1996, 'month' => 11, 'day' => 20, 'visibility' => 'full' ),
			array( 'kind' => 'death', 'month' => 10, 'day' => 10, 'visibility' => 'month-day' ),
			array( 'kind' => 'wedding', 'year' => 2020, 'month' => 4, 'day' => 4, 'visibility' => 'none' ),
		)
	);
	$items = axismundi_actors_get_anniversaries( $identity_id );
	ax_an_assert( $ax_an_results, 'one Actor owns many typed anniversaries in a child collection', true === $result && 3 === count( $items ) && 'birth' === $items[0]['kind'] && 'wedding' === $items[2]['kind'] );
	ax_an_assert( $ax_an_results, 'an anniversary requires a known kind and valid Gregorian month and day', is_wp_error( axismundi_actors_replace_anniversaries( $identity_id, array( array( 'kind' => 'nameday', 'month' => 10, 'day' => 10 ) ) ) ) && is_wp_error( axismundi_actors_replace_anniversaries( $identity_id, array( array( 'kind' => 'birth', 'month' => 2, 'day' => 31 ) ) ) ) );
	$card = axismundi_actors_jscontact_card( axismundi_actors_get_by_identity( $identity_id ) );
	$public = $card['anniversaries'] ?? array();
	ax_an_assert( $ax_an_results, 'only explicitly public rows project into JSContact', 2 === count( $public ) && ! in_array( 'wedding', array_column( $public, 'kind' ), true ) );
	$public_dates = array_column( $public, 'date' );
	ax_an_assert( $ax_an_results, 'full dates include their year while month-day dates withhold it', isset( $public[ 'a' . $items[0]['id'] ]['date']['year'] ) && ! isset( $public[ 'a' . $items[1]['id'] ]['date']['year'] ) );
	ax_an_assert( $ax_an_results, 'Actor anniversaries are Gregorian facts and do not claim a recurrence calendar', ! array_filter( $public_dates, static fn( array $date ) : bool => isset( $date['calendarScale'] ) ) );
	$profile_columns = array();
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema assertion.
	$profile_columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_profile_table() );
	ax_an_assert( $ax_an_results, 'the Person profile has no birthday columns', ! in_array( 'birth_month', $profile_columns, true ) && ! in_array( 'lunar_birth_month', $profile_columns, true ) );
} finally {
	foreach ( $ax_an_users as $user ) {
		wp_delete_user( $user );
	}
}
$failures = count( array_filter( $ax_an_results, static fn( bool $result ) : bool => ! $result ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_an_results ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
