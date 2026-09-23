/**
 * IDE Wizard block — editor registration + live preview.
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Spinner,
	ToggleControl,
	FormTokenField,
} from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import ideIcon from './icon.js';
import { asWizards, fetchWizard, initIde } from './ide-app.js';
import { initIdeResize } from './ide-resize.js';
import './style.scss';
import './editor.scss';

const HOOKS_PATH = '/forwp-advanced-code/v1/ide/hooks';
const GROUP_PATH = '/forwp-advanced-code/v1/ide/hooks-group';
const SET_PATH = '/forwp-advanced-code/v1/ide/hooks-set';
const MAX_SET = 12;

function hookToken( hook ) {
	if ( ! hook ) {
		return '';
	}
	return `${ hook.type === 'filter' ? 'filter' : 'action' }:${ hook.slug || '' }`;
}

function parseHookToken( token, catalog ) {
	const raw = String( token || '' ).trim();
	const colon = raw.match( /^(action|filter):([a-zA-Z0-9_]+)$/i );
	if ( colon ) {
		return {
			type: colon[ 1 ].toLowerCase(),
			slug: colon[ 2 ].toLowerCase(),
		};
	}

	const list = catalog || [];
	const hit = list.find(
		( hook ) =>
			hook.slug === raw ||
			hook.name === raw ||
			`${ hook.name } (${ hook.type })` === raw
	);
	if ( hit ) {
		return { type: hit.type, slug: hit.slug };
	}

	return null;
}

function restPath( type, slug, tourMode, selectedHooks ) {
	if ( tourMode === 'custom' ) {
		const query = ( selectedHooks || [] ).map( hookToken ).filter( Boolean ).join( ',' );
		return `${ SET_PATH }?hooks=${ encodeURIComponent( query ) }`;
	}
	const kind = tourMode === 'group' ? GROUP_PATH : HOOKS_PATH;
	return `${ kind }/${ type || 'action' }/${ slug || 'wp_head' }`;
}

function buildRestUrl( type, slug, tourMode, selectedHooks ) {
	const path = restPath( type, slug, tourMode, selectedHooks );
	if ( window.wpApiSettings?.root ) {
		return `${ window.wpApiSettings.root }${ path.replace( /^\//, '' ) }`;
	}
	if ( tourMode === 'custom' ) {
		if ( window.forwpAdvancedCodeIde?.restSet ) {
			const query = ( selectedHooks || [] )
				.map( hookToken )
				.filter( Boolean )
				.join( ',' );
			const base = window.forwpAdvancedCodeIde.restSet;
			return `${ base }${ base.includes( '?' ) ? '&' : '?' }hooks=${ encodeURIComponent( query ) }`;
		}
		return `/wp-json${ path }`;
	}
	if ( window.forwpAdvancedCodeIde?.restBase && tourMode !== 'group' ) {
		return `${ window.forwpAdvancedCodeIde.restBase.replace( /\/$/, '' ) }/${ type || 'action' }/${ slug || 'wp_head' }`;
	}
	return `/wp-json${ path }`;
}

function getIdeShellMarkup( { hookType, hookSlug, restUrl, tourMode, selectedHooks } ) {
	const type = hookType === 'filter' ? 'filter' : 'action';
	const slug = hookSlug || 'wp_head';
	const mode =
		tourMode === 'custom' || tourMode === 'group' ? tourMode : 'single';
	const config = JSON.stringify( {
		restUrl,
		hookType: type,
		hookSlug: slug,
		tourMode: mode,
		hooks: selectedHooks || [],
	} );

	return (
		<div
			className="forwp-ac-ide forwp-ac-ide--booting"
			data-forwp-config={ config }
			data-hook-type={ type }
			data-hook-slug={ slug }
			data-tour-mode={ mode }
			data-enable-fullview="1"
		>
			<div className="forwp-ac-ide__shell">
				<div className="forwp-ac-ide__titlebar">
					<span className="forwp-ac-ide__dot forwp-ac-ide__dot--red" />
					<span className="forwp-ac-ide__dot forwp-ac-ide__dot--yellow" />
					<span className="forwp-ac-ide__dot forwp-ac-ide__dot--green" />
					<span className="forwp-ac-ide__title">
						{ slug } — functions.php
					</span>
				</div>
				<div className="forwp-ac-ide__layout">
					<aside className="forwp-ac-ide__explorer">
						<div className="forwp-ac-ide__explorer-label">
							{ __( 'Explorer', '4wp-advanced-code' ) }
						</div>
						<button
							type="button"
							className="forwp-ac-ide__file is-active"
							tabIndex={ -1 }
						>
							functions.php
						</button>
					</aside>
					<div className="forwp-ac-ide__main">
						<div className="forwp-ac-ide__tabs">
							<span className="forwp-ac-ide__tab is-active">
								functions.php
							</span>
						</div>
						<div className="forwp-ac-ide__editor">
							<div className="forwp-ac-ide__gutter" aria-hidden="true" />
							<div className="forwp-ac-ide__code-wrap">
								<pre className="forwp-ac-ide__highlight" aria-hidden="true">
									<code className="language-php" />
								</pre>
								<textarea
									className="forwp-ac-ide__code"
									spellCheck="false"
									autoComplete="off"
									autoCorrect="off"
									autoCapitalize="off"
									aria-label={ __(
										'PHP practice editor',
										'4wp-advanced-code'
									) }
								/>
							</div>
						</div>
					</div>
					<aside className="forwp-ac-ide__tour" aria-live="polite">
						<div className="forwp-ac-ide__tour-kicker">
							{ mode === 'custom'
								? __( 'Hook quiz', '4wp-advanced-code' )
								: mode === 'group'
								? __( 'Group tour', '4wp-advanced-code' )
								: __( 'Hook wizard', '4wp-advanced-code' ) }
						</div>
						<div className="forwp-ac-ide__tour-progress" />
						<h3 className="forwp-ac-ide__tour-title" />
						<div className="forwp-ac-ide__tour-body" />
						<div className="forwp-ac-ide__tour-feedback" hidden />
						<div className="forwp-ac-ide__tour-actions">
							<button
								type="button"
								className="forwp-ac-ide__btn forwp-ac-ide__btn--ghost"
								data-ide-back
							>
								{ __( 'Back', '4wp-advanced-code' ) }
							</button>
							<button
								type="button"
								className="forwp-ac-ide__btn"
								data-ide-check
								hidden
							>
								{ __( 'Check', '4wp-advanced-code' ) }
							</button>
							<button
								type="button"
								className="forwp-ac-ide__btn forwp-ac-ide__btn--primary"
								data-ide-next
							>
								{ __( 'Next', '4wp-advanced-code' ) }
							</button>
						</div>
					</aside>
				</div>
				<div className="forwp-ac-ide__statusbar">
					<span data-ide-status-lang>PHP</span>
					<span data-ide-status-step />
				</div>
			</div>
		</div>
	);
}

function useHookCatalog() {
	const [ hooks, setHooks ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( ! apiFetch ) {
			setLoading( false );
			setError(
				__( 'Could not load the hooks catalog.', '4wp-advanced-code' )
			);
			return;
		}

		apiFetch( { path: HOOKS_PATH } )
			.then( ( response ) => {
				setHooks( response?.hooks || [] );
				setError( '' );
			} )
			.catch( () => {
				setHooks( [] );
				setError(
					__(
						'Could not load hooks from the theme JSON catalog.',
						'4wp-advanced-code'
					)
				);
			} )
			.finally( () => {
				setLoading( false );
			} );
	}, [] );

	return { hooks, loading, error };
}

function Edit( { attributes, setAttributes } ) {
	const hookType =
		attributes.hookType === 'filter' ? 'filter' : 'action';
	const hookSlug = attributes.hookSlug || 'wp_head';
	const selectedHooks = Array.isArray( attributes.selectedHooks )
		? attributes.selectedHooks.filter( ( hook ) => hook && hook.slug )
		: [];
	const tourMode = selectedHooks.length
		? 'custom'
		: attributes.tourMode === 'group'
		? 'group'
		: 'single';
	const { hooks, loading, error } = useHookCatalog();
	const shellRef = useRef( null );
	const blockProps = useBlockProps( {
		className: 'forwp-ac-ide-block',
	} );

	const typeOptions = [
		{ label: __( 'Action', '4wp-advanced-code' ), value: 'action' },
		{ label: __( 'Filter', '4wp-advanced-code' ), value: 'filter' },
	];
	const modeOptions = [
		{
			label: __( 'Single hook', '4wp-advanced-code' ),
			value: 'single',
		},
		{
			label: __( 'Related group', '4wp-advanced-code' ),
			value: 'group',
		},
		{
			label: __( 'Custom set (quiz)', '4wp-advanced-code' ),
			value: 'custom',
		},
	];

	const slugOptions = useMemo( () => {
		const filtered = hooks.filter( ( hook ) => hook.type === hookType );
		if ( ! filtered.length ) {
			return [
				{
					label: hookSlug,
					value: hookSlug,
				},
			];
		}
		return filtered.map( ( hook ) => ( {
			label: hook.tagline
				? `${ hook.name } — ${ hook.tagline }`
				: hook.name,
			value: hook.slug,
		} ) );
	}, [ hooks, hookType, hookSlug ] );

	const tokenSuggestions = useMemo(
		() => hooks.map( hookToken ).filter( Boolean ),
		[ hooks ]
	);

	const tokenDisplay = ( token ) => {
		const parsed = parseHookToken( token, hooks );
		if ( ! parsed ) {
			return token;
		}
		const hit = hooks.find(
			( hook ) => hook.type === parsed.type && hook.slug === parsed.slug
		);
		return hit ? `${ hit.name } (${ hit.type })` : token;
	};

	useEffect( () => {
		if ( loading || ! hooks.length || tourMode === 'custom' ) {
			return;
		}
		const match = hooks.find(
			( hook ) => hook.type === hookType && hook.slug === hookSlug
		);
		if ( match ) {
			return;
		}
		const first = hooks.find( ( hook ) => hook.type === hookType ) || hooks[ 0 ];
		if ( first ) {
			setAttributes( {
				hookType: first.type,
				hookSlug: first.slug,
			} );
		}
	}, [ loading, hooks, hookType, hookSlug, tourMode, setAttributes ] );

	const restUrl = buildRestUrl( hookType, hookSlug, tourMode, selectedHooks );
	const selectedKey = selectedHooks.map( hookToken ).join( ',' );

	useEffect( () => {
		const root = shellRef.current?.querySelector( '.forwp-ac-ide' );
		if ( ! root ) {
			return undefined;
		}

		delete root.dataset.forwpAcIdeReady;
		root.classList.add( 'forwp-ac-ide--booting' );

		const load = apiFetch
			? apiFetch( {
					path: restPath( hookType, hookSlug, tourMode, selectedHooks ),
			  } ).catch( () => fetchWizard( restUrl ) )
			: fetchWizard( restUrl );

		let cancelled = false;

		load
			.then( ( payload ) => {
				if ( cancelled || ! asWizards( payload ).length ) {
					return;
				}
				initIde( root, payload );
				initTerminalDisplay( root );
				initIdeResize( root );
			} )
			.catch( () => {
				if ( cancelled ) {
					return;
				}
				root.classList.remove( 'forwp-ac-ide--booting' );
			} );

		return () => {
			cancelled = true;
			root._forwpIdeResizeCleanup?.();
			root._forwpIdeCleanup?.();
		};
	}, [ hookType, hookSlug, tourMode, restUrl, selectedKey ] );

	const applySelected = ( tokens ) => {
		const next = [];
		const seen = {};
		( tokens || [] ).forEach( ( token ) => {
			const parsed = parseHookToken( token, hooks );
			if ( ! parsed ) {
				return;
			}
			const key = hookToken( parsed );
			if ( seen[ key ] || next.length >= MAX_SET ) {
				return;
			}
			seen[ key ] = true;
			next.push( parsed );
		} );
		setAttributes( {
			selectedHooks: next,
			tourMode: 'custom',
			hookType: next[ 0 ]?.type || hookType,
			hookSlug: next[ 0 ]?.slug || hookSlug,
		} );
	};

	const setMode = ( value ) => {
		if ( value === 'custom' ) {
			const seed =
				selectedHooks.length > 0
					? selectedHooks
					: [ { type: hookType, slug: hookSlug } ];
			setAttributes( {
				tourMode: 'custom',
				selectedHooks: seed,
			} );
			return;
		}
		setAttributes( {
			tourMode: value === 'group' ? 'group' : 'single',
			selectedHooks: [],
		} );
	};

	const tokenField = (
		<FormTokenField
			label={ __( 'Hooks in this quiz', '4wp-advanced-code' ) }
			value={ selectedHooks.map( hookToken ) }
			suggestions={ tokenSuggestions }
			onChange={ applySelected }
			maxLength={ MAX_SET }
			tokenizeOnSpace={ false }
			__experimentalExpandOnFocus={ true }
			__experimentalShowHowTo={ false }
			displayTransform={ tokenDisplay }
			help={
				error ||
				__(
					'Pick up to 12 hooks from the theme catalog. Each hook is a full wizard with its quiz steps.',
					'4wp-advanced-code'
				)
			}
		/>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Hook wizard', '4wp-advanced-code' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Mode', '4wp-advanced-code' ) }
						value={ tourMode }
						options={ modeOptions }
						onChange={ setMode }
					/>
					{ tourMode === 'custom' ? (
						tokenField
					) : (
						<>
							<SelectControl
								label={ __( 'Type', '4wp-advanced-code' ) }
								value={ hookType }
								options={ typeOptions }
								onChange={ ( value ) => {
									const nextType =
										value === 'filter' ? 'filter' : 'action';
									const first = hooks.find(
										( hook ) => hook.type === nextType
									);
									setAttributes( {
										hookType: nextType,
										hookSlug: first?.slug || hookSlug,
									} );
								} }
							/>
							<SelectControl
								label={ __( 'Hook', '4wp-advanced-code' ) }
								value={ hookSlug }
								options={ slugOptions }
								onChange={ ( value ) =>
									setAttributes( { hookSlug: value } )
								}
								disabled={ loading }
								help={
									error ||
									__(
										'Content comes from the theme JSON in inc/data/hooks/. Quiz questions live in the plugin.',
										'4wp-advanced-code'
									)
								}
							/>
							<ToggleControl
								label={ __( 'Group tour', '4wp-advanced-code' ) }
								checked={ tourMode === 'group' }
								onChange={ ( value ) =>
									setAttributes( {
										tourMode: value ? 'group' : 'single',
										selectedHooks: [],
									} )
								}
								help={ __(
									'Walk this hook, then each related_hooks entry, in one session.',
									'4wp-advanced-code'
								) }
							/>
						</>
					) }
					{ loading && <Spinner /> }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="forwp-ac-ide-block__bar">
					<span className="forwp-ac-ide-block__bar-label">
						{ tourMode === 'custom'
							? __( 'Quiz', '4wp-advanced-code' )
							: __( 'Hook', '4wp-advanced-code' ) }
					</span>
					{ tourMode === 'custom' ? (
						tokenField
					) : (
						<>
							<SelectControl
								label={ __( 'Type', '4wp-advanced-code' ) }
								value={ hookType }
								options={ typeOptions }
								onChange={ ( value ) => {
									const nextType =
										value === 'filter' ? 'filter' : 'action';
									const first = hooks.find(
										( hook ) => hook.type === nextType
									);
									setAttributes( {
										hookType: nextType,
										hookSlug: first?.slug || hookSlug,
									} );
								} }
							/>
							<SelectControl
								label={ __( 'Hook', '4wp-advanced-code' ) }
								value={ hookSlug }
								options={ slugOptions }
								onChange={ ( value ) =>
									setAttributes( { hookSlug: value } )
								}
								disabled={ loading }
							/>
						</>
					) }
					{ loading && <Spinner /> }
				</div>
				<div ref={ shellRef }>
					{ getIdeShellMarkup( {
						hookType,
						hookSlug,
						restUrl,
						tourMode,
						selectedHooks,
					} ) }
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	icon: ideIcon,
	edit: Edit,
	save: () => null,
} );
