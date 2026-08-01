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
 * Render the vote control as presentation only, for a surface that owns its own clicks.
 *
 * @param array<string,mixed> $context    Resolved vote state.
 * @param string              $up_label   Accessible label for the up control.
 * @param string              $down_label Accessible label for the down control.
 * @param string              $tally      Spelled-out counts for assistive technology.
 * @return string
 */
function axismundi_forum_render_vote_buttons_markup( array $context, string $up_label, string $down_label, string $tally ) : string {
	$disabled = ! $context['canVote'];
	ob_start();
	?>
	<div class="wp-block-axismundi-vote-buttons"
		data-ax-vote-object-uri="<?php echo esc_attr( (string) $context['objectUri'] ); ?>"
		data-ax-vote-endpoint="<?php echo esc_url( (string) $context['endpoint'] ); ?>"
		data-ax-vote-viewer="<?php echo esc_attr( (string) $context['viewer'] ); ?>"
		<?php if ( ! $disabled ) : ?>data-ax-nonce="<?php echo esc_attr( (string) $context['nonce'] ); ?>"<?php endif; ?>>
		<div class="axismundi-vote-buttons__group" role="group" aria-label="<?php esc_attr_e( 'Community vote', 'axismundi-forum' ); ?>">
			<button type="button" class="axismundi-vote-buttons__button axismundi-vote-buttons__button--up<?php echo $context['isUpvoted'] ? ' is-active' : ''; ?>"
				data-ax-action="vote" data-ax-direction="up"
				aria-pressed="<?php echo $context['isUpvoted'] ? 'true' : 'false'; ?>"
				aria-label="<?php echo esc_attr( $up_label ); ?>" title="<?php echo esc_attr( $up_label ); ?>"<?php disabled( $disabled ); ?>>
				<span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( (string) $context['upIcon'] ); ?></span>
			</button>
			<span class="axismundi-vote-buttons__score" title="<?php echo esc_attr( $tally ); ?>"><?php echo esc_html( (string) $context['formattedScore'] ); ?></span>
			<button type="button" class="axismundi-vote-buttons__button axismundi-vote-buttons__button--down<?php echo $context['isDownvoted'] ? ' is-active' : ''; ?>"
				data-ax-action="vote" data-ax-direction="down"
				aria-pressed="<?php echo $context['isDownvoted'] ? 'true' : 'false'; ?>"
				aria-label="<?php echo esc_attr( $down_label ); ?>" title="<?php echo esc_attr( $down_label ); ?>"<?php disabled( $disabled ); ?>>
				<span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( (string) $context['downIcon'] ); ?></span>
			</button>
		</div>
		<span class="screen-reader-text"><?php echo esc_html( $tally ); ?></span>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Render the dynamic vote control. */
function axismundi_forum_render_vote_buttons( array $attributes, string $content, WP_Block $block ) : string {
	$object_uri = axismundi_forum_vote_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return '';
	}
	// A viewer's own vote is personal state; it must never be served from a shared cache.
	if ( function_exists( 'axismundi_act_no_cache_like_state' ) ) {
		axismundi_act_no_cache_like_state();
	}
	$actor    = function_exists( 'axismundi_act_current_local_actor' ) ? axismundi_act_current_local_actor() : null;
	$can_vote = $actor instanceof Axismundi_Actor && ! is_wp_error( axismundi_act_resolve_like_target( $object_uri ) );
	$score    = axismundi_forum_vote_score( $object_uri, $actor instanceof Axismundi_Actor ? $actor->get_uri() : '' );
	/*
	 * Every value the markup binds lives in context, not in a `state` getter. Directives are
	 * processed server-side too, and a getter that only exists in the browser evaluates to
	 * nothing there — which blanked the score and icons and silently dropped `disabled` from a
	 * control the visitor is not allowed to use. Context values are present in both passes, so
	 * the pre-hydration markup is the same control the runtime then takes over.
	 */
	$context  = array(
		'objectUri'     => $object_uri,
		'up'            => $score['up'],
		'down'          => $score['down'],
		'score'         => $score['score'],
		'viewer'        => $score['viewer'],
		'isUpvoted'     => 'up' === $score['viewer'],
		'isDownvoted'   => 'down' === $score['viewer'],
		'upIcon'        => 'up' === $score['viewer'] ? 'thumb_up' : 'thumb_up_off_alt',
		'downIcon'      => 'down' === $score['viewer'] ? 'thumb_down' : 'thumb_down_off_alt',
		'formattedScore' => number_format_i18n( $score['score'] ),
		'isPending'     => false,
		'canVote'       => $can_vote,
		'isDisabled'    => ! $can_vote,
		'endpoint'      => rest_url( 'axismundi/v1/community-votes' ),
		'nonce'         => $can_vote ? wp_create_nonce( 'wp_rest' ) : '',
		'error'         => '',
		'errorFallback' => __( 'The vote could not be saved.', 'axismundi-forum' ),
		// The runtime rebuilds this sentence after a vote, so it needs the translated pattern
		// as well as the finished string; both are filled in below.
		'tally'         => '',
		'tallyTemplate' => '',
	);
	$blocked  = is_user_logged_in()
		? __( 'Activate a public Actor profile to vote.', 'axismundi-forum' )
		: __( 'Log in to vote.', 'axismundi-forum' );
	$up_label   = $can_vote ? __( 'Upvote', 'axismundi-forum' ) : $blocked;
	$down_label = $can_vote ? __( 'Downvote', 'axismundi-forum' ) : $blocked;
	// The net score is what a reader acts on; the two raw tallies stay available to assistive
	// technology so the number is not left unexplained.
	/* translators: 1: upvote count, 2: downvote count. */
	$tally_template   = __( '%1$s upvotes, %2$s downvotes', 'axismundi-forum' );
	$tally            = sprintf( $tally_template, number_format_i18n( $score['up'] ), number_format_i18n( $score['down'] ) );
	$context['tally']         = $tally;
	$context['tallyTemplate'] = $tally_template;
	/*
	 * Inside a feed the surrounding region owns clicks, because cards there are appended and
	 * replaced continuously and appended DOM never hydrates. On a single object page this control
	 * is the interaction and keeps owning itself. The feed variant omits the interactive
	 * directives rather than emitting them behind a guard: markup that is not there cannot fire
	 * twice.
	 */
	if ( function_exists( 'axismundi_op_object_template_option' )
		&& 'feed' === (string) axismundi_op_object_template_option( 'interactionOwner', 'block' ) ) {
		return axismundi_forum_render_vote_buttons_markup( $context, $up_label, $down_label, $tally );
	}
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="axismundi/vote-buttons" <?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="axismundi-vote-buttons__group" role="group" aria-label="<?php esc_attr_e( 'Community vote', 'axismundi-forum' ); ?>">
			<button type="button" class="axismundi-vote-buttons__button axismundi-vote-buttons__button--up" data-wp-on--click="actions.voteUp" data-wp-bind--aria-pressed="context.isUpvoted" data-wp-bind--disabled="context.isDisabled" data-wp-class--is-active="context.isUpvoted" aria-label="<?php echo esc_attr( $up_label ); ?>" title="<?php echo esc_attr( $up_label ); ?>"<?php disabled( ! $can_vote ); ?>>
				<span class="material-symbols-outlined" aria-hidden="true" data-wp-text="context.upIcon"><?php echo esc_html( 'up' === $score['viewer'] ? 'thumb_up' : 'thumb_up_off_alt' ); ?></span>
			</button>
			<span class="axismundi-vote-buttons__score" data-wp-text="context.formattedScore" title="<?php echo esc_attr( $tally ); ?>"><?php echo esc_html( number_format_i18n( $score['score'] ) ); ?></span>
			<button type="button" class="axismundi-vote-buttons__button axismundi-vote-buttons__button--down" data-wp-on--click="actions.voteDown" data-wp-bind--aria-pressed="context.isDownvoted" data-wp-bind--disabled="context.isDisabled" data-wp-class--is-active="context.isDownvoted" aria-label="<?php echo esc_attr( $down_label ); ?>" title="<?php echo esc_attr( $down_label ); ?>"<?php disabled( ! $can_vote ); ?>>
				<span class="material-symbols-outlined" aria-hidden="true" data-wp-text="context.downIcon"><?php echo esc_html( 'down' === $score['viewer'] ? 'thumb_down' : 'thumb_down_off_alt' ); ?></span>
			</button>
		</div>
		<span class="screen-reader-text" data-wp-text="context.tally"><?php echo esc_html( $tally ); ?></span>
		<span class="axismundi-vote-buttons__status" data-wp-text="context.error" aria-live="polite"></span>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Register the dynamic block. */
function axismundi_forum_register_vote_buttons_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/vote-buttons', array( 'render_callback' => 'axismundi_forum_render_vote_buttons' ) );
}
add_action( 'init', 'axismundi_forum_register_vote_buttons_block' );
