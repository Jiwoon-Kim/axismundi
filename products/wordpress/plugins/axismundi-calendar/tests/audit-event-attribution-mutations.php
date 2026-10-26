<?php
/** In-memory attribution mutations; production files are never modified. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$mode = (string) getenv( 'AX_EVENT_AXIS_MUTATION' );
if ( 'read' === $mode ) {
	$name = 'axismundi_cal_event_acting_actor_uri';
	$from = "\treturn '';\n}";
	$to = "\t\$author = axismundi_actors_get_for_user( (int) get_post_field( 'post_author', \$post_id ) );\n"
		. "\treturn \$author instanceof Axismundi_Actor ? \$author->get_uri() : '';\n}";
	$expected = 'Missing stored attribution never falls back to post_author';
} elseif ( 'backfill' === $mode ) {
	$name = 'axismundi_cal_backfill_event_acting_actors';
	$from = '$uri = axismundi_act_get_object_attribution( axismundi_cal_event_object_uri( $post ) );';
	$to = '$author = axismundi_actors_get_for_user( (int) $post->post_author );'
		. "\$uri = \$author instanceof Axismundi_Actor ? \$author->get_uri() : '';";
	$expected = 'Backfill cannot invent an Actor from a User without a recorded Create';
} else {
	throw new RuntimeException( 'Set AX_EVENT_AXIS_MUTATION to read or backfill.' );
}
$reflection = new ReflectionFunction( $name );
$lines = file( $reflection->getFileName() );
$source = str_replace( "\r\n", "\n", implode( '', array_slice( $lines, $reflection->getStartLine() - 1,
	$reflection->getEndLine() - $reflection->getStartLine() + 1 ) ) );
if ( 1 !== substr_count( $source, $from ) ) { throw new RuntimeException( 'Mutation target changed.' ); }
$mutant = 'axismundi_cal_axis_mutant';
eval( str_replace( $from, $to, str_replace( $name . '(', $mutant . '(', $source ) ) );
$audit = str_replace( $name . '(', $mutant . '(', file_get_contents( __DIR__ . '/audit-event-attribution-axis.php' ) );
try {
	eval( '?>' . $audit );
} catch ( RuntimeException $error ) {
	if ( $expected !== $error->getMessage() ) { throw $error; }
	echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
	return;
}
throw new RuntimeException( 'Attribution mutation survived.' );
