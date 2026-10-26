<?php
/** Bounded in-memory mutations; never rewrite shared production files or schema. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );

$mode = (string) getenv( 'AX_PRINCIPAL_MUTATION' );
$clone = static function ( string $name, string $replacement, string $from, string $to ) : void {
	$reflection = new ReflectionFunction( $name );
	$lines = file( $reflection->getFileName() );
	$source = implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $from ) ) {
		throw new RuntimeException( 'Mutation target changed: ' . $name );
	}
	$source = str_replace( $name . '(', $replacement . '(', $source );
	eval( str_replace( $from, $to, $source ) );
};
$audit = file_get_contents( __DIR__ . '/audit-principal-behavior.php' );
if ( 'kind' === $mode ) {
	$clone( 'axismundi_actors_principal_account_role', 'axismundi_actors_mutant_role',
		'AND principal_kind = %s AND principal_id = %d', 'AND LENGTH(%s) > 0 AND principal_id = %d' );
	function axismundi_actors_mutant_user_role( int $account_id, int $user_id ) : string {
		return axismundi_actors_mutant_role( $account_id, 'wp_user', $user_id );
	}
	$audit = str_replace( array( 'axismundi_actors_principal_account_role(', 'axismundi_actors_account_role(' ),
		array( 'axismundi_actors_mutant_role(', 'axismundi_actors_mutant_user_role(' ), $audit );
	$expected = 'Actor grant does not grant same-numbered User';
} elseif ( 'deleted-owner' === $mode ) {
	function axismundi_actors_mutant_raw_owner_count( int $account_id, string $kind ) : int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE identity_id = %d AND principal_kind = %s AND role = 'owner'", axismundi_actors_managers_table(), $account_id, $kind ) );
	}
	$clone( 'axismundi_actors_set_principal_role', 'axismundi_actors_mutant_writer',
		'axismundi_actors_principal_owner_count(', 'axismundi_actors_mutant_raw_owner_count(' );
	$audit = str_replace( 'axismundi_actors_set_principal_role(', 'axismundi_actors_mutant_writer(', $audit );
	$expected = 'Deleted User owner cannot permit demoting last live owner';
} else {
	throw new RuntimeException( 'Set AX_PRINCIPAL_MUTATION to kind or deleted-owner.' );
}
try {
	eval( '?>' . $audit );
} catch ( RuntimeException $error ) {
	if ( 'FAIL: ' . $expected !== $error->getMessage() ) {
		throw $error;
	}
	echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
	return;
}
throw new RuntimeException( 'The mutation survived its focused regression.' );
