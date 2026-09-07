<?php
/**
 * Question visual semantics regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_qv_results = array();

/** @param bool[] $results Test results. */
function ax_qv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Render the Question block against one current model. */
function ax_qv_render( array $poll ) : string {
	axismundi_op_set_current_object_view_model(
		array(
			'id'      => 'https://example.com/questions/' . wp_generate_uuid4(),
			'status'  => 'active',
			'poll'    => $poll,
		)
	);
	$html = do_blocks( '<!-- wp:axismundi/question /-->' );
	axismundi_op_set_current_object_view_model( null );
	return $html;
}

$one_of = ax_qv_render(
	array(
		'mode'         => 'oneOf',
		'options'      => array( array( 'name' => 'Cats', 'votes' => 3 ), array( 'name' => 'Dogs', 'votes' => 1 ) ),
		'voters_count' => 4,
		'closes_at'    => '',
		'closed_at'    => '',
	)
);
ax_qv_assert(
	$ax_qv_results,
	'a oneOf Question renders results as a segmented list with static distribution bars',
	false !== strpos( $one_of, 'is-style-list-segmented' )
		&& false !== strpos( $one_of, 'axismundi-question--one-of' )
		&& false !== strpos( $one_of, 'role="progressbar"' )
		&& false !== strpos( $one_of, 'aria-valuemax="100"' )
		&& false !== strpos( $one_of, 'aria-label="Cats: 3 votes, 75%"' )
		&& false !== strpos( $one_of, '3 votes, 75%' )
);

$any_of = ax_qv_render(
	array(
		'mode'         => 'anyOf',
		'options'      => array( array( 'name' => 'Red', 'votes' => 0 ), array( 'name' => 'Blue', 'votes' => 0 ) ),
		'voters_count' => 0,
		'closes_at'    => '',
		'closed_at'    => gmdate( 'c' ),
	)
);
ax_qv_assert(
	$ax_qv_results,
	'an anyOf Question preserves zero-value distribution bars and labels final results when closed',
	false !== strpos( $any_of, 'axismundi-question--any-of' )
		&& false !== strpos( $any_of, 'aria-valuenow="0"' )
		&& false !== strpos( $any_of, 'Final results' )
);

$ax_qv_failures = count( array_filter( $ax_qv_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_qv_results ), $ax_qv_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_qv_failures > 0 ? 1 : 0 );
}
exit( $ax_qv_failures > 0 ? 1 : 0 );
