<?php
/**
 * JSON-LD for terminal challenge blocks (LearningResource + HowTo).
 *
 * TechArticle markup from 4wp-seo-helper stays separate.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Structured data for practice-case terminals.
 */
class Terminal_Seo_Handler {

	/**
	 * @var array<int, array{data: array<string, mixed>, url: string}>
	 */
	private static array $items = array();

	/**
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_footer', array( self::class, 'output_json_ld' ), 20 );
	}

	/**
	 * @param array<string, mixed> $data    Challenge profile stored on the post.
	 * @param int                  $post_id Post that renders the terminal.
	 * @return void
	 */
	public static function register( array $data, int $post_id ): void {
		if ( $post_id <= 0 || isset( self::$items[ $post_id ] ) ) {
			return;
		}

		if ( Terminal_Profiles::TYPE_CHALLENGE !== Terminal_Profiles::detect_profile_type( $data, '' ) ) {
			return;
		}

		$title = trim( (string) ( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			return;
		}

		self::$items[ $post_id ] = array(
			'data' => $data,
			'url'  => (string) get_permalink( $post_id ),
		);
	}

	/**
	 * @return void
	 */
	public static function output_json_ld(): void {
		if ( empty( self::$items ) || ! get_option( 'forwp_advanced_code_seo_enabled', true ) ) {
			return;
		}

		$graph = array();
		foreach ( self::$items as $item ) {
			foreach ( self::build_graph_nodes( $item['data'], $item['url'] ) as $node ) {
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
		$json_ld = str_replace( '</', '<\/', (string) $json_ld );

		echo '<script type="application/ld+json">';
		echo $json_ld;
		echo '</script>' . "\n";

		self::$items = array();
	}

	/**
	 * @param array<string, mixed> $data Challenge profile.
	 * @param string               $url  Canonical post URL.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_graph_nodes( array $data, string $url ): array {
		$title = trim( (string) ( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			return array();
		}

		$desc = trim( (string) ( $data['description'] ?? '' ) );
		if ( '' === $desc ) {
			$desc = trim( (string) ( $data['instructions'] ?? '' ) );
		}

		$base_id  = '' !== $url ? $url . '#forwp-terminal-challenge' : '';
		$howto_id = '' !== $url ? $url . '#forwp-terminal-howto' : '';
		$steps    = self::how_to_steps( $data );

		$how_to = array();
		if ( ! empty( $steps ) ) {
			$how_to = array(
				'@type' => 'HowTo',
				'name'  => sprintf(
					/* translators: %s: practice case title */
					__( '%s WP-CLI practice tutorial', '4wp-advanced-code' ),
					$title
				),
				'step'  => $steps,
			);

			if ( '' !== $howto_id ) {
				$how_to['@id'] = $howto_id;
			}
			if ( '' !== $base_id ) {
				$how_to['isPartOf'] = array( '@id' => $base_id );
			}
			if ( '' !== $desc ) {
				$how_to['description'] = $desc;
			}
		}

		$resource = array(
			'@type'                => 'LearningResource',
			'name'                 => sprintf(
				/* translators: %s: practice case title */
				__( 'Interactive %s tutorial', '4wp-advanced-code' ),
				$title
			),
			'learningResourceType' => 'interactive exercise',
			'teaches'              => $title,
			'inLanguage'           => get_bloginfo( 'language' ) ?: 'en',
			'isAccessibleForFree'  => true,
			'educationalUse'       => 'practice',
		);

		if ( '' !== $desc ) {
			$resource['description'] = $desc;
		}

		if ( ! empty( $how_to ) ) {
			$resource['hasPart'] = '' !== $howto_id ? array( '@id' => $howto_id ) : $how_to;
		}

		if ( '' !== $base_id ) {
			$resource['@id'] = $base_id;
			$resource['url'] = $url;
		}

		$nodes = array( $resource );
		if ( ! empty( $how_to ) ) {
			$nodes[] = $how_to;
		}

		return $nodes;
	}

	/**
	 * Article steps from content.steps, then runtime challenge steps.
	 *
	 * @param array<string, mixed> $data Challenge profile.
	 * @return array<int, array<string, mixed>>
	 */
	private static function how_to_steps( array $data ): array {
		$content = isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array();
		$steps   = isset( $content['steps'] ) && is_array( $content['steps'] ) ? $content['steps'] : array();
		$out     = self::steps_from_article( $steps );

		if ( ! empty( $out ) ) {
			return $out;
		}

		$runtime = isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : array();

		return self::steps_from_runtime( $runtime );
	}

	/**
	 * @param array<int, mixed> $steps content.steps rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function steps_from_article( array $steps ): array {
		$out = array();

		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$title = trim( (string) ( $step['title'] ?? '' ) );
			$text  = self::paragraphs_text( $step['paragraphs'] ?? array() );
			if ( '' === $title && '' === $text ) {
				continue;
			}

			$item = array(
				'@type'    => 'HowToStep',
				'position' => count( $out ) + 1,
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
	 * @param array<int, mixed> $steps Runtime challenge steps.
	 * @return array<int, array<string, mixed>>
	 */
	private static function steps_from_runtime( array $steps ): array {
		$out = array();

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$hint = trim( (string) ( $step['hint'] ?? '' ) );
			$text = trim( (string) ( $step['command_hint'] ?? '' ) );
			if ( '' === $hint && '' === $text ) {
				continue;
			}

			$item = array(
				'@type'    => 'HowToStep',
				'position' => count( $out ) + 1,
				'name'     => '' !== $hint ? $hint : sprintf(
					/* translators: %d: step number */
					__( 'Step %d', '4wp-advanced-code' ),
					$index + 1
				),
			);
			if ( '' !== $text ) {
				$item['text'] = $text;
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * @param mixed $paragraphs Paragraph list or a single string.
	 */
	private static function paragraphs_text( $paragraphs ): string {
		if ( is_string( $paragraphs ) ) {
			$paragraphs = array( $paragraphs );
		}
		if ( ! is_array( $paragraphs ) ) {
			return '';
		}

		$parts = array();
		foreach ( $paragraphs as $paragraph ) {
			if ( ! is_string( $paragraph ) ) {
				continue;
			}
			$paragraph = trim( wp_strip_all_tags( $paragraph ) );
			if ( '' !== $paragraph ) {
				$parts[] = $paragraph;
			}
		}

		return implode( ' ', $parts );
	}
}
