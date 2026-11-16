<?php
/** Production POST adapter mutations, paired with unmodified controls. */
defined( 'ABSPATH' ) || exit( 1 );
$mutations = array(
	'permission' => array( 'if ( ! axismundi_act_admin_review_allowed( $moderator, $group ) ) {', 'if ( false ) {', 'Handler must reject unauthorized review before calling the service' ),
	'nonce' => array( "if ( ! wp_verify_nonce( \$input['_wpnonce'], axismundi_act_admin_review_nonce_action( \$moderator_id, \$group_id, \$uri ) ) ) {", 'if ( false ) {', 'Invalid nonce accepted' ),
	'publication' => array( "return axismundi_act_review_community_post( get_current_user_id(), \$moderator, \$group, \$uri, 'approve' === \$input['decision'], \$reason );", "return true;", 'Rendered Person must survive a later Account switch' ),
	'selection' => array( '$moderator = axismundi_actors_get_by_identity( $moderator_id );', '$moderator = axismundi_act_admin_publisher();', 'Moderator approval produces an Announce' ),
	'reason' => array( "if ( strlen( \$reason ) > 4096 || ( 'reject' === \$input['decision'] && '' === \$reason ) ) {", 'if ( false ) {', 'Handler must require a rejection reason before calling the service' ),
	'registration' => array( "add_management_page( __( 'Community Moderation', 'axismundi-activities' ), __( 'Community Moderation', 'axismundi-activities' ), 'read', 'axismundi-community-review', 'axismundi_act_render_community_review' );", "if ( axismundi_act_admin_review_groups( axismundi_act_admin_publisher() ) ) { add_management_page( __( 'Community Moderation', 'axismundi-activities' ), __( 'Community Moderation', 'axismundi-activities' ), 'read', 'axismundi-community-review', 'axismundi_act_render_community_review' ); }", 'Moderation page must register without memberships' ),
);
$caught = 0;
foreach ( $mutations as $name => [ $needle, $replacement, $expected ] ) {
	$function = 'registration' === $name ? 'axismundi_act_register_workflow_pages' : 'axismundi_act_admin_review_request';
	$reflection = new ReflectionFunction( $function );
	$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'Ambiguous mutation anchor: ' . $name ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxReviewAdmin_' . $name . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; use \\Axismundi_Actor; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$hits = 0;
		$callback = static function ( ...$args ) use ( $namespace, $function, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $function, $args ); };
		$error = null; ob_start();
		try { (static function ( $slot, $callback ) { ${$slot} = $callback; require __DIR__ . '/audit-community-review-admin.php'; })( 'registration' === $name ? 'community_admin_register' : 'community_admin_request', $callback ); }
		catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( ! $hits || ( ! $mutated && $error ) ) { throw $error ?? new RuntimeException( 'Adapter was not exercised' ); }
		if ( $mutated ) {
			if ( ! $error || $error->getMessage() !== $expected ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught; echo 'CAUGHT ' . $name . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
