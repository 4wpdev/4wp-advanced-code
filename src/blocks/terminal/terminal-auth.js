/**
 * Account / sign-in link for course progress (4wp-account).
 */

function getTerminalConfig() {
	return window.forwpAdvancedCodeTerminal || {};
}

function getLabel( key, fallback ) {
	const i18n = getTerminalConfig().i18n || {};
	return i18n[ key ] || fallback;
}

function isUserLoggedIn( config ) {
	if ( config.isLoggedIn === true || config.isLoggedIn === 1 || config.isLoggedIn === '1' ) {
		return true;
	}

	return document.body.classList.contains( 'logged-in' );
}

function buildAccountHref( accountUrl, isLoggedIn ) {
	const base = String( accountUrl || '' ).trim();
	if ( ! base ) {
		return '';
	}

	if ( isLoggedIn ) {
		return base;
	}

	const redirect = encodeURIComponent( window.location.href );
	const separator = base.includes( '?' ) ? '&' : '?';

	return `${ base }${ separator }redirect_to=${ redirect }`;
}

const USER_ICON =
	'<svg class="forwp-ac-terminal__auth-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Z" stroke="currentColor" stroke-width="1.8"/><path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

/**
 * @param {HTMLElement} root Terminal root (.forwp-ac-terminal).
 */
export function initTerminalAuth( root ) {
	if ( ! root || root.dataset.forwpAuthReady === '1' ) {
		return;
	}

	const config = getTerminalConfig();
	const accountUrl = String( config.accountUrl || '' ).trim();
	if ( ! accountUrl ) {
		return;
	}

	const header = root.querySelector( '.forwp-ac-terminal__header' );
	if ( ! header ) {
		return;
	}

	const isLoggedIn = isUserLoggedIn( config );
	const href = buildAccountHref( accountUrl, isLoggedIn );
	const label = isLoggedIn
		? getLabel( 'account', 'Account' )
		: getLabel( 'authorize', 'Authorize' );

	const link = document.createElement( 'a' );
	link.className = 'forwp-ac-terminal__auth-link';
	link.href = href;
	link.setAttribute( 'aria-label', label );
	link.title = label;
	link.innerHTML = USER_ICON;

	const labelEl = document.createElement( 'span' );
	labelEl.className = 'forwp-ac-terminal__auth-label';
	labelEl.textContent = label;
	link.appendChild( labelEl );

	const controls = header.querySelector(
		'.forwp-ac-terminal__display-controls'
	);
	if ( controls ) {
		controls.appendChild( link );
	} else {
		link.classList.add( 'forwp-ac-terminal__auth-link--solo' );
		header.appendChild( link );
	}

	root.dataset.forwpAuthReady = '1';
}

export { getLabel };
