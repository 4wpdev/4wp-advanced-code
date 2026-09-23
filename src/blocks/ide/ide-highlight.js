/**
 * Syntax overlay for the IDE wizard practice editor.
 * Uses window.hljs (enqueued separately). Does not change the Code Block.
 */

const EXT_TO_LANG = {
	php: 'php',
	js: 'javascript',
	mjs: 'javascript',
	cjs: 'javascript',
	jsx: 'javascript',
	ts: 'typescript',
	tsx: 'typescript',
	json: 'json',
	css: 'css',
	scss: 'scss',
	html: 'xml',
	htm: 'xml',
	xml: 'xml',
	md: 'markdown',
	sh: 'bash',
	bash: 'bash',
	py: 'python',
};

const LANG_LABEL = {
	php: 'PHP',
	javascript: 'JavaScript',
	typescript: 'TypeScript',
	json: 'JSON',
	css: 'CSS',
	scss: 'SCSS',
	xml: 'HTML',
	markdown: 'Markdown',
	bash: 'Bash',
	python: 'Python',
};

function escapeHtml( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

export function languageFromFile( file ) {
	const name = String( file || '' ).toLowerCase();
	const dot = name.lastIndexOf( '.' );
	const ext = dot >= 0 ? name.slice( dot + 1 ) : '';
	return EXT_TO_LANG[ ext ] || 'php';
}

export function languageLabel( language ) {
	return LANG_LABEL[ language ] || String( language || 'PHP' ).toUpperCase();
}

function highlightHtml( code, language ) {
	const source = String( code ?? '' );
	const hljs = window.hljs;
	if ( ! hljs?.highlight ) {
		return escapeHtml( source );
	}

	try {
		if ( language && typeof hljs.getLanguage === 'function' && hljs.getLanguage( language ) ) {
			return hljs.highlight( source, {
				language,
				ignoreIllegals: true,
			} ).value;
		}
		if ( typeof hljs.highlightAuto === 'function' ) {
			return hljs.highlightAuto( source ).value;
		}
	} catch {
		// fall through
	}

	return escapeHtml( source );
}

function ensureOverlay( textarea ) {
	if ( ! textarea ) {
		return { wrap: null, codeEl: null };
	}

	let wrap = textarea.closest( '.forwp-ac-ide__code-wrap' );
	if ( ! wrap ) {
		wrap = document.createElement( 'div' );
		wrap.className = 'forwp-ac-ide__code-wrap';
		textarea.parentNode?.insertBefore( wrap, textarea );
		wrap.appendChild( textarea );
	}

	let pre = wrap.querySelector( '.forwp-ac-ide__highlight' );
	if ( ! pre ) {
		pre = document.createElement( 'pre' );
		pre.className = 'forwp-ac-ide__highlight';
		pre.setAttribute( 'aria-hidden', 'true' );
		const code = document.createElement( 'code' );
		pre.appendChild( code );
		wrap.insertBefore( pre, textarea );
	}

	const codeEl = pre.querySelector( 'code' );
	return { wrap, codeEl };
}

/**
 * Attach overlay highlighter to a wizard root.
 *
 * @param {HTMLElement} root
 * @param {HTMLTextAreaElement|null} textarea
 * @return {{ paint: Function, setLanguageFromFile: Function, syncScroll: Function, destroy: Function }}
 */
export function initIdeHighlight( root, textarea ) {
	const statusLang = root?.querySelector( '[data-ide-status-lang]' );
	const { codeEl } = ensureOverlay( textarea );
	let language = 'php';
	let raf = 0;

	function paint() {
		if ( ! textarea || ! codeEl ) {
			return;
		}

		codeEl.className = `language-${ language }`;
		codeEl.innerHTML = highlightHtml( textarea.value, language );

		if ( window.hljs?.highlight ) {
			root.classList.add( 'forwp-ac-ide--hl' );
		}

		if ( statusLang ) {
			statusLang.textContent = languageLabel( language );
		}

		syncScroll();
	}

	function schedulePaint() {
		if ( raf ) {
			return;
		}
		raf = window.requestAnimationFrame( () => {
			raf = 0;
			paint();
		} );
	}

	function setLanguageFromFile( file ) {
		language = languageFromFile( file );
		paint();
	}

	function syncScroll() {
		const pre = codeEl?.parentElement;
		if ( ! textarea || ! pre ) {
			return;
		}
		pre.scrollTop = textarea.scrollTop;
		pre.scrollLeft = textarea.scrollLeft;
	}

	function destroy() {
		if ( raf ) {
			window.cancelAnimationFrame( raf );
			raf = 0;
		}
		root?.classList.remove( 'forwp-ac-ide--hl' );
	}

	return { paint, schedulePaint, setLanguageFromFile, syncScroll, destroy };
}
