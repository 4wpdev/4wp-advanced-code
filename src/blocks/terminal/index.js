/**
 * Terminal block — editor registration + live preview.
 */

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	Spinner,
	ToolbarGroup,
	ToolbarDropdownMenu,
	ToggleControl,
} from '@wordpress/components';
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import terminalIcon from './icon.js';
import { fetchProfileData, initTerminal } from './terminal-app.js';
import { initTerminalDisplay } from './terminal-display.js';
import { initTerminalAuth } from './terminal-auth.js';
import './style.scss';
import './editor.scss';

const REST_NAMESPACE = '/forwp-advanced-code/v1/terminal';
const PROFILES_PATH = '/forwp-advanced-code/v1/terminal-profiles';

const DEFAULT_PROFILE_OPTIONS = [
	{
		label: __( 'WP-CLI (wp-cli.json)', '4wp-advanced-code' ),
		value: 'wp-cli',
	},
];

function buildRestPath( profile ) {
	return `${ REST_NAMESPACE }/${ profile || 'wp-cli' }`;
}

function buildRestUrl( profile ) {
	const path = buildRestPath( profile ).replace( /^\//, '' );
	if ( window.wpApiSettings?.root ) {
		return `${ window.wpApiSettings.root }${ path }`;
	}
	return `/wp-json${ buildRestPath( profile ) }`;
}

function formatProfileOption( item ) {
	const title = item.title || item.label || item.slug;
	const file = item.file || `${ item.slug }.json`;
	const source =
		item.source === 'custom'
			? __( 'custom', '4wp-advanced-code' )
			: __( 'template', '4wp-advanced-code' );

	return {
		label: sprintf(
			/* translators: 1: profile title, 2: json filename, 3: template or custom */
			__( '%1$s — %2$s (%3$s)', '4wp-advanced-code' ),
			title,
			file,
			source
		),
		value: item.slug,
	};
}

function useTerminalProfiles() {
	const [ profileOptions, setProfileOptions ] = useState(
		DEFAULT_PROFILE_OPTIONS
	);
	const [ profilesLoading, setProfilesLoading ] = useState( true );
	const [ profilesError, setProfilesError ] = useState( '' );

	useEffect( () => {
		if ( ! apiFetch ) {
			setProfilesLoading( false );
			setProfilesError(
				__( 'Could not load profile list.', '4wp-advanced-code' )
			);
			return;
		}

		apiFetch( { path: PROFILES_PATH } )
			.then( ( response ) => {
				const list = response?.profiles || [];
				if ( list.length ) {
					setProfileOptions( list.map( formatProfileOption ) );
				}
				setProfilesError( '' );
			} )
			.catch( () => {
				setProfileOptions( DEFAULT_PROFILE_OPTIONS );
				setProfilesError(
					__(
						'Could not load profiles from the server. Only bundled templates are available.',
						'4wp-advanced-code'
					)
				);
			} )
			.finally( () => {
				setProfilesLoading( false );
			} );
	}, [] );

	return {
		profileOptions,
		profilesLoading,
		profilesError,
	};
}

function ProfileSelectControl( {
	label,
	value,
	options,
	onChange,
	disabled,
	help,
} ) {
	return (
		<SelectControl
			label={ label }
			value={ value }
			options={ options }
			onChange={ onChange }
			disabled={ disabled }
			help={ help }
		/>
	);
}

function resolveProfileSlug( profile ) {
	if ( ! profile || profile === 'embedded' ) {
		return 'embedded';
	}

	return profile;
}

function getTerminalShellMarkup( {
	profile,
	welcomeMessage,
	restUrl,
	title,
	enableFullView = true,
	enableSticky = true,
	stickySide = 'right',
} ) {
	const slug = resolveProfileSlug( profile );
	const config = JSON.stringify( {
		restUrl,
		profile: slug,
	} );
	const welcome = welcomeMessage || metadata.attributes.welcomeMessage.default;
	const shellTitle = title || ( slug === 'embedded' ? metadata.title : 'wp-cli — bash' );
	const side = stickySide === 'left' ? 'left' : 'right';

	return (
		<div
			className="forwp-ac-terminal forwp-ac-terminal--booting"
			data-forwp-config={ config }
			data-profile={ slug }
			data-welcome={ welcome }
			data-enable-fullview={ enableFullView ? '1' : '0' }
			data-enable-sticky={ enableSticky ? '1' : '0' }
			data-sticky-side={ side }
		>
			<div className="forwp-ac-terminal__shell">
				<div className="forwp-ac-terminal__header">
					<span className="forwp-ac-terminal__dot forwp-ac-terminal__dot--red" />
					<span className="forwp-ac-terminal__dot forwp-ac-terminal__dot--yellow" />
					<span className="forwp-ac-terminal__dot forwp-ac-terminal__dot--green" />
					<span className="forwp-ac-terminal__title">{ shellTitle }</span>
					<div className="forwp-ac-terminal__display-controls">
						{ enableFullView && (
							<button
								type="button"
								className="forwp-ac-terminal__display-btn"
								data-forwp-mode="fullview"
								aria-pressed="false"
								aria-label={ __(
									'Full view',
									'4wp-advanced-code'
								) }
								title={ __(
									'Full view',
									'4wp-advanced-code'
								) }
							>
								<span aria-hidden="true">⛶</span>
								<span className="forwp-ac-terminal__display-btn-label">
									{ __( 'Full view', '4wp-advanced-code' ) }
								</span>
							</button>
						) }
						{ enableSticky && (
							<button
								type="button"
								className="forwp-ac-terminal__display-btn"
								data-forwp-mode="sticky"
								aria-pressed="false"
								aria-label={ __(
									'Sticky panel',
									'4wp-advanced-code'
								) }
								title={ __(
									'Sticky panel',
									'4wp-advanced-code'
								) }
							>
								<span aria-hidden="true">📌</span>
								<span className="forwp-ac-terminal__display-btn-label">
									{ __( 'Sticky', '4wp-advanced-code' ) }
								</span>
							</button>
						) }
					</div>
				</div>
				<div className="forwp-ac-terminal__body">
					<div className="forwp-ac-terminal__main">
						<div
							className="forwp-ac-terminal__output"
							aria-live="polite"
						/>
						<form className="forwp-ac-terminal__form">
							<label className="forwp-ac-terminal__input-row">
								<span className="forwp-ac-terminal__prompt">
									$
								</span>
								<input
									type="text"
									className="forwp-ac-terminal__input"
									spellCheck="false"
									autoComplete="off"
									autoCorrect="off"
									autoCapitalize="off"
									aria-label={ __(
										'Terminal command',
										'4wp-advanced-code'
									) }
								/>
							</label>
						</form>
					</div>
					<aside className="forwp-ac-terminal__sidebar">
						<h3 className="forwp-ac-terminal__sidebar-title">
							{ __(
								'Command Categories',
								'4wp-advanced-code'
							) }
						</h3>
						<div className="forwp-ac-terminal__sidebar-list" />
					</aside>
				</div>
			</div>
		</div>
	);
}

function Edit( { attributes, setAttributes } ) {
	const {
		profile,
		welcomeMessage,
		enableFullView = true,
		enableSticky = true,
		stickySide = 'right',
	} = attributes;
	const terminalRef = useRef( null );
	const previousProfileRef = useRef( profile );
	const { profileOptions, profilesLoading, profilesError } =
		useTerminalProfiles();
	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.() || 0,
		[]
	);
	const blockProps = useBlockProps( {
		className: 'forwp-ac-terminal-block',
	} );

	const currentProfileLabel = useMemo( () => {
		const match = profileOptions.find(
			( option ) => option.value === ( profile || 'wp-cli' )
		);
		return match?.label || profile || 'wp-cli';
	}, [ profile, profileOptions ] );

	const handleProfileChange = useCallback(
		( value ) => {
			if ( ! value || value === profile ) {
				return;
			}

			setAttributes( { profile: value } );
		},
		[ profile, setAttributes ]
	);

	useEffect( () => {
		if ( ! apiFetch || profile === previousProfileRef.current ) {
			previousProfileRef.current = profile;
			return;
		}

		previousProfileRef.current = profile;

		apiFetch( { path: buildRestPath( profile ) } )
			.then( ( data ) => {
				if ( ! data?.title ) {
					return;
				}

				const defaultWelcome = metadata.attributes.welcomeMessage.default;
				const shouldSyncWelcome =
					! welcomeMessage || welcomeMessage === defaultWelcome;

				if ( shouldSyncWelcome ) {
					const isChallenge = data?.type === 'challenge';
					setAttributes( {
						welcomeMessage: isChallenge
							? data.instructions ||
							  data.description ||
							  data.title
							: sprintf(
									/* translators: %s: terminal profile title */
									__(
										'%s — type "help" for available commands',
										'4wp-advanced-code'
									),
									data.title
							  ),
					} );
				}
			} )
			.catch( () => {
				// Keep existing welcome message when profile data fails to load.
			} );
	}, [ profile, setAttributes, welcomeMessage ] );

	useEffect( () => {
		const root = terminalRef.current?.querySelector( '.forwp-ac-terminal' );
		if ( ! root ) {
			return;
		}

		root.dataset.forwpAcTerminalReady = '';
		delete root.dataset.forwpAuthReady;
		root.querySelector( '.forwp-ac-terminal__auth-link' )?.remove();
		root.classList.add( 'forwp-ac-terminal--booting' );
		root.classList.remove( 'forwp-ac-terminal--challenge' );
		delete root.dataset.forwpAcTerminalMode;

		const output = root.querySelector( '.forwp-ac-terminal__output' );
		const sidebar = root.querySelector(
			'.forwp-ac-terminal__sidebar-list'
		);
		const sidebarWrap = root.querySelector(
			'.forwp-ac-terminal__sidebar'
		);
		const challengePanel = root.querySelector(
			'.forwp-ac-terminal__challenge'
		);
		if ( output ) {
			output.innerHTML = '';
		}
		if ( sidebar ) {
			sidebar.innerHTML = '';
		}
		if ( sidebarWrap ) {
			sidebarWrap.hidden = false;
		}
		if ( challengePanel ) {
			challengePanel.remove();
		}
		root.querySelector( '.forwp-ac-terminal__challenge-actions' )?.remove();

		const restPath =
			( ! profile || profile === 'embedded' ) && postId
				? `/forwp-advanced-code/v1/posts/${ postId }/terminal-data`
				: buildRestPath( profile );
		const restUrl =
			( ! profile || profile === 'embedded' ) && postId
				? buildRestUrl( profile ).replace(
						/forwp-advanced-code\/v1\/terminal\/[^/?#]*/,
						`forwp-advanced-code/v1/posts/${ postId }/terminal-data`
				  )
				: buildRestUrl( profile );

		const load = apiFetch
			? apiFetch( { path: restPath } ).catch( () =>
					fetchProfileData( restUrl )
			  )
			: fetchProfileData( restUrl );

		load
			.then( ( data ) => {
				const titleEl = root.querySelector(
					'.forwp-ac-terminal__title'
				);
				if ( titleEl && data?.title ) {
					titleEl.textContent = data.title;
				}
				initTerminal( root, data, welcomeMessage );
				initTerminalDisplay( root );
				initTerminalAuth( root );
			} )
			.catch( () => {
				root.classList.remove( 'forwp-ac-terminal--booting' );
			} );
	}, [ profile, welcomeMessage, postId ] );

	const profileHelp = profilesLoading
		? __( 'Loading configuration files…', '4wp-advanced-code' )
		: __(
				'Each JSON file is one terminal profile. Manage files under 4WP Adv. Code → Terminal Profiles.',
				'4wp-advanced-code'
		  );

	const toolbarControls = profileOptions.map( ( option ) => ( {
		title: option.label,
		isActive: option.value === ( profile || 'wp-cli' ),
		onClick: () => handleProfileChange( option.value ),
	} ) );

	const restUrl = buildRestUrl( profile );

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarDropdownMenu
						icon="database"
						label={ __( 'Configuration file', '4wp-advanced-code' ) }
						text={
							profilesLoading
								? __( 'Loading…', '4wp-advanced-code' )
								: currentProfileLabel
						}
						controls={ toolbarControls }
						disabled={ profilesLoading || ! profileOptions.length }
					/>
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<PanelBody
					title={ __( 'Terminal settings', '4wp-advanced-code' ) }
					initialOpen={ true }
				>
					<ProfileSelectControl
						label={ __(
							'Configuration file (JSON profile)',
							'4wp-advanced-code'
						) }
						value={ profile || 'wp-cli' }
						options={ profileOptions }
						onChange={ handleProfileChange }
						disabled={ profilesLoading }
						help={ profilesError || profileHelp }
					/>
					{ profilesLoading && <Spinner /> }
					<TextControl
						label={ __( 'Welcome message', '4wp-advanced-code' ) }
						value={ welcomeMessage }
						onChange={ ( value ) =>
							setAttributes( { welcomeMessage: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Display modes', '4wp-advanced-code' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Full view button', '4wp-advanced-code' ) }
						help={ __(
							'Lets readers expand the terminal to fullscreen.',
							'4wp-advanced-code'
						) }
						checked={ enableFullView !== false }
						onChange={ ( value ) =>
							setAttributes( { enableFullView: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Sticky panel button', '4wp-advanced-code' ) }
						help={ __(
							'Compact terminal fixed to the side while scrolling the page.',
							'4wp-advanced-code'
						) }
						checked={ enableSticky !== false }
						onChange={ ( value ) =>
							setAttributes( { enableSticky: value } )
						}
					/>
					{ enableSticky !== false && (
						<SelectControl
							label={ __( 'Sticky side', '4wp-advanced-code' ) }
							value={ stickySide === 'left' ? 'left' : 'right' }
							options={ [
								{
									label: __(
										'Bottom right',
										'4wp-advanced-code'
									),
									value: 'right',
								},
								{
									label: __(
										'Bottom left',
										'4wp-advanced-code'
									),
									value: 'left',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { stickySide: value } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="forwp-ac-terminal-block__profile-bar">
					<span className="forwp-ac-terminal-block__profile-label">
						{ __( 'Configuration file', '4wp-advanced-code' ) }
					</span>
					<div className="forwp-ac-terminal-block__profile-select">
						<ProfileSelectControl
							label={ __(
								'Configuration file (JSON profile)',
								'4wp-advanced-code'
							) }
							value={ profile || 'wp-cli' }
							options={ profileOptions }
							onChange={ handleProfileChange }
							disabled={ profilesLoading }
							help=""
						/>
					</div>
					{ profilesLoading && <Spinner /> }
				</div>
				<div ref={ terminalRef }>
					{ getTerminalShellMarkup( {
						profile,
						welcomeMessage,
						restUrl,
						enableFullView,
						enableSticky,
						stickySide,
					} ) }
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	icon: terminalIcon,
	edit: Edit,
	save: () => null,
} );
