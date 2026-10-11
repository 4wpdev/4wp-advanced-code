/**
 * Shared Mermaid render helper.
 * Mermaid is loaded from assets/vendor (pinned, no CDN at runtime).
 */

let renderSequence = 0;
let renderQueue = Promise.resolve();

const FONT = 'Arial, sans-serif';

/**
 * Resolve Mermaid from window / globalThis (vendor bundle assigns both).
 *
 * @return {import('mermaid').default|null}
 */
function resolveMermaidApi() {
	const root = typeof globalThis !== 'undefined' ? globalThis : window;
	if ( root.mermaid && typeof root.mermaid.render === 'function' ) {
		return root.mermaid;
	}
	const bundled = root.__esbuild_esm_mermaid;
	if ( bundled ) {
		const api = bundled.default || bundled;
		if ( api && typeof api.render === 'function' ) {
			root.mermaid = api;
			return api;
		}
	}
	return null;
}

/**
 * @return {Promise<typeof import('mermaid').default>}
 */
async function getMermaid() {
	const existing = resolveMermaidApi();
	if ( existing ) {
		return existing;
	}

	// Dependency script may still be parsing; wait briefly.
	for ( let i = 0; i < 40; i++ ) {
		await new Promise( ( r ) => window.setTimeout( r, 50 ) );
		const api = resolveMermaidApi();
		if ( api ) {
			return api;
		}
	}

	throw new Error( 'Mermaid library is not loaded.' );
}

/**
 * @param {'light'|'dark'} theme Plugin theme → Mermaid theme.
 */
function mermaidTheme( theme ) {
	return theme === 'dark' ? 'dark' : 'default';
}

/**
 * @param {string} source Mermaid source.
 * @param {'light'|'dark'} [theme]
 * @return {Promise<{ svg: string, bindFunctions?: Function }>}
 */
export function renderMermaid( source, theme = 'light' ) {
	renderSequence += 1;
	const id = `forwp-mermaid-${ Date.now() }-${ renderSequence }`;

	const task = renderQueue.then( async () => {
		const mermaid = await getMermaid();

		if ( document.fonts ) {
			await document.fonts.ready;
		}

		mermaid.initialize( {
			startOnLoad: false,
			securityLevel: 'strict',
			suppressErrorRendering: true,
			fontFamily: FONT,
			htmlLabels: false,
			theme: mermaidTheme( theme ),
		} );

		return mermaid.render( id, source );
	} );

	renderQueue = task.catch( () => undefined );
	return task;
}

/**
 * Resolve block theme against plugin default.
 *
 * @param {string} theme auto|light|dark
 * @param {string} pluginTheme light|dark|terminal
 */
export function resolveTheme( theme, pluginTheme = 'light' ) {
	if ( theme === 'light' || theme === 'dark' ) {
		return theme;
	}
	return pluginTheme === 'dark' || pluginTheme === 'terminal'
		? 'dark'
		: 'light';
}
