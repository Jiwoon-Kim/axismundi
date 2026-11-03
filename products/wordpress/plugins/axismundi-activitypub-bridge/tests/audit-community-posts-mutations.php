<?php
/** Known-positive controls and isolated production-function mutations. */
defined( 'ABSPATH' ) || exit( 1 );
$mutations = array(
	'profile' => array( 'axismundi_actors_admit_community_post', '! axismundi_actors_is_community( $group )', 'false', 'community_store', 'Storage rejects Channel profile too' ),
	'root-type' => array( 'axismundi_act_admit_community_root', "! in_array( \$object['type'] ?? '', array( 'Page', 'Article' ), true )", 'false', 'community_admit', 'Bare Note is not a root submission' ),
	'moderator' => array( 'axismundi_actors_decide_community_post', "is_wp_error( \$members ) || ! in_array( \$moderator->get_uri(), \$members['items'], true )", 'false', 'community_decide', 'Non-moderator review is refused' ),
	'approval' => array( 'axismundi_act_review_community_post', "array(), \$decide", 'array(), null', 'community_review', 'Approval and publication commit together' ),
	'author' => array( 'axismundi_act_review_community_post', "'actor' => \$group->get_uri(), 'object' => \$create->get_payload()", "'actor' => \$group->get_uri(), 'object' => array_replace( \$create->get_payload(), array( 'actor' => \$group->get_uri() ) )", 'community_review', 'Group announcement preserves the Person authored Create' ),
);
$caught = 0;
foreach ( $mutations as $name => [ $function, $needle, $replacement, $slot, $expected ] ) {
	$reflection = new ReflectionFunction( $function );
	$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'Ambiguous mutation anchor: ' . $name ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxCommunityPost_' . str_replace( '-', '_', $name ) . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; use \\Axismundi_Activity; use \\Axismundi_Actor; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$hits = 0;
		$callback = static function ( ...$args ) use ( $namespace, $function, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $function, $args ); };
		$error = null;
		ob_start();
		try {
			(static function ( $slot, $callback ) { ${$slot} = $callback; require __DIR__ . '/audit-community-posts.php'; })( $slot, $callback );
		} catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( $hits < 1 || ( ! $mutated && $error ) ) { throw $error ?? new RuntimeException( 'Mutation callback was not exercised' ); }
		if ( $mutated ) {
			if ( ! $error || $expected !== $error->getMessage() ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught; echo 'CAUGHT ' . $name . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
