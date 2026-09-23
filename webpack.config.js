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
};

function withEntries( config ) {
	let entry = extraEntry;

	if ( typeof config.entry === 'function' ) {
		entry = { ...config.entry(), ...extraEntry };
	} else if ( config.entry && typeof config.entry === 'object' ) {
		entry = { ...config.entry, ...extraEntry };
	}

	return {
		...config,
		entry,
	};
}

module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( withEntries )
	: withEntries( defaultConfig );
