/**
 * Frontend: hydrate IDE wizard blocks.
 */

import { bootIdeRoot } from './ide-app.js';

function bootAll() {
	document.querySelectorAll( '.forwp-ac-ide' ).forEach( bootIdeRoot );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootAll );
} else {
	bootAll();
}
