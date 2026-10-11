<?php
/**
 * Diagram ImageObject JSON-LD (nests into TechArticle when available).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Collects diagram media and emits schema.org ImageObject.
 */
class Diagram_Seo {

	/**
	 * @var list<array<string, mixed>>
	 */
	private static array $items = array();

	/**
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'forwp_seo_techarticle_schema', array( self::class, 'inject_into_techarticle' ), 20, 2 );
		add_action( 'wp_footer', array( self::class, 'output_standalone_json_ld' ), 25 );
	}

	/**
	 * @param array<string, mixed> $item Diagram meta for schema.
	 * @return void
	 */
	public static function register( array $item ): void {
		if ( ! Modules::is_diagrams_enabled() || ! get_option( 'forwp_advanced_code_seo_enabled', true ) ) {
			return;
		}

		$png_id = isset( $item['png_id'] ) ? absint( $item['png_id'] ) : 0;
		$url    = $png_id > 0 ? (string) wp_get_attachment_url( $png_id ) : '';
		if ( '' === $url ) {
			return;
		}

		self::$items[] = array(
			'@type'       => 'ImageObject',
			'contentUrl'  => $url,
			'url'         => $url,
			'caption'     => (string) ( $item['caption'] ?? '' ),
			'description' => (string) ( $item['description'] ?? '' ),
			'name'        => (string) ( $item['title'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $schema TechArticle schema.
	 * @param \WP_Post             $post   Post.
	 * @return array<string, mixed>
	 */
	public static function inject_into_techarticle( array $schema, $post ): array {
		unset( $post );

		if ( empty( self::$items ) ) {
			return $schema;
		}

		$parts = isset( $schema['hasPart'] ) && is_array( $schema['hasPart'] )
			? $schema['hasPart']
			: array();

		foreach ( self::$items as $image ) {
			$node = self::with_optional_credit( $image );
			$parts[] = $node;
		}

		$schema['hasPart'] = $parts;
		self::$items       = array();

		return $schema;
	}

	/**
	 * Fallback when the page is not a TechArticle.
	 *
	 * @return void
	 */
	public static function output_standalone_json_ld(): void {
		if ( empty( self::$items ) || ! get_option( 'forwp_advanced_code_seo_enabled', true ) ) {
			return;
		}

		$graph = array();
		foreach ( self::$items as $image ) {
			$graph[] = self::with_optional_credit( $image );
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$json = str_replace( '</', '<\/', (string) $json );

		echo '<script type="application/ld+json">';
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</script>' . "\n";

		self::$items = array();
	}

	/**
	 * @param array<string, mixed> $image ImageObject node.
	 * @return array<string, mixed>
	 */
	private static function with_optional_credit( array $image ): array {
		foreach ( array( 'caption', 'description', 'name' ) as $key ) {
			if ( empty( $image[ $key ] ) ) {
				unset( $image[ $key ] );
			}
		}

		if ( ! Diagram_Settings::is_credit_enabled() ) {
			return $image;
		}

		$name = Diagram_Settings::site_credit_text();
		if ( '' === $name ) {
			return $image;
		}

		$image['creator']         = array(
			'@type' => 'Organization',
			'name'  => $name,
		);
		$image['creditText']      = $name;
		$image['copyrightNotice'] = $name;

		return $image;
	}
}
