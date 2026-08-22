<?php
/**
 * Re-compile imported practice cases when compiler/templates change.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * One-shot and versioned upgrades for terminal imports.
 */
class Terminal_Upgrade {

	public const CONTENT_VERSION = '9';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 25 );
	}

	/**
	 * Re-import registry destinations when content compiler version changes.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( 'forwp_ac_terminal_content_version', '' );

		if ( self::CONTENT_VERSION === $stored ) {
			return;
		}

		self::recompile_imported_posts();

		update_option( 'forwp_ac_terminal_content_version', self::CONTENT_VERSION, false );
	}

	/**
	 * Force-update all valid registry imports with the current compiler.
	 */
	public static function recompile_imported_posts(): void {
		$registry = Terminal_Import_Registry::get_all();

		foreach ( $registry as $slug => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$post_id   = isset( $entry['post_id'] ) ? absint( $entry['post_id'] ) : 0;
			$post_type = isset( $entry['post_type'] ) ? sanitize_key( (string) $entry['post_type'] ) : 'post';

			if ( $post_id <= 0 || ! Terminal_Import_Registry::is_valid_destination( $post_id, $post_type ) ) {
				continue;
			}

			$result = Terminal_Compiler::import(
				(string) $slug,
				$post_type,
				array(
					'force'   => true,
					'post_id' => $post_id,
				)
			);

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[4wp-advanced-code] Re-import failed for ' . $slug . ': ' . $result->get_error_message() );
			}
		}
	}
}
