#!/usr/bin/env node
/**
 * Install the local wp-env site and activate Omphalos after containers start.
 */

const { spawnSync } = require( 'child_process' );

const npx = process.platform === 'win32' ? 'npx.cmd' : 'npx';

function run( args, options = {} ) {
	const result = spawnSync( npx, [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		stdio: options.quiet ? 'ignore' : 'inherit',
		shell: false,
	} );

	return result.status === 0;
}

if ( ! run( [ 'core', 'is-installed' ], { quiet: true } ) ) {
	run( [
		'core',
		'install',
		'--url=http://localhost:8894',
		'--title=Omphalos',
		'--admin_user=admin',
		'--admin_password=password',
		'--admin_email=admin@example.test',
	] );
}

run( [ 'theme', 'activate', 'omphalos' ] );
