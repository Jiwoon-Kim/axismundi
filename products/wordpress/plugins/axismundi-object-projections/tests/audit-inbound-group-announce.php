<?php
/**
 * FEP-1b12 inbound Group Announce regression (dev-only; dist-excluded).
 *
 * A Group's embedded Create is preserved and cached only after that Group endorses an
 * internally consistent submission addressed to itself. This does not test presentation: a
 * followers-only Announce belongs in a recipient-scoped timeline, not a public profile feed.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once __DIR__ . '/../includes/remote-objects.php';
require_once __DIR__ . '/../includes/inbox-observations.php';

global $wpdb;
$ax_iga_results = array();
$ax_iga_uris    = array();
$ax_iga_ids     = array();
$ax_iga_object_uris = array();

/** Record one audit assertion. */
function ax_iga_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create a remote Actor suitable for one inbound fixture. */
function ax_iga_remote_actor( array &$ids, string $type, string $suffix ) {
	$uri   = 'https://example.com/' . strtolower( $type ) . 's/' . $suffix;
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => $type,
			'preferred_username' => $suffix,
			'display_name'       => $suffix,
			'profile_url'        => $uri,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => $type, 'preferredUsername' => $suffix, 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$ids[] = $actor->get_identity_id();
	}
	return $actor;
}

/** Build one Group-addressed embedded Create without changing any member. */
function ax_iga_create( Axismundi_Actor $author, Axismundi_Actor $group, string $suffix ) : array {
	$object_uri = 'https://example.com/articles/' . $suffix;
	return array(
		'id'       => 'https://example.com/activities/create-' . $suffix,
		'type'     => 'Create',
		'actor'    => $author->get_uri(),
		'audience' => $group->get_uri(),
		'object'   => array(
			'id'           => $object_uri,
			'type'         => 'Article',
			'attributedTo' => $author->get_uri(),
			'name'         => 'Forwarded topic',
			'content'      => '<p>Forwarded without rewriting.</p>',
			'mediaType'    => 'text/html',
			'audience'     => $group->get_uri(),
		),
	);
}

try {
	$suffix = strtolower( wp_generate_password( 10, false, false ) );
	$group  = ax_iga_remote_actor( $ax_iga_ids, 'Group', 'group_' . $suffix );
	$author = ax_iga_remote_actor( $ax_iga_ids, 'Person', 'author_' . $suffix );
	$booster = ax_iga_remote_actor( $ax_iga_ids, 'Person', 'booster_' . $suffix );

	$create = $group instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? ax_iga_create( $author, $group, $suffix ) : array();
	$create['proof'] = array( 'type' => 'DataIntegrityProof', 'cryptosuite' => 'eddsa-jcs-2022', 'proofValue' => 'zfuture' );
	$create['x:future'] = array( 'preserve' => true );
	$object_uri = (string) ( $create['object']['id'] ?? '' );
	$ax_iga_object_uris[] = $object_uri;
	$outer_uri = 'https://example.com/activities/announce-' . $suffix;
	$outer = $group instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $outer_uri, 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $create, 'to' => array( 'https://example.net/users/follower' ) ), 'inbound' )
		: new WP_Error( 'fixture' );
	$ax_iga_uris = array( $outer_uri, (string) ( $create['id'] ?? '' ) );
	$inner = function_exists( 'axismundi_act_get' ) ? axismundi_act_get( (string) ( $create['id'] ?? '' ) ) : null;
	$cached = '' !== $object_uri && function_exists( 'axismundi_op_get_remote_object' ) ? axismundi_op_get_remote_object( $object_uri ) : null;
	ax_iga_assert(
		$ax_iga_results,
		'a remote Group Announce preserves and caches its complete Group-addressed Create, including unknown future members',
		$outer instanceof Axismundi_Activity && $inner instanceof Axismundi_Activity && $inner->get_payload() === $create
			&& is_array( $cached ) && 'Forwarded topic' === (string) ( $cached['name'] ?? '' )
	);

	$person_create = $group instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? ax_iga_create( $author, $group, $suffix . '-person' ) : array();
	$person_outer_uri = 'https://example.com/activities/announce-person-' . $suffix;
	$person_outer = $booster instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $person_outer_uri, 'type' => 'Announce', 'actor' => $booster->get_uri(), 'object' => $person_create ), 'inbound' )
		: new WP_Error( 'fixture' );
	$ax_iga_uris[] = $person_outer_uri;
	ax_iga_assert(
		$ax_iga_results,
		'a Person boost never gains Group approval semantics',
		$person_outer instanceof Axismundi_Activity && null === axismundi_act_get( (string) ( $person_create['id'] ?? '' ) )
	);

	$elsewhere_create = $group instanceof Axismundi_Actor && $author instanceof Axismundi_Actor ? ax_iga_create( $author, $group, $suffix . '-elsewhere' ) : array();
	$elsewhere_create['audience'] = 'https://example.com/groups/elsewhere';
	$elsewhere_create['object']['audience'] = 'https://example.com/groups/elsewhere';
	$elsewhere_outer_uri = 'https://example.com/activities/announce-elsewhere-' . $suffix;
	$elsewhere_outer = $group instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $elsewhere_outer_uri, 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $elsewhere_create ), 'inbound' )
		: new WP_Error( 'fixture' );
	$ax_iga_uris[] = $elsewhere_outer_uri;
	ax_iga_assert(
		$ax_iga_results,
		'a Group cannot endorse a Create that does not address that Group as its community',
		$elsewhere_outer instanceof Axismundi_Activity && null === axismundi_act_get( (string) ( $elsewhere_create['id'] ?? '' ) )
	);
} finally {
	$activities = axismundi_act_activities_table();
	$objects = axismundi_op_remote_objects_table();
	$identities = axismundi_actors_identities_table();
	$actors = axismundi_actors_actors_table();
	$endpoints = axismundi_actors_endpoints_table();
	foreach ( array_filter( array_unique( $ax_iga_uris ) ) as $uri ) {
		$wpdb->delete( $activities, array( 'activity_uri_hash' => hash( 'sha256', $uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_filter( array_unique( $ax_iga_object_uris ) ) as $object_uri ) {
		$wpdb->delete( $objects, array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_iga_ids as $identity_id ) {
		$wpdb->delete( $endpoints, array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $actors, array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $identities, array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_iga_failed = count( array_filter( $ax_iga_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_iga_results ) - $ax_iga_failed, count( $ax_iga_results ) );
exit( $ax_iga_failed > 0 ? 1 : 0 );
