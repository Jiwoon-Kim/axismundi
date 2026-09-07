<?php
/**
 * Community vote REST mutation and Interactivity API block.
 *
 * One control, not two independent buttons: the up and down halves share a single state, because
 * an Actor holds one vote and pressing either side is a move between three states rather than two
 * separate toggles. Sending the direction the reader wants — instead of "add a Dislike" — keeps
 * the exclusivity rule on the server, where a stale page cannot get it wrong.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** REST permission gate: an activated public local Actor is required. */
function axismundi_forum_vote_rest_permission() : bool {
	return function_exists( 'axismundi_act_current_local_actor' ) && axismundi_act_current_local_actor() instanceof Axismundi_Actor;
}

/** Register the community vote mutation endpoint. */
function axismundi_forum_register_vote_rest_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/community-votes',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'axismundi_forum_rest_cast_vote',
			'permission_callback' => 'axismundi_forum_vote_rest_permission',
			'args'                => array(
				'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
				'direction'  => array( 'required' => true, 'type' => 'string', 'enum' => array( 'up', 'down', 'none' ) ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_forum_register_vote_rest_route' );

/** Handle a vote. */
function axismundi_forum_rest_cast_vote( WP_REST_Request $request ) {
	$actor = axismundi_act_current_local_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_forum_vote_actor', __( 'No active local Actor is available.', 'axismundi-forum' ), array( 'status' => 403 ) );
	}
	// The object must exist and be able to receive an interaction at all; a tombstoned or
	// unknown object is refused here rather than recorded as a vote on nothing.
	$target = axismundi_act_resolve_like_target( (string) $request['object_uri'] );
	if ( is_wp_error( $target ) ) {
		return $target;
	}
	$score = axismundi_forum_cast_vote( $actor, (string) $target['object_uri'], (string) $request['direction'] );
	if ( is_wp_error( $score ) ) {
		return $score;
	}
	return new WP_REST_Response( array( 'object_uri' => (string) $target['object_uri'] ) + $score, 200 );
}

/**
 * The object URI one vote block instance represents.
 *
 * Resolution mirrors the Like button: an explicit attribute wins, then the active object view
 * model (so a card inside a feed identifies itself rather than the surrounding template), then
 * the post in block context.
 */
function axismundi_forum_vote_block_object_uri( array $attributes, WP_Block $block ) : string {
	$uri = isset( $attributes['objectUri'] ) && function_exists( 'axismundi_act_uri' ) ? axismundi_act_uri( (string) $attributes['objectUri'] ) : '';
	if ( '' === $uri && function_exists( 'axismundi_act_like_block_object_uri' ) ) {
		$uri = axismundi_act_like_block_object_uri( array(), $block );
	}
	/** @param string $uri Object URI or empty. @param array<string,mixed> $attributes Block attributes. @param WP_Block $block Block instance. */
	return (string) apply_filters( 'axismundi_forum_vote_block_object_uri', $uri, $attributes, $block );
}

/**
 * Describe a community vote for the unified interaction block.
 *
 * The first interaction type another product registers, and the reason the registry exists:
 * Activities owns the control and has no idea what a community is, while a vote is only
 * meaningful inside one. Forum answers here and Activities never learns the concept.
 *
 * A vote is also the reason a type may describe more than one control. Up and down cannot both
 * be pressed, and the score between them is neither — one interaction with three parts rather
 * than three interactions that would each hold their own opinion of what the reader had chosen.
 *
 * Every bound value is a context value. Directives are processed on the server too, and a `state`
 * getter that exists only in the browser evaluates to nothing there: it blanked the score and the
 * icons and dropped `disabled` from a control the visitor was not allowed to use.
 *
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 * @return array<string,mixed>|null
 */
function axismundi_forum_describe_vote_interaction( array $attributes, WP_Block $block ) : ?array {
	$object_uri = axismundi_forum_vote_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return null;
	}
	/*
	 * Asked of the Object, not inferred from the page that placed the control.
	 *
	 * "A vote is only meaningful inside a community" was already the rule stated above, but it was
	 * being kept by placement alone: the block appeared on the two community templates and nowhere
	 * else, so it never had to decide. That held only while the template list did, which made a
	 * correctness property depend on an editorial one -- a single template edit, or a card rendered
	 * on some other surface, and a reader would be offered a vote on something no community can
	 * count.
	 *
	 * A local Topic answers here through its own object URI, so the Topic page keeps its vote.
	 */
	if ( ! ( axismundi_forum_object_community_group( $object_uri ) instanceof Axismundi_Actor ) ) {
		return null;
	}
	if ( function_exists( 'axismundi_act_no_cache_like_state' ) ) {
		axismundi_act_no_cache_like_state();
	}
	$actor    = function_exists( 'axismundi_act_current_local_actor' ) ? axismundi_act_current_local_actor() : null;
	$can_vote = $actor instanceof Axismundi_Actor && ! is_wp_error( axismundi_act_resolve_like_target( $object_uri ) );
	$score    = axismundi_forum_vote_score( $object_uri, $actor instanceof Axismundi_Actor ? $actor->get_uri() : '' );
	$blocked  = is_user_logged_in()
		? __( 'Activate a public Actor profile to vote.', 'axismundi-forum' )
		: __( 'Log in to vote.', 'axismundi-forum' );
	/* translators: 1: upvote count, 2: downvote count. */
	$tally_template = __( '%1$s upvotes, %2$s downvotes', 'axismundi-forum' );
	$tally          = sprintf( $tally_template, number_format_i18n( $score['up'] ), number_format_i18n( $score['down'] ) );

	$context = array(
		'objectUri'      => $object_uri,
		'up'             => $score['up'],
		'down'           => $score['down'],
		'score'          => $score['score'],
		'viewer'         => $score['viewer'],
		'isUpvoted'      => 'up' === $score['viewer'],
		'isDownvoted'    => 'down' === $score['viewer'],
		'upIcon'         => 'up' === $score['viewer'] ? 'thumb_up' : 'thumb_up_off_alt',
		'downIcon'       => 'down' === $score['viewer'] ? 'thumb_down' : 'thumb_down_off_alt',
		'formattedScore' => number_format_i18n( $score['score'] ),
		'isPending'      => false,
		'canVote'        => $can_vote,
		'isDisabled'     => ! $can_vote,
		'endpoint'       => rest_url( 'axismundi/v1/community-votes' ),
		'nonce'          => $can_vote ? wp_create_nonce( 'wp_rest' ) : '',
		'error'          => '',
		'errorFallback'  => __( 'The vote could not be saved.', 'axismundi-forum' ),
		'tally'          => $tally,
		'tallyTemplate'  => $tally_template,
	);

	$direction = static function ( string $way, string $label, bool $selected, string $icon ) use ( $can_vote, $blocked, $object_uri, $context ) : array {
		return array(
			'icon'       => $icon,
			// The glyph swaps between outline and filled as the reader moves between sides, so it
			// has to follow the context rather than stay at whatever the page was built with.
			'icon_bind'  => 'up' === $way ? 'context.upIcon' : 'context.downIcon',
			'label'      => $label,
			'aria_label' => $can_vote ? $label : $blocked,
			'selected'   => $selected,
			'toggle'     => true,
			'disabled'   => ! $can_vote,
			'bindings'   => array(
				'data-wp-on--click'          => 'up' === $way ? 'actions.voteUp' : 'actions.voteDown',
				'data-wp-bind--disabled'     => 'context.isDisabled',
				'data-wp-bind--aria-pressed' => 'up' === $way ? 'context.isUpvoted' : 'context.isDownvoted',
				'data-wp-class--is-selected' => 'up' === $way ? 'context.isUpvoted' : 'context.isDownvoted',
			),
			'delegated'  => array(
				'data-ax-action'     => 'vote',
				'data-ax-direction'  => $way,
				'data-ax-object-uri' => $object_uri,
				'data-ax-endpoint'   => (string) $context['endpoint'],
				'data-ax-nonce'      => (string) $context['nonce'],
			),
		);
	};

	return array(
		'icon'        => 'thumb_up',
		'label'       => __( 'Vote', 'axismundi-forum' ),
		'group_label' => __( 'Community vote', 'axismundi-forum' ),
		'namespace'   => 'axismundi/vote-buttons',
		'module'      => 'axismundi-interaction-vote',
		'context'     => $context,
		'controls'    => array(
			$direction( 'up', __( 'Upvote', 'axismundi-forum' ), 'up' === $score['viewer'], (string) $context['upIcon'] ),
			// The net score is what a reader acts on; the raw tallies stay available as its
			// title so the number is not left unexplained.
			array( 'text' => (string) $context['formattedScore'], 'text_bind' => 'context.formattedScore', 'aria_label' => $tally ),
			$direction( 'down', __( 'Downvote', 'axismundi-forum' ), 'down' === $score['viewer'], (string) $context['downIcon'] ),
		),
	);
}

/**
 * A Like offered on a community Object is a vote.
 *
 * The placement rule this implements has one clause, not five: community context votes, everything
 * else likes. A Group's feed, a Person's community surface and a community Object document are all
 * the first case; a Person's own timeline and an ordinary Object document are the second. None of
 * that is an editorial choice an author should be making per template, and a saved attribute could
 * not express it anyway — the hashtag archive and any thread render community and ordinary Objects
 * from the same saved card.
 *
 * Nothing is taken away by the swap. An upvote records `Like`, so the verb an author asked for is
 * exactly the verb the reader still sends; the community case only adds the opposite direction and
 * the score the two produce.
 *
 * @param string   $type       Authored interaction type.
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 */
function axismundi_forum_community_interaction_type( string $type, array $attributes, WP_Block $block ) : string {
	if ( ! in_array( $type, array( 'like', 'dislike' ), true ) ) {
		return $type;
	}
	$object_uri = axismundi_forum_vote_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return $type;
	}
	if ( ! axismundi_forum_object_community_group( $object_uri ) instanceof Axismundi_Actor ) {
		return $type;
	}
	// The authored pair becomes the single community vote group. The Like supplies its up side;
	// suppressing its Dislike sibling prevents the same downvote appearing twice.
	return 'like' === $type ? 'vote' : ( 'dislike' === $type ? '' : $type );
}
add_filter( 'axismundi_act_interaction_type', 'axismundi_forum_community_interaction_type', 10, 3 );

/** Offer the community vote as an interaction type. */
function axismundi_forum_register_vote_interaction_type() : void {
	if ( function_exists( 'axismundi_act_register_interaction_type' ) ) {
		axismundi_act_register_interaction_type(
			'vote',
			array(
				'describe' => 'axismundi_forum_describe_vote_interaction',
				'label'    => __( 'Vote', 'axismundi-forum' ),
				'icon'     => 'thumb_up',
			)
		);
	}
}
add_action( 'axismundi_act_register_interaction_types', 'axismundi_forum_register_vote_interaction_type' );

/**
 * Register the vote store this type brings with it.
 *
 * It was the vote block's own `viewScriptModule` before the control moved into the shared
 * interaction block, and it stays this plugin's to register: Activities loads whatever a type
 * declares without knowing what is in it.
 */
function axismundi_forum_register_vote_module() : void {
	$path = dirname( __DIR__ ) . '/assets/vote.js';
	if ( function_exists( 'wp_register_script_module' ) && is_readable( $path ) ) {
		wp_register_script_module(
			'axismundi-interaction-vote',
			plugins_url( 'assets/vote.js', dirname( __DIR__ ) . '/axismundi-forum.php' ),
			array( '@wordpress/interactivity' ),
			(string) filemtime( $path )
		);
	}
}
add_action( 'init', 'axismundi_forum_register_vote_module', 5 );
