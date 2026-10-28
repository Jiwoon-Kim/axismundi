<?php
/** Dev-only in-memory mutants. Production source files are never modified. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$original_user = get_current_user_id();
$before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
$actor = null;
$user = 0;
foreach ( $wpdb->get_results( 'SELECT m.identity_id,m.principal_id FROM ' . axismundi_actors_managers_table() . " m INNER JOIN {$wpdb->users} u ON u.ID=m.principal_id WHERE m.principal_kind='wp_user'", ARRAY_A ) as $row ) {
	$candidate = axismundi_actors_get_by_identity( (int) $row['identity_id'] );
	if ( $candidate instanceof Axismundi_Actor && 'private' === axismundi_actors_liked_visibility( $candidate )
		&& true === axismundi_act_outbox_authorize( (int) $row['principal_id'], $candidate ) ) {
		$actor = $candidate;
		$user = (int) $row['principal_id'];
		break;
	}
}
if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'An existing private local Person and live operator are required.' ); }
$mutant = static function ( string $function, string $needle, string $replacement ) : string {
	$reflection = new ReflectionFunction( $function );
	$lines = file( $reflection->getFileName() );
	$source = implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'Mutation anchor must match exactly once.' ); }
	$name = $function . '_audit_mutant_' . substr( hash( 'sha256', $replacement ), 0, 8 );
	$source = str_replace( 'function ' . $function . '(', 'function ' . $name . '(', $source );
	eval( str_replace( $needle, $replacement, $source ) );
	return $name;
};
$uris = array();
$remember = static function ( $value ) use ( &$uris ) {
	if ( ! $value instanceof Axismundi_Activity ) { throw new RuntimeException( 'Mutation fixture could not record an Activity.' ); }
	$uris[] = $value->get_uri();
	return $value;
};
try {
	$object = 'https://example.test/c2s-mutant/' . wp_generate_uuid4();
	$like = array( 'type' => 'Like', 'actor' => $actor->get_uri(), 'object' => $object );
	$old = $remember( axismundi_act_submit_outbox( $user, $actor, $like ) );
	$undo = array( 'type' => 'Undo', 'actor' => $actor->get_uri(), 'object' => $old->get_uri() );
	$remember( axismundi_act_submit_outbox( $user, $actor, $undo ) );
	$new = $remember( axismundi_act_submit_outbox( $user, $actor, $like ) );
	$remember( axismundi_act_submit_outbox( $user, $actor, $undo ) );
	if ( ! axismundi_act_get( $new->get_uri() )->is_effective() ) { throw new RuntimeException( 'Baseline does not preserve the new Like.' ); }
	$undo_mutant = $mutant( 'axismundi_act_submit_outbox', '$target = axismundi_act_get( $payload[\'object\'] );',
		'$old_target = axismundi_act_get( $payload[\'object\'] ); $target = axismundi_act_get_actor_like( $actor->get_uri(), $old_target->get_object_uri() ); $payload[\'object\'] = $target->get_uri();' );
	$remember( $undo_mutant( $user, $actor, $undo ) );
	if ( axismundi_act_get( $new->get_uri() )->is_effective() ) { throw new RuntimeException( 'Undo-target mutation escaped detection.' ); }
	echo "CAUGHT undo-target: substituting the current Like cancels the wrong Activity\n";
	if ( ! is_wp_error( axismundi_act_get_liked_collection_page( $actor->get_uri(), PHP_INT_MAX, 1 ) ) ) {
		throw new RuntimeException( 'Baseline exposes a private collection.' );
	}
	$privacy_mutant = $mutant( 'axismundi_act_get_liked_collection_page',
		"'public' !== axismundi_actors_liked_visibility( \$actor )", 'false' );
	if ( is_wp_error( $privacy_mutant( $actor->get_uri(), PHP_INT_MAX, 1 ) ) ) { throw new RuntimeException( 'Privacy mutation escaped detection.' ); }
	echo "CAUGHT collection-policy: bypassing private visibility exposes anonymous membership\n";
	wp_set_current_user( $user );
	if ( ! is_wp_error( axismundi_act_get_liked_collection_page( $actor->get_uri(), PHP_INT_MAX, 1 ) ) ) {
		throw new RuntimeException( 'Baseline grants an operator exception on the AP collection.' );
	}
	$owner_mutant = $mutant( 'axismundi_act_get_liked_collection_page',
		"'public' !== axismundi_actors_liked_visibility( \$actor )",
		"( 'public' !== axismundi_actors_liked_visibility( \$actor ) && ! axismundi_actors_can_access_workspace( \$actor, get_current_user_id() ) )" );
	if ( is_wp_error( $owner_mutant( $actor->get_uri(), PHP_INT_MAX, 1 ) ) ) { throw new RuntimeException( 'Operator exception mutation escaped detection.' ); }
	echo "CAUGHT operator-exception: AP disclosure must not depend on the logged-in operator\n";
} finally {
	wp_set_current_user( $original_user );
	foreach ( $uris as $uri ) {
		$activity = axismundi_act_get( $uri );
		if ( $activity instanceof Axismundi_Activity ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity->get_id() ) ); }
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $uri ) );
	}
}
if ( $before !== $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ) ) {
	throw new RuntimeException( 'Mutation fixtures changed existing ledger rows.' );
}
echo "PASS: three mutants caught; original ledger rows preserved\n";
