<?php
/**
 * Shared audience policy: one authored visibility choice → AS2 to/cc addressing.
 *
 * Activities owns audience *policy* only. The resolver is a pure function: it
 * never resolves recipient inboxes (that is the Bridge transport layer's
 * responsibility), never reads or writes the ledger, and never mutates the
 * Actor. Article and Note both call this one resolver so an Object and its
 * wrapping Create always share the same to/cc snapshot.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** The ActivityStreams public audience collection. */
function axismundi_act_public_audience_uri() : string {
	return 'https://www.w3.org/ns/activitystreams#Public';
}

/**
 * Canonical visibility label for one authored or stored value, or '' if unknown.
 *
 * Accepts the internal labels plus common host synonyms (Mastodon
 * public/unlisted/private/direct, Misskey home/specified) and collapses them to
 * the four canonical values: public, unlisted, followers, mentioned.
 */
function axismundi_act_canonical_visibility( string $visibility ) : string {
	$map = array(
		'public'       => 'public',
		'quiet_public' => 'unlisted',
		'quiet-public' => 'unlisted',
		'unlisted'     => 'unlisted',
		'home'         => 'unlisted',
		'followers'    => 'followers',
		'private'      => 'followers',
		'mentioned'    => 'mentioned',
		'direct'       => 'mentioned',
		'specified'    => 'mentioned',
	);
	$key = strtolower( trim( $visibility ) );
	return $map[ $key ] ?? '';
}

/**
 * Resolve one authored visibility choice into AS2 to/cc addressing.
 *
 * | visibility | to               | cc                        |
 * |------------|------------------|---------------------------|
 * | public     | Public           | followers + mentions      |
 * | unlisted   | followers        | Public + mentions         |
 * | followers  | followers        | mentions                  |
 * | mentioned  | mentions         | —                         |
 *
 * followers is addressed by the Actor's followers Collection URI, never by an
 * expanded list of follower inboxes. Only public and unlisted carry the Public
 * collection, so followers-only and mentioned-only objects stay non-public and
 * must fail closed to anonymous JSON-LD requests downstream.
 *
 * @param string[] $mention_actor_uris Explicitly mentioned Actor URIs.
 * @return array{visibility:string,to:string[],cc:string[],public:bool}|WP_Error
 */
function axismundi_act_resolve_audience( Axismundi_Actor $actor, string $visibility, array $mention_actor_uris = array() ) {
	if ( ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return new WP_Error( 'ax_act_audience_actor', __( 'The audience requires a public local Actor.', 'axismundi-activities' ) );
	}
	$visibility = axismundi_act_canonical_visibility( $visibility );
	if ( '' === $visibility ) {
		return new WP_Error( 'ax_act_audience_visibility', __( 'The audience visibility is not recognized.', 'axismundi-activities' ) );
	}

	$mentions = array();
	foreach ( $mention_actor_uris as $member ) {
		$uri = axismundi_act_member_uri( $member );
		if ( '' === $uri ) {
			return new WP_Error( 'ax_act_audience_mention', __( 'Every mentioned recipient must have a valid Actor URI.', 'axismundi-activities' ) );
		}
		$mentions[] = $uri;
	}
	$mentions = array_values( array_unique( $mentions ) );

	if ( 'mentioned' === $visibility && empty( $mentions ) ) {
		return new WP_Error( 'ax_act_audience_mentioned_empty', __( 'A mentioned-only object requires at least one recipient.', 'axismundi-activities' ) );
	}

	$followers = '';
	if ( in_array( $visibility, array( 'public', 'unlisted', 'followers' ), true ) ) {
		/** Let the representation owner supply the stable Followers collection address. */
		$followers = (string) apply_filters( 'axismundi_act_actor_followers_uri', '', $actor );
		if ( '' === $followers ) {
			return new WP_Error( 'ax_act_audience_followers', __( 'The Actor followers collection URI is unavailable.', 'axismundi-activities' ) );
		}
	}

	$public = axismundi_act_public_audience_uri();
	switch ( $visibility ) {
		case 'public':
			$to = array( $public );
			$cc = array_merge( array( $followers ), $mentions );
			break;
		case 'unlisted':
			$to = array( $followers );
			$cc = array_merge( array( $public ), $mentions );
			break;
		case 'followers':
			$to = array( $followers );
			$cc = $mentions;
			break;
		default:
			$to = $mentions;
			$cc = array();
			break;
	}

	$to = array_values( array_unique( array_filter( $to ) ) );
	$cc = array_values( array_unique( array_filter( $cc ) ) );

	return array(
		'visibility' => $visibility,
		'to'         => $to,
		'cc'         => $cc,
		'public'     => in_array( $public, $to, true ) || in_array( $public, $cc, true ),
	);
}
