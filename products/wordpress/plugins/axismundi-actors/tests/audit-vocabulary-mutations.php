<?php
/** Clone one policy function in memory; never rewrite production code. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$mode = (string) getenv( 'AX_VOCABULARY_MUTATION' );
switch ( $mode ) {
	case 'creation':
		$name = 'axismundi_actors_create_local';
		$from = "\$status = (string) ( \$args['status'] ?? 'public' );";
		$to = "\$status = (string) ( \$args['status'] ?? 'internal' );";
		$expected = 'Creation defaults to public for Person';
		break;
	case 'status':
		$name = 'axismundi_actors_set_status';
		$from = "array( 'public', 'disabled', 'tombstone' )";
		$to = "array( 'internal', 'public', 'disabled', 'tombstone' )";
		$expected = 'Actor transition rejects internal';
		break;
	case 'scope':
		$name = 'axismundi_actors_principal_account_role';
		$from = '$account = axismundi_actors_get_by_identity( $account_id );';
		// Force the retired stored-scope dependency, not the derived compatibility label.
		$to = $from . " global \$wpdb; if ( 'site' === \$wpdb->get_var( \$wpdb->prepare( 'SELECT actor_scope FROM %i WHERE identity_id = %d', axismundi_actors_actors_table(), \$account_id ) ) ) { return ''; }";
		$expected = 'Stale site scope cannot deny a principal grant';
		break;
	case 'signing':
		$name = 'axismundi_actors_signing_key_snapshot';
		$from = '! axismundi_actors_is_public_profile( $actor )';
		$to = '! $actor->is_handle_locked()';
		$expected = 'Disabled Account cannot act or sign';
		break;
	case 'principal':
		$name = 'axismundi_actors_principal_account_role';
		$from = "return array_key_exists( \$role, axismundi_actors_principal_roles() ) ? \$role : '';";
		$to = "return 'owner';";
		$expected = 'An Account without a principal grant remains inaccessible';
		break;
	default:
		throw new RuntimeException( 'Set AX_VOCABULARY_MUTATION to creation, status, scope, signing or principal.' );
}
$reflection = new ReflectionFunction( $name );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
if ( 1 !== substr_count( $source, $from ) ) { throw new RuntimeException( 'Mutation target changed.' ); }
if ( 'signing' === $mode ) {
	if ( 1 !== substr_count( $source, "i.status = 'public'" ) ) { throw new RuntimeException( 'Signing mutation target changed.' ); }
	$source = str_replace( "i.status = 'public'", "i.status <> 'tombstone'", $source );
}
$mutant = 'ax_vocabulary_mutant';
eval( str_replace( $name . '(', $mutant . '(', str_replace( $from, $to, $source ) ) );
$audit = str_replace( $name . '(', $mutant . '(', file_get_contents( __DIR__ . '/audit-vocabulary.php' ) );
try { eval( '?>' . $audit ); }
catch ( RuntimeException $error ) {
	if ( $expected !== $error->getMessage() ) { throw $error; }
	echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
	return;
}
throw new RuntimeException( 'Vocabulary mutation survived.' );
