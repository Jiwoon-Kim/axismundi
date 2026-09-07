<?php
/**
 * Whether a notice is delivered, held for review, or never made.
 *
 * Three outcomes, and the middle one is the reason this exists as a stage of its own:
 *
 *   accept   delivered, and counted
 *   filter   recorded and held: it appears among notification requests, not in the inbox
 *   drop     never written at all
 *
 * `filter` is not a polite word for discarding. Somebody with a policy against messages from
 * strangers still has to be able to find the one legitimate stranger who wrote to them, and a
 * quarantine they can look through is what makes the policy safe to turn on. Something silently
 * deleted is a harassment measure that also loses your new colleague's first message.
 *
 * This is not a preference. Preferences are about a person's attention -- which kinds they want, by
 * which transport -- and are asked later, of the reader. This is about the sender and the
 * relationship, asked once, when the fact is recorded.
 *
 * Two answers are borrowed rather than owned:
 *
 * Blocking is read from the Activity ledger, never stored here. A block is a public-facing social
 * relation with its own federated activity; a second copy in this plugin would be a second truth
 * about it, and the two would disagree the first time one was changed.
 *
 * Muting here is **notification-only**, and named that way so it stays that way. It means "do not
 * notify me about that Actor" and nothing more: their posts still appear in a timeline, their
 * replies still exist, a search still finds them. If a mute that reaches those surfaces is ever
 * wanted, it is a private relation between two Actors and belongs to a model that owns it -- not to
 * whichever product happened to need it first.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/** What can happen to an intent. */
const AXISMUNDI_NTF_OUTCOMES = array( 'accept', 'filter', 'drop' );

/**
 * Categories no policy may hold back.
 *
 * A quarantined security warning is worse than any amount of noise, and a moderation notice that a
 * filter swallowed is one somebody is not answering. Both are addressed by this site to its own
 * people rather than sent by a stranger, so the conditions here have nothing to say about them
 * anyway -- but the exemption is written down rather than left to follow from that.
 */
const AXISMUNDI_NTF_UNFILTERABLE = array( 'moderation', 'security' );

/**
 * How new an Actor has to be to count as new.
 *
 * Measured from when this site first saw them, which is not the same as when their account was
 * made -- there is no reliable way to learn the latter about a remote Actor, and pretending
 * otherwise would make the condition mean something it cannot check.
 */
const AXISMUNDI_NTF_NEW_ACTOR_DAYS = 3;

/**
 * The policy one Actor has set, with the defaults filled in.
 *
 * All off by default. Quarantining strangers on a site nobody is harassing produces an empty inbox
 * and a full requests list, which is a worse failure than the one it prevents.
 *
 * @param int $recipient_actor_id Recipient identity.
 * @return array<string,bool>
 */
function axismundi_ntf_policy( int $recipient_actor_id ) : array {
	global $wpdb;
	$defaults = array( 'filter_not_following' => false, 'filter_new_actors' => false, 'filter_automated' => false );
	if ( $recipient_actor_id <= 0 || ! axismundi_ntf_ready() ) {
		return $defaults;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_ntf_policies_table() . ' WHERE recipient_actor_id = %d', $recipient_actor_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return $defaults;
	}
	return array(
		'filter_not_following' => 1 === (int) $row['filter_not_following'],
		'filter_new_actors'    => 1 === (int) $row['filter_new_actors'],
		'filter_automated'     => 1 === (int) $row['filter_automated'],
	);
}

/**
 * Set one Actor's policy.
 *
 * @param int                 $recipient_actor_id Recipient identity.
 * @param array<string,mixed> $policy             Conditions to turn on or off.
 * @return bool
 */
function axismundi_ntf_set_policy( int $recipient_actor_id, array $policy ) : bool {
	global $wpdb;
	if ( $recipient_actor_id <= 0 || ! axismundi_ntf_ready() ) {
		return false;
	}
	$current = axismundi_ntf_policy( $recipient_actor_id );
	$row     = array(
		'recipient_actor_id'   => $recipient_actor_id,
		'filter_not_following' => (int) (bool) ( $policy['filter_not_following'] ?? $current['filter_not_following'] ),
		'filter_new_actors'    => (int) (bool) ( $policy['filter_new_actors'] ?? $current['filter_new_actors'] ),
		'filter_automated'     => (int) (bool) ( $policy['filter_automated'] ?? $current['filter_automated'] ),
		'updated_at'           => current_time( 'mysql', true ),
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->replace( axismundi_ntf_policies_table(), $row, array( '%d', '%d', '%d', '%d', '%s' ) );
}

/**
 * Stop being notified about one Actor.
 *
 * @param int    $recipient_actor_id Recipient identity.
 * @param string $muted_actor_uri    Actor to stop hearing about.
 * @return bool
 */
function axismundi_ntf_mute( int $recipient_actor_id, string $muted_actor_uri ) : bool {
	global $wpdb;
	$muted_actor_uri = trim( $muted_actor_uri );
	if ( $recipient_actor_id <= 0 || '' === $muted_actor_uri || ! axismundi_ntf_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->replace(
		axismundi_ntf_mutes_table(),
		array(
			'recipient_actor_id'   => $recipient_actor_id,
			'muted_actor_uri'      => $muted_actor_uri,
			'muted_actor_uri_hash' => hash( 'sha256', $muted_actor_uri ),
			'created_at'           => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s' )
	);
}

/**
 * Hear about them again.
 *
 * @param int    $recipient_actor_id Recipient identity.
 * @param string $muted_actor_uri    Actor to unmute.
 * @return bool
 */
function axismundi_ntf_unmute( int $recipient_actor_id, string $muted_actor_uri ) : bool {
	global $wpdb;
	if ( $recipient_actor_id <= 0 || ! axismundi_ntf_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete(
		axismundi_ntf_mutes_table(),
		array( 'recipient_actor_id' => $recipient_actor_id, 'muted_actor_uri_hash' => hash( 'sha256', trim( $muted_actor_uri ) ) ),
		array( '%d', '%s' )
	);
}

/**
 * Whether one Actor has muted another's notifications.
 *
 * @param int    $recipient_actor_id Recipient identity.
 * @param string $sender_uri         Sending Actor URI.
 * @return bool
 */
function axismundi_ntf_is_muted( int $recipient_actor_id, string $sender_uri ) : bool {
	global $wpdb;
	if ( $recipient_actor_id <= 0 || '' === trim( $sender_uri ) || ! axismundi_ntf_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . axismundi_ntf_mutes_table() . ' WHERE recipient_actor_id = %d AND muted_actor_uri_hash = %s',
			$recipient_actor_id,
			hash( 'sha256', trim( $sender_uri ) )
		)
	) > 0;
}

/**
 * Whether one Actor has blocked another, read from the ledger.
 *
 * @param string $recipient_uri Recipient Actor URI.
 * @param string $sender_uri    Sending Actor URI.
 * @return bool
 */
function axismundi_ntf_is_blocked( string $recipient_uri, string $sender_uri ) : bool {
	if ( ! function_exists( 'axismundi_act_get_relation' ) || '' === trim( $recipient_uri ) || '' === trim( $sender_uri ) ) {
		return false;
	}
	// `active` is the ledger's word for a block that stands. A block is unilateral -- nobody accepts
	// being blocked -- so it never passes through the `accepted` state a Follow has to earn.
	$block = axismundi_act_get_relation( 'block', $recipient_uri, $sender_uri );
	return is_array( $block ) && 'active' === (string) $block['state'];
}

/**
 * Whether one Actor follows another, read from the ledger.
 *
 * @param string $subject_uri Following Actor.
 * @param string $object_uri  Followed Actor.
 * @return bool
 */
function axismundi_ntf_follows( string $subject_uri, string $object_uri ) : bool {
	if ( ! function_exists( 'axismundi_act_get_relation' ) ) {
		return false;
	}
	/*
	 * Accepted, not merely requested. A pending Follow is somebody who has asked to follow and has
	 * not been let in, and treating that as a relationship would let the approval an Actor
	 * deliberately withheld be bypassed by asking -- the same rule the Event join eligibility keeps.
	 */
	$follow = axismundi_act_get_relation( 'follow', $subject_uri, $object_uri );
	return is_array( $follow ) && 'accepted' === (string) $follow['state'];
}

/**
 * What should happen to one intent.
 *
 * @param int    $recipient_actor_id Recipient identity.
 * @param string $recipient_uri      Recipient Actor URI.
 * @param string $sender_uri         The Actor whose act this was.
 * @param string $category           The kind's category.
 * @return string One of accept|filter|drop.
 */
function axismundi_ntf_acceptance( int $recipient_actor_id, string $recipient_uri, string $sender_uri, string $category ) : string {
	$sender_uri = trim( $sender_uri );
	if ( '' === $sender_uri || $sender_uri === trim( $recipient_uri ) ) {
		// An Actor's own act, and anything this site raised in nobody's name. Neither is a stranger.
		return 'accept';
	}
	if ( in_array( $category, AXISMUNDI_NTF_UNFILTERABLE, true ) ) {
		return 'accept';
	}
	/*
	 * Blocking and muting are decisions somebody made about this Actor in particular, so they end the
	 * question rather than filing it for review: a requests list full of the people you blocked is
	 * the block not working.
	 */
	if ( axismundi_ntf_is_blocked( $recipient_uri, $sender_uri ) || axismundi_ntf_is_muted( $recipient_actor_id, $sender_uri ) ) {
		return 'drop';
	}
	$policy = axismundi_ntf_policy( $recipient_actor_id );
	if ( array( false, false, false ) === array_values( $policy ) ) {
		return 'accept';
	}
	// Somebody you follow is not a stranger under any of the conditions below, which is the one
	// exemption Mastodon's policy also makes and the reason the conditions stay usable.
	if ( axismundi_ntf_follows( $recipient_uri, $sender_uri ) ) {
		return 'accept';
	}
	$sender = axismundi_ntf_has_actors() ? axismundi_actors_get_by_uri( $sender_uri ) : null;
	if ( $policy['filter_not_following'] ) {
		return 'filter';
	}
	if ( $policy['filter_automated'] && $sender instanceof Axismundi_Actor && in_array( $sender->get_type(), array( 'Service', 'Application' ), true ) ) {
		return 'filter';
	}
	if ( $policy['filter_new_actors'] && axismundi_ntf_is_new_actor( $sender ) ) {
		return 'filter';
	}
	return 'accept';
}

/**
 * Whether an Actor is new enough for a cautious policy to hold their message.
 *
 * Read from what the Actor reports about itself, which is the only account age there is: a remote
 * server tells us when the account was published or it does not, and there is nothing else to
 * consult. So three answers rather than two.
 *
 * An Actor that reports nothing is **not** treated as new. Most do not report it, and filtering on
 * a fact nobody has would quietly turn this condition into "filter almost everyone" under a name
 * that says something much narrower.
 *
 * An Actor this site has no record of at all is a different case: nothing is known, the policy was
 * turned on deliberately, and holding it for review is what the reader asked for. Nothing is lost --
 * a filtered notice is one they can still go and read.
 *
 * @param Axismundi_Actor|null $actor Sending Actor, when this site knows one.
 * @return bool
 */
function axismundi_ntf_is_new_actor( ?Axismundi_Actor $actor ) : bool {
	if ( ! $actor instanceof Axismundi_Actor ) {
		return true;
	}
	$published = trim( (string) $actor->get_published_at() );
	if ( '' === $published ) {
		return false;
	}
	$at = strtotime( $published );
	return false !== $at && $at > ( time() - ( AXISMUNDI_NTF_NEW_ACTOR_DAYS * DAY_IN_SECONDS ) );
}
