<?php
/** In-memory coverage mutants; no source file or database mutation. */
defined( 'ABSPATH' ) || exit( 1 );
$baseline = axismundi_op_vocabulary_snapshot();
if ( ! is_array( $baseline ) ) { throw new RuntimeException( 'A valid baseline is required.' ); }
$mode = getenv( 'AX_STANDARD_VOCAB_MUTATION' );
$mutant = $baseline;
$labels = array(
	'type' => 'Exact 54 standard types, without vendor types or omissions',
	'parent' => 'Invite has the standard tier, category and direct parents',
	'exclusion' => 'Arrive inherits Activity but excludes object',
	'property' => 'All Section 4 properties plus orderedItems, without duplicate language-map predicates',
);
switch ( $mode ) {
	case 'type': unset( $mutant['types']['Person'] ); break;
	case 'parent': $mutant['types']['Invite']['parents'] = array( 'Activity' ); break;
	case 'exclusion': $mutant['types']['IntransitiveActivity']['excludes'] = array(); break;
	case 'property': unset( $mutant['properties']['instrument'] ); break;
	default: throw new RuntimeException( 'Unknown standard vocabulary mutation.' );
}
$GLOBALS['axismundi_op_vocabulary_snapshot'] = $mutant;
try {
	try { require __DIR__ . '/audit-standard-vocabulary.php'; }
	catch ( RuntimeException $error ) {
		if ( $labels[ $mode ] !== $error->getMessage() ) { throw $error; }
		printf( "CAUGHT %s: %s\n", $mode, $error->getMessage() );
		return;
	}
	throw new RuntimeException( 'Standard vocabulary mutation survived: ' . $mode );
} finally {
	$GLOBALS['axismundi_op_vocabulary_snapshot'] = $baseline;
}
