<?php
/**
 * JSON-LD for code blocks.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * SEO and structured data functionality.
 */
class Seo_Handler {

	/**
	 * Collected code blocks for JSON-LD.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $code_blocks = array();

	/**
	 * Initialize SEO handler.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'output_json_ld' ), 20 );
		add_filter( 'the_content', array( self::class, 'collect_code_blocks' ), 5 );
	}

	/**
	 * Collect code blocks while rendering post content.
	 *
	 * @param string $content Post content.
	 */
	public static function collect_code_blocks( string $content ): string {
		if ( ! is_singular() || ! get_option( 'forwp_advanced_code_seo_enabled', true ) ) {
			return $content;
		}

		self::walk_blocks( parse_blocks( $content ) );

		return $content;
	}

	/**
	 * Recursively walk parsed blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	private static function walk_blocks( array $blocks ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( 'core/code' === $name || 'forwp/advanced-code' === $name ) {
				self::process_code_block( $block );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_blocks( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Process individual code block for SEO.
	 *
	 * @param array<string, mixed> $block Block data.
	 */
	private static function process_code_block( array $block ): void {
		$attrs = $block['attrs'] ?? array();

		if ( ! ( $attrs['seoEnabled'] ?? true ) ) {
			return;
		}

		if ( 'forwp/advanced-code' === ( $block['blockName'] ?? '' ) ) {
			$code = (string) ( $attrs['content'] ?? '' );
		} else {
			$code = wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) );
		}

		$language = $attrs['language'] ?? 'auto';
		if ( 'auto' === $language ) {
			$language = self::detect_language( $code );
		}

		$structured_data = array(
			'@type'               => 'SoftwareSourceCode',
			'codeRepository'      => get_permalink(),
			'codeSampleType'      => $attrs['seoType'] ?? 'example',
			'programmingLanguage' => $language,
			'text'                => $code,
		);

		if ( ! empty( $attrs['seoTitle'] ) ) {
			$structured_data['name'] = sanitize_text_field( $attrs['seoTitle'] );
		}

		if ( ! empty( $attrs['seoDescription'] ) ) {
			$structured_data['description'] = sanitize_text_field( $attrs['seoDescription'] );
		}

		$author = get_the_author_meta( 'display_name' );
		if ( $author ) {
			$structured_data['author'] = array(
				'@type' => 'Person',
				'name'  => $author,
			);
		}

		self::$code_blocks[] = $structured_data;
	}

	/**
	 * Output JSON-LD structured data in head.
	 */
	public static function output_json_ld(): void {
		if ( empty( self::$code_blocks ) || ! get_option( 'forwp_advanced_code_seo_enabled', true ) ) {
			return;
		}

		$json_ld = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'WebPage',
			'mainEntity' => self::$code_blocks,
		);

		echo '<script type="application/ld+json">';
		echo wp_json_encode( $json_ld );
		echo '</script>' . "\n";

		self::$code_blocks = array();
	}

	/**
	 * Detect programming language from code content.
	 */
	public static function detect_language( string $code ): string {
		if ( str_contains( $code, '<?php' ) ) {
			return 'php';
		}

		if ( preg_match( '/^\s*\{[\s\S]*"[\w-]+"\s*:/m', $code ) ) {
			return 'json';
		}

		if ( preg_match( '/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE)\s/i', $code ) ) {
			return 'sql';
		}

		if ( preg_match( '/^\s*(function|const|let|var|import|export)\s/m', $code ) ) {
			return 'javascript';
		}

		return 'plaintext';
	}
}
