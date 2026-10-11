/**
 * 4WP Advanced Code - Block Editor
 */

import './editor.scss';

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, createElement } from '@wordpress/element';
import {
	InspectorControls,
	PlainText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import metadata from '../block.json';
import { getLanguageOptions } from './languages.js';

registerBlockType( metadata.name, {
	...metadata,
	edit: EditComponent,
	save: () => null,
} );

/**
 * Add language (+ related) attributes to core/code so each block can override auto-detect.
 *
 * @param {object} settings Block settings.
 * @param {string} name     Block name.
 * @return {object}
 */
function addCoreCodeAttributes( settings, name ) {
	if ( name !== 'core/code' ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			language: {
				type: 'string',
				default: 'auto',
			},
			theme: {
				type: 'string',
				default: '',
			},
			showCopy: {
				type: 'boolean',
				default: true,
			},
			showShare: {
				type: 'boolean',
				default: true,
			},
			advancedEnabled: {
				type: 'boolean',
				default: true,
			},
			note: {
				type: 'string',
				default: '',
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'forwp/advanced-code/core-code-attributes',
	addCoreCodeAttributes
);

/**
 * Sidebar controls on core/code for language override.
 */
const withCoreCodeLanguageControls = createHigherOrderComponent(
	( BlockEdit ) => {
		return ( props ) => {
			if ( props.name !== 'core/code' ) {
				return createElement( BlockEdit, props );
			}

			const { attributes, setAttributes } = props;
			const language = attributes.language || 'auto';
			const theme = attributes.theme || '';
			const advancedEnabled = attributes.advancedEnabled !== false;

			return createElement(
				Fragment,
				null,
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{
							title: __( '4WP Code', '4wp-advanced-code' ),
							initialOpen: true,
						},
						createElement( ToggleControl, {
							label: __(
								'Enable advanced features',
								'4wp-advanced-code'
							),
							checked: advancedEnabled,
							onChange: ( value ) =>
								setAttributes( { advancedEnabled: value } ),
						} ),
						createElement( SelectControl, {
							label: __( 'Language', '4wp-advanced-code' ),
							value: language,
							options: getLanguageOptions(),
							onChange: ( value ) =>
								setAttributes( { language: value } ),
							help: __(
								'Override auto-detect when highlighting is wrong. Applies to this block only.',
								'4wp-advanced-code'
							),
						} ),
						createElement( SelectControl, {
							label: __( 'Theme override', '4wp-advanced-code' ),
							value: theme,
							options: [
								{
									label: __(
										'Default (global setting)',
										'4wp-advanced-code'
									),
									value: '',
								},
								{
									label: __( 'Light', '4wp-advanced-code' ),
									value: 'light',
								},
								{
									label: __( 'Dark', '4wp-advanced-code' ),
									value: 'dark',
								},
								{
									label: __(
										'Terminal',
										'4wp-advanced-code'
									),
									value: 'terminal',
								},
							],
							onChange: ( value ) =>
								setAttributes( { theme: value } ),
						} )
					)
				)
			);
		};
	},
	'withCoreCodeLanguageControls'
);

addFilter(
	'editor.BlockEdit',
	'forwp/advanced-code/core-code-language',
	withCoreCodeLanguageControls
);

/**
 * Edit component for forwp/advanced-code.
 *
 * @param {object} props Block props.
 */
function EditComponent( { attributes, setAttributes } ) {
	const {
		content,
		language,
		theme,
		showCopy,
		showShare,
		note,
		advancedEnabled,
		seoEnabled,
		seoTitle,
		seoDescription,
		seoType,
		slug,
	} = attributes;

	const blockProps = useBlockProps( {
		className: `wp-block-code-advanced theme-${ theme || 'light' }`,
	} );

	const languageOptions = getLanguageOptions();

	const themeOptions = [
		{
			label: __( 'Default (Global Setting)', '4wp-advanced-code' ),
			value: '',
		},
		{ label: __( 'Light', '4wp-advanced-code' ), value: 'light' },
		{ label: __( 'Dark', '4wp-advanced-code' ), value: 'dark' },
		{ label: __( 'Terminal', '4wp-advanced-code' ), value: 'terminal' },
	];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'General Settings', '4wp-advanced-code' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __(
							'Enable Advanced Features',
							'4wp-advanced-code'
						) }
						checked={ advancedEnabled }
						onChange={ ( value ) =>
							setAttributes( { advancedEnabled: value } )
						}
					/>

					<SelectControl
						label={ __( 'Language', '4wp-advanced-code' ) }
						value={ language }
						options={ languageOptions }
						onChange={ ( value ) =>
							setAttributes( { language: value } )
						}
						help={ __(
							'Override auto-detect when highlighting is wrong.',
							'4wp-advanced-code'
						) }
					/>

					<SelectControl
						label={ __( 'Theme Override', '4wp-advanced-code' ) }
						value={ theme }
						options={ themeOptions }
						onChange={ ( value ) =>
							setAttributes( { theme: value } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Display Options', '4wp-advanced-code' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Show Copy Button', '4wp-advanced-code' ) }
						checked={ showCopy }
						onChange={ ( value ) =>
							setAttributes( { showCopy: value } )
						}
					/>

					<ToggleControl
						label={ __( 'Show Share Button', '4wp-advanced-code' ) }
						checked={ showShare }
						onChange={ ( value ) =>
							setAttributes( { showShare: value } )
						}
					/>

					<TextareaControl
						label={ __( 'Additional Note', '4wp-advanced-code' ) }
						value={ note }
						onChange={ ( value ) =>
							setAttributes( { note: value } )
						}
						help={ __(
							'Optional note to display above the code block',
							'4wp-advanced-code'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'SEO Settings', '4wp-advanced-code' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Enable SEO Markup', '4wp-advanced-code' ) }
						checked={ seoEnabled }
						onChange={ ( value ) =>
							setAttributes( { seoEnabled: value } )
						}
					/>

					{ seoEnabled && (
						<>
							<TextControl
								label={ __(
									'SEO Title',
									'4wp-advanced-code'
								) }
								value={ seoTitle }
								onChange={ ( value ) =>
									setAttributes( { seoTitle: value } )
								}
							/>

							<TextareaControl
								label={ __(
									'SEO Description',
									'4wp-advanced-code'
								) }
								value={ seoDescription }
								onChange={ ( value ) =>
									setAttributes( {
										seoDescription: value,
									} )
								}
							/>

							<SelectControl
								label={ __(
									'Code Type',
									'4wp-advanced-code'
								) }
								value={ seoType }
								options={ [
									{
										label: __(
											'Example',
											'4wp-advanced-code'
										),
										value: 'example',
									},
									{
										label: __(
											'Full Code',
											'4wp-advanced-code'
										),
										value: 'full',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { seoType: value } )
								}
							/>

							<TextControl
								label={ __(
									'Custom Slug',
									'4wp-advanced-code'
								) }
								value={ slug }
								onChange={ ( value ) =>
									setAttributes( { slug: value } )
								}
								help={ __(
									'For direct linking to this code block',
									'4wp-advanced-code'
								) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div className="code-editor-container">
				{ note && (
					<div className="code-note-preview">{ note }</div>
				) }

				<div className="code-header-preview">
					<span className="language-indicator">
						{ language === 'auto'
							? __( 'Auto-detect', '4wp-advanced-code' )
							: language }
					</span>
					<div className="actions-preview">
						{ showCopy && (
							<span className="action-indicator">Copy</span>
						) }
						{ showShare && (
							<span className="action-indicator">Share</span>
						) }
					</div>
				</div>

				<PlainText
					tagName="pre"
					value={ content }
					onChange={ ( value ) =>
						setAttributes( { content: value } )
					}
					placeholder={ __( 'Write code...', '4wp-advanced-code' ) }
					className="code-input"
				/>
			</div>
		</div>
	);
}
