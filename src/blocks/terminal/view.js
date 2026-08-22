/**
 * Frontend: hydrate Terminal blocks.
 */

import { fetchProfileData, initTerminal } from './terminal-app.js';
import { initTerminalDisplay } from './terminal-display.js';
import { initTerminalAuth } from './terminal-auth.js';

function getRestUrl( root ) {
	try {
		const config = JSON.parse( root.dataset.forwpConfig || '{}' );
		if ( config.profileData && typeof config.profileData === 'object' ) {
			return null;
		}
		if ( config.restUrl ) {
			return config.restUrl;
		}
	} catch {
		// ignore
	}

	const profile = root.dataset.profile || 'wp-cli';
	if ( 'embedded' === profile || '' === profile ) {
		return null;
	}

	if ( window.forwpAdvancedCodeTerminal?.restBase ) {
		return `${ window.forwpAdvancedCodeTerminal.restBase }/${ profile }`;
	}

	return `/wp-json/forwp-advanced-code/v1/terminal/${ profile }`;
}

function getEmbeddedProfileData( root ) {
	try {
		const config = JSON.parse( root.dataset.forwpConfig || '{}' );
		if ( config.profileData && typeof config.profileData === 'object' ) {
			return config.profileData;
		}
	} catch {
		// ignore
	}

	return null;
}

function resolveProfileData( root ) {
	const embedded = getEmbeddedProfileData( root );
	if ( embedded ) {
		return Promise.resolve( embedded );
	}

	const restUrl = getRestUrl( root );
	if ( ! restUrl ) {
		return Promise.reject( new Error( 'Missing terminal profile data' ) );
	}

	return fetchProfileData( restUrl );
}

function bootBlock( root ) {
	let welcome =
		root.dataset.welcome ||
		'Interactive Terminal — type "help" for available commands';

	try {
		const config = JSON.parse( root.dataset.forwpConfig || '{}' );
		if ( config.welcomeMessage ) {
			welcome = config.welcomeMessage;
		}
	} catch {
		// ignore
	}

	resolveProfileData( root )
		.then( ( data ) => {
			initTerminal( root, data, welcome );
			initTerminalDisplay( root );
			initTerminalAuth( root );
		} )
		.catch( () => {
			root.classList.remove( 'forwp-ac-terminal--booting' );
			const output = root.querySelector( '.forwp-ac-terminal__output' );
			if ( output ) {
				output.innerHTML =
					'<div class="forwp-ac-terminal__line forwp-ac-terminal__line--error">Could not load terminal commands. Please refresh the page.</div>';
			}
		} );
}

function bootAll() {
	document.querySelectorAll( '.forwp-ac-terminal' ).forEach( bootBlock );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootAll );
} else {
	bootAll();
}
