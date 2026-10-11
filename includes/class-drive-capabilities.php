<?php
/**
 * Drive Patterns / import mapping for 4WP Diagram (family plugin allow-list).
 *
 * Mirrors 4wp-faq / 4wp-seo-helper: wrap capability + block template + fence → block.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * 4WP Drive integration owned by Advanced Code (not Drive’s static catalog alone).
 */
class Drive_Capabilities {

	public const CAPABILITY_ID = '4wp-diagram';
	public const BLOCK_NAME    = 'forwp/diagram';
	public const PLUGIN_SLUG   = '4wp-advanced-code';
	public const PLUGIN_FILE   = '4wp-advanced-code/4wp-advanced-code.php';

	/**
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'forwp_drive_family_plugins', array( self::class, 'register_family_plugin' ) );
		add_filter( 'forwp_drive_wrap_capabilities', array( self::class, 'register_wrap' ) );
		add_filter( 'forwp_drive_block_templates', array( self::class, 'register_template' ) );
		add_filter( 'forwp_drive_markdown_code_fence', array( self::class, 'map_fence' ), 10, 3 );
		add_filter( 'forwp_drive_import_body_html', array( self::class, 'map_pre_code_html' ), 10, 2 );
	}

	/**
	 * Catalog card when this plugin is inactive / for Patterns Integrations.
	 *
	 * @param array<string, array<string, mixed>> $plugins Slug => definition.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_family_plugin( array $plugins ): array {
		$plugins[ self::PLUGIN_SLUG ] = array(
			'slug'        => self::PLUGIN_SLUG,
			'label'       => '4WP Advanced Code',
			'file'        => self::PLUGIN_FILE,
			'uri'         => 'https://4wp.dev/plugin/4wp-advanced-code/',
			'wporg'       => '',
			'description' => __( 'Mermaid fences → 4WP Diagram block (SVG/PNG) on import.', '4wp-advanced-code' ),
			'wrap_labels' => array( '4WP Diagram' ),
		);

		return $plugins;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function export(): array {
		if ( ! Modules::is_diagrams_enabled() ) {
			return array();
		}

		return array(
			array(
				'id'            => self::CAPABILITY_ID,
				'origin'        => 'family',
				'plugin'        => self::PLUGIN_SLUG,
				'block'         => self::BLOCK_NAME,
				'label'         => __( '4WP Diagram', '4wp-advanced-code' ),
				'maps'          => array(),
				'wrap'          => 'marker',
				'match'         => array(
					'type'    => 'fence',
					'level'   => 0,
					'pattern' => 'mermaid',
				),
				'heading_seeds' => '',
				'prompt'        => __( 'Markdown: ```mermaid … ``` becomes forwp/diagram. Google Doc monospace that starts with flowchart / sequenceDiagram / stateDiagram is mapped the same way. Diagrams module must be On.', '4wp-advanced-code' ),
				'auto'          => true,
				'manual'        => true,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $items Drive catalog.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_wrap( array $items ): array {
		foreach ( self::export() as $row ) {
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * @param array<string, array<string, mixed>> $templates Template id => definition.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_template( array $templates ): array {
		$templates[ self::CAPABILITY_ID ] = array(
			'label'            => __( '4WP Diagram', '4wp-advanced-code' ),
			'description'      => __( 'Maps ```mermaid fences (and Mermaid-looking monospace) to the forwp/diagram block.', '4wp-advanced-code' ),
			'recipe_type'      => 'mermaid-fence',
			'block'            => self::BLOCK_NAME,
			'requires_plugins' => array( self::PLUGIN_FILE ),
			'available'        => Modules::is_diagrams_enabled(),
			'default_enabled'  => true,
			'doc_hint'         => __( 'Markdown: ```mermaid … ```. Optional %% steps: A,B,C inside the fence for front-end playback.', '4wp-advanced-code' ),
		);

		return $templates;
	}

	/**
	 * ```mermaid → <!-- wp:forwp/diagram {"code":"..."} /-->
	 *
	 * @param string $html     Default <pre><code> HTML.
	 * @param string $code     Fence body.
	 * @param string $language Fence language tag.
	 */
	public static function map_fence( string $html, string $code, string $language ): string {
		if ( ! Modules::is_diagrams_enabled() ) {
			return $html;
		}

		$language = strtolower( trim( $language ) );
		if ( 'mermaid' !== $language && ! self::looks_like_mermaid( $code ) ) {
			return $html;
		}

		if ( 'mermaid' !== $language && '' !== $language ) {
			return $html;
		}

		$block = self::serialize_diagram_block( $code );
		return '' !== $block ? $block : $html;
	}

	/**
	 * After recipes: turn leftover Mermaid <pre><code> into diagram blocks.
	 *
	 * @param string               $body_html Import body HTML.
	 * @param array<string, mixed> $context   Import context (unused).
	 */
	public static function map_pre_code_html( string $body_html, $context = array() ): string {
		unset( $context );

		if ( ! Modules::is_diagrams_enabled() || '' === $body_html ) {
			return $body_html;
		}

		if ( false !== strpos( $body_html, 'wp:forwp/diagram' ) ) {
			return $body_html;
		}

		return (string) preg_replace_callback(
			'/<pre(?:\s[^>]*)?>\s*<code(?:\s[^>]*)?>([\s\S]*?)<\/code>\s*<\/pre>/i',
			static function ( array $matches ): string {
				$code = html_entity_decode( wp_strip_all_tags( $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$code = trim( $code );
				if ( ! self::looks_like_mermaid( $code ) ) {
					return $matches[0];
				}
				$block = self::serialize_diagram_block( $code );
				return '' !== $block ? $block : $matches[0];
			},
			$body_html
		);
	}

	/**
	 * @param string $code Mermaid source.
	 */
	public static function serialize_diagram_block( string $code ): string {
		$code = trim( $code );
		if ( '' === $code ) {
			return '';
		}

		$attrs = array( 'code' => $code );

		// Must use WP comment escaping — Mermaid arrows (`-->`) would otherwise
		// terminate the HTML comment early and eat the following ```php fence.
		if ( function_exists( 'serialize_block' ) ) {
			return serialize_block(
				array(
					'blockName'    => self::BLOCK_NAME,
					'attrs'        => $attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		}

		if ( function_exists( 'serialize_block_attributes' ) ) {
			$encoded = serialize_block_attributes( $attrs );
			return sprintf( '<!-- wp:%1$s %2$s/-->', self::BLOCK_NAME, $encoded );
		}

		$json = wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}
		$json = strtr(
			$json,
			array(
				'\\\\' => '\\u005c',
				'--'   => '\\u002d\\u002d',
				'<'    => '\\u003c',
				'>'    => '\\u003e',
				'&'    => '\\u0026',
				'\\"'  => '\\u0022',
			)
		);

		return sprintf( '<!-- wp:%1$s %2$s /-->', self::BLOCK_NAME, $json );
	}

	/**
	 * Heuristic for monospace blocks without a language tag.
	 */
	public static function looks_like_mermaid( string $code ): bool {
		$code = ltrim( $code );
		if ( '' === $code ) {
			return false;
		}

		return (bool) preg_match(
			'/^(flowchart|graph|sequenceDiagram|classDiagram|stateDiagram(?:-v2)?|erDiagram|journey|gantt|pie|mindmap|timeline|quadrantChart|requirementDiagram|C4Context|gitGraph)\b/m',
			$code
		);
	}
}
