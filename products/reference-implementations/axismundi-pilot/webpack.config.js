/**
 * Axismundi Pilot — webpack.config.js
 *
 * Produces two output bundles:
 *   public/js/color-scheme.js   — Interactivity API script module
 *
 * Build:  npm run build
 * Watch:  npm run start
 */

const [scriptConfig, moduleConfig] = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = [
	{
		// Script module: Interactivity API store
		...moduleConfig,
		entry: {
			'js/color-scheme': path.resolve( process.cwd(), 'resources/js', 'color-scheme.js' ),
		},
	},
];
