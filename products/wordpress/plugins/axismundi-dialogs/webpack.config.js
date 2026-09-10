const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		edit: path.resolve( __dirname, 'src/dialog-icon.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'blocks/dialog-icon' ),
		filename: '[name].js',
		clean: false,
	},
};
