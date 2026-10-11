<?php
/**
 * Register Gutenberg blocks built from src/blocks/.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration helper.
 */
class Blocks {
	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_blocks' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'localize_diagram_editor' ) );
	}

	/**
	 * Pass plugin theme to the diagram editor (auto theme).
	 */
	public static function localize_diagram_editor(): void {
		if ( ! Modules::is_diagrams_enabled() ) {
			return;
		}

		$handle = function_exists( 'generate_block_asset_handle' )
			? generate_block_asset_handle( 'forwp/diagram', 'editorScript' )
			: 'forwp-diagram-editor-script';

		if ( ! wp_script_is( $handle, 'registered' ) && ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			'window.forwpAdvancedCodeDiagram = ' . wp_json_encode(
				array(
					'pluginTheme' => get_option( 'forwp_advanced_code_theme', 'light' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Registers compiled blocks under build/blocks/{component}/block.json.
	 */
	public static function register_blocks(): void {
		$pattern = FORWP_ADVANCED_CODE_PATH . 'build/blocks/*/block.json';
		$paths   = glob( $pattern );

		if ( ! is_array( $paths ) || empty( $paths ) ) {
			return;
		}

		foreach ( $paths as $block_json_path ) {
			$block_folder = dirname( $block_json_path );
			$block_name   = basename( $block_folder );

			if ( ! is_dir( $block_folder ) ) {
				continue;
			}

			if ( ! Modules::is_block_module_enabled( $block_name ) ) {
				continue;
			}

			$args = array();

			if ( 'terminal' === $block_name ) {
				$args['render_callback'] = array( Blocks\Terminal_Block_Render::class, 'render' );
			}

			if ( 'ide' === $block_name ) {
				$args['render_callback'] = array( Blocks\Ide_Block_Render::class, 'render' );
			}

			if ( 'diagram' === $block_name ) {
				$args['render_callback'] = array( Blocks\Diagram_Block_Render::class, 'render' );
			}

			register_block_type( $block_folder, $args );
		}
	}
}
