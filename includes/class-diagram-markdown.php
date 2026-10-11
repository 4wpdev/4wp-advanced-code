<?php
/**
 * @deprecated 2.0.0 Use Drive_Capabilities for Drive Mermaid mapping.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Back-compat shim — mapping lives in Drive_Capabilities.
 */
class Diagram_Markdown {

	/**
	 * @return void
	 */
	public static function init(): void {
		// Drive_Capabilities::init() registers forwp_drive_markdown_code_fence.
	}

	/**
	 * @param string $html     Default HTML.
	 * @param string $code     Fence body.
	 * @param string $language Fence language.
	 */
	public static function map_fence( string $html, string $code, string $language ): string {
		return Drive_Capabilities::map_fence( $html, $code, $language );
	}
}
