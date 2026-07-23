<?php
/**
 * Local Actor PropertyValue profile-field regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/profile-fields.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_profile_field_results = array();
$ax_profile_field_users   = array();
$ax_profile_field_ids     = array();

/** @param bool[] $results Results. */
function ax_profile_field_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed internal table name; identifiers cannot use value placeholders.
	$columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_profile_fields_table() );
	ax_profile_field_assert( $ax_profile_field_results, 'profile-field table installs with ordered PropertyValue and verification columns', in_array( 'field_name', $columns, true ) && in_array( 'field_value', $columns, true ) && in_array( 'position', $columns, true ) && in_array( 'verification_status', $columns, true ) && in_array( 'verified_at', $columns, true ) );

	$user_id = (int) wp_insert_user( array( 'user_login' => 'ax_profile_fields', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_profile_field_users[] = $user_id;
	$actor = axismundi_actors_ensure_for_user( $user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_profile_field_ids[] = $actor->get_identity_id();
	}
	$saved = $actor instanceof Axismundi_Actor ? axismundi_actors_save_profile_fields(
		$actor,
		array(
			array( 'name' => 'Website', 'url' => 'https://example.com/me/' ),
			array( 'name' => 'Mastodon', 'url' => 'https://mastodon.example/@alice' ),
		)
	) : false;
	$stored = $actor instanceof Axismundi_Actor ? axismundi_actors_get_profile_fields( $actor->get_identity_id() ) : array();
	ax_profile_field_assert( $ax_profile_field_results, 'local Actor profile links preserve authored order', true === $saved && 2 === count( $stored ) && 'Website' === $stored[0]['name'] && 'Mastodon' === $stored[1]['name'] );

	$attachments = $actor instanceof Axismundi_Actor ? axismundi_actors_profile_field_attachments( $actor ) : array();
	ax_profile_field_assert( $ax_profile_field_results, 'profile links serialize as safe PropertyValue rel-me attachments', 2 === count( $attachments ) && 'PropertyValue' === $attachments[0]['type'] && 'Website' === $attachments[0]['name'] && false !== strpos( $attachments[0]['value'], 'href="https://example.com/me/"' ) && false !== strpos( $attachments[0]['value'], 'rel="me nofollow noopener noreferrer"' ) );

	ob_start();
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_text_form( $actor );
		axismundi_actors_profile_fields_form( $actor );
	}
	$admin_markup = (string) ob_get_clean();
	ax_profile_field_assert( $ax_profile_field_results, 'profile editor removes the About field and exposes ordered PropertyValue links', false === strpos( $admin_markup, 'ax-actor-content' ) && false !== strpos( $admin_markup, 'Profile links' ) && false !== strpos( $admin_markup, 'profile_field_url[]' ) && false !== strpos( $admin_markup, 'ax-actor-profile-fields__drag' ) && false !== strpos( $admin_markup, 'ax-actor-profile-fields__move-up' ) && false !== strpos( $admin_markup, 'ax-actor-profile-fields__add' ) && false !== strpos( $admin_markup, 'Verify' ) );

	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axpf' . $user_id );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
		wp_set_current_user( 1 );
		$target = 'https://example.com/me/';
		$mock = static function ( $pre, $args, $url ) use ( $target, $actor ) {
			if ( $target !== $url ) {
				return $pre;
			}
			return array(
				'headers'  => array( 'content-type' => 'text/html' ),
				'body'     => '<!doctype html><a href="' . esc_url( $actor->get_profile_url() ) . '" rel="noopener me">Me</a>',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		$verified = axismundi_actors_verify_profile_field( $actor, $target );
		remove_filter( 'pre_http_request', $mock, 10 );
		$after_verified = axismundi_actors_get_profile_fields( $actor->get_identity_id() );
		ax_profile_field_assert( $ax_profile_field_results, 'explicit bounded HTML verification recognizes reciprocal rel-me and stores status', true === $verified && 'verified' === $after_verified[0]['verification_status'] );

		$reordered = axismundi_actors_save_profile_fields( $actor, array( array( 'name' => 'Mastodon', 'url' => 'https://mastodon.example/@alice' ), array( 'name' => 'Renamed Website', 'url' => $target ) ) );
		$after_reordered = axismundi_actors_get_profile_fields( $actor->get_identity_id() );
		ax_profile_field_assert( $ax_profile_field_results, 'reordering or relabeling preserves a verified URL while a changed URL would reset it', true === $reordered && 'verified' === $after_reordered[1]['verification_status'] );

		$previous_route_actor = $GLOBALS['axismundi_actors_current_actor'] ?? null;
		$GLOBALS['axismundi_actors_current_actor'] = $actor;
		$profile_block_markup = render_block(
			array(
				'blockName'    => 'axismundi/actor-profile-fields',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$GLOBALS['axismundi_actors_current_actor'] = $previous_route_actor;
		ax_profile_field_assert( $ax_profile_field_results, 'the profile-fields block renders saved links and a check only for verified links', false !== strpos( $profile_block_markup, 'Renamed Website' ) && false !== strpos( $profile_block_markup, 'ax-actor-profile-fields-block__verified' ) );

		$changed_url = axismundi_actors_save_profile_fields( $actor, array( array( 'name' => 'Mastodon', 'url' => 'https://mastodon.example/@alice' ), array( 'name' => 'Changed Website', 'url' => 'https://example.com/changed/' ) ) );
		$after_changed_url = axismundi_actors_get_profile_fields( $actor->get_identity_id() );
		ax_profile_field_assert( $ax_profile_field_results, 'changing a verified link URL resets it to unverified', true === $changed_url && 'unverified' === $after_changed_url[1]['verification_status'] );
	}

	$invalid = $actor instanceof Axismundi_Actor ? axismundi_actors_save_profile_fields( $actor, array( array( 'name' => 'Bad', 'url' => 'javascript:alert(1)' ) ) ) : false;
	$after_invalid = $actor instanceof Axismundi_Actor ? axismundi_actors_get_profile_fields( $actor->get_identity_id() ) : array();
	ax_profile_field_assert( $ax_profile_field_results, 'invalid link replacement fails closed without dropping existing fields', is_wp_error( $invalid ) && 2 === count( $after_invalid ) && 'Mastodon' === $after_invalid[0]['name'] && 'Changed Website' === $after_invalid[1]['name'] );
} finally {
	foreach ( array_unique( $ax_profile_field_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_profile_fields_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_profile_field_users as $user_id ) {
		if ( get_userdata( $user_id ) ) {
			wp_delete_user( $user_id );
		}
	}
}

$ax_profile_field_failures = count( array_filter( $ax_profile_field_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_profile_field_results ), $ax_profile_field_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_profile_field_failures > 0 ? 1 : 0 );
}
exit( $ax_profile_field_failures > 0 ? 1 : 0 );
