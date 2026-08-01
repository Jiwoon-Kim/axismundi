<?php
/**
 * Unified interaction block regression (dev-only; dist-excluded).
 *
 * Six blocks each drew their own button and their six stylesheets had already drifted. This locks
 * the contract that replaced them: a type says what it is, the block says what a button is, and
 * neither reaches into the other.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ib_results = array();
$ax_ib_users   = array();
$ax_ib_posts   = array();
$ax_ib_ids     = array();

/** @param bool[] $results Results. */
function ax_ib_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Read one attribute off the rendered control, without matching a binding of the same name. */
function ax_ib_button_attr( string $html, string $attribute ) : string {
	if ( ! preg_match( '#<button\b([^>]*)>#', $html, $tag ) ) {
		return '';
	}
	return preg_match( '#(?<![-\w])' . preg_quote( $attribute, '#' ) . '="([^"]*)"#', $tag[1], $found ) ? $found[1] : '';
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axib_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_ib_users[] = $owner;
	wp_set_current_user( $owner );
	$actor       = axismundi_actors_ensure_for_user( $owner );
	$ax_ib_ids[] = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axib' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_act_current_local_actor();

	$note          = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_content' => '<p>Interaction target.</p>' ) );
	$ax_ib_posts[] = $note;
	$saved         = axismundi_note_save( $note, array( 'visibility' => 'public' ) );
	if ( ! is_wp_error( $saved ) ) {
		wp_update_post( array( 'ID' => $note, 'post_status' => 'publish' ) );
	}
	$envelope   = axismundi_note_get( $note );
	$object_uri = is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '';
	$markup     = '<!-- wp:axismundi/interaction {"type":"like","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->';

	ax_ib_assert(
		$ax_ib_results,
		'Like is offered through the registry rather than being built into the block',
		array_key_exists( 'like', axismundi_act_interaction_types() )
			&& is_callable( axismundi_act_interaction_types()['like']['describe'] )
	);

	// A type nobody registered is not a broken button, it is no button.
	ax_ib_assert(
		$ax_ib_results,
		'an unregistered type renders nothing at all',
		'' === trim( do_blocks( '<!-- wp:axismundi/interaction {"type":"nonesuch","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' ) )
	);

	/*
	 * The defect this block exists partly to fix. The control it replaced added its selected class
	 * at hydration, so a reader who had liked something was shown, until the page finished
	 * booting, that they had not — and was shown that forever with JavaScript off.
	 */
	$liked      = axismundi_act_like_object( $actor, $object_uri );
	$was_liked  = axismundi_act_get_like_state( $actor->get_uri(), $object_uri );
	$liked_html = do_blocks( $markup );
	axismundi_act_unlike_object( $actor, $object_uri );
	$now_liked  = axismundi_act_get_like_state( $actor->get_uri(), $object_uri );
	$plain_html = do_blocks( $markup );

	ax_ib_assert(
		$ax_ib_results,
		'the ledger really moved in both directions, so the markup below reflects a state and not a default',
		! is_wp_error( $liked ) && '' !== $object_uri && true === $was_liked && false === $now_liked
	);

	ax_ib_assert(
		$ax_ib_results,
		'selected state is in the markup the server sends, not added when the page boots',
		false !== strpos( ax_ib_button_attr( $liked_html, 'class' ), 'is-selected' )
			&& 'true' === ax_ib_button_attr( $liked_html, 'aria-pressed' )
			&& false === strpos( ax_ib_button_attr( $plain_html, 'class' ), 'is-selected' )
			&& 'false' === ax_ib_button_attr( $plain_html, 'aria-pressed' )
	);

	// Small is what every other button on the site is; extra small is chosen, never inherited.
	$xs_html = do_blocks( '<!-- wp:axismundi/interaction {"type":"like","size":"xs","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	ax_ib_assert(
		$ax_ib_results,
		'an interaction is small unless it is asked to be extra small',
		false !== strpos( ax_ib_button_attr( $plain_html, 'class' ), 'is-size-sm' )
			&& false !== strpos( ax_ib_button_attr( $xs_html, 'class' ), 'is-size-xs' )
	);

	/*
	 * Two owners for one click is a bug you have to remember not to write. The delegated variant
	 * omits the directives instead of guarding them, so absent markup cannot double-fire.
	 */
	$GLOBALS['axismundi_op_object_template_options'] = array( 'interactionOwner' => 'feed' );
	$delegated_html = do_blocks( $markup );
	$GLOBALS['axismundi_op_object_template_options'] = array();

	ax_ib_assert(
		$ax_ib_results,
		'a feed-owned interaction carries its target as data and none of the interactive directives',
		false !== strpos( $delegated_html, 'data-ax-action="like"' )
			&& false === strpos( $delegated_html, 'data-wp-interactive' )
			&& false === strpos( $delegated_html, 'data-wp-on--click' )
			&& false !== strpos( $plain_html, 'data-wp-interactive' )
			&& false === strpos( $plain_html, 'data-ax-action' )
	);

	/*
	 * The reason the six stylesheets drifted: each owned properties the theme also owned, and at
	 * equal specificity the later sheet won silently. This block owns appearance and size, and
	 * shape and motion arrive through `wp-element-button` — so those must not appear here at all.
	 */
	$stylesheet = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/interaction/style.css' );
	ax_ib_assert(
		$ax_ib_results,
		'the block never redeclares the shape and motion the theme owns',
		false === strpos( $stylesheet, 'border-radius' )
			&& false === strpos( $stylesheet, 'transition' )
			&& false !== strpos( $stylesheet, '--md-sys-state-hover-state-layer-opacity' )
	);

	ax_ib_assert(
		$ax_ib_results,
		'the control carries the core button class so the theme reaches it',
		false !== strpos( ax_ib_button_attr( $plain_html, 'class' ), 'wp-element-button' )
	);
} finally {
	$GLOBALS['axismundi_op_object_template_options'] = array();
	foreach ( array_unique( $ax_ib_posts ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_filter( array_unique( $ax_ib_ids ) ) as $identity_id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( 0 );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_filter( array_unique( $ax_ib_users ) ) as $user_id ) {
		if ( get_userdata( (int) $user_id ) ) {
			wp_delete_user( (int) $user_id );
		}
	}
}

$ax_ib_failed = count( array_filter( $ax_ib_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ib_results ) - $ax_ib_failed, count( $ax_ib_results ) );
exit( $ax_ib_failed > 0 ? 1 : 0 );
