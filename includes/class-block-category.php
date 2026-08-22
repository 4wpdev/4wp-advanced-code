<?php
/**
 * Gutenberg block category for 4WP Advanced Code blocks.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Registers custom block inserter category.
 */
class Block_Category {

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_filter( 'block_categories_all', array( self::class, 'register' ), 10, 2 );
	}

	/**
	 * Prepends plugin block category in the inserter.
	 *
	 * @param array<int, array<string, mixed>> $categories       Registered categories.
	 * @param \WP_Block_Editor_Context        $editor_context Editor context.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register( array $categories, $editor_context ): array {
		unset( $editor_context );

		return array_merge(
			array(
				array(
					'slug'  => 'forwp-advanced-code',
					'title' => __( '4WP Adv. Code', '4wp-advanced-code' ),
					'icon'  => 'editor-code',
				),
			),
			$categories
		);
	}
}
