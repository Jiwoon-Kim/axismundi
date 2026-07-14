<?php
/**
 * Phase 1 — transformer registry regression (dev-only; dist-excluded).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';

$ax_reg_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_reg_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

// Reset the request-local registry (this fixture owns registration directly).
$GLOBALS['axismundi_op_object_transformers']     = array();
$GLOBALS['axismundi_op_collection_transformers'] = array();
$GLOBALS['axismundi_op_sequence']                = 0;
$GLOBALS['axismundi_op_loaded']                  = true; // Skip the action; register inline.

$noop_uri       = static fn( $s ) : string => 'https://example.com/?p=1';
$noop_transform = static fn( $s ) => array();

// ID validation.
$bad_id  = axismundi_op_register_object_transformer( 'Bad ID', array( 'supports' => '__return_true', 'object_uri' => $noop_uri, 'transform' => $noop_transform ) );
$bad_cb  = axismundi_op_register_object_transformer( 'nocb', array( 'supports' => '__return_true', 'object_uri' => 'not-callable', 'transform' => $noop_transform ) );
ax_reg_assert( $ax_reg_results, 'registration rejects a non-slug id and a non-callable callback', is_wp_error( $bad_id ) && 'ax_op_transformer_id' === $bad_id->get_error_code() && is_wp_error( $bad_cb ) && 'ax_op_transformer_args' === $bad_cb->get_error_code() );

// Priority + registration-order determinism.
axismundi_op_register_object_transformer( 'low', array( 'supports' => '__return_false', 'object_uri' => $noop_uri, 'transform' => $noop_transform, 'priority' => 20 ) );
axismundi_op_register_object_transformer( 'high', array( 'supports' => '__return_false', 'object_uri' => $noop_uri, 'transform' => $noop_transform, 'priority' => 5 ) );
axismundi_op_register_object_transformer( 'mid_a', array( 'supports' => '__return_false', 'object_uri' => $noop_uri, 'transform' => $noop_transform, 'priority' => 10 ) );
axismundi_op_register_object_transformer( 'mid_b', array( 'supports' => '__return_false', 'object_uri' => $noop_uri, 'transform' => $noop_transform, 'priority' => 10 ) );
$order = array_column( axismundi_op_object_transformers(), 'id' );
ax_reg_assert( $ax_reg_results, 'transformers sort by priority then registration order', array( 'high', 'mid_a', 'mid_b', 'low' ) === $order );

// Resolution returns the first supporting transformer; a supports() exception is skipped.
$GLOBALS['axismundi_op_object_transformers'] = array();
$GLOBALS['axismundi_op_sequence']            = 0;
axismundi_op_register_object_transformer( 'throws', array( 'supports' => static function ( $s ) { throw new \RuntimeException( 'boom' ); }, 'object_uri' => $noop_uri, 'transform' => $noop_transform, 'priority' => 1 ) );
axismundi_op_register_object_transformer( 'wants_post', array( 'supports' => static fn( $s ) : bool => is_string( $s ) && 'post' === $s, 'object_uri' => $noop_uri, 'transform' => $noop_transform, 'priority' => 5 ) );
$resolved = axismundi_op_resolve_object_transformer( 'post' );
$none     = axismundi_op_resolve_object_transformer( 'attachment' );
ax_reg_assert( $ax_reg_results, 'resolution skips a throwing supports() and returns the first real match, or null', is_array( $resolved ) && 'wants_post' === $resolved['id'] && null === $none );

// Object and collection registries are independent.
$GLOBALS['axismundi_op_object_transformers']     = array();
$GLOBALS['axismundi_op_collection_transformers'] = array();
axismundi_op_register_collection_transformer( 'a_coll', array( 'supports' => '__return_true', 'collection_uri' => $noop_uri, 'transform' => $noop_transform ) );
ax_reg_assert( $ax_reg_results, 'collection registry is separate from the object registry', 1 === count( axismundi_op_collection_transformers() ) && 0 === count( axismundi_op_object_transformers() ) );

$ax_reg_failures = count( array_filter( $ax_reg_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_reg_results ), $ax_reg_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_reg_failures > 0 ? 1 : 0 );
}
exit( $ax_reg_failures > 0 ? 1 : 0 );
