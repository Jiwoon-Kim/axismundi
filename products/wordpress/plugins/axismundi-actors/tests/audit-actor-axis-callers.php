<?php
/** Keep single-User Actor lookup confined to documented User/legacy consumers. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$allowed = array(
	'axismundi-actors/includes/admin.php::axismundi_actors_authorize_user_actor' => 'E: legacy User activation API',
	'axismundi-actors/includes/admin.php::axismundi_actors_can_manage_user_actor' => 'E: legacy User activation adapter delegates existing Accounts to the Account gate',
	'axismundi-actors/includes/admin.php::axismundi_actors_profile_panel' => 'D: edited WordPress User summary',
	'axismundi-actors/includes/admin.php::axismundi_actors_users_column_content' => 'D: fixed Users-table row',
	'axismundi-actors/includes/avatar.php::axismundi_actors_filter_avatar_data' => 'D: WordPress User avatar',
	'axismundi-actors/includes/repository.php::axismundi_actors_ensure_for_user' => 'D: User-bound compatibility identity',
	'axismundi-actors/includes/acting-actor.php::axismundi_actors_default_acting_actor' => 'E: disabled-rollout resolver',
	'axismundi-actors/includes/account-operations.php::axismundi_actors_legacy_current_workspace' => 'E: disabled-rollout resolver',
	'axismundi-actors/includes/routing.php::axismundi_actors_legacy_author_profile_redirect_url' => 'E: historical author redirect',
	'axismundi-activities/includes/local-social-ui.php::axismundi_act_users_follow_column_content' => 'D: fixed Users-row target, not acting subject',
	'axismundi-activitypub-bridge/includes/migration-scan.php::axismundi_activitypub_bridge_legacy_local_actor' => 'E: old official-plugin User identifiers',
	'axismundi-contacts/includes/lifecycle.php::axismundi_contacts_purge_for_deleted_user' => 'D: WP User deletion lifecycle; Contacts redesign is separate',
);
$calls = 0;
foreach ( glob( WP_PLUGIN_DIR . '/axismundi-*/includes', GLOB_ONLYDIR ) as $directory ) {
	// E: the hard-cutoff Note plugin is frozen, not a migration target.
	if ( 'axismundi-note' === basename( dirname( $directory ) ) ) { continue; }
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' !== $file->getExtension() ) { continue; }
		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( WP_PLUGIN_DIR ) + 1 ) );
		$function = '(file)';
		$depth = 0;
		$stack = array();
		$pending = false;
		$name_expected = false;
		$name = null;
		foreach ( token_get_all( file_get_contents( $file->getPathname() ) ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_CURLY_OPEN === $token[0] || T_DOLLAR_OPEN_CURLY_BRACES === $token[0] ) { ++$depth; continue; }
				if ( T_FUNCTION === $token[0] ) { $pending = $name_expected = true; $name = null; continue; }
				if ( T_STRING === $token[0] && $name_expected ) { $name = $token[1]; $name_expected = false; continue; }
				if ( T_STRING === $token[0] && 'axismundi_actors_get_for_user' === strtolower( $token[1] ) ) {
					$key = $relative . '::' . $function;
					if ( ! isset( $allowed[ $key ] ) ) { throw new RuntimeException( 'Unclassified single-Actor consumer: ' . $key . ':' . $token[2] ); }
					++$calls;
				}
				continue;
			}
			if ( '(' === $token && $name_expected ) { $name_expected = false; }
			if ( '{' === $token ) {
				++$depth;
				if ( $pending ) { $stack[ $depth ] = $function; $function = $name ?? $function; $pending = false; }
			} elseif ( '}' === $token ) {
				if ( isset( $stack[ $depth ] ) ) { $function = $stack[ $depth ]; unset( $stack[ $depth ] ); }
				--$depth;
			} elseif ( ';' === $token && $pending ) { $pending = false; }
		}
	}
}
printf( "PASS actor-axis-callers %d\n", $calls );
