/**
 * Progressive enhancement: step highlight + edge animation.
 * Maps by source node IDs (%% steps: A,B,C), not Mermaid-generated IDs.
 * Respects prefers-reduced-motion.
 */

function prefersReducedMotion() {
	return (
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	);
}

/**
 * Find SVG group/node whose id encodes the source node id.
 *
 * @param {SVGElement} svg
 * @param {string} nodeId
 * @return {Element|null}
 */
function findNode( svg, nodeId ) {
	const safe = nodeId.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	const re = new RegExp( `(^|[-_])${ safe }($|[-_])`, 'i' );

	const candidates = svg.querySelectorAll(
		'.node, .flowchart-label, [id], g[class*="node"]'
	);
	for ( const el of candidates ) {
		const id = el.getAttribute( 'id' ) || '';
		const dataId = el.getAttribute( 'data-id' ) || '';
		if ( dataId === nodeId || id === nodeId || re.test( id ) ) {
			return el.closest( 'g' ) || el;
		}
	}

	// Fallback: title text matches label-less id usage.
	for ( const el of svg.querySelectorAll( 'g.node, g[id]' ) ) {
		const id = el.getAttribute( 'id' ) || '';
		if ( id.includes( `-${ nodeId }-` ) || id.endsWith( `-${ nodeId }` ) ) {
			return el;
		}
	}

	return null;
}

/**
 * @param {SVGElement} svg
 * @param {string} fromId
 * @param {string} toId
 * @return {Element|null}
 */
function findEdge( svg, fromId, toId ) {
	const paths = svg.querySelectorAll(
		'.edgePath, .flowchart-link, path[id*="L_"], path[class*="edge"]'
	);
	const a = fromId.toLowerCase();
	const b = toId.toLowerCase();

	for ( const path of paths ) {
		const id = ( path.getAttribute( 'id' ) || '' ).toLowerCase();
		const parentId = (
			path.closest( '[id]' )?.getAttribute( 'id' ) || ''
		).toLowerCase();
		const hay = id + ' ' + parentId;
		if ( hay.includes( a ) && hay.includes( b ) ) {
			return path.tagName === 'path' ? path : path.querySelector( 'path' ) || path;
		}
	}

	return null;
}

function clearHighlights( root ) {
	root.querySelectorAll( '.forwp-diagram-is-active, .forwp-diagram-is-edge' ).forEach(
		( el ) => {
			el.classList.remove( 'forwp-diagram-is-active', 'forwp-diagram-is-edge' );
			el.style.removeProperty( 'stroke-dasharray' );
			el.style.removeProperty( 'stroke-dashoffset' );
		}
	);
}

function animateEdge( edge ) {
	if ( ! edge || prefersReducedMotion() ) {
		edge?.classList.add( 'forwp-diagram-is-edge' );
		return;
	}

	edge.classList.add( 'forwp-diagram-is-edge' );
	const length =
		typeof edge.getTotalLength === 'function' ? edge.getTotalLength() : 200;
	edge.style.strokeDasharray = String( length );
	edge.style.strokeDashoffset = String( length );
	// Force reflow then animate.
	void edge.getBoundingClientRect();
	edge.style.transition = 'stroke-dashoffset 0.6s ease';
	edge.style.strokeDashoffset = '0';
}

/**
 * @param {HTMLElement} figure
 */
function bootDiagram( figure ) {
	if ( figure.dataset.forwpDiagramReady === '1' ) {
		return;
	}

	const stepsRaw = figure.getAttribute( 'data-forwp-steps' ) || '';
	const steps = stepsRaw
		.split( ',' )
		.map( ( s ) => s.trim() )
		.filter( Boolean );
	if ( ! steps.length ) {
		return;
	}

	const svg = figure.querySelector( 'svg' );
	const controls = figure.querySelector( '.forwp-diagram__controls' );
	if ( ! svg || ! controls ) {
		return;
	}

	controls.hidden = false;
	figure.dataset.forwpDiagramReady = '1';

	let index = -1;
	let playTimer = null;

	const showStep = ( i ) => {
		clearHighlights( figure );
		if ( i < 0 || i >= steps.length ) {
			return;
		}
		const node = findNode( svg, steps[ i ] );
		node?.classList.add( 'forwp-diagram-is-active' );
		if ( i > 0 ) {
			const edge = findEdge( svg, steps[ i - 1 ], steps[ i ] );
			animateEdge( edge );
		}
	};

	const go = ( delta ) => {
		index = Math.max( 0, Math.min( steps.length - 1, index + delta ) );
		showStep( index );
	};

	const stopPlay = () => {
		if ( playTimer ) {
			window.clearInterval( playTimer );
			playTimer = null;
		}
		const btn = controls.querySelector( '[data-forwp-diagram-action="play"]' );
		if ( btn ) {
			btn.textContent = btn.dataset.labelPlay || 'Play';
		}
	};

	controls.addEventListener( 'click', ( event ) => {
		const btn = event.target.closest( '[data-forwp-diagram-action]' );
		if ( ! btn ) {
			return;
		}
		const action = btn.getAttribute( 'data-forwp-diagram-action' );
		if ( action === 'prev' ) {
			stopPlay();
			if ( index < 0 ) {
				index = 0;
			}
			go( -1 );
		} else if ( action === 'next' ) {
			stopPlay();
			if ( index < 0 ) {
				index = -1;
			}
			go( 1 );
		} else if ( action === 'play' ) {
			if ( playTimer ) {
				stopPlay();
				return;
			}
			btn.dataset.labelPlay = btn.textContent;
			btn.textContent = 'Pause';
			if ( index < 0 ) {
				index = -1;
			}
			const tick = () => {
				if ( index >= steps.length - 1 ) {
					stopPlay();
					return;
				}
				go( 1 );
			};
			tick();
			if ( ! prefersReducedMotion() ) {
				playTimer = window.setInterval( tick, 1200 );
			}
		}
	} );

	svg.addEventListener( 'click', ( event ) => {
		const g = event.target.closest( 'g[id], .node' );
		if ( ! g ) {
			return;
		}
		const id = g.getAttribute( 'id' ) || '';
		const hit = steps.findIndex( ( step ) => {
			const re = new RegExp(
				`(^|[-_])${ step.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) }($|[-_])`,
				'i'
			);
			return re.test( id ) || id.includes( `-${ step }-` );
		} );
		if ( hit >= 0 ) {
			stopPlay();
			index = hit;
			showStep( index );
		}
	} );
}

function bootAll() {
	document
		.querySelectorAll( '.wp-block-forwp-diagram.forwp-diagram--has-steps' )
		.forEach( bootDiagram );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootAll, { once: true } );
} else {
	bootAll();
}
