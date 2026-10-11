/**
 * Shared language options for Code blocks.
 */

import { __ } from '@wordpress/i18n';

/**
 * @return {Array<{ label: string, value: string }>}
 */
export function getLanguageOptions() {
	return [
		{ label: __( 'Auto-detect', '4wp-advanced-code' ), value: 'auto' },
		{ label: 'PHP', value: 'php' },
		{ label: 'JavaScript', value: 'javascript' },
		{ label: 'TypeScript', value: 'typescript' },
		{ label: 'HTML', value: 'html' },
		{ label: 'CSS', value: 'css' },
		{ label: 'SCSS', value: 'scss' },
		{ label: 'Python', value: 'python' },
		{ label: 'JSON', value: 'json' },
		{ label: 'SQL', value: 'sql' },
		{ label: 'Bash / Shell', value: 'bash' },
		{ label: 'PowerShell', value: 'powershell' },
		{ label: 'YAML', value: 'yaml' },
		{ label: 'XML', value: 'xml' },
		{ label: 'Markdown', value: 'markdown' },
		{ label: 'Plain text', value: 'plaintext' },
		{ label: 'Mermaid', value: 'mermaid' },
	];
}
