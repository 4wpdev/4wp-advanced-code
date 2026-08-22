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

			register_block_type( $block_folder, $args );
		}
	}
}
