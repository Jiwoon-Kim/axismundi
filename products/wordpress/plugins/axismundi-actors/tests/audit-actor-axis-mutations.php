<?php
/** Focused in-memory mutations; never edit production files. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/admin.php';
$mode = (string) getenv( 'AX_ACTOR_AXIS_MUTATION' );
switch ( $mode ) {
	case 'topic':
		$name = 'axismundi_forum_topic_author';
		$from = 'return $id > 0 ? axismundi_actors_get_by_identity( $id ) : null;';
		$to = 'return $id > 0 ? axismundi_actors_get_by_identity( $id ) : axismundi_actors_get_for_user( (int) $topic->post_author );';
		$expected = 'Unknown Topic attribution is not inferred from either User or current Account';
		break;
	case 'folder':
		$name = 'axismundi_media_folder_actor_uri';
		$from = '$actor = \'\' !== $attributed ? axismundi_actors_get_by_uri( $attributed ) : null;';
		$to = '$actor = axismundi_actors_get_for_user( axismundi_media_folder_owner( $term_id ) );';
		$expected = 'Folder storage ownership does not invent publication attribution';
		break;
	case 'selection':
		$name = 'axismundi_act_current_local_actor';
		$from = 'axismundi_actors_workspace_publisher( $user_id )';
		$to = 'axismundi_actors_get_for_user( $user_id )';
		$expected = 'Social actor follows selected Person B';
		break;
	case 'profile':
		$name = 'axismundi_actors_admin_profile_actor';
		$from = '$result = axismundi_actors_current_workspace_result();';
		$to = '$result = array( \'actor\' => axismundi_actors_get_for_user( get_current_user_id() ) );';
		$expected = 'Actor Profile edits selected Organization';
		break;
	case 'plural':
		$name = 'axismundi_actors_list_local_actors';
		$from = 'return $actors;';
		$to = 'return array_filter( $actors, static fn( $actor ) => $actor->get_local_user_id() > 0 );';
		$expected = 'Transport inventory includes multiple Persons and Organization without User inference';
		break;
	default:
		throw new RuntimeException( 'Set AX_ACTOR_AXIS_MUTATION to topic, folder, selection, profile or plural.' );
}
$reflection = new ReflectionFunction( $name );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1,
	$reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
if ( 1 !== substr_count( $source, $from ) ) { throw new RuntimeException( 'Mutation target changed: ' . $name ); }
$mutant = 'axismundi_axis_mutant';
eval( str_replace( $from, $to, str_replace( $name . '(', $mutant . '(', $source ) ) );
$audit = str_replace( $name . '(', $mutant . '(', file_get_contents( __DIR__ . '/audit-actor-axis.php' ) );
try {
	eval( '?>' . $audit );
} catch ( RuntimeException $error ) {
	if ( $expected !== $error->getMessage() ) { throw $error; }
	echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
	return;
}
throw new RuntimeException( 'Actor axis mutation survived.' );
