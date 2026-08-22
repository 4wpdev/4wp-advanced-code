<?php
/**
 * Plugin modules: Code Block, Terminal, IDE.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Module enable/disable flags (Settings screen).
 */
class Modules {

	public const OPTION_CODE     = 'forwp_ac_module_code';
	public const OPTION_TERMINAL = 'forwp_ac_module_terminal';
	public const OPTION_IDE      = 'forwp_ac_module_ide';

	/**
	 * Module metadata for Settings UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function definitions(): array {
		return array(
			'code'     => array(
				'option'      => self::OPTION_CODE,
				'label'       => __( 'Code Block', '4wp-advanced-code' ),
				'description' => __( 'Enhanced core/code — syntax highlighting, copy, share, SEO JSON-LD.', '4wp-advanced-code' ),
			),
			'terminal' => array(
				'option'      => self::OPTION_TERMINAL,
				'label'       => __( 'Terminal', '4wp-advanced-code' ),
				'description' => __( 'Interactive CLI simulator block with JSON profile files.', '4wp-advanced-code' ),
			),
			'ide'      => array(
				'option'      => self::OPTION_IDE,
				'label'       => __( 'IDE', '4wp-advanced-code' ),
				'description' => __( 'VS Code–style lesson shell for code review training (coming soon).', '4wp-advanced-code' ),
			),
		);
	}

	/**
	 * Whether Code Block module is active.
	 */
	public static function is_code_enabled(): bool {
		$legacy = get_option( 'forwp_advanced_code_enabled', null );
		$value  = get_option( self::OPTION_CODE, null );

		if ( null === $value && null !== $legacy ) {
			return (bool) $legacy;
		}

		return null === $value ? true : (bool) $value;
	}

	/**
	 * Whether Terminal module is active.
	 */
	public static function is_terminal_enabled(): bool {
		return (bool) get_option( self::OPTION_TERMINAL, true );
	}

	/**
	 * Whether IDE module is active.
	 */
	public static function is_ide_enabled(): bool {
		return (bool) get_option( self::OPTION_IDE, false );
	}

	/**
	 * Map build/blocks folder name to module key.
	 *
	 * @param string $block_folder Folder basename under build/blocks/.
	 */
	public static function is_block_module_enabled( string $block_folder ): bool {
		switch ( $block_folder ) {
			case 'terminal':
				return self::is_terminal_enabled();
			case 'ide':
				return self::is_ide_enabled();
			default:
				return true;
		}
	}
}
