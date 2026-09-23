/**
 * Draggable split between editor and tour panel (stacked mobile + fullview).
 */

const STORAGE_KEY = 'forwpIdeStackSplit';
const DEFAULT_MAIN_PCT = 38;
const MIN_MAIN_PX = 120;
const MIN_TOUR_PX = 180;

function isStackedLayout( layout ) {
	if ( ! layout ) {
		return false;
	}
	const style = getComputedStyle( layout );
	return style.gridTemplateColumns === '1fr' || layout.classList.contains( 'forwp-ac-ide__layout--stacked' );
}

function readStoredPct() {
	try {
		const raw = sessionStorage.getItem( STORAGE_KEY );
		const n = Number( raw );
		if ( Number.isFinite( n ) && n >= 22 && n <= 72 ) {
			return n;
		}
	} catch {
		// ignore
	}
	return DEFAULT_MAIN_PCT;
}

function applySplit( layout, mainPct ) {
	const pct = Math.max( 22, Math.min( 72, mainPct ) );
	layout.style.setProperty( '--forwp-ide-main-pct', `${ pct }%` );
	layout.dataset.ideMainPct = String( pct );
}

function syncStackedClass( layout ) {
	const stacked = window.matchMedia( '(max-width: 900px)' ).matches;
	layout.classList.toggle( 'forwp-ac-ide__layout--stacked', stacked );
	return stacked;
}

export function initIdeResize( root ) {
	if ( ! root || root.dataset.forwpIdeResizeReady === '1' ) {
		return;
	}

	const layout = root.querySelector( '.forwp-ac-ide__layout' );
	const main = root.querySelector( '.forwp-ac-ide__main' );
	const tour = root.querySelector( '.forwp-ac-ide__tour' );
	if ( ! layout || ! main || ! tour ) {
		return;
	}

	root.dataset.forwpIdeResizeReady = '1';

	let handle = layout.querySelector( '.forwp-ac-ide__resize-handle' );
	if ( ! handle ) {
		handle = document.createElement( 'div' );
		handle.className = 'forwp-ac-ide__resize-handle';
		handle.setAttribute( 'role', 'separator' );
		handle.setAttribute( 'aria-orientation', 'horizontal' );
		handle.setAttribute( 'aria-label', 'Resize editor and lesson panels' );
		handle.tabIndex = 0;
		tour.parentNode?.insertBefore( handle, tour );
	}

	applySplit( layout, readStoredPct() );
	syncStackedClass( layout );

	const mq = window.matchMedia( '(max-width: 900px)' );
	const onMq = () => syncStackedClass( layout );
	mq.addEventListener( 'change', onMq );

	let dragging = false;
	let startPct = DEFAULT_MAIN_PCT;

	const pctFromPointer = ( clientY ) => {
		const rect = layout.getBoundingClientRect();
		const y = clientY - rect.top;
		const h = rect.height;
		if ( h <= MIN_MAIN_PX + MIN_TOUR_PX ) {
			return startPct;
		}
		const mainPx = Math.max( MIN_MAIN_PX, Math.min( h - MIN_TOUR_PX, y ) );
		return ( mainPx / h ) * 100;
	};

	const onPointerDown = ( event ) => {
		if ( ! syncStackedClass( layout ) ) {
			return;
		}
		event.preventDefault();
		dragging = true;
		startPct = Number( layout.dataset.ideMainPct || DEFAULT_MAIN_PCT );
		handle.classList.add( 'is-dragging' );
		document.body.classList.add( 'forwp-ac-ide--resizing' );
		handle.setPointerCapture?.( event.pointerId );
	};

	const onPointerMove = ( event ) => {
		if ( ! dragging ) {
			return;
		}
		event.preventDefault();
		const pct = pctFromPointer( event.clientY );
		applySplit( layout, pct );
	};

	const finishDrag = ( event ) => {
		if ( ! dragging ) {
			return;
		}
		dragging = false;
		handle.classList.remove( 'is-dragging' );
		document.body.classList.remove( 'forwp-ac-ide--resizing' );
		try {
			sessionStorage.setItem( STORAGE_KEY, layout.dataset.ideMainPct || '' );
		} catch {
			// ignore
		}
		handle.releasePointerCapture?.( event.pointerId );
	};

	const onKeydown = ( event ) => {
		if ( ! syncStackedClass( layout ) ) {
			return;
		}
		const step = event.shiftKey ? 8 : 3;
		let pct = Number( layout.dataset.ideMainPct || DEFAULT_MAIN_PCT );
		if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			pct -= step;
		} else if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			pct += step;
		} else {
			return;
		}
		applySplit( layout, pct );
		try {
			sessionStorage.setItem( STORAGE_KEY, layout.dataset.ideMainPct || '' );
		} catch {
			// ignore
		}
	};

	handle.addEventListener( 'pointerdown', onPointerDown );
	handle.addEventListener( 'pointermove', onPointerMove );
	handle.addEventListener( 'pointerup', finishDrag );
	handle.addEventListener( 'pointercancel', finishDrag );
	handle.addEventListener( 'keydown', onKeydown );

	root._forwpIdeResizeCleanup = () => {
		mq.removeEventListener( 'change', onMq );
		handle.removeEventListener( 'pointerdown', onPointerDown );
		handle.removeEventListener( 'pointermove', onPointerMove );
		handle.removeEventListener( 'pointerup', finishDrag );
		handle.removeEventListener( 'pointercancel', finishDrag );
		handle.removeEventListener( 'keydown', onKeydown );
		delete root.dataset.forwpIdeResizeReady;
		root._forwpIdeResizeCleanup = null;
	};
}
