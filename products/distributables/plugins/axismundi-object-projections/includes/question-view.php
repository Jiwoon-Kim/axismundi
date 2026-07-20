<?php
/**
 * Read-only Question/Poll display, driven by the current object view model.
 *
 * The block is unified (v1) and purely presentational: it renders whatever
 * `poll` shape the current view model's adapter supplies (Note today; a
 * remote-cache adapter later) and owns no vote authority itself. Editing
 * (choosing options, casting a vote) is a later increment.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Render one option row with a proportional bar, tally-agnostic. */
function axismundi_op_render_question_option( array $option, int $max_votes ) : string {
	$name    = trim( (string) ( $option['name'] ?? '' ) );
	$votes   = max( 0, (int) ( $option['votes'] ?? 0 ) );
	$percent = $max_votes > 0 ? (int) round( ( $votes / $max_votes ) * 100 ) : 0;
	return '<li class="axismundi-question__option">'
		. '<div class="axismundi-question__option-row">'
		. '<span class="axismundi-question__option-name">' . esc_html( $name ) . '</span>'
		. '<span class="axismundi-question__option-percent">' . esc_html( $percent . '%' ) . '</span>'
		. '</div>'
		. '<div class="axismundi-question__bar"><div class="axismundi-question__bar-fill" style="width:' . esc_attr( $percent ) . '%"></div></div>'
		. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
}

/** Render the read-only poll for the request's current object view model. */
function axismundi_op_render_question_block() : string {
	$model = axismundi_op_current_object_view_model();
	$poll  = is_array( $model ) ? ( $model['poll'] ?? null ) : null;
	if ( ! is_array( $poll ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '';
	}
	$options = array_values( array_filter( (array) ( $poll['options'] ?? array() ), 'is_array' ) );
	if ( empty( $options ) ) {
		return '';
	}
	$max_votes = 1;
	foreach ( $options as $option ) {
		$max_votes = max( $max_votes, (int) ( $option['votes'] ?? 0 ) );
	}
	$items = array_map(
		static fn( array $option ) : string => axismundi_op_render_question_option( $option, $max_votes ),
		$options
	);

	$closed      = ! empty( $poll['closed_at'] );
	$voters      = max( 0, (int) ( $poll['voters_count'] ?? 0 ) );
	$meta_parts  = array();
	$meta_parts[] = sprintf(
		/* translators: %d: number of voters. */
		_n( '%d vote', '%d votes', $voters, 'axismundi-object-projections' ),
		$voters
	);
	if ( $closed ) {
		$meta_parts[] = __( 'Final results', 'axismundi-object-projections' );
	} elseif ( '' !== (string) ( $poll['closes_at'] ?? '' ) ) {
		$timestamp = strtotime( (string) $poll['closes_at'] );
		$meta_parts[] = false !== $timestamp
			? sprintf(
				/* translators: %s: closing date/time. */
				__( 'Voting closes %s', 'axismundi-object-projections' ),
				wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp )
			)
			: __( 'Open for voting', 'axismundi-object-projections' );
	} else {
		$meta_parts[] = __( 'Open for voting', 'axismundi-object-projections' );
	}

	return '<div class="axismundi-question axismundi-question--' . ( $closed ? 'closed' : 'open' ) . '">'
		. '<ul class="axismundi-question__options">' . implode( '', $items ) . '</ul>'
		. '<p class="axismundi-question__meta">' . esc_html( implode( ' | ', $meta_parts ) ) . '</p>'
		. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Items escaped by axismundi_op_render_question_option(); meta escaped above.
}

/** Register the server-rendered Question/Poll block (no editor script). */
function axismundi_op_register_question_block() : void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		'axismundi/question',
		array(
			'api_version'     => 3,
			'title'           => __( 'Axismundi Question', 'axismundi-object-projections' ),
			'category'        => 'theme',
			'render_callback' => 'axismundi_op_render_question_block',
			'supports'        => array( 'html' => false, 'inserter' => false ),
		)
	);
}
add_action( 'init', 'axismundi_op_register_question_block' );
