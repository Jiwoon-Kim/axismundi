<?php
/**
 * Actor profile header template regression (dev-only; dist-excluded).
 *
 * The profile header is ordinary Core Group markup. Its nested Actor leaves
 * resolve the current profile route themselves; no composite wrapper owns a
 * second Actor context or a parallel editor-only rendering contract.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

global $wpdb;
$ax_ah_results = array();
$ax_ah_ids     = array();
$ax_ah_users   = array();
$ax_ah_atts    = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $condition Holds.
 * @return void
 */
function ax_ah_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * Create one registered, public local actor.
 *
 * @param string $login WP user login.
 * @param string $name  Display name.
 * @param string $handle Preferred username to register.
 * @return Axismundi_Actor
 */
function ax_ah_public_actor( array &$results, array &$ids, array &$users, string $login, string $name, string $handle ) : Axismundi_Actor {
	$user_id = (int) wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password(),
			'user_email'   => $login . '-private@example.test',
			'display_name' => $name,
			'description'  => $name . ' bio.',
			'role'         => 'author',
		)
	);
	$users[] = $user_id;
	$actor   = axismundi_actors_ensure_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		ax_ah_assert( $results, "fixture creates {$login}", false );
		exit( 1 );
	}
	$ids[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return axismundi_actors_get_by_uuid( $actor->get_uuid() );
}

/** Create one tiny local header image whose attachment URL can reach the cover block. */
function ax_ah_header_attachment( array &$attachments, int $author ) : int {
	$upload = wp_upload_bits(
		'ax-actor-header.png',
		null,
		base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLq2QAAAABJRU5ErkJggg==' )
	);
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return 0;
	}
	$attachment_id = (int) wp_insert_attachment(
		array(
			'post_title'     => 'AX Actor header fixture',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/png',
			'post_author'    => $author,
		),
		(string) $upload['file']
	);
	if ( $attachment_id <= 0 ) {
		return 0;
	}
	$metadata = wp_generate_attachment_metadata( $attachment_id, (string) $upload['file'] );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
	$attachments[] = $attachment_id;
	return $attachment_id;
}

try {
	axismundi_actors_install();

	$registry = WP_Block_Type_Registry::get_instance();
	ax_ah_assert(
		$ax_ah_results,
		'the profile header uses Core Group and the retired composite wrapper is not registered',
		$registry->is_registered( 'core/group' ) && ! $registry->is_registered( 'axismundi/account-header' )
			&& $registry->is_registered( 'axismundi/actor-avatar' )
			&& $registry->is_registered( 'axismundi/actor-identity' ) && $registry->is_registered( 'axismundi/actor-biography' )
			&& false !== ( $registry->get_registered( 'axismundi/actor-avatar' )->supports['multiple'] ?? null )
	);

	$alice = ax_ah_public_actor( $ax_ah_results, $ax_ah_ids, $ax_ah_users, 'ax_ah_alice', 'Alice Header', 'alice_header' );
	$bob   = ax_ah_public_actor( $ax_ah_results, $ax_ah_ids, $ax_ah_users, 'ax_ah_bob', 'Bob Header', 'bob_header' );
	$alice_user = get_user_by( 'login', 'ax_ah_alice' );
	$header_id  = $alice_user instanceof WP_User ? ax_ah_header_attachment( $ax_ah_atts, $alice_user->ID ) : 0;
	$header_set = $header_id > 0 && $alice_user instanceof WP_User
		? axismundi_actors_set_profile_media( $alice, 'header', $header_id, $alice_user->ID )
		: false;
	$alice = axismundi_actors_get_by_uuid( $alice->get_uuid() );
	ax_ah_assert( $ax_ah_results, 'fixture gives the current Actor a real local header image', true === $header_set && $alice instanceof Axismundi_Actor && $header_id === $alice->get_header_attachment_id() );

	$GLOBALS['axismundi_actors_profile_actor'] = $alice;
	$route_markup = '<!-- wp:group {"className":"ax-actor-profile__header"} --><div class="wp-block-group ax-actor-profile__header"><!-- wp:axismundi/object-featured-image {"showPlaceholder":true} /--><!-- wp:group {"className":"ax-actor-profile__head"} --><div class="wp-block-group ax-actor-profile__head"><!-- wp:axismundi/actor-avatar /--><!-- wp:axismundi/actor-identity /--></div><!-- /wp:group --><!-- wp:axismundi/actor-biography /--></div><!-- /wp:group -->';
	$route_rendered = do_blocks( $route_markup );
	ax_ah_assert(
		$ax_ah_results,
		'route context renders the Core Group header wrapper and head row',
		false !== strpos( $route_rendered, 'ax-actor-profile__header' ) && false !== strpos( $route_rendered, 'ax-actor-profile__head' )
	);
	ax_ah_assert(
		$ax_ah_results,
		'route context renders the movable Avatar, Identity, and Biography leaves without the retired wrapper',
		false === strpos( $route_rendered, 'wp-block-axismundi-account-header' )
			&& false !== strpos( $route_rendered, 'wp-block-axismundi-actor-avatar' )
			&& false !== strpos( $route_rendered, 'wp-block-axismundi-actor-identity' )
			&& false !== strpos( $route_rendered, 'wp-block-axismundi-actor-biography' )
	);
	ax_ah_assert(
		$ax_ah_results,
		'route context renders the current Actor without publishing an email or website field',
		false !== strpos( $route_rendered, 'Alice Header' )
			&& false === strpos( $route_rendered, 'ax-actor-biography__website' )
			&& false === strpos( $route_rendered, 'ax_ah_alice-private@example.test' )
	);
	$header_cover = do_blocks( '<!-- wp:axismundi/object-featured-image {"showPlaceholder":true} /-->' );
	ax_ah_assert(
		$ax_ah_results,
		'a profile banner inside a Core Group resolves the route Actor header rather than falling back to an empty Object cover',
		false !== strpos( $header_cover, wp_get_attachment_url( $header_id ) ) && false === strpos( $header_cover, 'is-empty' )
	);

	$GLOBALS['axismundi_actors_profile_actor'] = $bob;
	$second_route_rendered = do_blocks( '<!-- wp:group {"className":"ax-actor-profile__header"} --><!-- wp:axismundi/actor-identity /--><!-- /wp:group -->' );
	ax_ah_assert(
		$ax_ah_results,
		'Actor leaves resolve the current route directly rather than a saved wrapper actorId',
		false !== strpos( $second_route_rendered, 'Bob Header' ) && false === strpos( $second_route_rendered, 'Alice Header' )
	);

	$GLOBALS['axismundi_actors_profile_actor'] = $alice;
	$no_handle_rendered = do_blocks( '<!-- wp:axismundi/actor-identity {"showHandle":false} /-->' );
	ax_ah_assert( $ax_ah_results, 'actor-identity showHandle:false hides the federated handle', false === strpos( $no_handle_rendered, 'ax-actor-identity__handle' ) );

	$with_type_rendered = do_blocks( '<!-- wp:axismundi/actor-identity {"showTypeBadge":true} /-->' );
	ax_ah_assert( $ax_ah_results, 'actor-identity showTypeBadge:true renders the Actor type badge', false !== strpos( $with_type_rendered, 'ax-actor-identity__type' ) );

	$carol_user_id = (int) wp_insert_user(
		array(
			'user_login' => 'ax_ah_carol',
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		)
	);
	$ax_ah_users[] = $carol_user_id;
	$carol = axismundi_actors_ensure_for_user( $carol_user_id );
	ax_ah_assert( $ax_ah_results, 'fixture creates one internal local Person for the preview-notice case', $carol instanceof Axismundi_Actor && 'internal' === $carol->get_status() );
	if ( $carol instanceof Axismundi_Actor ) {
		$ax_ah_ids[] = $carol->get_identity_id();
	}

	$GLOBALS['axismundi_actors_profile_actor'] = $carol;
	$preview_rendered = do_blocks( '<!-- wp:axismundi/actor-biography /-->' );
	ax_ah_assert( $ax_ah_results, 'a non-public route actor renders the biography private-preview notice', false !== strpos( $preview_rendered, 'Private preview' ) );
} finally {
	$GLOBALS['axismundi_actors_profile_actor'] = null;
	foreach ( array_unique( $ax_ah_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_ah_users as $fixture_user_id ) {
		if ( get_userdata( $fixture_user_id ) ) {
			wp_delete_user( $fixture_user_id );
		}
	}
	foreach ( $ax_ah_atts as $attachment_id ) {
		wp_delete_attachment( $attachment_id, true );
	}
}

$ax_ah_failures = count( array_filter( $ax_ah_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ah_results ), $ax_ah_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ah_failures > 0 ? 1 : 0 );
}
exit( $ax_ah_failures > 0 ? 1 : 0 );
