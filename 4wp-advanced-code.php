<?php
/**
 * Plugin Name:       4WP Advanced Code
 * Plugin URI:        https://4wp.dev/
 * Description:       Enhanced Code block for Gutenberg: syntax highlighting, copy and share controls, and optional SoftwareSourceCode JSON-LD.
 * Version:           1.0.9
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            4wpdev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       4wp-advanced-code
 *
 * @package ForWP\AdvancedCode
 */

defined( 'ABSPATH' ) || exit;

define( 'FORWP_ADVANCED_CODE_VERSION', '1.0.2' );
define( 'FORWP_ADVANCED_CODE_FILE', __FILE__ );
define( 'FORWP_ADVANCED_CODE_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_ADVANCED_CODE_URL', plugin_dir_url( __FILE__ ) );

require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-block-wrapper.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-seo-handler.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-modules.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-block-category.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-settings.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-rest.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-import-registry.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-profiles.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-post-meta.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-compiler.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-upgrade.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/blocks/class-terminal-block-render.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-block-runtime.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-terminal-admin.php';
require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-blocks.php';

ForWP\AdvancedCode\Block_Category::init();
ForWP\AdvancedCode\Block_Wrapper::init();
ForWP\AdvancedCode\Seo_Handler::init();
ForWP\AdvancedCode\Settings::init();
ForWP\AdvancedCode\Terminal_Post_Meta::init();
ForWP\AdvancedCode\Terminal_Block_Runtime::init();
ForWP\AdvancedCode\Terminal_Upgrade::init();
ForWP\AdvancedCode\Terminal_Rest::init();
ForWP\AdvancedCode\Terminal_Admin::init();
ForWP\AdvancedCode\Blocks::init();

add_action(
	'init',
	static function (): void {
		if ( ForWP\AdvancedCode\Modules::is_code_enabled() ) {
			register_block_type( FORWP_ADVANCED_CODE_PATH . 'block.json' );
		}
	},
	20
);

add_action( 'wp_enqueue_scripts', 'forwp_advanced_code_enqueue_frontend_assets' );

/**
 * Enqueue frontend assets when the page contains code blocks.
 */
function forwp_advanced_code_enqueue_frontend_assets(): void {
	if ( ! ForWP\AdvancedCode\Modules::is_code_enabled() ) {
		return;
	}

	if ( ! forwp_advanced_code_page_has_code_blocks() ) {
		return;
	}

	wp_enqueue_script(
		'forwp-advanced-code-highlight',
		FORWP_ADVANCED_CODE_URL . 'assets/highlight.min.js',
		array(),
		'11.11.1',
		true
	);

	wp_enqueue_script(
		'forwp-advanced-code-frontend',
		FORWP_ADVANCED_CODE_URL . 'assets/frontend.js',
		array( 'forwp-advanced-code-highlight' ),
		FORWP_ADVANCED_CODE_VERSION,
		true
	);

	wp_enqueue_style(
		'forwp-advanced-code-frontend',
		FORWP_ADVANCED_CODE_URL . 'assets/frontend.css',
		array(),
		FORWP_ADVANCED_CODE_VERSION
	);

	$theme = get_option( 'forwp_advanced_code_theme', 'light' );
	$theme_file = 'highlight-default.min.css';

	if ( in_array( $theme, array( 'dark', 'terminal' ), true ) ) {
		$theme_file = 'highlight-dark.min.css';
	}

	wp_enqueue_style(
		'forwp-advanced-code-highlight-theme',
		FORWP_ADVANCED_CODE_URL . 'assets/' . $theme_file,
		array( 'forwp-advanced-code-frontend' ),
		'11.11.1'
	);
}

/**
 * Whether the current singular post contains core/code or forwp/advanced-code blocks.
 */
function forwp_advanced_code_page_has_code_blocks(): bool {
	if ( ! is_singular() ) {
		return false;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post || ! has_blocks( $post->post_content ) ) {
		return false;
	}

	return has_block( 'core/code', $post ) || has_block( 'forwp/advanced-code', $post );
}
