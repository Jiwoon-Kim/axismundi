<?php
/** Forum's Actor policy, distinct from Account access and WordPress authorship. */
defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_SUBMISSION_ACTOR_META = '_ax_forum_submission_actor_identity_id';

/** Actor type policy shared by local participants and remote moderator projection. */
function axismundi_forum_person_actor( $actor ) : bool {
	return $actor instanceof Axismundi_Actor && 'Person' === $actor->get_type();
}

/** Preserve the existing local managed-Group product restriction in one place. */
function axismundi_forum_local_group( $actor ) : bool {
	return $actor instanceof Axismundi_Actor && $actor->is_local() && $actor->is_managed() && 'Group' === $actor->get_type();
}

/** Local Person is a product restriction, not a personal-ownership test. */
function axismundi_forum_local_person( $actor ) : bool {
	return axismundi_forum_person_actor( $actor ) && $actor->is_local();
}

/** Whether one local Person can participate on the wire. */
function axismundi_forum_public_local_person( $actor ) : bool {
	return axismundi_forum_local_person( $actor ) && 'public' === $actor->get_status() && $actor->is_handle_locked();
}

/** Use the shared selection kernel; never reconstruct a User's supposed single Actor. */
function axismundi_forum_selected_actor( int $user_id, bool $publication = false ) : ?Axismundi_Actor {
	if ( ! function_exists( 'axismundi_actors_current_workspace' ) ) { return null; }
	return $publication ? axismundi_actors_workspace_publisher( $user_id ) : axismundi_actors_current_workspace( $user_id );
}

/** Accessible Actor identities for User-scoped inventory, not an acting-Actor choice. */
function axismundi_forum_accessible_people( int $user_id ) : array {
	return function_exists( 'axismundi_actors_accounts_for_user' )
		? array_filter( axismundi_actors_accounts_for_user( $user_id ), 'axismundi_forum_local_person' ) : array();
}

/** Read a committed publisher, or the explicit submission intent before the first Create. */
function axismundi_forum_topic_author( WP_Post $topic ) : ?Axismundi_Actor {
	$uri = function_exists( 'axismundi_act_get_object_attribution' )
		? axismundi_act_get_object_attribution( axismundi_forum_topic_object_uri( $topic ) ) : '';
	if ( '' !== $uri ) { return axismundi_actors_get_by_uri( $uri ); }
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	$id = is_array( $entry ) ? (int) ( $entry['submission_actor_identity_id'] ?? 0 )
		: (int) get_post_meta( $topic->ID, AXISMUNDI_FORUM_SUBMISSION_ACTOR_META, true );
	return $id > 0 ? axismundi_actors_get_by_identity( $id ) : null;
}
