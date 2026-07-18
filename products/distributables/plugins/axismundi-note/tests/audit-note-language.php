<?php
/**
 * Note effective-language resolution regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_lang_results   = array();
$ax_lang_post_ids  = array();
$ax_lang_user_ids  = array();
$ax_lang_actor_ids = array();

/** @param bool[] $results Results. */
function ax_lang_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login = 'ax_lang_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_lang_user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	$identity_id = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	if ( $identity_id > 0 ) {
		$ax_lang_actor_ids[] = $identity_id;
		axismundi_actors_register_handle( $identity_id, $login );
		axismundi_actors_set_status( $identity_id, 'public' );
	}

	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $uid, 'post_title' => 'Language note' ) );
	$ax_lang_post_ids[] = $post_id;
	$post = get_post( $post_id );

	// The stored authored value wins over every inherited candidate.
	axismundi_actors_set_default_language( $identity_id, 'ja' );
	axismundi_note_save( $post_id, array( 'language_tag' => 'ko_kr' ) );
	ax_lang_assert( $ax_lang_results, 'an explicit envelope language overrides the inheritance chain', 'ko-KR' === axismundi_note_effective_language( $post ) );

	$bad_language = axismundi_note_save( $post_id, array( 'language_tag' => 'x_invalid' ) );
	ax_lang_assert( $ax_lang_results, 'an explicitly invalid language fails closed without replacing the stored value', is_wp_error( $bad_language ) && 'ax_note_language' === $bad_language->get_error_code() && 'ko-KR' === axismundi_note_get( $post_id )['language_tag'] );

	// With no stored value, the author Actor default language is next.
	axismundi_note_save( $post_id, array( 'language_tag' => '' ) );
	ax_lang_assert( $ax_lang_results, 'a draft with no language inherits the author Actor default language', 'ja' === axismundi_note_effective_language( $post ) );

	// With no Actor default, the author WordPress locale is next.
	$wpdb->update( axismundi_actors_actors_table(), array( 'default_language' => null ), array( 'identity_id' => $identity_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	update_user_meta( $uid, 'locale', 'de_DE' );
	ax_lang_assert( $ax_lang_results, 'without an Actor default the author WordPress locale is normalized to BCP-47', 'de-DE' === axismundi_note_effective_language( $post ) );

	// With neither, the site locale terminates the chain.
	delete_user_meta( $uid, 'locale' );
	$site = axismundi_actors_site_language();
	ax_lang_assert( $ax_lang_results, 'the site locale terminates the inheritance chain', '' !== $site && $site === axismundi_note_effective_language( $post ) );

	// The resolver stores nothing: the envelope language stays empty until a Create.
	$envelope = axismundi_note_get( $post_id );
	ax_lang_assert( $ax_lang_results, 'the read-time resolver never writes the resolved value into the envelope', is_array( $envelope ) && '' === (string) $envelope['language_tag'] );
} finally {
	foreach ( array_unique( $ax_lang_post_ids ) as $pid ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $pid ) instanceof WP_Post ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_lang_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_lang_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_lang_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_lang_failures = count( array_filter( $ax_lang_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_lang_results ), $ax_lang_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_lang_failures > 0 ? 1 : 0 );
}
exit( $ax_lang_failures > 0 ? 1 : 0 );
