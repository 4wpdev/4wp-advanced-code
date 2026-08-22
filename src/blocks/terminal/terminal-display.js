/**
 * Terminal display modes: full view (fullscreen) and sticky compact panel.
 */

function getBlockShell( root ) {
	return (
		root.closest( '.wp-block-forwp-advanced-code-terminal' ) ||
		root.closest( '.forwp-ac-terminal-block' ) ||
		root
	);
}

function readDisplaySettings( root ) {
	const shell = getBlockShell( root );

	const readFlag = ( datasetKey ) => {
		if ( root.dataset[ datasetKey ] !== undefined ) {
			return root.dataset[ datasetKey ] !== '0';
		}
		if ( shell?.dataset?.[ datasetKey ] !== undefined ) {
			return shell.dataset[ datasetKey ] !== '0';
		}
		return true;
	};

	const stickySide =
		root.dataset.stickySide === 'left' ||
		shell?.dataset?.stickySide === 'left'
			? 'left'
			: 'right';

	return {
		enableFullView: readFlag( 'enableFullview' ),
		enableSticky: readFlag( 'enableSticky' ),
		stickySide,
	};
}

function getDisplayLabels() {
	const i18n = window.forwpAdvancedCodeTerminal?.i18n || {};

	return {
		fullView: i18n.fullView || 'Full view',
		sticky: i18n.sticky || 'Sticky',
	};
}

function createDisplayButton( mode, label ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'forwp-ac-terminal__display-btn';
	btn.setAttribute( 'data-forwp-mode', mode );
	btn.setAttribute( 'aria-pressed', 'false' );
	btn.setAttribute( 'aria-label', label );
	btn.title = label;
	btn.innerHTML =
		( mode === 'fullview' ? '⛶' : '📌' ) +
		`<span class="forwp-ac-terminal__display-btn-label">${ label }</span>`;
	return btn;
}

function ensureDisplayControls( root, enableFullView, enableSticky ) {
	if ( root.querySelector( '.forwp-ac-terminal__display-controls' ) ) {
		return;
	}

	const header = root.querySelector( '.forwp-ac-terminal__header' );
	if ( ! header ) {
		return;
	}

	const labels = getDisplayLabels();
	const controls = document.createElement( 'div' );
	controls.className = 'forwp-ac-terminal__display-controls';

	if ( enableFullView ) {
		controls.appendChild(
			createDisplayButton( 'fullview', labels.fullView )
		);
	}

	if ( enableSticky ) {
		controls.appendChild( createDisplayButton( 'sticky', labels.sticky ) );
	}

	if ( ! controls.children.length ) {
		return;
	}

	header.appendChild( controls );
}

function isFullscreen( root ) {
	const doc = document;
	const fsEl = doc.fullscreenElement || doc.webkitFullscreenElement;
	return fsEl === root || fsEl === getBlockShell( root );
}

function requestFullscreen( el ) {
	const req =
		el.requestFullscreen?.bind( el ) ||
		el.webkitRequestFullscreen?.bind( el );
	return Promise.resolve( req?.() ).catch( () => {} );
}

function exitFullscreen() {
	const doc = document;
	const exit =
		doc.exitFullscreen?.bind( doc ) ||
		doc.webkitExitFullscreen?.bind( doc );
	return Promise.resolve( exit?.() ).catch( () => {} );
}

function syncButtons( root, mode ) {
	root.querySelectorAll( '[data-forwp-mode]' ).forEach( ( btn ) => {
		const btnMode = btn.getAttribute( 'data-forwp-mode' );
		const pressed =
			( btnMode === 'fullview' && mode === 'fullview' ) ||
			( btnMode === 'sticky' && mode === 'sticky' );
		btn.setAttribute( 'aria-pressed', pressed ? 'true' : 'false' );
	} );
}

function syncStickyBodyClass() {
	const hasSticky = document.querySelector(
		'.wp-block-forwp-advanced-code-terminal.forwp-ac-terminal--sticky, .forwp-ac-terminal-block.forwp-ac-terminal--sticky'
	);
	document.body.classList.toggle(
		'forwp-ac-terminal-display-sticky',
		!! hasSticky
	);
}

function canOutputScroll( output, deltaY ) {
	if ( ! output ) {
		return false;
	}

	const { scrollTop, scrollHeight, clientHeight } = output;

	if ( deltaY < 0 ) {
		return scrollTop > 0;
	}

	if ( deltaY > 0 ) {
		return scrollTop + clientHeight < scrollHeight - 1;
	}

	return false;
}

function bindFullscreenKeyboard( root ) {
	const input = root.querySelector( '.forwp-ac-terminal__input' );
	const viewport = window.visualViewport;

	if ( ! input || ! viewport ) {
		return () => {};
	}

	const clearKeyboardLayout = () => {
		root.classList.remove( 'forwp-ac-terminal--keyboard-open' );
		root.style.removeProperty( 'height' );
		root.style.removeProperty( 'transform' );
	};

	const syncKeyboardLayout = () => {
		if ( ! isFullscreen( root ) ) {
			clearKeyboardLayout();
			return;
		}

		const keyboardGap =
			window.innerHeight - viewport.height - viewport.offsetTop;
		const inputFocused = document.activeElement === input;

		if ( inputFocused || keyboardGap > 80 ) {
			root.classList.add( 'forwp-ac-terminal--keyboard-open' );
			root.style.height = `${ Math.round( viewport.height ) }px`;
			root.style.transform = `translateY(${ Math.round(
				viewport.offsetTop
			) }px)`;
			return;
		}

		clearKeyboardLayout();
	};

	const onViewportChange = () => {
		syncKeyboardLayout();
	};

	const onInputFocus = () => {
		syncKeyboardLayout();
		window.requestAnimationFrame( syncKeyboardLayout );
		setTimeout( syncKeyboardLayout, 120 );
		setTimeout( syncKeyboardLayout, 320 );
	};

	const onInputBlur = () => {
		setTimeout( () => {
			if ( document.activeElement === input ) {
				return;
			}
			clearKeyboardLayout();
		}, 120 );
	};

	viewport.addEventListener( 'resize', onViewportChange );
	viewport.addEventListener( 'scroll', onViewportChange );
	input.addEventListener( 'focus', onInputFocus );
	input.addEventListener( 'blur', onInputBlur );
	syncKeyboardLayout();

	return () => {
		viewport.removeEventListener( 'resize', onViewportChange );
		viewport.removeEventListener( 'scroll', onViewportChange );
		input.removeEventListener( 'focus', onInputFocus );
		input.removeEventListener( 'blur', onInputBlur );
		clearKeyboardLayout();
	};
}

/**
 * @param {HTMLElement} root Terminal root (.forwp-ac-terminal).
 */
export function initTerminalDisplay( root ) {
	if ( ! root || root.dataset.forwpDisplayReady === '1' ) {
		return;
	}

	const { enableFullView, enableSticky, stickySide } =
		readDisplaySettings( root );

	if ( ! enableFullView && ! enableSticky ) {
		return;
	}

	ensureDisplayControls( root, enableFullView, enableSticky );

	root.dataset.forwpDisplayReady = '1';

	const shell = getBlockShell( root );
	let mode = 'default';
	let stickyPlaceholder = null;
	let stickyWheelHandler = null;
	let keyboardUnbind = null;

	const syncStickyPlaceholder = () => {
		if ( ! stickyPlaceholder ) {
			return;
		}

		stickyPlaceholder.style.height = `${ shell.offsetHeight }px`;
	};

	const ensureStickyPlaceholder = () => {
		if ( stickyPlaceholder?.parentNode ) {
			syncStickyPlaceholder();
			return;
		}

		stickyPlaceholder = document.createElement( 'div' );
		stickyPlaceholder.className = 'forwp-ac-terminal__sticky-placeholder';
		stickyPlaceholder.setAttribute( 'aria-hidden', 'true' );
		syncStickyPlaceholder();
		shell.parentNode?.insertBefore( stickyPlaceholder, shell );
	};

	const removeStickyPlaceholder = () => {
		stickyPlaceholder?.remove();
		stickyPlaceholder = null;
	};

	const bindStickyWheel = () => {
		if ( stickyWheelHandler ) {
			return;
		}

		stickyWheelHandler = ( event ) => {
			if ( mode !== 'sticky' ) {
				return;
			}

			const output = root.querySelector( '.forwp-ac-terminal__output' );
			if (
				output?.contains( event.target ) &&
				canOutputScroll( output, event.deltaY )
			) {
				return;
			}

			window.scrollBy( { top: event.deltaY, left: 0, behavior: 'auto' } );
			event.preventDefault();
		};

		shell.addEventListener( 'wheel', stickyWheelHandler, { passive: false } );
	};

	const unbindStickyWheel = () => {
		if ( ! stickyWheelHandler ) {
			return;
		}

		shell.removeEventListener( 'wheel', stickyWheelHandler );
		stickyWheelHandler = null;
	};

	const setMode = ( next ) => {
		mode = next;
		shell.classList.remove(
			'forwp-ac-terminal--sticky',
			'forwp-ac-terminal--sticky-left',
			'forwp-ac-terminal--sticky-right'
		);
		root.classList.remove( 'forwp-ac-terminal--fullview' );
		removeStickyPlaceholder();
		unbindStickyWheel();

		if ( next === 'sticky' ) {
			shell.classList.add( 'forwp-ac-terminal--sticky' );
			shell.classList.add(
				stickySide === 'left'
					? 'forwp-ac-terminal--sticky-left'
					: 'forwp-ac-terminal--sticky-right'
			);
			if ( ! shell.closest( '.forwp-practice-case__workspace-terminal' ) ) {
				ensureStickyPlaceholder();
			}
			bindStickyWheel();
		}

		if ( next === 'fullview' ) {
			root.classList.add( 'forwp-ac-terminal--fullview' );
		}

		syncButtons( root, mode );
		syncStickyBodyClass();
	};

	const onFullscreenChange = () => {
		keyboardUnbind?.();
		keyboardUnbind = null;

		if ( isFullscreen( root ) ) {
			if ( mode === 'sticky' ) {
				setMode( 'default' );
			}
			setMode( 'fullview' );
			keyboardUnbind = bindFullscreenKeyboard( root );
			return;
		}

		if ( mode === 'fullview' ) {
			setMode( 'default' );
		}
	};

	document.addEventListener( 'fullscreenchange', onFullscreenChange );
	document.addEventListener( 'webkitfullscreenchange', onFullscreenChange );

	root.addEventListener( 'click', ( event ) => {
		const btn = event.target.closest( '[data-forwp-mode]' );
		if ( ! btn || ! root.contains( btn ) ) {
			return;
		}

		const btnMode = btn.getAttribute( 'data-forwp-mode' );

		if ( btnMode === 'fullview' && enableFullView ) {
			event.preventDefault();
			if ( isFullscreen( root ) ) {
				void exitFullscreen();
				return;
			}
			if ( mode === 'sticky' ) {
				setMode( 'default' );
			}
			void requestFullscreen( root );
			return;
		}

		if ( btnMode === 'sticky' && enableSticky ) {
			event.preventDefault();
			if ( mode === 'sticky' ) {
				if ( isFullscreen( root ) ) {
					void exitFullscreen();
				}
				setMode( 'default' );
				return;
			}
			if ( isFullscreen( root ) ) {
				void exitFullscreen();
			}
			setMode( 'sticky' );
		}
	} );

	syncButtons( root, mode );
}
