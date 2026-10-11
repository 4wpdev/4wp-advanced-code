/**
 * Resolve documentation URL from a command entry and optional profile docBase.
 *
 * @param {object}      entry       Command definition.
 * @param {object|null} profileMeta Profile-level options (docBase).
 * @return {string}
 */
function resolveDocUrl( entry, profileMeta ) {
	const raw =
		entry.doc ?? entry.docUrl ?? entry.documentation ?? '';
	const str = String( raw ).trim();

	if ( ! str ) {
		return '';
	}

	if ( /^https?:\/\//i.test( str ) || str.startsWith( '#' ) ) {
		return str;
	}

	if ( str.startsWith( '/' ) ) {
		return str;
	}

	const base = String( profileMeta?.docBase || '' ).trim();
	if ( base ) {
		return `${ base.replace( /\/$/, '' ) }/${ str.replace( /^\//, '' ) }`;
	}

	return '';
}

/**
 * @param {object}      entry       Command definition.
 * @param {object|null} profileMeta Profile-level options.
 * @return {string}
 */
function resolveDocLabel( entry, profileMeta ) {
	const label =
		entry.docLabel ??
		entry.documentationLabel ??
		profileMeta?.docLabel ??
		'';
	return String( label ).trim();
}

/**
 * Emulation / streaming fields from a command entry.
 *
 * @param {object} entry Command definition.
 * @return {{ emulate: boolean, lineDelayMs: number, startDelayMs: number, lines: Array<object>|null }}
 */
function resolveEmulate( entry ) {
	const emulateCfg =
		entry && typeof entry.emulate === 'object' && entry.emulate
			? entry.emulate
			: null;
	const lineDelayMs = Number(
		entry?.lineDelayMs ??
			emulateCfg?.lineDelayMs ??
			emulateCfg?.delayMs ??
			0
	);
	const startDelayMs = Number(
		entry?.startDelayMs ?? emulateCfg?.startDelayMs ?? ( lineDelayMs || 0 )
	);
	const emulateFlag =
		entry?.emulate === true ||
		!! emulateCfg ||
		lineDelayMs > 0 ||
		( Array.isArray( entry?.lines ) && entry.lines.length > 0 );

	return {
		emulate: !! emulateFlag,
		lineDelayMs: lineDelayMs > 0 ? lineDelayMs : 280,
		startDelayMs: startDelayMs > 0 ? startDelayMs : lineDelayMs > 0 ? lineDelayMs : 180,
		lines: Array.isArray( entry?.lines ) ? entry.lines : null,
	};
}

/**
 * Match user input against terminal profile command entries.
 *
 * @param {string}        inputCommand User input.
 * @param {Array<object>} commands     Command definitions.
 * @param {object}        profileMeta  Optional profile fields (docBase, docLabel).
 * @return {{ output: string, tip: string, slug: string, doc: string, docLabel: string, emulate: boolean, lineDelayMs: number, startDelayMs: number, lines: Array<object>|null }}
 */
export function findCommand( inputCommand, commands, profileMeta = {} ) {
	const command = String( inputCommand || '' ).trim().toLowerCase();
	const list = Array.isArray( commands ) ? commands : [];

	const exactMatch = list.find(
		( c ) =>
			typeof c.command === 'string' &&
			c.command.toLowerCase() === command
	);
	if ( exactMatch ) {
		return {
			output: exactMatch.output,
			tip: exactMatch.tip || '',
			slug: exactMatch.slug || 'help',
			doc: resolveDocUrl( exactMatch, profileMeta ),
			docLabel: resolveDocLabel( exactMatch, profileMeta ),
			...resolveEmulate( exactMatch ),
		};
	}

	const patternMatch = list.find(
		( c ) =>
			typeof c.pattern === 'string' &&
			command.startsWith( c.pattern.toLowerCase() )
	);
	if ( patternMatch ) {
		const parts = command.split( ' ' );
		const param = parts[ 3 ] || patternMatch.defaultParam || '';
		return {
			output: String( patternMatch.output || '' ).replace(
				/{param}/g,
				param
			),
			tip: patternMatch.tip || '',
			slug: patternMatch.slug || 'help',
			doc: resolveDocUrl( patternMatch, profileMeta ),
			docLabel: resolveDocLabel( patternMatch, profileMeta ),
			...resolveEmulate( patternMatch ),
		};
	}

	return {
		output: `Error: '${ inputCommand }' is not a registered command.\nType 'help' to see available commands.`,
		tip: 'Check command spelling or browse categories in the sidebar.',
		slug: 'help',
		doc: '',
		docLabel: '',
		emulate: false,
		lineDelayMs: 0,
		startDelayMs: 0,
		lines: null,
	};
}
