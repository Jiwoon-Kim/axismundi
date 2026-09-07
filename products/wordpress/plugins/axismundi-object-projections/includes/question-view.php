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

/** Render one read-only option result as a static distribution bar. */
function axismundi_op_render_question_option( array $option, int $voters_count ) : string {
	$name    = trim( (string) ( $option['name'] ?? '' ) );
	$votes   = max( 0, (int) ( $option['votes'] ?? 0 ) );
	$percent = $voters_count > 0 ? (int) round( ( $votes / $voters_count ) * 100 ) : 0;
	$selected = ! empty( $option['is_selected'] );
	$label   = sprintf(
		/* translators: 1: option name, 2: vote count, 3: share of participating voters. */
		__( '%1$s: %2$d votes, %3$d%%', 'axismundi-object-projections' ),
		$name,
		$votes,
		$percent
	);
	$summary = sprintf(
		/* translators: 1: vote count, 2: share of participating voters. */
		__( '%1$d votes, %2$d%%', 'axismundi-object-projections' ),
		$votes,
		$percent
	);
	return '<li class="axismundi-question__option axismundi-question__result' . ( $selected ? ' is-selected' : '' ) . '">'
		. '<div class="axismundi-question__option-row">'
		. ( $selected ? '<span class="material-symbols-outlined axismundi-question__selected-icon" aria-hidden="true">check</span>' : '' )
		. '<span class="axismundi-question__option-name">' . esc_html( $name ) . '</span>'
		. '<span class="axismundi-question__option-percent">' . esc_html( $summary ) . '</span>'
		. '</div>'
		. '<div class="axismundi-question__result-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $percent ) . '" aria-label="' . esc_attr( $label ) . '">'
		. '<span class="axismundi-question__result-meter-value" style="--_value:' . esc_attr( $percent . '%' ) . '"></span>'
		. '</div>'
		. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
}

/** Render the read-only poll for the request's current object view model. */
function axismundi_op_render_question_block() : string {
	$model = axismundi_op_current_object_view_model();
	$poll  = is_array( $model ) ? ( $model['poll'] ?? null ) : null;
	if ( ! is_array( $poll ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '';
	}
	$poll = (array) apply_filters( 'axismundi_op_question_display_poll', $poll, $model );
	$options = array_values( array_filter( (array) ( $poll['options'] ?? array() ), 'is_array' ) );
	if ( empty( $options ) ) {
		return '';
	}
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

	$show_results = (bool) apply_filters( 'axismundi_op_question_show_results', true, $model, $poll );
	$items = $show_results
		? array_map( static fn( array $option ) : string => axismundi_op_render_question_option( $option, $voters ), $options )
		: array();
	$actions = (string) apply_filters( 'axismundi_op_question_actions', '', $model, $poll );
	$mode = 'anyOf' === (string) ( $poll['mode'] ?? '' ) ? 'any-of' : 'one-of';
	return '<div class="axismundi-question axismundi-question--' . ( $closed ? 'closed' : 'open' ) . ' axismundi-question--' . $mode . '">'
		. ( $show_results ? '<ul class="wp-block-list is-style-list-segmented axismundi-question__options">' . implode( '', $items ) . '</ul>' : '' )
		. $actions
		. '<p class="axismundi-question__meta">' . esc_html( implode( ' | ', $meta_parts ) ) . '</p>'
		. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Items escaped by axismundi_op_render_question_option(); meta escaped above.
}

/** Register the server-rendered Question/Poll block and its editor preview. */
function axismundi_op_register_question_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/question' );
}
add_action( 'init', 'axismundi_op_register_question_block' );
