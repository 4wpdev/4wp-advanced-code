/**
 * Terminal UI — shared by editor preview and frontend view.
 */

import { findCommand } from './find-command.js';
import { initChallenge } from './challenge-app.js';
import { createCommandHistory } from './command-history.js';

function escapeHtml( text ) {
	return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

/**
 * @param {string} restUrl REST endpoint URL.
 * @return {Promise<object>}
 */
export async function fetchProfileData( restUrl ) {
	const res = await fetch( restUrl, {
		credentials: 'same-origin',
		headers: { Accept: 'application/json' },
	} );
	if ( ! res.ok ) {
		throw new Error( `Failed to load terminal profile (${ res.status })` );
	}
	return res.json();
}

function scrollOutputToBottom( outputEl ) {
	if ( outputEl ) {
		outputEl.scrollTop = outputEl.scrollHeight;
	}
}

function appendLine( outputEl, className, html ) {
	const line = document.createElement( 'div' );
	line.className = `forwp-ac-terminal__line ${ className }`;
	line.innerHTML = html;
	outputEl.appendChild( line );
	scrollOutputToBottom( outputEl );
}

function sleep( ms ) {
	return new Promise( ( resolve ) => {
		window.setTimeout( resolve, Math.max( 0, Number( ms ) || 0 ) );
	} );
}

/**
 * Build timed lines for output emulation.
 *
 * @param {{ output?: string, emulate?: boolean, lineDelayMs?: number, startDelayMs?: number, lines?: Array<object>|null }} result
 * @return {Array<{ text: string, delayMs: number, replace: boolean }>|null}
 */
function normalizeEmulateLines( result ) {
	if ( ! result || ! result.emulate ) {
		return null;
	}

	const defaultDelay = Number( result.lineDelayMs ) > 0 ? Number( result.lineDelayMs ) : 280;
	const startDelay =
		Number( result.startDelayMs ) > 0 ? Number( result.startDelayMs ) : defaultDelay;

	if ( Array.isArray( result.lines ) && result.lines.length ) {
		return result.lines.map( ( step, index ) => {
			const text = String( step?.text ?? step?.t ?? '' );
			const raw =
				step?.delayMs ?? step?.ms ?? step?.delay;
			// Explicit 0 = no pause. Missing = profile/command default.
			const delayMs =
				raw === undefined || raw === null || raw === ''
					? index === 0
						? startDelay
						: defaultDelay
					: Math.max( 0, Number( raw ) || 0 );
			return {
				text,
				delayMs,
				replace: !!( step?.replace || step?.mode === 'replace' ),
			};
		} );
	}

	const parts = String( result.output || '' ).split( '\n' );
	return parts.map( ( text, index ) => ( {
		text,
		delayMs: index === 0 ? startDelay : defaultDelay,
		replace: false,
	} ) );
}

/**
 * Stream command output line-by-line (optional in-place replace for progress bars).
 *
 * @param {HTMLElement} outputEl
 * @param {object}      result
 * @return {Promise<void>}
 */
async function streamCommandOutput( outputEl, result ) {
	const steps = normalizeEmulateLines( result );

	if ( ! steps ) {
		appendLine(
			outputEl,
			'forwp-ac-terminal__line--response',
			`<pre class="forwp-ac-terminal__pre">${ escapeHtml(
				result.output || ''
			) }</pre>`
		);
		return;
	}

	const wrap = document.createElement( 'div' );
	wrap.className =
		'forwp-ac-terminal__line forwp-ac-terminal__line--response forwp-ac-terminal__line--streaming';
	const pre = document.createElement( 'pre' );
	pre.className = 'forwp-ac-terminal__pre';
	wrap.appendChild( pre );
	outputEl.appendChild( wrap );

	const buffer = [];
	for ( let i = 0; i < steps.length; i++ ) {
		const step = steps[ i ];
		await sleep( step.delayMs );
		if ( step.replace && buffer.length ) {
			buffer[ buffer.length - 1 ] = step.text;
		} else {
			buffer.push( step.text );
		}
		pre.textContent = buffer.join( '\n' );
		scrollOutputToBottom( outputEl );
	}

	wrap.classList.remove( 'forwp-ac-terminal__line--streaming' );
}

function docHref( docUrl ) {
	const url = String( docUrl || '' ).trim();
	return url ? escapeHtml( url ) : '';
}

function buildTipHtml( tip, docUrl, docLabel ) {
	const url = String( docUrl || '' ).trim();
	let html = `<div class="forwp-ac-terminal__tip"><strong>TIP:</strong> ${ escapeHtml(
		tip
	) }`;

	if ( url ) {
		const label = String( docLabel || '' ).trim() || 'Documentation';
		html += ` <a href="${ docHref(
			url
		) }" class="forwp-ac-terminal__doc-link" target="_blank" rel="noopener noreferrer">${ escapeHtml(
			label
		) }</a>`;
	}

	html += '</div>';
	return html;
}

/**
 * @param {HTMLElement} root           Block root element.
 * @param {object}      data           Profile JSON.
 * @param {string}      welcomeMessage Custom welcome line.
 */
export function initTerminal( root, data, welcomeMessage ) {
	if ( ! root || root.dataset.forwpAcTerminalReady === '1' ) {
		return;
	}

	if ( data?.type === 'challenge' ) {
		initChallenge( root, data, welcomeMessage );
		return;
	}

	const outputEl = root.querySelector( '.forwp-ac-terminal__output' );
	const inputEl = root.querySelector( '.forwp-ac-terminal__input' );
	const formEl = root.querySelector( '.forwp-ac-terminal__form' );
	const sidebarEl = root.querySelector( '.forwp-ac-terminal__sidebar-list' );
	const promptEl = root.querySelector( '.forwp-ac-terminal__prompt' );

	if ( ! outputEl || ! inputEl || ! formEl || ! sidebarEl ) {
		return;
	}

	const commands = data?.commands || [];
	const categories = data?.categories || [];
	const prompt = data?.prompt || '$';
	const profileMeta = {
		docBase: data?.docBase || '',
		docLabel: data?.docLabel || '',
	};
	let isBusy = false;
	const commandHistory = createCommandHistory( inputEl );
	commandHistory.attach();

	if ( promptEl ) {
		promptEl.textContent = prompt;
	}

	root.dataset.forwpAcTerminalReady = '1';
	root.classList.remove( 'forwp-ac-terminal--booting' );

	appendLine(
		outputEl,
		'forwp-ac-terminal__line--system',
		escapeHtml(
			welcomeMessage ||
				'Interactive Terminal — type "help" for available commands'
		)
	);

	let sidebarHtml = '';
	categories.forEach( ( cat, catIndex ) => {
		const color = cat.color || '#569cd6';
		sidebarHtml += `<details class="forwp-ac-terminal__cat"${
			catIndex === 0 ? ' open' : ''
		}>`;
		sidebarHtml += `<summary class="forwp-ac-terminal__cat-head" style="--forwp-ac-cat-color:${ escapeHtml(
			color
		) }">`;
		sidebarHtml += `<span class="forwp-ac-terminal__cat-dot"></span>`;
		sidebarHtml += `<span class="forwp-ac-terminal__cat-name">${ escapeHtml(
			cat.name || cat.id
		) }</span>`;
		sidebarHtml += `</summary>`;
		sidebarHtml += `<div class="forwp-ac-terminal__cat-body">`;
		( cat.commands || [] ).forEach( ( item ) => {
			sidebarHtml += `<button type="button" class="forwp-ac-terminal__cmd" data-forwp-cmd="${ escapeHtml(
				item.cmd
			) }">`;
			sidebarHtml += `<code class="forwp-ac-terminal__cmd-text">${ escapeHtml(
				item.cmd
			) }</code>`;
			if ( item.desc ) {
				sidebarHtml += `<span class="forwp-ac-terminal__cmd-desc">${ escapeHtml(
					item.desc
				) }</span>`;
			}
			sidebarHtml += `</button>`;
		} );
		sidebarHtml += `</div></details>`;
	} );
	sidebarEl.innerHTML = sidebarHtml;

	async function runCommand( rawCommand ) {
		const cmd = String( rawCommand || '' ).trim();
		if ( ! cmd || isBusy ) {
			return;
		}

		commandHistory.push( cmd );

		isBusy = true;
		inputEl.disabled = true;
		root.classList.add( 'forwp-ac-terminal--busy' );

		appendLine(
			outputEl,
			'forwp-ac-terminal__line--command',
			`<span class="forwp-ac-terminal__prompt">${ escapeHtml(
				prompt
			) }</span> <span class="forwp-ac-terminal__cmd-echo">${ escapeHtml(
				cmd
			) }</span>`
		);

		const result = findCommand( cmd, commands, profileMeta );

		try {
			await streamCommandOutput( outputEl, result );

			if ( result.tip ) {
				appendLine(
					outputEl,
					'forwp-ac-terminal__line--tip',
					buildTipHtml( result.tip, result.doc, result.docLabel )
				);
			}

			root.dispatchEvent(
				new CustomEvent( 'forwp-ac-terminal-command', {
					bubbles: true,
					detail: {
						command: cmd,
						slug: result.slug,
						profile: data?.profile,
					},
				} )
			);
		} finally {
			isBusy = false;
			inputEl.disabled = false;
			root.classList.remove( 'forwp-ac-terminal--busy' );
			inputEl.focus();
			scrollOutputToBottom( outputEl );
		}
	}

	formEl.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		const value = inputEl.value;
		inputEl.value = '';
		runCommand( value );
	} );

	sidebarEl.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-forwp-cmd]' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		runCommand( btn.getAttribute( 'data-forwp-cmd' ) );
	} );

	inputEl.focus();
}
