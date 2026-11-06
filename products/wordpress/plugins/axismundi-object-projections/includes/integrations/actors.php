<?php
/**
 * First-party Axismundi Actor projection.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Read-only source for one Actor's public Outbox projection. */
final class Axismundi_OP_Actor_Outbox {
	public function __construct( private Axismundi_Actor $actor, private bool $page = false, private string $cursor = '', private ?Closure $reader = null, public readonly bool $owner_view = false, public readonly string $activity_type = '' ) {}
	public function get_actor() : Axismundi_Actor { return $this->actor; }
	public function is_page() : bool { return $this->page; }
	public function get_cursor() : string { return $this->cursor; }
	public function get_reader() : ?Closure { return $this->reader; }
}

/** Read-only source for one Actor's follower-count projection. */
final class Axismundi_OP_Actor_Followers {
	public function __construct( private Axismundi_Actor $actor, private string $collection = 'followers', private int $page = 0 ) {}
	public function get_actor() : Axismundi_Actor { return $this->actor; }
	public function get_collection() : string { return 'following' === $this->collection ? 'following' : 'followers'; }
	public function get_page() : int { return $this->page; }
}

/** Stable public Outbox URI owned by the representation layer. */
function axismundi_op_actor_outbox_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/outbox' );
}

/**
 * Members per Outbox page.
 *
 * Measured 2026-10-18: the ledger page itself costs 2 ms at twenty members and 3 ms at
 * fifty, nine milliseconds for a page that has to walk a long private run, and a served
 * page weighs nine kilobytes. The expensive part of a page is the request around it, not
 * the page, so the only thing this number buys is fewer round trips -- fifty turns a
 * thousand-activity Outbox from fifty-two requests into twenty-one. It is deliberately a
 * constant rather than a caller argument: a collection whose page size varies by caller
 * hands peers cursors that mean different things.
 */
const AXISMUNDI_OP_OUTBOX_PAGE_SIZE = 50;

/**
 * Members per Outbox page, after instance policy.
 *
 * Filterable because this is a policy number rather than a protocol one, and because a
 * test that depends on the shipped default asserts the default rather than the paging:
 * measured 2026-10-18, raising the constant alone turned four continuation checks red,
 * since a twenty-one-Activity fixture stopped having a second page at all.
 *
 * @since 0.1.3
 * @param int $size Members per page.
 */
function axismundi_op_outbox_page_size() : int {
	$size = (int) apply_filters( 'axismundi_op_outbox_page_size', AXISMUNDI_OP_OUTBOX_PAGE_SIZE );
	return max( 1, min( 50, $size ) );
}

/**
 * Stable page URI for a cursor-positioned Actor Outbox page.
 *
 * The cursor is an opaque token from the Activity ledger and currently contains a space,
 * because it carries a SQL datetime. `add_query_arg()` does not encode what it is given --
 * `build_query()` calls `_http_build_query()` with `$urlencode = false` -- so the raw token
 * reached the published `next` link. Measured 2026-10-18: a conformant client refused the
 * whole chain as an invalid URL while the server answered the percent-encoded form with
 * 200, so the paging worked and the link to it did not. Encoding belongs here, at the
 * boundary that owns the URL, rather than in the token's format, which the ledger owns.
 */
function axismundi_op_actor_outbox_page_url( Axismundi_Actor $actor, string $cursor = '', string $activity_type = '' ) : string {
	$args = array( 'page' => 1 );
	if ( '' !== $activity_type ) {
		$args['activity_type'] = rawurlencode( $activity_type );
	}
	$url = add_query_arg( $args, axismundi_op_actor_outbox_url( $actor ) );
	return '' === $cursor ? $url : $url . '&cursor=' . rawurlencode( $cursor );
}

/** Stable Followers URI owned by the representation layer. */
function axismundi_op_actor_followers_url( Axismundi_Actor $actor ) : string {
	return axismundi_op_actor_follow_collection_url( $actor, 'followers' );
}

/** Stable Followers/Following URI owned by the representation layer. */
function axismundi_op_actor_follow_collection_url( Axismundi_Actor $actor, string $collection, int $page = 0 ) : string {
	$collection = 'following' === $collection ? 'following' : 'followers';
	$url = rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/' . $collection );
	return $page > 0 ? add_query_arg( 'page', $page, $url ) : $url;
}

/** Supply Activities with the representation-owned Followers address. */
function axismundi_op_supply_actor_followers_url( string $uri, Axismundi_Actor $actor ) : string {
	return axismundi_op_actor_followers_url( $actor );
}
add_filter( 'axismundi_act_actor_followers_uri', 'axismundi_op_supply_actor_followers_url', 10, 2 );

/** Public representation gate for one local Actor. */
function axismundi_op_actor_visible( Axismundi_Actor $actor ) : bool {
	return function_exists( 'axismundi_actors_is_public_profile' )
		&& axismundi_actors_is_public_profile( $actor );
}

/** Project one Axismundi Actor; transport fields are supplied by a bridge filter. */
function axismundi_op_actor_transform( Axismundi_Actor $actor ) : array {
	$profile = function_exists( 'axismundi_actors_profile_data' )
		? axismundi_actors_profile_data( $actor )
		: array();
	$language = function_exists( 'axismundi_actors_serialization_language' )
		? axismundi_actors_serialization_language( $actor )
		: $actor->get_default_language();
	$name = function_exists( 'axismundi_actors_resolve_text' )
		? axismundi_actors_resolve_text( $actor, 'name', $language )
		: (string) ( $profile['name'] ?? $actor->get_display_name() );
	$summary = function_exists( 'axismundi_actors_resolve_text' )
		? axismundi_actors_resolve_text( $actor, 'summary', $language )
		: (string) ( $profile['summary'] ?? '' );
	$object  = array(
		'id'                      => $actor->get_uri(),
		'type'                    => $actor->get_type(),
		'url'                     => $actor->get_profile_url(),
		'preferredUsername'       => $actor->get_preferred_username(),
		'name'                    => $name,
		'summary'                 => $summary,
		'manuallyApprovesFollowers' => (bool) apply_filters( 'axismundi_op_actor_manually_approves_followers', true === $actor->get_policy_flag( 'manually_approves_followers' ), $actor ),
	);
	if ( $actor->is_local() && function_exists( 'axismundi_act_get_liked_collection_page' ) ) {
		$object['liked'] = axismundi_op_actor_liked_url( $actor );
	}
	/*
	 * Language maps beside the scalars, not instead of them.
	 *
	 * This is deliberately the opposite of the Article and Note decision, and the reason is
	 * the audience. An Object is fetched by a server that already decided to talk to us; an
	 * Actor is the first thing anyone resolves, and a profile that renders blank on a
	 * follower's instance is not a purity problem, it is an invisible account. Mastodon
	 * publishes both for exactly this reason — measured on the wire, its `content` and
	 * `contentMap` carry byte-identical values.
	 *
	 * `und` never enters a map. "Undetermined" is not a language to tag text with; a value
	 * whose language nobody established belongs in the scalar, where it makes no claim.
	 */
	/*
	 * Read through the name map rather than the raw text store, so `nameMap` says what the scalar
	 * says: a Person's structured name is overlaid there, and reading the store directly would put a
	 * different Korean name in the map than the one resolved into `name`.
	 */
	if ( function_exists( 'axismundi_actors_name_map' ) ) {
		$maps = array( 'name' => array(), 'summary' => array() );
		foreach ( axismundi_actors_name_map( $actor ) as $language => $by_field ) {
			$language = (string) $language;
			if ( '' === $language || 'und' === strtolower( $language ) ) {
				continue;
			}
			foreach ( array( 'name', 'summary' ) as $field ) {
				$value = (string) ( $by_field[ $field ] ?? '' );
				if ( '' !== $value ) {
					$maps[ $field ][ $language ] = $value;
				}
			}
		}
		foreach ( array( 'name' => 'nameMap', 'summary' => 'summaryMap' ) as $field => $member ) {
			// Only when it says something the scalar does not: a map with one entry equal to
			// the scalar is bytes every receiver stores to learn nothing.
			if ( array() !== $maps[ $field ] && array( (string) $object[ $field ] ) !== array_values( $maps[ $field ] ) ) {
				$object[ $member ] = $maps[ $field ];
			}
		}
	}

	if ( null !== $actor->get_policy_flag( 'discoverable' ) ) {
		$object['discoverable'] = $actor->get_policy_flag( 'discoverable' );
	}
	if ( null !== $actor->get_policy_flag( 'indexable' ) ) {
		$object['indexable'] = $actor->get_policy_flag( 'indexable' );
	}
	if ( '' !== $actor->get_published_at() ) {
		$object['published'] = mysql2date( DATE_RFC3339, $actor->get_published_at(), false );
	}
	$avatar_id = $actor->get_avatar_attachment_id();
	$header_id = $actor->get_header_attachment_id();
	$avatar    = $avatar_id > 0 ? wp_get_attachment_image_url( $avatar_id, 'medium' ) : (string) ( $profile['avatar'] ?? '' );
	$header    = $header_id > 0 ? wp_get_attachment_image_url( $header_id, 'large' ) : '';
	if ( '' !== $avatar ) {
		$object['icon'] = array( 'type' => 'Image', 'url' => esc_url_raw( $avatar ) );
	}
	if ( '' !== $header ) {
		$object['image'] = array( 'type' => 'Image', 'url' => esc_url_raw( $header ) );
	}
	if ( function_exists( 'axismundi_actors_profile_field_attachments' ) ) {
		$attachments = axismundi_actors_profile_field_attachments( $actor );
		if ( ! empty( $attachments ) ) {
			$object['attachment'] = $attachments;
		}
	}

	/*
	 * The custom emoji this profile uses, declared for the document as a whole.
	 *
	 * A display name and a biography are published text like any other, so a `:shortcode:`
	 * in either needs the same declaration a Note's body does — without it the name arrives
	 * elsewhere as a bare word. Profile field labels count too: Mastodon renders emoji in
	 * them, and a receiver can only do that if we say what they are.
	 *
	 * Read from the stored texts rather than only from the projected scalars, because those
	 * are not the same set. This document currently publishes one `name` and one `summary`,
	 * resolved for a single language, while the store holds a row per language. Gathering
	 * only what is projected would be correct today and quietly wrong the moment
	 * `nameMap`/`summaryMap` ship: a Korean biography's emoji would go undeclared because
	 * the English one happened to win the resolution.
	 *
	 * There is no language axis to reconcile — `tag[]` has none and needs none, since the
	 * same `:axismundi:` in a Korean and an English biography is one emoji. So the union
	 * over every stored variant is both the answer now and the answer later.
	 */
	if ( function_exists( 'axismundi_emoji_outbound_tags' ) ) {
		$emoji_texts = array( (string) $object['name'], (string) $object['summary'] );
		if ( function_exists( 'axismundi_actors_get_text_map' ) ) {
			// Keyed language => field => value; every value is published text somewhere.
			foreach ( axismundi_actors_get_text_map( $actor->get_identity_id() ) as $by_field ) {
				foreach ( (array) $by_field as $value ) {
					$emoji_texts[] = (string) $value;
				}
			}
		}
		foreach ( (array) ( $object['attachment'] ?? array() ) as $field ) {
			$emoji_texts[] = (string) ( $field['name'] ?? '' );
		}
		$emoji_tags = axismundi_emoji_outbound_tags( $emoji_texts );
		if ( ! empty( $emoji_tags ) ) {
			$object['tag'] = array_merge( (array) ( $object['tag'] ?? array() ), $emoji_tags );
		}
	}
	/*
	 * The collection addresses belong to the Actor document, not to whichever plugin
	 * fills them. ActivityPub requires an actor to name its `outbox`, and a reader that
	 * follows the address is answered by this plugin's own routes. Without Activities
	 * those collections are empty rather than absent — an empty OrderedCollection is a
	 * true statement, while a missing `outbox` makes the document not an actor at all.
	 */
	$object['outbox']    = axismundi_op_actor_outbox_url( $actor );
	$object['followers'] = axismundi_op_actor_followers_url( $actor );
	$object['following'] = axismundi_op_actor_follow_collection_url( $actor, 'following' );
	/**
	 * Let an Actor domain add public representation fields without owning the Actor document.
	 *
	 * Community policy uses this for a Group's attributedTo moderator collection. The projection
	 * layer still owns visibility, base identifiers, JSON-LD context, and transport fields.
	 *
	 * @param array<string,mixed> $object Public Actor representation so far.
	 * @param Axismundi_Actor     $actor  Projected Actor.
	 */
	$object = (array) apply_filters( 'axismundi_op_actor_projection_fields', $object, $actor );

	/**
	 * Supply protocol transport properties without transferring representation ownership.
	 *
	 * @param array<string,mixed> $fields inbox/publicKey/endpoints/etc.
	 * @param Axismundi_Actor     $actor  Local Actor.
	 */
	$transport = function_exists( 'axismundi_actors_transport_members' )
		? axismundi_actors_transport_members( $actor )
		: array();
	$fields    = (array) apply_filters( 'axismundi_op_actor_transport_fields', $transport, $actor );
	$allowed   = array_intersect_key( $fields, array_flip( array( 'inbox', 'endpoints', 'publicKey' ) ) );
	return array_merge( $object, $allowed );
}

/** Public visibility gate for an Actor Outbox. */
function axismundi_op_actor_outbox_visible( Axismundi_OP_Actor_Outbox $source ) : bool {
	return axismundi_op_actor_visible( $source->get_actor() );
}

/**
 * Project one Actor's public Activity ledger into an OrderedCollection.
 *
 * The ledger is Activities' to own. When it is absent the collection is served empty:
 * the Actor document advertises this address, and an advertised address that 404s is a
 * broken document. "No Activities recorded here" and "no ledger installed" look the
 * same from outside, which is the honest answer in both cases.
 */
function axismundi_op_actor_outbox_transform( Axismundi_OP_Actor_Outbox $source ) {
	$actor = $source->get_actor();
	/*
	 * Activities owns the member predicate and cursor reader. OP owns the URI and
	 * document shape. Going through the contract keeps that split explicit while
	 * preserving the old empty response when Activities is not installed.
	 */
	$type = $source->activity_type;
	$root = axismundi_op_actor_outbox_url( $actor );
	if ( '' !== $type ) {
		$root = add_query_arg( 'activity_type', rawurlencode( $type ), $root );
	}
	$page  = function_exists( 'axismundi_act_get_public_outbox_page' )
		? axismundi_act_get_public_outbox_page( $actor->get_uri(), axismundi_op_outbox_page_size(), $source->get_cursor(), $source->get_reader(), true, $type )
		: ( function_exists( 'axismundi_op_read_collection_contract' ) && is_array( axismundi_op_collection_contract( 'ap-outbox' ) )
		? axismundi_op_read_collection_contract( 'ap-outbox', $actor->get_uri(), axismundi_op_outbox_page_size(), $source->get_cursor() )
		: array( 'items' => array(), 'next_cursor' => '', 'has_more' => false ) );
	if ( is_wp_error( $page ) ) {
		return $page;
	}
	if ( $source->is_page() ) {
		foreach ( $page['items'] as &$activity ) {
			if ( ! in_array( $activity['type'] ?? '', array( 'Create', 'Update' ), true ) || ! is_array( $activity['object'] ?? null ) ) {
				continue;
			}
			$uri = $activity['object']['id'] ?? '';
			if ( ! is_string( $uri ) || '' === $uri ) {
				unset( $activity['object'] );
				continue;
			}
			$row = axismundi_op_get_local_object( $uri );
			if ( $row && 'tombstone' === $row['object_status'] ) {
				$activity['object'] = ! empty( $row['publicly_readable'] ) ? $row['document'] : $uri;
			} elseif ( ! $row || 'Update' === $activity['type'] || ( empty( $row['publicly_readable'] ) && ! $source->owner_view ) ) {
				$activity['object'] = $uri;
			}
		}
		unset( $activity );
		$out = array(
			'id'           => axismundi_op_actor_outbox_page_url( $actor, $source->get_cursor(), $type ),
			'type'         => 'OrderedCollectionPage',
			'attributedTo' => $actor->get_uri(),
			'url'          => $actor->get_profile_url(),
			'partOf'       => $root,
		);
		$out = axismundi_op_assemble_collection( 'ap-outbox', $out, array( 'items' => $page['items'] ) );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		if ( ! empty( $page['has_more'] ) && '' !== (string) $page['next_cursor'] ) {
			$out['next'] = axismundi_op_actor_outbox_page_url( $actor, (string) $page['next_cursor'], $type );
		}
		return $out;
	}

	$out = array(
		'id'           => $root,
		'type'         => 'OrderedCollection',
		'attributedTo' => $actor->get_uri(),
		'url'          => $actor->get_profile_url(),
	);
	if ( ! empty( $page['items'] ) ) {
		$out['first'] = axismundi_op_actor_outbox_page_url( $actor, '', $type );
	}
	return $out;
}

/** Public disclosure gate for an Actor Followers collection. */
function axismundi_op_actor_followers_visible( Axismundi_OP_Actor_Followers $source ) : bool {
	return axismundi_op_actor_visible( $source->get_actor() );
}

/**
 * Project Follow collections as OrderedCollection roots and pages.
 *
 * The root is always served, whatever the policy, because the Actor document advertises
 * its address and an advertised URI that 404s is a broken document. What varies is how
 * much of it is filled in:
 *
 *   public     - `totalItems` and `first`.
 *   count-only - `totalItems`, no `first`. The absence of `first` is the machine-readable
 *                way to say "do not enumerate this", and it is the same signal we honour
 *                when reading somebody else's collection.
 *   private    - neither. `totalItems` is omitted rather than sent as 0: zero is a claim
 *                that the account has no followers, which is not what was asked for.
 *
 * @param Axismundi_OP_Actor_Followers $source Collection source.
 * @return array<string,mixed>
 */
function axismundi_op_actor_followers_transform( Axismundi_OP_Actor_Followers $source ) {
	$actor  = $source->get_actor();
	$kind   = $source->get_collection();
	$page   = $source->get_page();
	$policy = function_exists( 'axismundi_actors_follow_collections_policy' )
		? axismundi_actors_follow_collections_policy( $actor )
		: 'private';
	/*
	 * Relation state is Activities'. Without it this collection cannot be counted, so it
	 * is served as `private` is: the address answers, and it makes no claim. Sending
	 * `totalItems: 0` instead would assert that the Actor has no followers, which is a
	 * different statement from "this site cannot tell you".
	 */
	if ( ! function_exists( 'axismundi_act_get_follow_collection_page' ) ) {
		$policy = 'private';
	}
	$public = 'public' === $policy;
	$url    = axismundi_op_actor_follow_collection_url( $actor, $kind );
	$data   = $public || 'count-only' === $policy
		? axismundi_op_read_collection_contract( 'ap-' . $kind, 'followers' === $kind ? 'object' : 'subject', $actor->get_uri(), max( 1, $page ), 20 )
		: array( 'items' => array(), 'total' => 0, 'has_more' => false );

	if ( $page > 0 ) {
		// Pages are route-gated to `public` already; this stays defensive rather than
		// trusting that the only caller will always be the one that exists today.
		$out = array(
			'id'           => axismundi_op_actor_follow_collection_url( $actor, $kind, $page ),
			'type'         => 'OrderedCollectionPage',
			'attributedTo' => $actor->get_uri(),
			'url'          => $actor->get_profile_url(),
			'partOf'       => $url,
		);
		$out = axismundi_op_assemble_collection( 'ap-' . $kind, $out, array( 'total' => $data['total'], 'items' => $public ? $data['items'] : array() ), 'public', 'private' !== $policy );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		if ( $public && ! empty( $data['has_more'] ) ) {
			$out['next'] = axismundi_op_actor_follow_collection_url( $actor, $kind, $page + 1 );
		}
		return $out;
	}

	$out = array(
		'id'           => $url,
		'type'         => 'OrderedCollection',
		'attributedTo' => $actor->get_uri(),
		'url'          => $actor->get_profile_url(),
	);
	if ( null === axismundi_op_collection_contract( 'ap-' . $kind ) ) {
		return $out;
	}
	$out = axismundi_op_assemble_collection( 'ap-' . $kind, $out, array( 'total' => $data['total'] ), 'public', 'private' !== $policy );
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	if ( $public && $data['total'] > 0 ) {
		$out['first'] = axismundi_op_actor_follow_collection_url( $actor, $kind, 1 );
	}
	return $out;
}

/** Register the Actor transformer when the Actors plugin is available. */
function axismundi_op_register_actor_transformers() : void {
	if ( ! class_exists( 'Axismundi_Actor' ) ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'axismundi-actor',
		array(
			'supports'   => static fn( $source ) : bool => $source instanceof Axismundi_Actor,
			'object_uri' => static fn( Axismundi_Actor $actor ) : string => $actor->get_uri(),
			'transform'  => 'axismundi_op_actor_transform',
			'visible'    => 'axismundi_op_actor_visible',
			'priority'   => 5,
		)
	);
	axismundi_op_register_collection_transformer(
		'axismundi-actor-outbox',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Actor_Outbox,
			'collection_uri' => static fn( Axismundi_OP_Actor_Outbox $source ) : string => $source->is_page()
				? axismundi_op_actor_outbox_page_url( $source->get_actor(), $source->get_cursor() )
				: axismundi_op_actor_outbox_url( $source->get_actor() ),
			'transform'      => 'axismundi_op_actor_outbox_transform',
			'visible'        => 'axismundi_op_actor_outbox_visible',
			'priority'       => 5,
		)
	);
	axismundi_op_register_collection_transformer(
		'axismundi-actor-followers',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Actor_Followers,
			'collection_uri' => static fn( Axismundi_OP_Actor_Followers $source ) : string => axismundi_op_actor_follow_collection_url( $source->get_actor(), $source->get_collection(), $source->get_page() ),
			'transform'      => 'axismundi_op_actor_followers_transform',
			'visible'        => 'axismundi_op_actor_followers_visible',
			'priority'       => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_actor_transformers' );

/** Register the representation-owned public Outbox route. */
function axismundi_op_register_actor_outbox_route() : void {
	// The route exists whenever Actors does, because the Actor document names it. The
	// ledger only decides whether the collection has anything in it.
	if ( ! class_exists( 'Axismundi_Actor' ) || ! function_exists( 'axismundi_actors_get_by_uuid' ) ) {
		return;
	}
	register_rest_route(
		'axismundi/v1',
		'/actors/(?P<uuid>[0-9a-f-]{36})/outbox',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_op_get_actor_outbox',
			'permission_callback' => '__return_true',
			'args'                => array(
				'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ),
				'page' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1 ),
				'cursor' => array( 'required' => false, 'type' => 'string' ),
				'activity_type' => array( 'required' => false, 'type' => 'string', 'pattern' => '^[A-Za-z][A-Za-z0-9]{0,63}$' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_op_register_actor_outbox_route' );

add_action( 'rest_api_init', static function (): void {
	if ( ! function_exists( 'axismundi_actors_get_by_uuid' ) || ! function_exists( 'axismundi_act_get_outbox_after' ) ) {
		return;
	}
	register_rest_route( 'axismundi/v1', '/actors/(?P<uuid>[0-9a-f-]{36})/sent', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'axismundi_op_get_actor_sent',
		'permission_callback' => '__return_true',
		'args'                => array(
			'page'        => array( 'type' => 'integer', 'enum' => array( 1 ) ),
			'cursor'      => array( 'type' => 'string' ),
			'object_type' => array( 'type' => 'string', 'pattern' => '^[A-Za-z][A-Za-z0-9]{0,63}$' ),
			'in_reply_to' => array( 'type' => 'string', 'validate_callback' => static fn( $value ) => is_string( $value ) && ( in_array( $value, array( 'none', 'any', 'unknown' ), true ) || (bool) preg_match( '~^https?://[^\s]+$~i', $value ) ) ),
			'audience'    => array( 'type' => 'string', 'validate_callback' => static fn( $value ) => is_string( $value ) && ( in_array( $value, array( 'none', 'any', 'unknown' ), true ) || (bool) preg_match( '~^https?://[^\s]+$~i', $value ) ) ),
		),
	) );
} );

/** Summarize the newest visible content-management event per Object. */
function axismundi_op_get_actor_sent( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( (string) $request['uuid'] );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! axismundi_op_actor_visible( $actor ) ) {
		return new WP_Error( 'ax_op_sent_not_found', __( 'The Actor Sent collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$cursor = (string) $request['cursor'];
	$position = '' === $cursor ? null : axismundi_act_parse_feed_cursor( $cursor );
	if ( '' !== $cursor && null === $position ) {
		return new WP_Error( 'ax_op_sent_cursor', __( 'The Sent cursor is invalid.', 'axismundi-object-projections' ), array( 'status' => 400 ) );
	}
	$type = (string) $request['object_type'];
	$audience = (string) $request['audience'];
	$reply = (string) $request['in_reply_to'];
	$root = add_query_arg( array_filter( array( 'object_type' => rawurlencode( $type ), 'audience' => rawurlencode( $audience ), 'in_reply_to' => rawurlencode( $reply ) ) ), rest_url( 'axismundi/v1/actors/' . $actor->get_uuid() . '/sent' ) );
	$first = add_query_arg( 'page', '1', $root );
	$out = array( '@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $root, 'type' => 'OrderedCollection', 'attributedTo' => $actor->get_uri(), 'first' => $first );
	if ( ! $request['page'] && '' === $cursor ) {
		return axismundi_op_actor_collection_response( $out );
	}
	list( $reader, $owner ) = axismundi_op_actor_collection_reader( $actor );
	$out['type'] = 'OrderedCollectionPage';
	$out['id'] = '' === $cursor ? $first : add_query_arg( 'cursor', rawurlencode( $cursor ), $first );
	$out['partOf'] = $root;
	unset( $out['first'] );
	$out['orderedItems'] = array();
	$seen = array();
	$groups = array( axismundi_op_actor_followers_url( $actor ) => false );
	$scan = '';
	$last = '';
	$limit = axismundi_op_outbox_page_size();
	// Rebuild the visible prefix so older events for an Object cannot repeat on later pages.
	while ( $batch = axismundi_act_get_outbox_after( $actor->get_uri(), 50, $scan, true, '', true ) ) {
		foreach ( $batch as $activity ) {
			$scan = axismundi_act_feed_cursor( $activity );
			if ( '' === $scan ) {
				break 2;
			}
			$uri = $activity->get_object_uri();
			if ( ! $uri || isset( $seen[ $uri ] ) || ! in_array( $activity->get_type(), array( 'Create', 'Update', 'Delete' ), true ) ) {
				continue;
			}
			$payload = $reader ? $reader( $activity ) : apply_filters( 'axismundi_act_public_outbox_payload', axismundi_act_public_payload( $activity ), $activity );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$seen[ $uri ] = true;
			$key = axismundi_act_parse_feed_cursor( $scan );
			if ( $position && array( $key['time'], $key['id'] ) >= array( $position['time'], $position['id'] ) ) {
				continue;
			}
			if ( 'Delete' === $activity->get_type() && empty( $payload['origin'] ) ) {
				continue;
			}
			if ( '' !== $audience && ! axismundi_op_sent_matches_audience( $payload, $audience, $groups ) ) {
				continue;
			}
			$row = axismundi_op_get_local_object( $uri );
			if ( $row && ( 'tombstone' === $row['object_status'] || ( ! $owner && empty( $row['publicly_readable'] ) ) ) ) {
				continue;
			}
			$source = $row ? null : axismundi_op_resolve_source_by_uri( $uri );
			$object = $row['document'] ?? ( null !== $source ? axismundi_op_transform_object( $source ) : null );
			if ( ! $owner && is_wp_error( $object ) && 'ax_op_not_public' === $object->get_error_code() && ! ( $source instanceof WP_Post && 'trash' === $source->post_status ) ) {
				continue;
			}
			$item = array( 'id' => $uri );
			$parents = is_array( $object ) ? axismundi_op_application_recipient_uris( $object['inReplyTo'] ?? null ) : array();
			$reply_state = ! is_array( $object ) ? 'unknown' : ( $parents ? 'any' : 'none' );
			if ( '' !== $reply && $reply !== $reply_state && ! in_array( $reply, $parents, true ) ) {
				continue;
			}
			if ( is_array( $object ) ) {
				$item += array_intersect_key( $object, array_flip( array( 'type', 'published', 'updated', 'attributedTo' ) ) );
				if ( $parents ) {
					$item['inReplyTo'] = 1 === count( $parents ) ? reset( $parents ) : array_values( $parents );
				}
			}
			if ( '' !== $type && ! in_array( $type, (array) ( $item['type'] ?? null ), true ) ) {
				continue;
			}
			if ( count( $out['orderedItems'] ) >= $limit ) {
				$out['next'] = add_query_arg( 'cursor', rawurlencode( $last ), $first );
				break 2;
			}
			$out['orderedItems'][] = $item;
			$last = $scan;
		}
	}
	return axismundi_op_actor_collection_response( $out );
}

function axismundi_op_sent_matches_audience( array $payload, string $filter, array &$groups ) : bool {
	$unknown = false;
	$has_group = false;
	foreach ( array( 'to', 'cc', 'audience' ) as $field ) {
		foreach ( axismundi_op_application_recipient_uris( $payload[ $field ] ?? null ) as $uri ) {
			if ( in_array( $uri, array( 'https://www.w3.org/ns/activitystreams#Public', 'as:Public', 'Public' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $uri, $groups ) ) {
				$recipient = axismundi_actors_get_by_uri( $uri );
				$groups[ $uri ] = $recipient ? 'Group' === $recipient->get_type() : null;
			}
			if ( true === $groups[ $uri ] && $filter === $uri ) {
				return true;
			}
			$has_group = $has_group || true === $groups[ $uri ];
			$unknown = $unknown || null === $groups[ $uri ];
		}
	}
	return ( 'any' === $filter && $has_group ) || ( ! $has_group && ( $unknown ? 'unknown' : 'none' ) === $filter );
}

/** Register the representation-owned Followers route. */
function axismundi_op_register_actor_followers_route() : void {
	// Registered with Actors alone for the same reason as the Outbox: the document
	// advertises the address. Without relation state it answers without a count.
	if ( ! class_exists( 'Axismundi_Actor' ) || ! function_exists( 'axismundi_actors_get_by_uuid' ) ) {
		return;
	}
	register_rest_route(
		'axismundi/v1',
		'/actors/(?P<uuid>[0-9a-f-]{36})/(?P<collection>followers|following)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_op_get_actor_followers',
			'permission_callback' => '__return_true',
			'args'                => array(
				'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ),
				'collection' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'followers', 'following' ) ),
				'page' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_op_register_actor_followers_route' );

/** Serve one public local Actor Outbox without transport-plugin involvement. */
function axismundi_op_get_actor_outbox( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( strtolower( (string) $request['uuid'] ) );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! axismundi_op_actor_visible( $actor ) ) {
		return new WP_Error( 'ax_op_outbox_not_found', __( 'The Actor outbox was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	list( $reader, $owner ) = axismundi_op_actor_collection_reader( $actor );
	return axismundi_op_actor_collection_response( axismundi_op_transform_collection( new Axismundi_OP_Actor_Outbox( $actor, absint( $request['page'] ) > 0, (string) $request['cursor'], $reader, $owner, (string) $request['activity_type'] ) ) );
}

function axismundi_op_actor_collection_reader( Axismundi_Actor $actor ) : array {
	$reader = null;
	$owner = false;
	if ( get_current_user_id() && function_exists( 'axismundi_act_outbox_authorize' ) && function_exists( 'axismundi_act_get_public_outbox_page' ) ) {
		$viewer = axismundi_act_current_local_actor();
		$viewer_uri = $viewer && axismundi_actors_can_act_as( $viewer, get_current_user_id() ) ? $viewer->get_uri() : '';
		$owner = $viewer_uri === $actor->get_uri() && true === axismundi_act_outbox_authorize( get_current_user_id(), $actor );
		$follow = '' !== $viewer_uri ? axismundi_act_get_relation( 'follow', $viewer_uri, $actor->get_uri() ) : null;
		$followers = ( $follow['state'] ?? '' ) === 'accepted' ? axismundi_op_actor_followers_url( $actor ) : '';
		if ( $owner || '' !== $viewer_uri ) {
			$reader = static function ( Axismundi_Activity $activity ) use ( $owner, $viewer_uri, $followers ) : ?array {
				$payload = $activity->get_payload();
				if ( $owner ) { return $payload; }
				$public = apply_filters( 'axismundi_act_public_outbox_payload', axismundi_act_public_payload( $activity ), $activity );
				if ( is_array( $public ) ) { return $public; }
				$visible = false;
				foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) {
					$members = axismundi_op_application_recipient_uris( $payload[ $field ] ?? null );
					$visible = $visible || in_array( $viewer_uri, $members, true ) || ( '' !== $followers && in_array( $followers, $members, true ) );
				}
				unset( $payload['bto'], $payload['bcc'] );
				if ( is_array( $payload['object'] ?? null ) ) { unset( $payload['object']['bto'], $payload['object']['bcc'] ); }
				return $visible ? $payload : null;
			};
		}
	}
	return array( $reader, $owner );
}

function axismundi_op_actor_collection_response( $collection ) {
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'private, no-store' );
	$response->header( 'Vary', 'Authorization, Cookie, X-WP-Nonce' );
	if ( isset( $collection['next'] ) && '' !== (string) $collection['next'] ) {
		// AS2 owns the document member; this is the equivalent HTTP affordance for
		// ordinary clients. Forward-only is intentional: a cursor has no honest
		// reverse page until the Collection contract supplies one.
		$response->link_header( 'next', (string) $collection['next'] );
	}
	return $response;
}

/** Serve one public count-only Followers collection. */
function axismundi_op_get_actor_followers( WP_REST_Request $request ) {
	$actor  = axismundi_actors_get_by_uuid( strtolower( (string) $request['uuid'] ) );
	$kind = 'following' === (string) $request['collection'] ? 'following' : 'followers';
	$page = max( 0, absint( $request['page'] ) );
	$source = $actor instanceof Axismundi_Actor ? new Axismundi_OP_Actor_Followers( $actor, $kind, $page ) : null;
	// `function_exists` matters here and not only in the transform: this endpoint is
	// public, so an Actors plugin older than this policy would turn a version skew into a
	// fatal error on an anonymous request rather than a missing feature.
	$pages_allowed = $actor instanceof Axismundi_Actor
		&& function_exists( 'axismundi_actors_follow_collections_are_public' )
		&& axismundi_actors_follow_collections_are_public( $actor );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! $source instanceof Axismundi_OP_Actor_Followers || ! axismundi_op_actor_followers_visible( $source ) || ( $page > 0 && ! $pages_allowed ) ) {
		return new WP_Error( 'ax_op_followers_not_found', __( 'The Actor followers collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( $source );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
