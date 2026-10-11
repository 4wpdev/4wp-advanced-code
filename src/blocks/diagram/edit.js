/**
 * Diagram block editor — textarea + debounced Mermaid preview.
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { renderMermaid, resolveTheme } from './render-mermaid.js';
import { svgToPngDataUrl, uploadDiagramPng } from './png-upload.js';

const DEBOUNCE_MS = 300;

export default function Edit( { attributes, setAttributes } ) {
	const {
		code,
		svg,
		pngId,
		title,
		description,
		caption,
		theme,
	} = attributes;

	const previewRef = useRef( null );
	const revision = useRef( 0 );
	const lastSvg = useRef( svg || '' );
	const [ error, setError ] = useState( '' );
	const [ uploading, setUploading ] = useState( false );

	const postId = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return editor?.getCurrentPostId?.() || 0;
	}, [] );

	const pluginTheme = useSelect( ( select ) => {
		// Prefer block theme; plugin default comes from localized data if present.
		const data = window.forwpAdvancedCodeDiagram || {};
		return data.pluginTheme || 'light';
	}, [] );

	const resolved = resolveTheme( theme, pluginTheme );
	const blockProps = useBlockProps( {
		className: `forwp-diagram-editor forwp-diagram-editor--${ resolved }`,
	} );

	useEffect( () => {
		revision.current += 1;
		const current = revision.current;

		const timer = window.setTimeout( async () => {
			const source = ( code || '' ).trim();
			if ( ! source ) {
				if ( previewRef.current ) {
					previewRef.current.replaceChildren();
				}
				setError(
					__(
						'Enter Mermaid syntax to preview the diagram.',
						'4wp-advanced-code'
					)
				);
				return;
			}

			try {
				const result = await renderMermaid( source, resolved );
				if ( current !== revision.current || ! previewRef.current ) {
					return;
				}

				previewRef.current.innerHTML = result.svg;
				const el = previewRef.current.querySelector( 'svg' );
				if ( el ) {
					el.setAttribute( 'role', 'img' );
					if ( title ) {
						el.setAttribute( 'aria-label', title );
					}
				}
				result.bindFunctions?.( previewRef.current );
				setError( '' );

				const markup = el ? el.outerHTML : result.svg;
				if ( markup && markup !== lastSvg.current ) {
					lastSvg.current = markup;
					setAttributes( { svg: markup } );
					persistPng( markup );
				}
			} catch ( err ) {
				if ( current !== revision.current ) {
					return;
				}
				previewRef.current?.replaceChildren();
				setError(
					err?.message ||
						__(
							'Mermaid could not render this diagram.',
							'4wp-advanced-code'
						)
				);
			}
		}, DEBOUNCE_MS );

		return () => window.clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ code, resolved, title ] );

	async function persistPng( markup ) {
		if ( uploading ) {
			return;
		}
		setUploading( true );
		try {
			const dataUrl = await svgToPngDataUrl( markup );
			const response = await uploadDiagramPng( {
				dataUrl,
				title: title || __( 'Diagram', '4wp-advanced-code' ),
				alt: description || title || __( 'Diagram', '4wp-advanced-code' ),
				postId,
				pngId,
			} );
			if ( response?.id ) {
				setAttributes( { pngId: response.id } );
			}
		} catch ( e ) {
			// Preview still works; PNG is best-effort for SEO.
		} finally {
			setUploading( false );
		}
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Diagram', '4wp-advanced-code' ) }
					initialOpen
				>
					<SelectControl
						label={ __( 'Theme', '4wp-advanced-code' ) }
						value={ theme }
						options={ [
							{
								label: __(
									'Match plugin (auto)',
									'4wp-advanced-code'
								),
								value: 'auto',
							},
							{
								label: __( 'Light', '4wp-advanced-code' ),
								value: 'light',
							},
							{
								label: __( 'Dark', '4wp-advanced-code' ),
								value: 'dark',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { theme: value } )
						}
					/>
					<TextControl
						label={ __( 'Title', '4wp-advanced-code' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
						help={ __(
							'Used as SVG <title> and schema name.',
							'4wp-advanced-code'
						) }
					/>
					<TextareaControl
						label={ __( 'Description', '4wp-advanced-code' ) }
						value={ description }
						onChange={ ( value ) =>
							setAttributes( { description: value } )
						}
						help={ __(
							'SVG <desc>, PNG alt, and ImageObject description.',
							'4wp-advanced-code'
						) }
					/>
					<TextControl
						label={ __( 'Caption', '4wp-advanced-code' ) }
						value={ caption }
						onChange={ ( value ) =>
							setAttributes( { caption: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Instructions', '4wp-advanced-code' ) }
					initialOpen={ false }
				>
					<ol>
						<li>
							{ __(
								'Paste Mermaid in the editor.',
								'4wp-advanced-code'
							) }
						</li>
						<li>
							{ __(
								'Optional: %% steps: A,B,C',
								'4wp-advanced-code'
							) }
						</li>
						<li>
							{ __(
								'Set Title / Description / Caption.',
								'4wp-advanced-code'
							) }
						</li>
						<li>
							{ __( 'Update the post.', '4wp-advanced-code' ) }
						</li>
					</ol>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<TextareaControl
					className="forwp-diagram-editor__code"
					label={ __( 'Mermaid syntax', '4wp-advanced-code' ) }
					value={ code }
					rows={ 12 }
					onChange={ ( value ) => setAttributes( { code: value } ) }
					help={ __(
						'Optional: %% steps: A,B,C for playback on the front end.',
						'4wp-advanced-code'
					) }
					spellCheck={ false }
				/>
				<div
					className="forwp-diagram-editor__preview"
					aria-live="polite"
				>
					{ error ? (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) : null }
					{ uploading ? (
						<p className="forwp-diagram-editor__status">
							{ __(
								'Saving PNG preview…',
								'4wp-advanced-code'
							) }
						</p>
					) : null }
					<div ref={ previewRef } />
				</div>
			</div>
		</>
	);
}
