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

/**
 * Read one stored to/cc pair back as the visibility it was authored with.
 *
 * The inverse of `axismundi_act_resolve_audience()`, and deliberately living beside it: a reader
 * that derived the same four labels from its own reading of the table would agree with the writer
 * only until one of them changed.
 *
 * It is inverse-where-it-can-be and honest where it cannot. Public in `to` and public in `cc` are
 * the two arrangements only this table produces, so those two read back exactly. Telling
 * followers-only from mentioned-only needs the author's followers collection address, which is
 * known for a local Actor and often not for a remote one — a peer addresses its own followers
 * collection and nothing obliges us to have seen that Actor. Without it the honest answer is that
 * the object is restricted, not which restriction, so this returns `limited` rather than picking
 * the likelier of the two. A guess here would be a claim about who can read something.
 *
 * @param string[] $to             Stored `to` audience.
 * @param string[] $cc             Stored `cc` audience.
 * @param string   $followers_uri  Author's followers collection URI, when known.
 * @return string One of public, unlisted, followers, mentioned, limited.
 */
function axismundi_act_audience_visibility( array $to, array $cc, string $followers_uri = '' ) : string {
	$public = axismundi_act_public_audience_uri();
	$legacy = array( $public, 'as:Public', 'Public' );
	$normalize = static function ( array $members ) : array {
		$uris = array();
		foreach ( $members as $member ) {
			$uri = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $member ) : '';
			if ( '' === $uri && is_scalar( $member ) ) {
				$uri = (string) $member;
			}
			if ( '' !== $uri ) {
				$uris[] = $uri;
			}
		}
		return $uris;
	};
	$to = $normalize( $to );
	$cc = $normalize( $cc );

	if ( array_intersect( $legacy, $to ) ) {
		return 'public';
	}
	if ( array_intersect( $legacy, $cc ) ) {
		return 'unlisted';
	}
	if ( '' === $followers_uri ) {
		return 'limited';
	}
	return in_array( $followers_uri, $to, true ) || in_array( $followers_uri, $cc, true ) ? 'followers' : 'mentioned';
}

/**
 * Which Groups, if any, one Object was posted into.
 *
 * The question "is this part of a community" has to be answerable for a remote Object we have only
 * observed, or a Person's Community surface can never include anything from Lemmy or NodeBB. That
 * rules out asking a local product whether it recognises the Topic: those predicates are about our
 * own Forum tables, which is exactly why Group context has been invisible on remote profiles.
 *
 * So the evidence is the addressing the Object arrived with. `audience` is the FEP-1b12 field for
 * naming the community an Object belongs to and Lemmy populates it; `to`/`cc` are read as well
 * because a Group is frequently addressed there too. Whether a URI *is* a Group is not this
 * function's judgement and not Forum's either — Actors holds the registry and answers by type.
 *
 * A product that owns a thread graph contributes the one case addressing cannot show: a reply whose
 * parent lives in a Group, where the reply itself names only its parent.
 *
 * `primary_group_uri` is deliberately conservative. It is filled when the author said which
 * community they meant — an explicit `audience` — or when there is only one candidate and no
 * ambiguity to resolve. With several Groups and no `audience`, picking the first would let a card
 * name the wrong community with total confidence, so it names none and the caller shows nothing
 * rather than something false. Cross-posting is where that would bite, and it is coming.
 *
 * @param array<string,mixed> $payload    AS2 Object, or an Activity whose `object` is embedded.
 * @param string              $object_uri Canonical Object URI, when known.
 * @return array{has_group_context:bool,group_uris:string[],primary_group_uri:string}
 */
function axismundi_act_group_context( array $payload, string $object_uri = '' ) : array {
	$is_group = static function ( string $uri ) : bool {
		if ( '' === $uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
			return false;
		}
		$actor = axismundi_actors_get_by_uri( $uri );
		return $actor instanceof Axismundi_Actor && 'Group' === $actor->get_type();
	};
	$listed = static function ( $value ) : array {
		if ( null === $value ) {
			return array();
		}
		$members = is_array( $value ) && array_is_list( $value ) ? $value : array( $value );
		$uris    = array();
		foreach ( $members as $member ) {
			$uri = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $member ) : '';
			if ( '' === $uri && is_scalar( $member ) ) {
				$uri = (string) $member;
			}
			if ( '' !== $uri ) {
				$uris[] = $uri;
			}
		}
		return $uris;
	};

	/*
	 * The envelope and the Object it carries, read together.
	 *
	 * A `Create` addresses Public at its root and leaves `audience` on the embedded Note, which is
	 * the ordinary shape and the one Lemmy sends. Reading only the root finds `to: [Public]` and
	 * concludes there is no community — losing exactly the case this classifier exists for. An
	 * Object fetched on its own has no envelope, so the same merge covers both by looking in each
	 * place and caring about neither.
	 */
	$embedded  = is_array( $payload['object'] ?? null ) ? $payload['object'] : array();
	$declared  = array_values(
		array_filter(
			array_merge( $listed( $payload['audience'] ?? null ), $listed( $embedded['audience'] ?? null ) ),
			$is_group
		)
	);
	$addressed = array_values(
		array_filter(
			array_merge(
				$listed( $payload['to'] ?? null ),
				$listed( $payload['cc'] ?? null ),
				$listed( $embedded['to'] ?? null ),
				$listed( $embedded['cc'] ?? null )
			),
			$is_group
		)
	);
	/**
	 * Let a product that owns a thread graph name the Groups a reply belongs to.
	 *
	 * A reply addresses its parent, not the community, so this is the one form of Group context
	 * that cannot be read off the wire. Forum answers it for threads it knows; nothing here needs
	 * to understand what a Topic is.
	 *
	 * @param string[]            $uris       Group URIs contributed so far.
	 * @param string              $object_uri Canonical Object URI.
	 * @param array<string,mixed> $payload    Object or Activity payload.
	 */
	$threaded = (array) apply_filters( 'axismundi_act_group_context_uris', array(), $object_uri, $payload );
	$threaded = array_values( array_filter( array_map( 'strval', $threaded ), $is_group ) );

	$all = array_values( array_unique( array_merge( $declared, $addressed, $threaded ) ) );

	$primary = '';
	if ( 1 === count( $declared ) ) {
		$primary = $declared[0];
	} elseif ( empty( $declared ) && 1 === count( $all ) ) {
		$primary = $all[0];
	}

	return array(
		'has_group_context' => ! empty( $all ),
		'group_uris'        => $all,
		'primary_group_uri' => $primary,
	);
}
