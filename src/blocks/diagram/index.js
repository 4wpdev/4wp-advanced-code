/**
 * Register forwp/diagram block.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit.js';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	...metadata,
	title: __( '4WP Diagram', '4wp-advanced-code' ),
	edit: Edit,
	save: () => null,
} );
