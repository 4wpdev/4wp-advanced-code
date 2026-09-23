/**
 * IDE wizard runtime: Back/Next tour, quiz (not a gate), optional group of wizards.
 */

import { initTerminalDisplay } from '../terminal/terminal-display.js';
import { initIdeHighlight } from './ide-highlight.js';
import { initIdeResize } from './ide-resize.js';

function labels() {
	return (
		window.forwpAdvancedCodeIde?.labels || {
			back: 'Back',
			next: 'Next',
			done: 'Done',
			check: 'Check',
			restart: 'Restart',
			loadExample: 'Load example into editor',
			resetStarter: 'Reset starter',
			looksGood: 'Looks good. Continue when you are ready.',
			stepOf: 'Step %1$s of %2$s',
			hookOf: 'Hook %1$s of %2$s',
			nextHook: 'Next hook',
			quizIncorrect: 'Not quite — Next is still available.',
			quizHint: 'Think and click. Next is never blocked.',
			groupTour: 'Group tour',
			hookQuiz: 'Hook quiz',
			hookWizard: 'Hook wizard',
			loadError:
				'Could not load this hook wizard. Check that the theme hooks JSON exists.',
			fullView: 'Full view',
		}
	);
}

function formatStepOf( current, total ) {
	return labels().stepOf.replace( '%1$s', String( current ) ).replace( '%2$s', String( total ) );
}

function formatHookOf( current, total ) {
	return labels().hookOf.replace( '%1$s', String( current ) ).replace( '%2$s', String( total ) );
}

function formatProgress( wizard, wizardIndex, wizardsLength, current, total ) {
	const step = formatStepOf( current, total );
	if ( wizardsLength <= 1 ) {
		return step;
	}
	const slug = wizard.slug || wizard.name || '';
	return `${ slug } · ${ formatHookOf( wizardIndex + 1, wizardsLength ) } · ${ step }`;
}

function escapeHtml( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

function escapeRegExp( value ) {
	return String( value ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

/**
 * Accept a single wizard or a group payload { wizards: [...] }.
 *
 * @param {Object} data REST payload.
 * @return {Array<Object>}
 */
export function asWizards( data ) {
	if ( Array.isArray( data?.wizards ) && data.wizards.length ) {
		return data.wizards.filter(
			( wizard ) => Array.isArray( wizard?.steps ) && wizard.steps.length
		);
	}
	if ( Array.isArray( data?.steps ) && data.steps.length ) {
		return [ data ];
	}
	return [];
}

export function fetchWizard( restUrl ) {
	return fetch( restUrl, { credentials: 'same-origin' } ).then( ( response ) => {
		if ( ! response.ok ) {
			throw new Error( 'wizard-http' );
		}
		return response.json();
	} );
}

function getRestUrl( root ) {
	try {
		const config = JSON.parse( root.dataset.forwpConfig || '{}' );
		if ( config.restUrl ) {
			return config.restUrl;
		}
		if ( ( config.tourMode === 'custom' || root.dataset.tourMode === 'custom' ) && Array.isArray( config.hooks ) && config.hooks.length ) {
			const query = config.hooks
				.map( ( hook ) => `${ hook.type || 'action' }:${ hook.slug }` )
				.join( ',' );
			const setBase = window.forwpAdvancedCodeIde?.restSet;
			if ( setBase ) {
				return `${ setBase }${ setBase.includes( '?' ) ? '&' : '?' }hooks=${ encodeURIComponent( query ) }`;
			}
			return `/wp-json/forwp-advanced-code/v1/ide/hooks-set?hooks=${ encodeURIComponent( query ) }`;
		}
	} catch {
		// ignore
	}

	const type = root.dataset.hookType || 'action';
	const slug = root.dataset.hookSlug || 'wp_head';
	const group = ( root.dataset.tourMode || '' ) === 'group';
	const base = window.forwpAdvancedCodeIde?.restBase;

	if ( group ) {
		if ( base ) {
			return `${ base.replace( /\/ide\/hooks\/?$/, '/ide/hooks-group' ) }/${ type }/${ slug }`;
		}
		return `/wp-json/forwp-advanced-code/v1/ide/hooks-group/${ type }/${ slug }`;
	}

	if ( base ) {
		return `${ base.replace( /\/$/, '' ) }/${ type }/${ slug }`;
	}

	return `/wp-json/forwp-advanced-code/v1/ide/hooks/${ type }/${ slug }`;
}

function syncGutter( textarea, gutter ) {
	if ( ! textarea || ! gutter ) {
		return;
	}

	const lines = Math.max( textarea.value.split( '\n' ).length, 1 );
	const html = [];
	for ( let i = 1; i <= lines; i++ ) {
		html.push( `<span>${ i }</span>` );
	}
	gutter.innerHTML = html.join( '' );
	gutter.scrollTop = textarea.scrollTop;
}

function checkPractice( code, check ) {
	if ( ! check ) {
		return { ok: true, hints: [] };
	}

	const hints = [];
	const fn = String( check.function || '' );
	const hook = String( check.hook || '' );

	if ( fn && ! new RegExp( `\\b${ escapeRegExp( fn ) }\\s*\\(`, 'i' ).test( code ) ) {
		hints.push( `Use ${ fn }( … ).` );
	}

	if (
		hook &&
		! new RegExp( `['"]${ escapeRegExp( hook ) }['"]` ).test( code )
	) {
		hints.push( `Pass the hook name '${ hook }'.` );
	}

	if ( check.returns && ! /\breturn\b/.test( code ) ) {
		hints.push( 'Filters should return a value.' );
	}

	return { ok: hints.length === 0, hints };
}

function metaChips( meta ) {
	if ( ! meta ) {
		return '';
	}

	const chips = [ 'type', 'phase', 'since', 'source' ]
		.filter( ( key ) => meta[ key ] )
		.map(
			( key ) =>
				`<span class="forwp-ac-ide__chip"><em>${ escapeHtml( key ) }</em> ${ escapeHtml(
					meta[ key ]
				) }</span>`
		);

	return chips.length
		? `<div class="forwp-ac-ide__chips">${ chips.join( '' ) }</div>`
		: '';
}

function listBlock( title, items ) {
	if ( ! items || ! items.length ) {
		return '';
	}

	const lis = items
		.map( ( item ) => `<li>${ escapeHtml( item ) }</li>` )
		.join( '' );

	return `<h4 class="forwp-ac-ide__subhead">${ escapeHtml( title ) }</h4><ul class="forwp-ac-ide__list">${ lis }</ul>`;
}

function renderStepBody( step ) {
	const parts = [];

	if ( step.tagline ) {
		parts.push(
			`<p class="forwp-ac-ide__tagline">${ escapeHtml( step.tagline ) }</p>`
		);
	}

	if ( step.body ) {
		parts.push( `<p>${ escapeHtml( step.body ) }</p>` );
	}

	if ( step.meta ) {
		parts.push( metaChips( step.meta ) );
	}

	if ( step.signature ) {
		parts.push(
			`<pre class="forwp-ac-ide__pre"><code>${ escapeHtml(
				step.signature
			) }</code></pre>`
		);
	}

	if ( step.parameters && step.parameters.length ) {
		const rows = step.parameters
			.map( ( param ) => {
				const type = param.type
					? `<code>${ escapeHtml( param.type ) }</code> `
					: '';
				const desc = param.description
					? ` — ${ escapeHtml( param.description ) }`
					: '';
				return `<li>${ type }<strong>${ escapeHtml(
					param.name
				) }</strong>${ desc }</li>`;
			} )
			.join( '' );
		parts.push(
			`<h4 class="forwp-ac-ide__subhead">Parameters</h4><ul class="forwp-ac-ide__list">${ rows }</ul>`
		);
	}

	if ( step.code ) {
		parts.push(
			`<pre class="forwp-ac-ide__pre"><code>${ escapeHtml(
				step.code
			) }</code></pre>`
		);
		parts.push(
			`<button type="button" class="forwp-ac-ide__btn forwp-ac-ide__btn--link" data-ide-load-example>${ escapeHtml(
				labels().loadExample
			) }</button>`
		);
	}

	if ( step.kind === 'practice' ) {
		parts.push(
			`<button type="button" class="forwp-ac-ide__btn forwp-ac-ide__btn--link" data-ide-reset>${ escapeHtml(
				labels().resetStarter
			) }</button>`
		);
	}

	if ( step.kind === 'quiz' && Array.isArray( step.options ) ) {
		const opts = step.options
			.map(
				( option ) =>
					`<button type="button" class="forwp-ac-ide__quiz-option" data-ide-quiz-option="${ escapeHtml(
						option.id
					) }" aria-pressed="false">${ escapeHtml( option.label ) }</button>`
			)
			.join( '' );
		parts.push(
			`<div class="forwp-ac-ide__quiz" role="group">${ opts }</div>`
		);
		parts.push(
			`<p class="forwp-ac-ide__quiz-hint">${ escapeHtml(
				labels().quizHint
			) }</p>`
		);
	}

	if ( step.mistakes ) {
		parts.push( listBlock( 'Common mistakes', step.mistakes ) );
	}

	if ( step.use_cases ) {
		parts.push( listBlock( 'Use cases', step.use_cases ) );
	}

	if ( step.related && step.related.length ) {
		const lis = step.related
			.map( ( item ) => {
				const rel = item.relation
					? ` <span class="forwp-ac-ide__chip">${ escapeHtml(
							item.relation
					  ) }</span>`
					: '';
				return `<li><code>${ escapeHtml( item.slug ) }</code>${ rel }</li>`;
			} )
			.join( '' );
		parts.push(
			`<h4 class="forwp-ac-ide__subhead">Related hooks</h4><ul class="forwp-ac-ide__list">${ lis }</ul>`
		);
	}

	if ( step.ref_url || step.docs_url ) {
		const links = [];
		if ( step.ref_url ) {
			links.push(
				`<a href="${ escapeHtml(
					step.ref_url
				) }" target="_blank" rel="noopener noreferrer">Reference</a>`
			);
		}
		if ( step.docs_url ) {
			links.push(
				`<a href="${ escapeHtml(
					step.docs_url
				) }" target="_blank" rel="noopener noreferrer">developer.wordpress.org</a>`
			);
		}
		parts.push(
			`<p class="forwp-ac-ide__links">${ links.join( ' · ' ) }</p>`
		);
	}

	if ( step.kind === 'done' ) {
		parts.push(
			`<button type="button" class="forwp-ac-ide__btn forwp-ac-ide__btn--link" data-ide-restart>${ escapeHtml(
				labels().restart
			) }</button>`
		);
	}

	return parts.join( '' );
}

function currentWizard( ctx ) {
	return ctx.wizards[ ctx.wizardIndex ];
}

function currentStep( ctx ) {
	const wizard = currentWizard( ctx );
	return wizard?.steps?.[ ctx.index ];
}

function bindTourActions( root, ctx ) {
	const body = root.querySelector( '.forwp-ac-ide__tour-body' );
	if ( ! body ) {
		return;
	}

	body.querySelector( '[data-ide-load-example]' )?.addEventListener(
		'click',
		() => {
			const step = currentStep( ctx );
			if ( step?.code ) {
				const value = step.code.endsWith( '\n' )
					? step.code
					: `${ step.code }\n`;
				ctx.setCode( value );
				ctx.codes[ ctx.wizardIndex ] = value;
			}
		}
	);

	body.querySelector( '[data-ide-reset]' )?.addEventListener( 'click', () => {
		const wizard = currentWizard( ctx );
		const value = wizard?.starter || '';
		ctx.codes[ ctx.wizardIndex ] = value;
		ctx.setCode( value );
	} );

	body.querySelector( '[data-ide-restart]' )?.addEventListener( 'click', () => {
		ctx.codes = ctx.wizards.map( ( wizard ) => wizard.starter || '' );
		ctx.wizardIndex = 0;
		ctx.setCode( ctx.codes[ 0 ] || '' );
		ctx.goTo( 0, 0 );
	} );

	body.querySelectorAll( '[data-ide-quiz-option]' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const step = currentStep( ctx );
			const id = btn.getAttribute( 'data-ide-quiz-option' );
			const option = ( step?.options || [] ).find( ( item ) => item.id === id );
			const ok = Boolean( option?.correct );
			const feedback = step?.feedback || {};

			body.querySelectorAll( '[data-ide-quiz-option]' ).forEach( ( other ) => {
				other.classList.remove( 'is-picked', 'is-right', 'is-wrong' );
				other.setAttribute( 'aria-pressed', 'false' );
			} );
			btn.classList.add( 'is-picked', ok ? 'is-right' : 'is-wrong' );
			btn.setAttribute( 'aria-pressed', 'true' );

			if ( ok ) {
				showFeedback(
					root,
					true,
					[],
					feedback.correct || labels().looksGood
				);
			} else {
				showFeedback( root, false, [
					feedback.incorrect || labels().quizIncorrect,
				] );
			}
		} );
	} );
}

function showFeedback( root, ok, hints, message ) {
	const el = root.querySelector( '.forwp-ac-ide__tour-feedback' );
	if ( ! el ) {
		return;
	}

	el.hidden = false;
	el.classList.toggle( 'is-ok', ok );
	el.classList.toggle( 'is-hint', ! ok );

	if ( ok ) {
		el.textContent = message || labels().looksGood;
		return;
	}

	el.innerHTML = ( hints || [] )
		.map( ( hint ) => `<p>${ escapeHtml( hint ) }</p>` )
		.join( '' );
}

function clearFeedback( root ) {
	const el = root.querySelector( '.forwp-ac-ide__tour-feedback' );
	if ( ! el ) {
		return;
	}
	el.hidden = true;
	el.textContent = '';
	el.classList.remove( 'is-ok', 'is-hint' );
}

export function initIde( root, data ) {
	const wizards = asWizards( data );
	if ( ! root || ! wizards.length ) {
		return;
	}

	if ( root._forwpIdeCleanup ) {
		root._forwpIdeCleanup();
	}

	const titleEl = root.querySelector( '.forwp-ac-ide__title' );
	const kickerEl = root.querySelector( '.forwp-ac-ide__tour-kicker' );
	const progressEl = root.querySelector( '.forwp-ac-ide__tour-progress' );
	const headingEl = root.querySelector( '.forwp-ac-ide__tour-title' );
	const bodyEl = root.querySelector( '.forwp-ac-ide__tour-body' );
	const backBtn = root.querySelector( '[data-ide-back]' );
	const nextBtn = root.querySelector( '[data-ide-next]' );
	const checkBtn = root.querySelector( '[data-ide-check]' );
	const statusEl = root.querySelector( '[data-ide-status-step]' );
	const textarea = root.querySelector( '.forwp-ac-ide__code' );
	const gutter = root.querySelector( '.forwp-ac-ide__gutter' );
	const fileBtn = root.querySelector( '.forwp-ac-ide__file' );
	const tabEl = root.querySelector( '.forwp-ac-ide__tab' );
	const highlight = initIdeHighlight( root, textarea );

	const ctx = {
		wizards,
		wizardIndex: 0,
		index: 0,
		codes: wizards.map( ( wizard ) => wizard.starter || '' ),
		setCode( value ) {
			if ( textarea ) {
				textarea.value = value;
				syncGutter( textarea, gutter );
				highlight.paint();
			}
		},
		saveCode() {
			if ( textarea ) {
				ctx.codes[ ctx.wizardIndex ] = textarea.value;
			}
		},
		loadCode() {
			ctx.setCode( ctx.codes[ ctx.wizardIndex ] || '' );
		},
		goTo( nextIndex, nextWizardIndex ) {
			if ( typeof nextWizardIndex === 'number' ) {
				ctx.wizardIndex = nextWizardIndex;
			}
			ctx.index = nextIndex;
			render();
		},
	};

	function render() {
		const wizard = currentWizard( ctx );
		const steps = wizard?.steps || [];
		const step = steps[ ctx.index ];
		if ( ! wizard || ! step ) {
			return;
		}

		const total = steps.length;
		const current = ctx.index + 1;
		const lastStep = ctx.index === total - 1;
		const lastWizard = ctx.wizardIndex === ctx.wizards.length - 1;
		const progress = formatProgress(
			wizard,
			ctx.wizardIndex,
			ctx.wizards.length,
			current,
			total
		);

		if ( kickerEl ) {
			if ( ctx.wizards.length > 1 ) {
				kickerEl.textContent =
					( root.dataset.tourMode || '' ) === 'custom'
						? labels().hookQuiz
						: labels().groupTour;
			} else {
				kickerEl.textContent = labels().hookWizard;
			}
		}
		if ( titleEl ) {
			titleEl.textContent = `${ wizard.file || 'functions.php' } — ${
				wizard.slug || wizard.name
			}`;
		}
		if ( fileBtn ) {
			fileBtn.textContent = wizard.file || 'functions.php';
		}
		if ( tabEl ) {
			tabEl.textContent = wizard.file || 'functions.php';
		}
		highlight.setLanguageFromFile( wizard.file || 'functions.php' );
		if ( progressEl ) {
			progressEl.textContent = progress;
		}
		if ( headingEl ) {
			headingEl.textContent = step.title || '';
		}
		if ( bodyEl ) {
			bodyEl.innerHTML = renderStepBody( step );
			bindTourActions( root, ctx );
		}
		if ( statusEl ) {
			statusEl.textContent = progress;
		}
		if ( backBtn ) {
			backBtn.disabled = ctx.wizardIndex === 0 && ctx.index === 0;
		}
		if ( nextBtn ) {
			if ( lastStep && lastWizard ) {
				nextBtn.textContent = labels().done;
				nextBtn.disabled = true;
			} else if ( lastStep && ! lastWizard ) {
				nextBtn.textContent = labels().nextHook;
				nextBtn.disabled = false;
			} else {
				nextBtn.textContent = labels().next;
				nextBtn.disabled = false;
			}
		}
		if ( checkBtn ) {
			const showCheck = step.kind === 'practice';
			checkBtn.hidden = ! showCheck;
			checkBtn.textContent = labels().check;
		}

		clearFeedback( root );
		root.classList.toggle( 'forwp-ac-ide--practice', step.kind === 'practice' );
		root.classList.toggle( 'forwp-ac-ide--quiz', step.kind === 'quiz' );
	}

	const onBack = () => {
		if ( ctx.index > 0 ) {
			ctx.goTo( ctx.index - 1 );
			return;
		}
		if ( ctx.wizardIndex > 0 ) {
			ctx.saveCode();
			ctx.wizardIndex -= 1;
			ctx.index = Math.max(
				( currentWizard( ctx )?.steps?.length || 1 ) - 1,
				0
			);
			ctx.loadCode();
			render();
		}
	};

	const onNext = () => {
		const wizard = currentWizard( ctx );
		const steps = wizard?.steps || [];
		if ( ctx.index < steps.length - 1 ) {
			ctx.goTo( ctx.index + 1 );
			return;
		}
		if ( ctx.wizardIndex < ctx.wizards.length - 1 ) {
			ctx.saveCode();
			ctx.wizardIndex += 1;
			ctx.index = 0;
			ctx.loadCode();
			render();
		}
	};

	const onCheck = () => {
		const step = currentStep( ctx );
		const result = checkPractice( textarea?.value || '', step?.check );
		showFeedback( root, result.ok, result.hints );
	};

	const onInput = () => {
		syncGutter( textarea, gutter );
		if ( textarea ) {
			ctx.codes[ ctx.wizardIndex ] = textarea.value;
		}
		highlight.schedulePaint();
	};
	const onScroll = () => {
		if ( gutter && textarea ) {
			gutter.scrollTop = textarea.scrollTop;
		}
		highlight.syncScroll();
	};
	const onKeydown = ( event ) => {
		if ( event.key !== 'Tab' ) {
			return;
		}
		event.preventDefault();
		const start = textarea.selectionStart;
		const end = textarea.selectionEnd;
		textarea.value =
			textarea.value.slice( 0, start ) + '\t' + textarea.value.slice( end );
		textarea.selectionStart = textarea.selectionEnd = start + 1;
		syncGutter( textarea, gutter );
		ctx.codes[ ctx.wizardIndex ] = textarea.value;
		highlight.paint();
	};

	backBtn?.addEventListener( 'click', onBack );
	nextBtn?.addEventListener( 'click', onNext );
	checkBtn?.addEventListener( 'click', onCheck );
	textarea?.addEventListener( 'input', onInput );
	textarea?.addEventListener( 'scroll', onScroll );
	textarea?.addEventListener( 'keydown', onKeydown );

	ctx.setCode( ctx.codes[ 0 ] || '' );
	render();
	root.classList.remove( 'forwp-ac-ide--booting' );
	initTerminalDisplay( root );
	initIdeResize( root );

	root._forwpIdeCleanup = () => {
		backBtn?.removeEventListener( 'click', onBack );
		nextBtn?.removeEventListener( 'click', onNext );
		checkBtn?.removeEventListener( 'click', onCheck );
		textarea?.removeEventListener( 'input', onInput );
		textarea?.removeEventListener( 'scroll', onScroll );
		textarea?.removeEventListener( 'keydown', onKeydown );
		highlight.destroy();
		root._forwpIdeResizeCleanup?.();
		root._forwpIdeCleanup = null;
	};
}

export function bootIdeRoot( root ) {
	if ( ! root || root.dataset.forwpAcIdeReady === '1' ) {
		return;
	}

	root.dataset.forwpAcIdeReady = '1';
	const hasSsr = root.dataset.forwpSsr === '1';
	if ( ! hasSsr ) {
		root.classList.add( 'forwp-ac-ide--booting' );
	}
	initTerminalDisplay( root );
	initIdeResize( root );

	fetchWizard( getRestUrl( root ) )
		.then( ( payload ) => {
			initIde( root, payload );
		} )
		.catch( () => {
			delete root.dataset.forwpAcIdeReady;
			if ( ! hasSsr ) {
				root.classList.remove( 'forwp-ac-ide--booting' );
			}
			const body = root.querySelector( '.forwp-ac-ide__tour-body' );
			if ( body ) {
				body.innerHTML = `<p class="forwp-ac-ide__error">${ escapeHtml(
					labels().loadError
				) }</p>`;
			}
		} );
}
