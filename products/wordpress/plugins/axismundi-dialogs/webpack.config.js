const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'dialog-icon/edit': path.resolve( __dirname, 'src/dialog-icon.js' ),
		'dialog-button-group/edit': path.resolve( __dirname, 'src/dialog-button-group.js' ),
		'dialog-button/edit': path.resolve( __dirname, 'src/dialog-button.js' ),
		'dialog-icon-button/edit': path.resolve( __dirname, 'src/dialog-icon-button.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'blocks' ),
		filename: '[name].js',
		clean: false,
	},
};
