<?php
/**
 * Interaction policy: which Activities an Object accepts about itself.
 *
 * `interactionPolicy` reads like a representation field, but what it states is which
 * Activity an Object will accept -- and that is this package's question, not a property of
 * any one document type. It therefore lives here once, for every Object: an Article, a
 * Note, a Question, a Forum Topic, and a Quote of any of them.
 *
 * The layers:
 *
 *   Activities             vocabulary, validation, lookup, and enforcement
 *   the object's domain    stores what its author wrote, and answers the lookup
 *   Object Projections     serializes the answer as `interactionPolicy`
 *
 * A domain answers `axismundi_act_object_quote_policy` for the sources it owns. A product
 * that imposes a context, such as a Forum community over its Topics, narrows that answer at
 * a later priority -- it may only narrow, because a context whose members could relax it
 * would not be a rule.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * The authored quote policies, ordered from most open to most closed.
 *
 * The order is the contract that makes two policies comparable, which is what lets a
 * context impose a ceiling. An empty string is "not authored" and is not a policy.
 *
 * @return string[]
 */
function axismundi_act_quote_policies() : array {
	return array( 'anyone', 'followers', 'me' );
}

/**
 * Sanitize one authored quote policy, returning '' when it is not one.
 *
 * @param mixed $value Candidate policy.
 * @return string
 */
function axismundi_act_sanitize_quote_policy( $value ) : string {
	$value = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
	return in_array( $value, axismundi_act_quote_policies(), true ) ? $value : '';
}

/**
 * The narrower of two policies, treating '' as "no opinion".
 *
 * @param string $a One policy.
 * @param string $b Another policy.
 * @return string
 */
function axismundi_act_narrower_quote_policy( string $a, string $b ) : string {
	$a = axismundi_act_sanitize_quote_policy( $a );
	$b = axismundi_act_sanitize_quote_policy( $b );
	if ( '' === $a || '' === $b ) {
		return '' === $a ? $b : $a;
	}
	$order = axismundi_act_quote_policies();
	return array_search( $a, $order, true ) >= array_search( $b, $order, true ) ? $a : $b;
}

/**
 * The effective quote policy of one Object, or '' when its author wrote none.
 *
 * @param mixed $source Domain object: a WP_Post, an envelope row, whatever its owner uses.
 * @return string
 */
function axismundi_act_object_quote_policy( $source ) : string {
	/**
	 * Answer with the quote policy authored for one Object.
	 *
	 * A domain answers for the sources it owns and returns the value it was given for any
	 * other. A context that governs an Object -- a community over its Topics -- hooks a
	 * later priority and may only return the same or a narrower policy.
	 *
	 * @since 0.1.1
	 * @param string $policy Policy resolved so far, '' when none.
	 * @param mixed  $source Domain object.
	 */
	return axismundi_act_sanitize_quote_policy( apply_filters( 'axismundi_act_object_quote_policy', '', $source ) );
}

/**
 * The Actor collection one policy approves in advance, or '' when it approves nobody.
 *
 * FEP-044f states automatic approval as an audience, so the policy words are resolved here
 * into the collection each one means. `followers` needs the Actor's followers address,
 * which representation owns and supplies through `axismundi_act_actor_followers_uri`.
 *
 * @param string $policy    Authored policy.
 * @param string $actor_uri Author Actor URI.
 * @return string
 */
function axismundi_act_quote_automatic_approval( string $policy, string $actor_uri ) : string {
	$policy    = axismundi_act_sanitize_quote_policy( $policy );
	$actor_uri = axismundi_act_uri( $actor_uri );
	if ( '' === $policy || '' === $actor_uri ) {
		return '';
	}
	if ( 'anyone' === $policy ) {
		return axismundi_act_public_audience_uri();
	}
	if ( 'me' === $policy ) {
		return $actor_uri;
	}
	$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $actor_uri ) : null;
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return '';
	}
	/** @see axismundi_act_actor_followers_uri */
	return (string) apply_filters( 'axismundi_act_actor_followers_uri', '', $actor );
}

/**
 * The FEP-044f `interactionPolicy` for one Object, or null when nothing was authored.
 *
 * The declaration is advisory: it says what will be approved without asking, and never
 * stands in for the QuoteAuthorization evidence that a quote actually holds.
 *
 * @param mixed  $source    Domain object.
 * @param string $actor_uri Author Actor URI.
 * @return array<string,mixed>|null
 */
function axismundi_act_object_interaction_policy( $source, string $actor_uri ) : ?array {
	$automatic = axismundi_act_quote_automatic_approval( axismundi_act_object_quote_policy( $source ), $actor_uri );
	return '' === $automatic ? null : array( 'canQuote' => array( 'automaticApproval' => $automatic ) );
}
