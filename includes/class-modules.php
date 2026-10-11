<?php
/**
 * Plugin modules: Code Block, Terminal, IDE, Diagrams.
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
	public const OPTION_DIAGRAMS = 'forwp_ac_module_diagrams';

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
				'description' => __( 'VS Code–style hook wizard: one tour per hook, practice add_action / add_filter.', '4wp-advanced-code' ),
			),
			'diagrams' => array(
				'option'      => self::OPTION_DIAGRAMS,
				'label'       => __( 'Diagrams', '4wp-advanced-code' ),
				'description' => __( 'Mermaid diagrams: live editor, inline SVG, PNG for SEO, optional step playback.', '4wp-advanced-code' ),
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
		self::maybe_unlock_ide();

		$value = get_option( self::OPTION_IDE, null );

		return null === $value ? true : (bool) $value;
	}

	/**
	 * Whether Diagrams module is active.
	 */
	public static function is_diagrams_enabled(): bool {
		$value = get_option( self::OPTION_DIAGRAMS, null );

		return null === $value ? true : (bool) $value;
	}

	/**
	 * The Settings IDE checkbox used to be disabled, so saving the form stored 0.
	 * Unlock once now that the block exists.
	 */
	private static function maybe_unlock_ide(): void {
		if ( get_option( 'forwp_ac_ide_unlocked', false ) ) {
			return;
		}

		update_option( self::OPTION_IDE, true );
		update_option( 'forwp_ac_ide_unlocked', '1' );
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
			case 'diagram':
				return self::is_diagrams_enabled();
			default:
				return true;
		}
	}
}
