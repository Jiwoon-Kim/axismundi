#!/usr/bin/env node
/**
 * Seed @wordpress/env's offline latest-version cache.
 *
 * @wordpress/env parses its default config before merging local .wp-env.json.
 * When the machine is offline and this theme's wp-env cache is fresh, that
 * default parse asks WordPress.org for the latest stable version and fails
 * before our explicit `core` setting can take effect.
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const crypto = require( 'crypto' );

const themeRoot = path.resolve( __dirname, '..' );
const configPath = path.join( themeRoot, '.wp-env.json' );
const cacheRoot = process.env.WP_ENV_HOME
	? path.resolve( process.env.WP_ENV_HOME )
	: path.join( os.homedir(), '.wp-env' );
const cacheKey = crypto.createHash( 'md5' ).update( configPath ).digest( 'hex' );
const cacheDir = path.join( cacheRoot, cacheKey );
const cachePath = path.join( cacheDir, 'wp-env-cache.json' );

fs.mkdirSync( cacheDir, { recursive: true } );

const cache = fs.existsSync( cachePath )
	? JSON.parse( fs.readFileSync( cachePath, 'utf8' ) )
	: {};

cache.latestWordPressVersion = cache.latestWordPressVersion || '7.0';

fs.writeFileSync( cachePath, JSON.stringify( cache ) );
console.log( `Seeded wp-env cache: ${ cachePath }` );
