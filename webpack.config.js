const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const extraEntry = {
	index: path.resolve( process.cwd(), 'src', 'index.js' ),
	'blocks/terminal/index': path.resolve(
		process.cwd(),
		'src/blocks/terminal/index.js'
	),
	'blocks/terminal/view': path.resolve(
		process.cwd(),
		'src/blocks/terminal/view.js'
	),
	'blocks/ide/index': path.resolve( process.cwd(), 'src/blocks/ide/index.js' ),
	'blocks/ide/view': path.resolve( process.cwd(), 'src/blocks/ide/view.js' ),
	'blocks/diagram/index': path.resolve(
		process.cwd(),
		'src/blocks/diagram/index.js'
	),
	'blocks/diagram/view': path.resolve(
		process.cwd(),
		'src/blocks/diagram/view.js'
	),
};

function withEntries( config ) {
	let entry = extraEntry;

	if ( typeof config.entry === 'function' ) {
		entry = { ...config.entry(), ...extraEntry };
	} else if ( config.entry && typeof config.entry === 'object' ) {
		entry = { ...config.entry, ...extraEntry };
	}

	const plugins = [ ...( config.plugins || [] ) ];

	try {
		const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
		plugins.push(
			new CopyWebpackPlugin( {
				patterns: [
					{
						from: '**/block.json',
						context: path.resolve( process.cwd(), 'src/blocks' ),
						to: 'blocks/[path][name][ext]',
					},
				],
			} )
		);
	} catch ( e ) {
		// copy-webpack-plugin ships with @wordpress/scripts; ignore if missing.
	}

	return {
		...config,
		entry,
		plugins,
	};
}

module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( withEntries )
	: withEntries( defaultConfig );
