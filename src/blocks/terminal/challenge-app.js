/**
 * Terminal challenge (quiz) mode.
 */

import { createCommandHistory } from './command-history.js';
import { getLabel } from './terminal-auth.js';

function escapeHtml( text ) {
	return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
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

/**
 * Normalize CLI input for comparison.
 *
 * @param {string} command Raw command.
 * @return {string}
 */
export function normalizeCommand( command ) {
	return String( command || '' )
		.trim()
		.replace(/\s+/g, ' ')
		.toLowerCase();
}

/**
 * Check whether a command matches any accepted variant.
 *
 * @param {string}   input    User command.
 * @param {string[]} accepted Accepted commands from JSON.
 * @return {boolean}
 */
export function isAcceptedCommand( input, accepted ) {
	const norm = normalizeCommand( input );
	if ( ! norm || ! Array.isArray( accepted ) || ! accepted.length ) {
		return false;
	}

	return accepted.some( ( candidate ) => {
		const acceptedNorm = normalizeCommand( candidate );
		if ( ! acceptedNorm ) {
			return false;
		}

		if ( norm === acceptedNorm ) {
			return true;
		}

		if ( norm.startsWith( acceptedNorm + ' ' ) ) {
			return true;
		}

		if ( norm.startsWith( acceptedNorm + '--' ) ) {
			return true;
		}

		return false;
	} );
}

function getRevealAfter( step ) {
	const value = parseInt( step?.reveal_after_attempts, 10 );
	return Number.isFinite( value ) && value > 0 ? value : 2;
}

function renderChallengePanel( panel, data, stepIndex, totalSteps, step ) {
	const title = data?.title || 'Challenge';
	const stepNumber = stepIndex + 1;

	panel.innerHTML = `
		<div class="forwp-ac-terminal__challenge-head">
			<span class="forwp-ac-terminal__challenge-badge">${ escapeHtml(
				`Step ${ stepNumber } of ${ totalSteps }`
			) }</span>
			<span class="forwp-ac-terminal__challenge-name">${ escapeHtml(
				title
			) }</span>
		</div>
		<p class="forwp-ac-terminal__challenge-hint">${ escapeHtml(
			step?.hint || ''
		) }</p>
	`;
}

function renderCompletion( outputEl, data, mistakes ) {
	const completion = data?.completion || {};
	const sections = [
		{
			key: 'perfect',
			label: 'What you did well',
			className: 'forwp-ac-terminal__line--challenge-success',
		},
		{
			key: 'optimize',
			label: 'What to optimize',
			className: 'forwp-ac-terminal__line--challenge-tip',
		},
		{
			key: 'next',
			label: 'Next steps',
			className: 'forwp-ac-terminal__line--challenge-tip',
		},
	];

	appendLine(
		outputEl,
		'forwp-ac-terminal__line--challenge-complete',
		'<strong>Challenge complete.</strong> You finished all steps.'
	);

	if ( mistakes.length ) {
		appendLine(
			outputEl,
			'forwp-ac-terminal__line--challenge-wrong',
			`<strong>Steps with mistakes:</strong> ${ escapeHtml(
				mistakes.join( ', ' )
			) }`
		);
	}

	sections.forEach( ( section ) => {
		const items = completion[ section.key ];
		if ( ! Array.isArray( items ) || ! items.length ) {
			return;
		}

		let html = `<strong>${ escapeHtml( section.label ) }</strong><ul>`;
		items.forEach( ( item ) => {
			html += `<li>${ escapeHtml( item ) }</li>`;
		} );
		html += '</ul>';

		appendLine( outputEl, section.className, html );
	} );
}

/**
 * @param {HTMLElement} root           Terminal root.
 * @param {object}      data           Challenge profile JSON.
 * @param {string}      welcomeMessage Optional welcome override.
 */
export function initChallenge( root, data, welcomeMessage ) {
	if ( ! root || root.dataset.forwpAcTerminalReady === '1' ) {
		return;
	}

	const outputEl = root.querySelector( '.forwp-ac-terminal__output' );
	const inputEl = root.querySelector( '.forwp-ac-terminal__input' );
	const formEl = root.querySelector( '.forwp-ac-terminal__form' );
	const promptEl = root.querySelector( '.forwp-ac-terminal__prompt' );
	const titleEl = root.querySelector( '.forwp-ac-terminal__title' );
	const sidebarEl = root.querySelector( '.forwp-ac-terminal__sidebar' );
	const mainEl = root.querySelector( '.forwp-ac-terminal__main' );

	if ( ! outputEl || ! inputEl || ! formEl || ! mainEl ) {
		return;
	}

	const steps = Array.isArray( data?.steps ) ? data.steps : [];
	if ( ! steps.length ) {
		appendLine(
			outputEl,
			'forwp-ac-terminal__line--error',
			'Challenge profile has no steps.'
		);
		return;
	}

	const prompt = data?.prompt || '$';
	let stepIndex = 0;
	let isBusy = false;
	let isComplete = false;
	const attempts = steps.map( () => 0 );
	const mistakes = [];
	const commandHistory = createCommandHistory( inputEl );
	commandHistory.attach();

	root.classList.add( 'forwp-ac-terminal--challenge' );
	root.dataset.forwpAcTerminalReady = '1';
	root.dataset.forwpAcTerminalMode = 'challenge';
	root.classList.remove( 'forwp-ac-terminal--booting' );

	if ( sidebarEl ) {
		sidebarEl.hidden = true;
	}

	if ( promptEl ) {
		promptEl.textContent = prompt;
	}

	if ( titleEl && data?.title ) {
		titleEl.textContent = data.title;
	}

	let panel = root.querySelector( '.forwp-ac-terminal__challenge' );
	if ( ! panel ) {
		panel = document.createElement( 'div' );
		panel.className = 'forwp-ac-terminal__challenge';
		mainEl.insertBefore( panel, outputEl );
	}

	const intro =
		welcomeMessage ||
		data?.instructions ||
		data?.description ||
		'Type each command below. The terminal validates your input at every step.';

	function appendIntroLines() {
		appendLine(
			outputEl,
			'forwp-ac-terminal__line--system',
			escapeHtml( intro )
		);

		if (
			data?.description &&
			welcomeMessage &&
			data.description !== welcomeMessage
		) {
			appendLine(
				outputEl,
				'forwp-ac-terminal__line--system',
				escapeHtml( data.description )
			);
		}
	}

	appendIntroLines();

	let completionActionsEl = null;

	function showCompletionActions() {
		if ( completionActionsEl ) {
			return;
		}

		const inputRow = formEl.querySelector(
			'.forwp-ac-terminal__input-row'
		);
		if ( ! inputRow ) {
			return;
		}

		completionActionsEl = document.createElement( 'div' );
		completionActionsEl.className =
			'forwp-ac-terminal__challenge-actions';

		const saveBtn = document.createElement( 'button' );
		saveBtn.type = 'button';
		saveBtn.className = 'forwp-ac-terminal__save-btn';
		saveBtn.disabled = true;
		saveBtn.setAttribute( 'aria-disabled', 'true' );
		saveBtn.title = getLabel( 'comingSoon', 'Coming Soon' );
		saveBtn.setAttribute(
			'aria-label',
			getLabel( 'saveProgressAria', 'Save progress' )
		);
		saveBtn.innerHTML =
			'<span class="forwp-ac-terminal__save-btn-icon" aria-hidden="true">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
			'<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>' +
			'<polyline points="17 21 17 13 7 13 7 21"/>' +
			'<polyline points="7 3 7 8 15 8"/>' +
			'</svg></span>' +
			`<span class="forwp-ac-terminal__save-btn-label">${ escapeHtml(
				getLabel( 'saveProgress', 'Save' )
			) }</span>`;

		const retakeBtn = document.createElement( 'button' );
		retakeBtn.type = 'button';
		retakeBtn.className = 'forwp-ac-terminal__retake-btn';
		retakeBtn.setAttribute(
			'aria-label',
			getLabel( 'retakeChallengeAria', 'Retake challenge' )
		);
		retakeBtn.innerHTML =
			'<span class="forwp-ac-terminal__retake-btn-icon" aria-hidden="true">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
			'<path d="M21 2v6h-6"/>' +
			'<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>' +
			'<path d="M3 22v-6h6"/>' +
			'<path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>' +
			'</svg></span>' +
			`<span class="forwp-ac-terminal__retake-btn-label">${ escapeHtml(
				getLabel( 'retakeChallenge', 'Retake' )
			) }</span>`;
		retakeBtn.addEventListener( 'click', () => {
			resetChallenge();
		} );

		completionActionsEl.appendChild( saveBtn );
		completionActionsEl.appendChild( retakeBtn );
		inputRow.appendChild( completionActionsEl );
	}

	function hideCompletionActions() {
		completionActionsEl?.remove();
		completionActionsEl = null;
	}

	function resetAttempts() {
		for ( let i = 0; i < attempts.length; i += 1 ) {
			attempts[ i ] = 0;
		}
		mistakes.length = 0;
	}

	function resetChallenge() {
		stepIndex = 0;
		isBusy = false;
		isComplete = false;
		resetAttempts();
		commandHistory.clear();

		outputEl.innerHTML = '';
		appendIntroLines();

		inputEl.disabled = false;
		inputEl.placeholder = '';
		inputEl.value = '';

		hideCompletionActions();
		updatePanel();
		emitActiveStep( { initial: true } );
		inputEl.focus();

		root.dispatchEvent(
			new CustomEvent( 'forwp-ac-terminal-challenge-retake', {
				bubbles: true,
				detail: {
					profile: data?.profile || data?.slug,
				},
			} )
		);
	}

	function updatePanel() {
		if ( isComplete ) {
			panel.innerHTML = `
				<div class="forwp-ac-terminal__challenge-head">
					<span class="forwp-ac-terminal__challenge-badge forwp-ac-terminal__challenge-badge--done">Complete</span>
					<span class="forwp-ac-terminal__challenge-name">${ escapeHtml(
						data?.title || 'Challenge'
					) }</span>
				</div>
			`;
			return;
		}

		renderChallengePanel(
			panel,
			data,
			stepIndex,
			steps.length,
			steps[ stepIndex ]
		);
	}

	function emitActiveStep( extraDetail = {} ) {
		const currentStep = isComplete ? steps.length : stepIndex + 1;
		root.dataset.challengeStep = String( currentStep );

		root.dispatchEvent(
			new CustomEvent( 'forwp-ac-terminal-challenge-active-step', {
				bubbles: true,
				detail: {
					currentStep,
					totalSteps: steps.length,
					profile: data?.profile || data?.slug,
					...extraDetail,
				},
			} )
		);
	}

	function finishChallenge() {
		isComplete = true;
		inputEl.disabled = true;
		inputEl.placeholder = 'Challenge complete';
		updatePanel();
		renderCompletion( outputEl, data, mistakes );
		showCompletionActions();
	}

	function handleWrong( step, cmd ) {
		attempts[ stepIndex ] += 1;

		appendLine(
			outputEl,
			'forwp-ac-terminal__line--challenge-wrong',
			`<strong>Not quite.</strong> ${ escapeHtml(
				step.feedback_wrong || 'Try again.'
			) }`
		);

		const revealAfter = getRevealAfter( step );
		const commandHint =
			step.command_hint ||
			( Array.isArray( step.accepted ) ? step.accepted[ 0 ] : '' );

		if ( attempts[ stepIndex ] >= revealAfter && commandHint ) {
			appendLine(
				outputEl,
				'forwp-ac-terminal__line--challenge-hint',
				`<strong>Hint:</strong> <code>${ escapeHtml(
					commandHint
				) }</code>`
			);
		}

		if ( ! mistakes.includes( stepIndex + 1 ) ) {
			mistakes.push( stepIndex + 1 );
		}

		isBusy = false;
		inputEl.disabled = false;
		inputEl.focus();

		emitActiveStep( { error: true, command: cmd } );

		root.dispatchEvent(
			new CustomEvent( 'forwp-ac-terminal-challenge-wrong', {
				bubbles: true,
				detail: {
					command: cmd,
					step: stepIndex + 1,
					currentStep: stepIndex + 1,
					attempts: attempts[ stepIndex ],
					profile: data?.profile || data?.slug,
				},
			} )
		);
	}

	function handleCorrect( step, cmd ) {
		appendLine(
			outputEl,
			'forwp-ac-terminal__line--response',
			`<pre class="forwp-ac-terminal__pre">${ escapeHtml(
				step.output || 'Success.'
			) }</pre>`
		);

		if ( step.feedback_correct ) {
			appendLine(
				outputEl,
				'forwp-ac-terminal__line--challenge-success',
				`<strong>Correct.</strong> ${ escapeHtml(
					step.feedback_correct
				) }`
			);
		}

		stepIndex += 1;

		if ( stepIndex >= steps.length ) {
			isBusy = false;
			finishChallenge();
			emitActiveStep( { complete: true, command: cmd } );
			root.dispatchEvent(
				new CustomEvent( 'forwp-ac-terminal-challenge-complete', {
					bubbles: true,
					detail: {
						profile: data?.profile || data?.slug,
						mistakes: [ ...mistakes ],
					},
				} )
			);
			return;
		}

		updatePanel();
		appendLine(
			outputEl,
			'forwp-ac-terminal__line--system',
			escapeHtml( `Step ${ stepIndex + 1 } of ${ steps.length }` )
		);

		isBusy = false;
		inputEl.disabled = false;
		inputEl.focus();

		emitActiveStep( { command: cmd } );

		root.dispatchEvent(
			new CustomEvent( 'forwp-ac-terminal-challenge-step', {
				bubbles: true,
				detail: {
					command: cmd,
					step: stepIndex + 1,
					currentStep: stepIndex + 1,
					profile: data?.profile || data?.slug,
				},
			} )
		);
	}

	function runCommand( rawCommand ) {
		const cmd = String( rawCommand || '' ).trim();
		if ( ! cmd || isBusy || isComplete ) {
			return;
		}

		commandHistory.push( cmd );

		const step = steps[ stepIndex ];
		if ( ! step ) {
			return;
		}

		isBusy = true;
		inputEl.disabled = true;

		appendLine(
			outputEl,
			'forwp-ac-terminal__line--command',
			`<span class="forwp-ac-terminal__prompt">${ escapeHtml(
				prompt
			) }</span> <span class="forwp-ac-terminal__cmd-echo">${ escapeHtml(
				cmd
			) }</span>`
		);

		if ( isAcceptedCommand( cmd, step.accepted || [] ) ) {
			handleCorrect( step, cmd );
			return;
		}

		handleWrong( step, cmd );
	}

	updatePanel();
	emitActiveStep( { initial: true } );

	formEl.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		const value = inputEl.value;
		inputEl.value = '';
		runCommand( value );
	} );

	inputEl.focus();
}
