<?php
/**
 * Quote as an interaction.
 *
 * The same shape as Reply and for the same reason: both open a composer with one field already
 * filled in — Reply with what is being replied to, Quote with what is being quoted — so both are
 * navigation rather than a mutation, and both are a link when the reader can use them.
 *
 * Quote has no count and no pressed state. There is no "quoted" toggle to be in: quoting produces
 * a new Object of the reader's own, and whether they have done it before is not a property of the
 * thing they quoted.
 *
 * Where the composer lives is not this plugin's business. Activities knows that quoting starts
 * somewhere and asks; the product that owns Note composition answers, exactly as it does for
 * Reply. A build with no composer installed returns nothing and the control renders inert, which
 * is the honest answer rather than a link into a page that will not know what to do.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describe a Quote for the unified interaction block.
 *
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 * @return array<string,mixed>|null
 */
function axismundi_act_describe_quote_interaction( array $attributes, WP_Block $block ) : ?array {
	$object_uri = axismundi_act_like_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return null;
	}
	$actor = axismundi_act_current_local_actor();
	/*
	 * Not every Object can be quoted. The composer refuses one it cannot resolve — neither a local
	 * Note nor an observation it holds — and an empty URL is how it says so. That is a real state
	 * of this Object rather than a missing feature, so the control stays and explains itself
	 * instead of disappearing and leaving a gap in the row.
	 */
	$compose_url = $actor instanceof Axismundi_Actor ? (string) apply_filters( 'axismundi_act_quote_compose_url', '', $object_uri ) : '';
	$reason      = __( 'This post cannot be quoted.', 'axismundi-activities' );
	if ( ! $actor instanceof Axismundi_Actor ) {
		$reason = is_user_logged_in()
			? __( 'Activate a public Actor profile to quote.', 'axismundi-activities' )
			: __( 'Log in to quote.', 'axismundi-activities' );
	}
	return array(
		'icon'       => 'format_quote',
		'label'      => __( 'Quote', 'axismundi-activities' ),
		'aria_label' => '' !== $compose_url ? __( 'Quote', 'axismundi-activities' ) : $reason,
		'href'       => $compose_url,
		'disabled'   => '' === $compose_url,
	);
}

/** Offer Quote as an interaction type. */
function axismundi_act_register_quote_interaction_type() : void {
	if ( function_exists( 'axismundi_act_register_interaction_type' ) ) {
		axismundi_act_register_interaction_type(
			'quote',
			array(
				'describe' => 'axismundi_act_describe_quote_interaction',
				'label'    => __( 'Quote', 'axismundi-activities' ),
				'icon'     => 'format_quote',
			)
		);
	}
}
add_action( 'axismundi_act_register_interaction_types', 'axismundi_act_register_quote_interaction_type' );
