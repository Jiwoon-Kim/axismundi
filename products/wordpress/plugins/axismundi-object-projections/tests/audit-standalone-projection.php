<?php
/**
 * Representation without a ledger (dev-only).
 *
 * Actors plus this plugin must be enough for another server to read what this site
 * publishes. Activities owns authored visibility and the Activity ledger; the Bridge owns
 * transport. Neither may be required for a published post to answer as ActivityStreams,
 * or for an Actor document to name the collections ActivityPub requires it to name.
 *
 * These checks pin that contract from the outside, at the seams a reader would touch.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_sp_results = array();
$ax_sp_posts   = array();

/** @param bool[] $results Results. */
function ax_sp_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_sp_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$ax_sp_user = $ax_sp_user ? (int) $ax_sp_user[0]->ID : 0;
	$ax_sp_actor = $ax_sp_user && function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $ax_sp_user ) : null;
	if ( ! $ax_sp_actor instanceof Axismundi_Actor ) {
		ax_sp_assert( $ax_sp_results, 'an administrator Actor exists to attribute a post to', false );
		printf( "\n%d/%d passed\n", 0, 1 );
		return;
	}

	$ax_sp_post_id = wp_insert_post(
		array(
			'post_title'   => 'Standalone projection fixture',
			'post_content' => 'Published means public.',
			'post_status'  => 'publish',
			'post_author'  => $ax_sp_user,
		)
	);
	$ax_sp_posts[] = $ax_sp_post_id;
	$ax_sp_post    = get_post( $ax_sp_post_id );

	// The default this plugin owns: a published post carries public addressing, and the
	// meta default says so without anyone authoring a visibility.
	ax_sp_assert( $ax_sp_results, 'a published post defaults to public visibility', 'public' === axismundi_op_post_visibility( $ax_sp_post ) );

	$ax_sp_default = axismundi_op_default_public_audience( $ax_sp_actor, array( 'https://remote.example/users/mentioned' ) );
	ax_sp_assert(
		$ax_sp_results,
		'the ledger-free default addresses the public collection in `to`',
		array( 'https://www.w3.org/ns/activitystreams#Public' ) === $ax_sp_default['to'] && true === $ax_sp_default['public']
	);
	ax_sp_assert(
		$ax_sp_results,
		'the ledger-free default carries followers and mentions in `cc`',
		in_array( axismundi_op_actor_followers_url( $ax_sp_actor ), $ax_sp_default['cc'], true )
			&& in_array( 'https://remote.example/users/mentioned', $ax_sp_default['cc'], true )
	);

	// The Actor document names the collections ActivityPub requires of an actor. These are
	// representation-owned addresses, so they are present whatever else is installed.
	$ax_sp_actor_doc = axismundi_op_actor_transform( $ax_sp_actor );
	ax_sp_assert(
		$ax_sp_results,
		'the Actor document names outbox, followers and following',
		is_array( $ax_sp_actor_doc )
			&& axismundi_op_actor_outbox_url( $ax_sp_actor ) === ( $ax_sp_actor_doc['outbox'] ?? '' )
			&& axismundi_op_actor_followers_url( $ax_sp_actor ) === ( $ax_sp_actor_doc['followers'] ?? '' )
			&& '' !== (string) ( $ax_sp_actor_doc['following'] ?? '' )
	);

	// The advertised Outbox address answers, and says how many Activities it holds.
	$ax_sp_outbox = axismundi_op_transform_collection( new Axismundi_OP_Actor_Outbox( $ax_sp_actor ) );
	ax_sp_assert(
		$ax_sp_results,
		'the advertised Outbox answers as an OrderedCollection with a count',
		is_array( $ax_sp_outbox )
			&& 'OrderedCollection' === ( $ax_sp_outbox['type'] ?? '' )
			&& array_key_exists( 'totalItems', $ax_sp_outbox )
			&& count( (array) ( $ax_sp_outbox['orderedItems'] ?? array() ) ) === (int) $ax_sp_outbox['totalItems']
	);

	// A published post is projected, and anonymous negotiation may disclose it.
	$ax_sp_article = axismundi_op_transform_object( $ax_sp_post );
	ax_sp_assert(
		$ax_sp_results,
		'a published post projects as an Article addressed to the public collection',
		is_array( $ax_sp_article )
			&& 'Article' === ( $ax_sp_article['type'] ?? '' )
			&& in_array( 'https://www.w3.org/ns/activitystreams#Public', (array) ( $ax_sp_article['to'] ?? array() ), true )
	);
	ax_sp_assert( $ax_sp_results, 'anonymous ActivityStreams negotiation may disclose it', axismundi_op_post_article_publicly_readable( $ax_sp_post ) );

	// The one thing this layer must not guess at: a non-public authored visibility is not
	// resolved without the policy owner. With Activities present it resolves as authored.
	update_post_meta( $ax_sp_post_id, AXISMUNDI_OP_POST_VISIBILITY_META, 'followers' );
	$ax_sp_followers_only = axismundi_op_post_article_audience( get_post( $ax_sp_post_id ), array() );
	ax_sp_assert(
		$ax_sp_results,
		function_exists( 'axismundi_act_resolve_audience' )
			? 'a followers-only post resolves through Activities and is not public'
			: 'a followers-only post is refused rather than guessed at without Activities',
		function_exists( 'axismundi_act_resolve_audience' )
			? ( is_array( $ax_sp_followers_only ) && true !== $ax_sp_followers_only['public'] )
			: ( is_wp_error( $ax_sp_followers_only ) && 'ax_op_post_audience_policy' === $ax_sp_followers_only->get_error_code() )
	);
} finally {
	foreach ( $ax_sp_posts as $ax_sp_post_id ) {
		if ( $ax_sp_post_id && ! is_wp_error( $ax_sp_post_id ) ) {
			wp_delete_post( (int) $ax_sp_post_id, true );
		}
	}
}

printf( "\n%d/%d passed\n", count( array_filter( $ax_sp_results ) ), count( $ax_sp_results ) );
