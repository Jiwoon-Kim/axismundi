<?php
/**
 * Topic inherits the core Post Article projection (dev-only).
 *
 * A Topic is one of this site's Articles with a Group around it. The core Post projection
 * is the single source of truth for how this site describes a document, and a Topic may
 * differ from it only in what Forum owns: the object URI, the author it resolved, the
 * addressing of a submission, and the three Group-context members.
 *
 * Restating the body here is how a Topic lost mentions, hashtags, emoji declarations,
 * summaries and sensitivity while a Post kept them. This audit pins the inheritance so a
 * later edit cannot quietly reintroduce that divergence.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-object-projections/includes/post-article.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';

$ax_tia_results = array();
$ax_tia_posts   = array();

/** @param bool[] $results Results. */
function ax_tia_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_tia_topic = get_posts( array( 'post_type' => 'ax_topic', 'numberposts' => 1, 'post_status' => 'publish' ) );
	$ax_tia_topic = $ax_tia_topic ? $ax_tia_topic[0] : null;
	if ( ! $ax_tia_topic instanceof WP_Post ) {
		ax_tia_assert( $ax_tia_results, 'a published Topic exists to project', false );
		printf( "\n%d/%d passed\n", 0, 1 );
		return;
	}

	$ax_tia_article = axismundi_forum_topic_to_article( $ax_tia_topic );
	if ( is_wp_error( $ax_tia_article ) ) {
		ax_tia_assert( $ax_tia_results, 'the Topic projects without error: ' . $ax_tia_article->get_error_code(), false );
		printf( "\n%d/%d passed\n", 0, 1 );
		return;
	}

	// The same body, authored as a core post, is the reference this Topic must match.
	$ax_tia_post_id = wp_insert_post(
		array(
			'post_title'   => (string) $ax_tia_topic->post_title,
			'post_content' => (string) $ax_tia_topic->post_content,
			'post_status'  => 'publish',
			'post_author'  => (int) $ax_tia_topic->post_author,
		)
	);
	$ax_tia_posts[] = $ax_tia_post_id;
	$ax_tia_post    = get_post( $ax_tia_post_id );
	$ax_tia_core    = axismundi_op_post_to_article( $ax_tia_post );

	ax_tia_assert( $ax_tia_results, 'the reference core Post projects', is_array( $ax_tia_core ) );
	if ( ! is_array( $ax_tia_core ) ) {
		printf( "\n%d/%d passed\n", count( array_filter( $ax_tia_results ) ), count( $ax_tia_results ) );
		return;
	}

	// Forum owns exactly these three members beyond the Article's own.
	$ax_tia_forum_owned = array( 'audience', 'context', 'commentsEnabled' );
	/*
	 * `interactionPolicy` is projected from an authored setting, and those settings are
	 * registered for `post` alone, so a Topic has none to project. That is a gap in the
	 * authoring surface, not a second projection: the member is absent because nobody set
	 * it, and the cause is asserted below rather than waved through here.
	 */
	$ax_tia_unauthored = array( 'interactionPolicy' );
	$ax_tia_missing    = array_diff( array_keys( $ax_tia_core ), array_keys( $ax_tia_article ), $ax_tia_unauthored );
	$ax_tia_extra       = array_diff( array_keys( $ax_tia_article ), array_keys( $ax_tia_core ), $ax_tia_forum_owned );

	ax_tia_assert(
		$ax_tia_results,
		'a Topic carries every member the core Post Article carries: ' . ( $ax_tia_missing ? implode( ', ', $ax_tia_missing ) : 'none missing' ),
		array() === $ax_tia_missing
	);
	ax_tia_assert(
		$ax_tia_results,
		'a Topic adds only the Group context members: ' . ( $ax_tia_extra ? implode( ', ', $ax_tia_extra ) : 'none added' ),
		array() === $ax_tia_extra
	);

	// The language decision is the Article's, not a second policy. Whichever form the core
	// projection chose, the Topic chose the same one.
	$ax_tia_language_members = array( 'name', 'content', 'nameMap', 'contentMap' );
	$ax_tia_core_language    = array_values( array_intersect( $ax_tia_language_members, array_keys( $ax_tia_core ) ) );
	$ax_tia_topic_language   = array_values( array_intersect( $ax_tia_language_members, array_keys( $ax_tia_article ) ) );
	ax_tia_assert(
		$ax_tia_results,
		'the Topic language members match the Article policy (' . implode( ' + ', $ax_tia_core_language ) . ')',
		$ax_tia_core_language === $ax_tia_topic_language
	);

	// What Forum claims, it claims for a reason, and it is not the body.
	ax_tia_assert(
		$ax_tia_results,
		'Forum claims the Topic object URI its own routing serves',
		axismundi_forum_topic_object_uri( $ax_tia_topic ) === ( $ax_tia_article['id'] ?? '' )
	);
	// The one member a Topic does not carry, and why: no authored quote policy exists for
	// this post type. If that ever changes, the member must appear rather than stay absent.
	ax_tia_assert(
		$ax_tia_results,
		'the only unmatched member is one nobody authored for this post type',
		'' === axismundi_op_post_quote_policy( $ax_tia_topic ) && ! isset( $ax_tia_article['interactionPolicy'] )
	);
	ax_tia_assert(
		$ax_tia_results,
		'a submission is addressed to its Group rather than by the authored visibility policy',
		array( (string) ( $ax_tia_article['audience'] ?? '' ) ) === (array) ( $ax_tia_article['to'] ?? array() )
	);
	/*
	 * Who may quote a Topic is settled by two parties: the community sets the rule its
	 * members publish under, and the author may be stricter inside it. The effective policy
	 * is the narrower of the two, never the looser, or the community rule would be advisory.
	 */
	if ( function_exists( 'axismundi_act_object_quote_policy' ) ) {
		$ax_tia_ceiling = static fn() : string => 'followers';
		update_post_meta( (int) $ax_tia_topic->ID, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'anyone' );
		ax_tia_assert(
			$ax_tia_results,
			'an authored Topic policy applies when its community sets no rule',
			'anyone' === axismundi_act_object_quote_policy( $ax_tia_topic )
		);
		add_filter( 'axismundi_forum_community_quote_ceiling', $ax_tia_ceiling );
		ax_tia_assert(
			$ax_tia_results,
			'a Topic cannot be quoted more widely than its community allows',
			'followers' === axismundi_act_object_quote_policy( $ax_tia_topic )
		);
		update_post_meta( (int) $ax_tia_topic->ID, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'me' );
		ax_tia_assert(
			$ax_tia_results,
			'a Topic may be stricter than its community',
			'me' === axismundi_act_object_quote_policy( $ax_tia_topic )
		);
		delete_post_meta( (int) $ax_tia_topic->ID, AXISMUNDI_OP_POST_QUOTE_POLICY_META );
		ax_tia_assert(
			$ax_tia_results,
			'the community rule stands on its own when nobody authored one',
			'followers' === axismundi_act_object_quote_policy( $ax_tia_topic )
		);
		remove_filter( 'axismundi_forum_community_quote_ceiling', $ax_tia_ceiling );
	}

} finally {
	foreach ( $ax_tia_posts as $ax_tia_post_id ) {
		if ( $ax_tia_post_id && ! is_wp_error( $ax_tia_post_id ) ) {
			wp_delete_post( (int) $ax_tia_post_id, true );
		}
	}
}

printf( "\n%d/%d passed\n", count( array_filter( $ax_tia_results ) ), count( $ax_tia_results ) );
