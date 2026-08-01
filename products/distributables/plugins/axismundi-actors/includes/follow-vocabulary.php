<?php
/**
 * What a Follow is called, according to what it points at.
 *
 * There is one relation on the wire and one row in the ledger: `Follow`. Splitting it into a
 * second activity type would be a protocol invention, and it would break every peer that already
 * knows what Follow means. What differs is only what the act *is* to a reader — following a
 * person is subscribing to them; following a community is subscribing to it, and calling that
 * "following" reads as if the community were a person.
 *
 * So this is a vocabulary and nothing more. Nothing here changes what is stored, sent, or
 * accepted; it changes the words on the button and above the list.
 *
 * A Group's subscribers are also not its members. Membership is a Forum concept with its own
 * evidence and its own accepted/pending states, and moderators are a third thing again — a
 * permission, not a relation. Those stay where they belong, and this vocabulary deliberately says
 * nothing about them.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * The follow vocabulary for one kind of Actor.
 *
 * Several entries are whole sentences rather than the verb they contain, because the verb cannot
 * simply be substituted into one template: a person is followed, but a community is subscribed
 * *to*, and the preposition belongs to the sentence rather than to the word. Composing these from
 * a verb would produce "Subscribe designforum" in English and worse in languages that inflect,
 * so each is its own translatable string.
 *
 * @param string $type Actor type.
 * @return array{verb:string,verb_done:string,undo:string,pending:string,inbound:string,outbound:string,verb_target:string,sign_in:string,remote_intro:string,target_label:string}
 */
function axismundi_actors_follow_vocabulary( string $type ) : array {
	$words = 'Group' === $type
		? array(
			'verb'         => __( 'Subscribe', 'axismundi-actors' ),
			'verb_done'    => __( 'Subscribed', 'axismundi-actors' ),
			'undo'         => __( 'Unsubscribe', 'axismundi-actors' ),
			'pending'      => __( 'Requested', 'axismundi-actors' ),
			'inbound'      => __( 'Subscribers', 'axismundi-actors' ),
			'outbound'     => __( 'Subscriptions', 'axismundi-actors' ),
			/* translators: %s: community name. */
			'verb_target'  => __( 'Subscribe to %s', 'axismundi-actors' ),
			'sign_in'      => __( 'Log in to subscribe', 'axismundi-actors' ),
			'remote_intro' => __( 'Subscribe to this community from your own Fediverse account.', 'axismundi-actors' ),
			'target_label' => __( 'Community to subscribe to', 'axismundi-actors' ),
		)
		: array(
			'verb'         => __( 'Follow', 'axismundi-actors' ),
			'verb_done'    => __( 'Following', 'axismundi-actors' ),
			'undo'         => __( 'Unfollow', 'axismundi-actors' ),
			'pending'      => __( 'Requested', 'axismundi-actors' ),
			'inbound'      => __( 'Followers', 'axismundi-actors' ),
			'outbound'     => __( 'Following', 'axismundi-actors' ),
			/* translators: %s: actor display name. */
			'verb_target'  => __( 'Follow %s', 'axismundi-actors' ),
			'sign_in'      => __( 'Log in to follow', 'axismundi-actors' ),
			'remote_intro' => __( 'Follow this profile from your own Fediverse account.', 'axismundi-actors' ),
			'target_label' => __( 'Profile to follow', 'axismundi-actors' ),
		);
	/**
	 * Adjust the words one kind of Actor's follow relation is described with.
	 *
	 * @param array<string,string> $words Vocabulary.
	 * @param string               $type  Actor type.
	 */
	return (array) apply_filters( 'axismundi_actors_follow_vocabulary', $words, $type );
}

/**
 * One word from the vocabulary for one Actor.
 *
 * @param Axismundi_Actor|null $actor Actor the relation points at.
 * @param string               $key   Vocabulary key.
 * @return string
 */
function axismundi_actors_follow_word( ?Axismundi_Actor $actor, string $key ) : string {
	$words = axismundi_actors_follow_vocabulary( $actor instanceof Axismundi_Actor ? $actor->get_type() : 'Person' );
	return (string) ( $words[ $key ] ?? '' );
}

/**
 * Roles held by the Actors in a Group's subscriber list.
 *
 * A Group's list is more legible when the people who run it are visible in it, and the reader
 * already expects that from every forum they have used. Actors does not know what a moderator is,
 * so the product that owns the permission answers.
 *
 * @param Axismundi_Actor $group    Group whose list is being rendered.
 * @param string[]        $subjects Actor URIs in the list.
 * @return array<string,string> Actor URI => short role label.
 */
function axismundi_actors_follow_list_roles( Axismundi_Actor $group, array $subjects ) : array {
	if ( 'Group' !== $group->get_type() || empty( $subjects ) ) {
		return array();
	}
	/**
	 * Label the Actors in a Group's subscriber list that hold a role in it.
	 *
	 * @param array<string,string> $roles    Actor URI => role label.
	 * @param Axismundi_Actor      $group    Group being listed.
	 * @param string[]             $subjects Actor URIs in the list.
	 */
	return (array) apply_filters( 'axismundi_actors_follow_list_roles', array(), $group, $subjects );
}
