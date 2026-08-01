<?php
/**
 * Reply authoring entry point for one Object view.
 *
 * The first version deliberately delegates composition to the Note editor. A
 * future front-end editor can replace only the compose URL filter while the
 * Object URI, public-reply count, and block contract remain stable.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Resolve the current Object URI and its bounded visible-thread count. */
function axismundi_act_reply_button_data( array $attributes, WP_Block $block ) : array {
	$uri   = axismundi_act_like_block_object_uri( $attributes, $block );
	$count = array( 'count' => 0, 'truncated' => false );
	if ( '' !== $uri && function_exists( 'axismundi_op_get_display_reply_tree_count' ) ) {
		$count = axismundi_op_get_display_reply_tree_count( $uri );
	} elseif ( '' !== $uri && function_exists( 'axismundi_op_get_public_reply_collection_count' ) ) {
		$count = axismundi_op_get_public_reply_collection_count( $uri );
	}
	return array(
		'object_uri' => $uri,
		'count'      => max( 0, (int) ( $count['count'] ?? 0 ) ),
		'truncated'  => ! empty( $count['truncated'] ),
	);
}


/**
 * Describe a Reply for the unified interaction block.
 *
 * The one interaction that goes somewhere rather than changing something: it opens a composer, so
 * it is a link when the reader can use it and an inert control when they cannot. It has no pressed
 * state either, which is why it does not claim to be a toggle.
 *
 * If the composer ever becomes a dialog on the page instead of a destination, only this function
 * changes: it stops returning `href` and returns the trigger bindings and popover markup the
 * Announce menu and the reaction picker already use. The block does not need to learn anything
 * new for that, which is the point of describing an interaction rather than drawing one.
 *
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 * @return array<string,mixed>|null
 */
function axismundi_act_describe_reply_interaction( array $attributes, WP_Block $block ) : ?array {
	$data = axismundi_act_reply_button_data( $attributes, $block );
	if ( '' === $data['object_uri'] ) {
		return null;
	}
	$actor       = axismundi_act_current_local_actor();
	$compose_url = $actor instanceof Axismundi_Actor ? (string) apply_filters( 'axismundi_act_reply_compose_url', '', $data['object_uri'] ) : '';
	// A bounded scan reports "at least this many", and the count says so rather than rounding a
	// truncation down into a number that looks exact.
	$count = number_format_i18n( $data['count'] ) . ( $data['truncated'] ? '+' : '' );
	return array(
		'icon'       => 'reply',
		'label'      => __( 'Reply', 'axismundi-activities' ),
		'aria_label' => '' !== $compose_url
			? sprintf(
				/* translators: %s: number of visible replies. */
				_n( 'Reply (%s reply)', 'Reply (%s replies)', (int) $data['count'], 'axismundi-activities' ),
				$count
			)
			: ( is_user_logged_in() ? __( 'Activate a public Actor profile to reply.', 'axismundi-activities' ) : __( 'Log in to reply.', 'axismundi-activities' ) ),
		'count'      => (int) $data['count'],
		'count_text' => $count,
		'href'       => $compose_url,
		'disabled'   => '' === $compose_url,
	);
}

/** Offer Reply as an interaction type. */
function axismundi_act_register_reply_interaction_type() : void {
	if ( function_exists( 'axismundi_act_register_interaction_type' ) ) {
		axismundi_act_register_interaction_type(
			'reply',
			array(
				'describe' => 'axismundi_act_describe_reply_interaction',
				'label'    => __( 'Reply', 'axismundi-activities' ),
				'icon'     => 'reply',
			)
		);
	}
}
add_action( 'axismundi_act_register_interaction_types', 'axismundi_act_register_reply_interaction_type' );
