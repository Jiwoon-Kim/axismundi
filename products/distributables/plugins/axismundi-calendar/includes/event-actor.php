<?php
/**
 * Which Actor an Event was published by.
 *
 * Three identities meet on one Event and each answers a different question. Collapsing any two of
 * them looks harmless until somebody runs an organisation:
 *
 *   post_author         the WordPress account that typed it -- who to hold responsible, who may edit
 *   Calendar authority  the Actor whose collection it is filed in -- the target of a share, the owner
 *   acting Actor        the Actor it is published by -- attributedTo, Create.actor, organizer, the
 *                       Actor that answers a Join and sends an Invite
 *
 * Google shows the first and the second apart on every event card: "김지운 (calendar owner)" beside
 * "created by: 김지운 (a different account)". That is not a display nicety -- it is what happens when
 * somebody with write access to a shared calendar adds an event, and a model that stores only the
 * calendar's owner has silently reassigned the authorship.
 *
 * Stored as an identity id rather than an Actor URI. A URI carries the host, so a site that moves
 * domain would leave every past Event attributed to an address that no longer resolves; the identity
 * survives the move and the URI is derived when it is needed.
 *
 * The stored value is a record of who published it, not a permission. Eligibility is re-checked
 * whenever somebody writes -- a manager role can be taken away between two edits -- and the check is
 * the Actors one, so "may I publish as this" has a single answer across every plugin.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The identity stored on one Event, or 0 when it predates this contract.
 *
 * @param int $post_id Event post ID.
 * @return int
 */
function axismundi_cal_event_acting_actor_identity( int $post_id ) : int {
	$envelope = axismundi_cal_event_get( $post_id );
	return is_array( $envelope ) ? (int) ( $envelope['acting_actor_identity_id'] ?? 0 ) : 0;
}

/**
 * The Actor one Event was published by.
 *
 * Falls back to the account that wrote it, which is what this plugin derived before there was
 * anywhere to record a choice. The fallback is for rows written then -- not a second way of
 * answering the question, which is why it is here and not in every caller.
 *
 * @param int $post_id Event post ID.
 * @return string Actor URI, or '' when the author has no Actor either.
 */
function axismundi_cal_event_acting_actor_uri( int $post_id ) : string {
	$identity_id = axismundi_cal_event_acting_actor_identity( $post_id );
	if ( $identity_id > 0 && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		$actor = axismundi_actors_get_by_identity( $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			return (string) $actor->get_uri();
		}
	}
	return axismundi_cal_author_person_actor_uri( $post_id );
}

/**
 * The Person Actor of the account that wrote an Event.
 *
 * What "whose Event is this" used to be derived from, kept as the fallback for Events written before
 * the choice was recorded and as the default for somebody who has never made one.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function axismundi_cal_author_person_actor_uri( int $post_id ) : string {
	$author = (int) get_post_field( 'post_author', $post_id );
	if ( $author <= 0 || ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return '';
	}
	$actor = axismundi_actors_get_for_user( $author );
	return $actor instanceof Axismundi_Actor ? (string) $actor->get_uri() : '';
}

/**
 * The Actor a new Event would be published by, for the user writing it.
 *
 * The switcher's answer, and deliberately not a fourth resolver: a person who has chosen to post as
 * an Organization has chosen it for their Events too, and a Calendar that asked the question its own
 * way would be a second place for that choice to be made differently.
 *
 * @param int $user_id WP user, or 0 for the current one.
 * @return int Identity id, or 0 when the user has no Actor to publish as.
 */
function axismundi_cal_authoring_actor_identity( int $user_id = 0 ) : int {
	if ( ! function_exists( 'axismundi_actors_acting_actor' ) ) {
		return 0;
	}
	$actor = axismundi_actors_acting_actor( $user_id > 0 ? $user_id : null );
	return $actor instanceof Axismundi_Actor ? (int) $actor->get_identity_id() : 0;
}

/**
 * The Actor URI a Calendar should file a new Event under.
 *
 * Asked of the Event first, so an edit does not re-file somebody else's Event under the editor's own
 * Actor, and of the writer only when there is nothing recorded yet.
 *
 * @param int $post_id Event post ID, or 0 for an Event that does not exist yet.
 * @return string
 */
function axismundi_cal_authoring_actor_uri( int $post_id = 0 ) : string {
	if ( $post_id > 0 ) {
		$stored = axismundi_cal_event_acting_actor_uri( $post_id );
		if ( '' !== $stored ) {
			return $stored;
		}
		// An Event whose author has no Actor has no answer, and inventing the saver's own would file
		// somebody else's Event on their calendar. The caller refuses the write instead.
		if ( (int) get_post_field( 'post_author', $post_id ) !== get_current_user_id() ) {
			return '';
		}
	}
	$identity_id = axismundi_cal_authoring_actor_identity();
	if ( $identity_id > 0 && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		$actor = axismundi_actors_get_by_identity( $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			return (string) $actor->get_uri();
		}
	}
	return '';
}

/**
 * Decide what to store for one save.
 *
 * An Event keeps the Actor it was published by. An edit is not a republication, so a stored value is
 * left alone unless the caller names a different one -- otherwise an administrator fixing a typo on
 * an Organization's Event would quietly make it theirs.
 *
 * A named Actor is checked against the writer's current authority rather than the stored selection,
 * because that is the guarantee the switcher makes: choosing an Actor once is not permission to keep
 * publishing as it after the role is revoked.
 *
 * @param int                      $post_id  Event post ID.
 * @param array<string,mixed>      $fields   Submitted envelope fields.
 * @param array<string,mixed>|null $existing Stored envelope.
 * @return int|WP_Error Identity id (0 when this site has no Actors at all).
 */
function axismundi_cal_resolve_event_acting_actor( int $post_id, array $fields, ?array $existing ) {
	$stored = (int) ( $existing['acting_actor_identity_id'] ?? 0 );
	if ( ! array_key_exists( 'acting_actor_identity_id', $fields ) ) {
		if ( $stored > 0 ) {
			return $stored;
		}
		/*
		 * The switcher's choice applies to somebody writing their own Event, and only then. A save made
		 * on another account's behalf -- an import, a migration, an administrator writing as somebody
		 * else -- is not that person publishing, and taking the saver's chosen Actor there would quietly
		 * reassign authorship to whoever happened to run the code.
		 */
		$author = (int) get_post_field( 'post_author', $post_id );
		if ( $author > 0 && $author === get_current_user_id() ) {
			$default = axismundi_cal_authoring_actor_identity( $author );
			if ( $default > 0 ) {
				return $default;
			}
		}
		// The author's own Person, which is what this plugin derived before there was anywhere to record
		// a choice -- and still the honest answer for a writer who has never made one.
		$fallback = axismundi_cal_author_person_actor_uri( $post_id );
		if ( '' !== $fallback && function_exists( 'axismundi_actors_get_by_uri' ) ) {
			$actor = axismundi_actors_get_by_uri( $fallback );
			return $actor instanceof Axismundi_Actor ? (int) $actor->get_identity_id() : 0;
		}
		return 0;
	}

	$requested = (int) $fields['acting_actor_identity_id'];
	if ( $requested <= 0 ) {
		return 0;
	}
	if ( ! function_exists( 'axismundi_actors_get_by_identity' ) || ! function_exists( 'axismundi_actors_can_act_as' ) ) {
		return new WP_Error( 'ax_event_acting_actor', __( 'Actor identities are unavailable.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$actor = axismundi_actors_get_by_identity( $requested );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_act_as( $actor, get_current_user_id() ) ) {
		return new WP_Error( 'ax_event_acting_actor', __( 'You cannot publish an event as that Actor.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	return $requested;
}

/**
 * Record, for Events written before there was anywhere to record it, the Actor this plugin was
 * deriving anyway.
 *
 * A migration that changed who an Event is attributed to would be rewriting history; this one writes
 * down the answer that was already being computed, so nothing observable changes and the derivation
 * stops being consulted.
 *
 * @return int Rows filled.
 */
function axismundi_cal_backfill_event_acting_actors() : int {
	global $wpdb;
	if ( ! axismundi_cal_ready() || ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return 0;
	}
	$table = axismundi_cal_events_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$ids = (array) $wpdb->get_col( "SELECT post_id FROM {$table} WHERE acting_actor_identity_id = 0" );
	$filled = 0;
	foreach ( $ids as $post_id ) {
		$author = (int) get_post_field( 'post_author', (int) $post_id );
		$actor  = $author > 0 ? axismundi_actors_get_for_user( $author ) : null;
		if ( ! $actor instanceof Axismundi_Actor ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'acting_actor_identity_id' => $actor->get_identity_id() ), array( 'post_id' => (int) $post_id ), array( '%d' ), array( '%d' ) );
		++$filled;
	}
	return $filled;
}
