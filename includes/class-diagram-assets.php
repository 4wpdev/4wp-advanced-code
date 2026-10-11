<?php
/**
 * Register / enqueue the pinned local Mermaid library (editor only).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Mermaid script handle shared by the diagram editor.
 */
class Diagram_Assets {

	public const HANDLE = 'forwp-ac-mermaid';

	/**
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
		add_action( 'init', array( self::class, 'attach_editor_dependency' ), 30 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_for_editor' ), 5 );
	}

	/**
	 * @return void
	 */
	public static function register(): void {
		if ( ! Modules::is_diagrams_enabled() ) {
			return;
		}

		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		$path = FORWP_ADVANCED_CODE_PATH . 'assets/vendor/mermaid/mermaid.min.js';
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			FORWP_ADVANCED_CODE_URL . 'assets/vendor/mermaid/mermaid.min.js',
			array(),
			defined( 'FORWP_ADVANCED_CODE_MERMAID_VERSION' ) ? FORWP_ADVANCED_CODE_MERMAID_VERSION : '11.6.0',
			true
		);
	}

	/**
	 * After the diagram block registers, make Mermaid a hard dependency of its editor script.
	 *
	 * @return void
	 */
	public static function attach_editor_dependency(): void {
		if ( ! Modules::is_diagrams_enabled() ) {
			return;
		}

		self::register();

		foreach ( self::diagram_editor_handles() as $handle ) {
			$script = wp_scripts()->query( $handle, 'registered' );
			if ( ! $script ) {
				continue;
			}
			if ( ! in_array( self::HANDLE, $script->deps, true ) ) {
				$script->deps[] = self::HANDLE;
			}
		}
	}

	/**
	 * Ensure Mermaid is printed with the block editor (same frame as the block script).
	 *
	 * @return void
	 */
	public static function enqueue_for_editor(): void {
		if ( ! Modules::is_diagrams_enabled() ) {
			return;
		}

		self::register();
		self::attach_editor_dependency();

		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_enqueue_script( self::HANDLE );
		}
	}

	/**
	 * @return array<int, string>
	 */
	private static function diagram_editor_handles(): array {
		$handles = array( 'forwp-diagram-editor-script' );

		if ( function_exists( 'generate_block_asset_handle' ) ) {
			$handles[] = generate_block_asset_handle( 'forwp/diagram', 'editorScript' );
		}

		return array_values( array_unique( array_filter( $handles ) ) );
	}
}
