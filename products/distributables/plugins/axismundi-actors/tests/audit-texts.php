<?php
/**
 * Phase 4d — multilingual Actor profile regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/texts.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_text_results = array();
$ax_text_ids     = array();
$ax_text_users   = array();

/** @param array $results Results. @param string $label Contract. @param bool $cond Holds. */
function ax_text_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$actors = axismundi_actors_actors_table();
	$texts  = axismundi_actors_texts_table();
	$cols   = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$actors}" ); // phpcs:ignore WordPress.DB
	$index  = (array) $wpdb->get_col( "SHOW INDEX FROM {$texts} WHERE Key_name = 'identity_field_language'" ); // phpcs:ignore WordPress.DB
	ax_text_assert( $ax_text_results, 'schema adds default_language, text table, and unique key (v4+)', in_array( 'default_language', $cols, true ) && $wpdb->get_var( "SHOW TABLES LIKE '{$texts}'" ) === $texts && ! empty( $index ) && (int) get_option( 'ax_actors_db_version' ) >= 4 ); // phpcs:ignore WordPress.DB

	$uid = (int) wp_insert_user( array( 'user_login' => 'ax_text_alice', 'user_pass' => wp_generate_password(), 'display_name' => 'Live Alice', 'description' => 'Live bio', 'role' => 'author' ) );
	$ax_text_users[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	$ax_text_ids[] = $actor->get_identity_id();

	ax_text_assert( $ax_text_results, 'new local actors default to the normalized site language without copying WP_User text', axismundi_actors_site_language() === $actor->get_default_language() && array() === axismundi_actors_get_text_map( $actor->get_identity_id() ) );
	ax_text_assert( $ax_text_results, 'language tags normalize to BCP-47 case and invalid tags are rejected', 'ko-KR' === axismundi_actors_normalize_language_tag( 'ko_KR' ) && 'zh-Hant-TW' === axismundi_actors_normalize_language_tag( 'ZH_hant_tw' ) && '' === axismundi_actors_normalize_language_tag( 'bad tag!' ) );

	$set_name = axismundi_actors_set_text( $actor->get_identity_id(), 'name', 'ko_KR', '앨리스' );
	$set_bio  = axismundi_actors_set_text( $actor->get_identity_id(), 'summary', 'en_US', '<strong>English bio</strong><script>bad()</script>' );
	$set_long = axismundi_actors_set_text( $actor->get_identity_id(), 'content', 'en', '<p>Long about</p>' );
	$map      = axismundi_actors_get_text_map( $actor->get_identity_id() );
	ax_text_assert( $ax_text_results, 'explicit name, summary, and content translations upsert and sanitize', true === $set_name && true === $set_bio && true === $set_long && '앨리스' === $map['ko-KR']['name'] && false === strpos( $map['en-US']['summary'], '<script>' ) && '<p>Long about</p>' === $map['en']['content'] );
	update_user_meta( $uid, 'locale', 'ko_KR' );
	ax_text_assert( $ax_text_results, 'a local Person HTML profile prefers an authored translation matching their WordPress profile language', 'ko-KR' === axismundi_actors_profile_language( $actor ) && '앨리스' === axismundi_actors_resolve_text( $actor, 'name', axismundi_actors_profile_language( $actor ) ) );

	axismundi_actors_set_default_language( $actor->get_identity_id(), 'ko_KR' );
	$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	ax_text_assert( $ax_text_results, 'default language is mutable and normalized', 'ko-KR' === $actor->get_default_language() );
	ax_text_assert( $ax_text_results, 'resolution uses exact, base, then default language', false !== strpos( axismundi_actors_resolve_text( $actor, 'summary', 'en-US' ), 'English bio' ) && '<p>Long about</p>' === axismundi_actors_resolve_text( $actor, 'content', 'en-GB' ) && '앨리스' === axismundi_actors_resolve_text( $actor, 'name', 'fr-FR' ) );

	axismundi_actors_set_text( $actor->get_identity_id(), 'name', 'ko-KR', '' );
	$map = axismundi_actors_get_text_map( $actor->get_identity_id() );
	ax_text_assert( $ax_text_results, 'empty text deletes the row instead of storing an empty translation', ! isset( $map['ko-KR']['name'] ) );
	axismundi_actors_set_text( $actor->get_identity_id(), 'summary', 'en-US', '' );
	ax_text_assert( $ax_text_results, 'missing translations fall back to live WP_User values', 'Live Alice' === axismundi_actors_resolve_text( $actor, 'name', 'fr-FR' ) && 'Live bio' === axismundi_actors_resolve_text( $actor, 'summary', 'fr-FR' ) );

	$invalid = axismundi_actors_set_text( $actor->get_identity_id(), 'unknown', 'en', 'x' );
	ax_text_assert( $ax_text_results, 'invalid fields are rejected without creating rows', is_wp_error( $invalid ) );
} finally {
	foreach ( array_unique( $ax_text_ids ) as $identity_id ) {
		axismundi_actors_delete_texts( (int) $identity_id );
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $ax_text_users as $user_id ) {
		if ( get_userdata( $user_id ) ) {
			wp_delete_user( $user_id );
		}
	}
}

$ax_text_failures = count( array_filter( $ax_text_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_text_results ), $ax_text_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_text_failures > 0 ? 1 : 0 );
}
exit( $ax_text_failures > 0 ? 1 : 0 );
