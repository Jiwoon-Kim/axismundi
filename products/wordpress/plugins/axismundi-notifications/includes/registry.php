<?php
/**
 * What kinds of thing there are to be told.
 *
 * One list rather than one per product, because the settings screen, the acceptance policy and the
 * bundling rules all have to enumerate the same set -- and three enumerations of one set is three
 * places for a new kind to be missing from.
 *
 * Kinds are namespaced by the product that declares them (`axismundi-calendar/event-invited`), so a
 * second product can name something similarly without colliding, and a kind read back years later
 * still says where it came from.
 *
 * Categories are what somebody subscribes to. They are not transports: `calendar` and `moderation`
 * are categories, while in-app, push and email are the ways a category reaches you. Conflating the
 * two is how a settings screen ends up asking whether you want "email" as though it were a subject.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * What somebody subscribes to.
 *
 * Subjects, all of them: the kind of thing a notification is about. `low` was here once and was a
 * mistake -- it is not a subject anybody has an opinion about, it is how loudly something asks for
 * attention, and that is what `urgency` says. Left in, it produced a settings screen offering
 * "calendar, social, low", which is a list with one item that does not belong to the same question.
 *
 * A reaction is `social` with an urgency of `quiet`, and somebody who wants fewer of those is
 * answering a different question than somebody who wants no calendar notices at all.
 */
const AXISMUNDI_NTF_CATEGORIES = array( 'conversation', 'social', 'calendar', 'moderation', 'security' );

/**
 * How much attention a kind asks for, before any preference is applied.
 *
 * The default rather than the rule. `immediate` is something that needs an answer or changes a plan
 * somebody has already made; `bundled` is worth knowing in aggregate; `quiet` is worth having in the
 * list and never worth interrupting anybody for.
 */
const AXISMUNDI_NTF_URGENCIES = array( 'immediate', 'bundled', 'quiet' );

/**
 * Register one kind of notification.
 *
 * @param string              $kind Namespaced kind, e.g. `axismundi-calendar/event-invited`.
 * @param array<string,mixed> $args category, urgency, label.
 * @return bool Whether it was registered.
 */
function axismundi_ntf_register_kind( string $kind, array $args = array() ) : bool {
	$kind = trim( $kind );
	if ( '' === $kind || ! str_contains( $kind, '/' ) ) {
		// Namespaced or not at all: an unprefixed kind is one that cannot be attributed later.
		return false;
	}
	$category = (string) ( $args['category'] ?? '' );
	$urgency  = (string) ( $args['urgency'] ?? 'bundled' );
	if ( ! in_array( $category, AXISMUNDI_NTF_CATEGORIES, true ) || ! in_array( $urgency, AXISMUNDI_NTF_URGENCIES, true ) ) {
		return false;
	}
	$kinds          = axismundi_ntf_registered_kinds();
	$kinds[ $kind ] = array(
		'category' => $category,
		'urgency'  => $urgency,
		'label'    => (string) ( $args['label'] ?? $kind ),
	);
	$GLOBALS['axismundi_ntf_kinds'] = $kinds;
	return true;
}

/**
 * Every registered kind.
 *
 * @return array<string,array<string,mixed>>
 */
function axismundi_ntf_registered_kinds() : array {
	if ( ! isset( $GLOBALS['axismundi_ntf_kinds'] ) || ! is_array( $GLOBALS['axismundi_ntf_kinds'] ) ) {
		$GLOBALS['axismundi_ntf_kinds'] = array();
	}
	return (array) $GLOBALS['axismundi_ntf_kinds'];
}

/**
 * What one kind was registered as, or null.
 *
 * @param string $kind Kind.
 * @return array<string,mixed>|null
 */
function axismundi_ntf_kind( string $kind ) : ?array {
	$kinds = axismundi_ntf_registered_kinds();
	return isset( $kinds[ $kind ] ) ? (array) $kinds[ $kind ] : null;
}

/**
 * Let products declare their kinds.
 *
 * Fired once, late enough that every plugin has loaded and early enough that nothing can have
 * resolved an intent yet. A kind that is not registered is not stored, which is deliberate: an
 * inbox holding entries no settings screen can describe is one nobody can turn off.
 *
 * @return void
 */
function axismundi_ntf_register_kinds() : void {
	/**
	 * Fires so products can register the kinds of notification they produce.
	 */
	do_action( 'axismundi_notification_register_kinds' );
}
add_action( 'init', 'axismundi_ntf_register_kinds', 5 );
