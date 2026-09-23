<?php
/**
 * JSON-LD for IDE wizard blocks (LearningResource + HowTo).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Structured data for hook practice wizards.
 */
class Ide_Seo_Handler {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static array $bootstraps = array();

	/**
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_footer', array( self::class, 'output_json_ld' ), 20 );
	}

	/**
	 * @param array<string, mixed> $bootstrap Output from Ide_Catalog::ssr_bootstrap().
	 * @return void
	 */
	public static function register( array $bootstrap ): void {
		if ( empty( $bootstrap['wizard'] ) || empty( $bootstrap['step'] ) ) {
			return;
		}

		self::$bootstraps[] = $bootstrap;
	}

	/**
	 * @return void
	 */
	public static function output_json_ld(): void {
		if ( empty( self::$bootstraps ) || ! get_option( 'forwp_advanced_code_seo_enabled', true ) ) {
			return;
		}

		$graph = array();
		foreach ( self::$bootstraps as $bootstrap ) {
			$nodes = self::build_graph_nodes( $bootstrap );
			foreach ( $nodes as $node ) {
				$graph[] = $node;
			}
		}

		if ( empty( $graph ) ) {
			return;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		$json_ld = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		// Prevent HTML parser from treating strings like "</body>" or "</script>"
		// inside JSON-LD as real closing tags (breaks the page/footer DOM).
		$json_ld = str_replace( '</', '<\/', (string) $json_ld );

		echo '<script type="application/ld+json">';
		echo $json_ld;
		echo '</script>' . "\n";

		self::$bootstraps = array();
	}

	/**
	 * @param array<string, mixed> $bootstrap Bootstrap payload.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_graph_nodes( array $bootstrap ): array {
		$wizard = $bootstrap['wizard'];
		if ( ! is_array( $wizard ) ) {
			return array();
		}

		$step = $bootstrap['step'];
		if ( ! is_array( $step ) ) {
			return array();
		}

		$name = (string) ( $wizard['name'] ?? $wizard['slug'] ?? '' );
		if ( '' === $name ) {
			return array();
		}

		$type = 'filter' === ( $bootstrap['type'] ?? 'action' ) ? 'filter' : 'action';
		$desc = trim( (string) ( $step['body'] ?? '' ) );
		if ( '' === $desc && ! empty( $step['tagline'] ) ) {
			$desc = trim( (string) $step['tagline'] );
		}

		$url       = self::current_url();
		$base_id   = '' !== $url ? $url . '#forwp-ide-wizard' : '';
		$howto_id  = '' !== $url ? $url . '#forwp-ide-howto' : '';
		$code_id   = '' !== $url ? $url . '#forwp-ide-example-code' : '';

		$how_to = array(
			'@type' => 'HowTo',
			'name'  => sprintf(
				/* translators: %s: hook name */
				__( '%s WordPress hook practice tutorial', '4wp-advanced-code' ),
				$name
			),
			'step'  => self::how_to_steps( $wizard ),
		);

		if ( '' !== $howto_id ) {
			$how_to['@id']      = $howto_id;
			$how_to['isPartOf'] = array( '@id' => $base_id );
		}

		if ( '' !== $desc ) {
			$how_to['description'] = $desc;
		}

		$resource = array(
			'@type'                => 'LearningResource',
			'name'                 => sprintf(
				/* translators: %s: hook name */
				__( 'Interactive %s tutorial', '4wp-advanced-code' ),
				$name
			),
			'description'          => $desc,
			'learningResourceType' => 'interactive exercise',
			'teaches'              => sprintf(
				/* translators: 1: hook name, 2: action or filter */
				__( 'WordPress %1$s %2$s hook', '4wp-advanced-code' ),
				$name,
				$type
			),
			'inLanguage'           => get_bloginfo( 'language' ) ?: 'en',
			'isAccessibleForFree'  => true,
			'educationalUse'       => 'practice',
			'hasPart'              => '' !== $howto_id ? array( '@id' => $howto_id ) : $how_to,
		);

		if ( '' !== $base_id ) {
			$resource['@id']  = $base_id;
			$resource['url']  = $url;
		}

		$nodes = array( $resource, $how_to );

		$example = trim( (string) ( $bootstrap['example_code'] ?? '' ) );
		if ( '' !== $example ) {
			$code_node = array(
				'@type'               => 'SoftwareSourceCode',
				'name'                => sprintf(
					/* translators: %s: hook name */
					__( '%s hook code example', '4wp-advanced-code' ),
					$name
				),
				'programmingLanguage' => 'PHP',
				'codeSampleType'      => 'code snippet',
				'description'         => $example,
			);

			if ( '' !== $code_id ) {
				$code_node['@id']      = $code_id;
				$code_node['isPartOf'] = array( '@id' => $base_id );
				$resource['hasPart']   = array(
					array( '@id' => $howto_id ),
					array( '@id' => $code_id ),
				);
			}

			$nodes[] = $code_node;
			$nodes[0] = $resource;
		}

		return $nodes;
	}

	/**
	 * @param array<string, mixed> $wizard Wizard payload.
	 * @return array<int, array<string, string>>
	 */
	private static function how_to_steps( array $wizard ): array {
		$steps = isset( $wizard['steps'] ) && is_array( $wizard['steps'] ) ? $wizard['steps'] : array();
		$out   = array();

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$title = trim( (string) ( $step['title'] ?? '' ) );
			$text  = trim( (string) ( $step['body'] ?? '' ) );
			if ( '' === $title && '' === $text ) {
				continue;
			}

			$item = array(
				'@type'    => 'HowToStep',
				'position' => $index + 1,
			);

			if ( '' !== $title ) {
				$item['name'] = $title;
			}
			if ( '' !== $text ) {
				$item['text'] = $text;
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * @return string
	 */
	private static function current_url(): string {
		if ( class_exists( '\FourWPDev_Hooks_Catalog' ) ) {
			$hook = \FourWPDev_Hooks_Catalog::current();
			if ( is_array( $hook ) ) {
				return (string) \FourWPDev_Hooks_Catalog::permalink( $hook );
			}
		}

		global $wp;
		if ( isset( $wp ) && $wp instanceof \WP ) {
			return home_url( add_query_arg( array(), $wp->request ) );
		}

		return '';
	}
}
