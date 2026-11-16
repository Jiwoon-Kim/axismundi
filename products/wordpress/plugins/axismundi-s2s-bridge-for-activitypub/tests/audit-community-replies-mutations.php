<?php
defined( 'ABSPATH' ) || exit( 1 );
$mutations = array(
	'delivery' => array( 'axismundi_activitypub_bridge_activity_inboxes', 'if ( axismundi_activitypub_bridge_is_direct_group_submission( $activity ) ) {', 'if ( false ) {', 'community_reply_inboxes', 'Remote reply delivers only to the Community Inbox' ),
	'addressing' => array( 'axismundi_act_prepare_community_reply', "\$note['to'] = array( \$group->get_uri() );", "\$note['to'] = array( 'https://www.w3.org/ns/activitystreams#Public' );", 'community_reply_prepare', 'Reply addressing preserves Group to and parent author cc' ),
	'context' => array( 'axismundi_act_prepare_community_reply', '$context = axismundi_act_community_reply_context( $parent_uri );', '$context = null;', 'community_reply_prepare', 'Reply context resolves the accepted Community' ),
	'announce' => array( 'axismundi_act_review_community_post', 'return axismundi_act_record_source_activity(', "return new WP_Error( 'audit_no_announce', 'Announce disabled' ); return axismundi_act_record_source_activity(", 'community_reply_review', 'Local reply becomes Group Announce only after approval' ),
);
$caught = 0;
foreach ( $mutations as $name => [ $function, $needle, $replacement, $slot, $expected ] ) {
	$r = new ReflectionFunction( $function );
	$source = implode( '', array_slice( file( $r->getFileName() ), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'Ambiguous mutation anchor: ' . $name ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxCommunityReply_' . $name . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; use \\Axismundi_Actor; use \\Axismundi_Activity; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$hits = 0;
		$callback = static function ( ...$args ) use ( $namespace, $function, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $function, $args ); };
		$error = null; ob_start();
		try { (static function ( $slot, $callback ) { ${$slot} = $callback; require __DIR__ . '/audit-community-replies.php'; })( $slot, $callback ); }
		catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( ! $hits || ( ! $mutated && $error ) ) { throw $error ?? new RuntimeException( 'Callback was not exercised' ); }
		if ( $mutated ) {
			if ( ! $error || $error->getMessage() !== $expected ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught; echo 'CAUGHT ' . $name . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
