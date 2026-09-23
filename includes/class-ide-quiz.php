<?php
/**
 * IDE quiz questions keyed by hook slug. Plugin data, not theme SEO JSON.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Loads think-and-click quiz items from assets/data/ide-quiz/{slug}.json.
 */
class Ide_Quiz {

	/**
	 * Bundled quiz directory.
	 */
	public static function dir(): string {
		return FORWP_ADVANCED_CODE_PATH . 'assets/data/ide-quiz';
	}

	/**
	 * Normalized questions for one hook. Empty if none.
	 *
	 * @param string $slug Hook name.
	 * @return array<int, array<string, mixed>>
	 */
	public static function questions_for( string $slug ): array {
		$slug = Ide_Catalog::sanitize_slug( $slug );
		if ( '' === $slug || 0 === strpos( $slug, '_' ) ) {
			return array();
		}

		/**
		 * Override quiz payload for a hook (return an array of questions or a { questions: [] } object).
		 *
		 * @param array<string, mixed>|null $override Null to use JSON files.
		 * @param string                    $slug     Hook slug.
		 */
		$override = apply_filters( 'forwp_ac_ide_quiz', null, $slug );
		if ( is_array( $override ) ) {
			return self::normalize( $override, $slug );
		}

		$path = self::file_path( $slug );
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$raw  = file_get_contents( $path );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) ) {
			return array();
		}

		return self::normalize( $data, $slug );
	}

	/**
	 * @param string $slug Hook name.
	 */
	public static function file_path( string $slug ): string {
		$slug = Ide_Catalog::sanitize_slug( $slug );
		$base = self::dir() . '/' . $slug . '.json';

		/**
		 * Filter the quiz JSON path for a slug.
		 *
		 * @param string $base Default plugin path.
		 * @param string $slug Hook slug.
		 */
		$filtered = apply_filters( 'forwp_ac_ide_quiz_path', $base, $slug );

		return is_string( $filtered ) && '' !== $filtered ? $filtered : $base;
	}

	/**
	 * @param array<string, mixed> $data Raw file or filter payload.
	 * @param string               $slug Hook slug.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize( array $data, string $slug ): array {
		$list = array();
		if ( isset( $data['questions'] ) && is_array( $data['questions'] ) ) {
			$list = $data['questions'];
		} elseif ( isset( $data[0] ) ) {
			$list = $data;
		}

		$out = array();
		foreach ( $list as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = trim( (string) ( $item['question'] ?? $item['q'] ?? '' ) );
			$options  = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array();
			if ( '' === $question || count( $options ) < 2 ) {
				continue;
			}

			$normalized_options = array();
			foreach ( $options as $opt_i => $option ) {
				if ( is_string( $option ) ) {
					$normalized_options[] = array(
						'id'      => 'opt-' . $opt_i,
						'label'   => $option,
						'correct' => false,
					);
					continue;
				}
				if ( ! is_array( $option ) ) {
					continue;
				}
				$label = trim( (string) ( $option['label'] ?? $option['text'] ?? '' ) );
				if ( '' === $label ) {
					continue;
				}
				$id = trim( (string) ( $option['id'] ?? '' ) );
				if ( '' === $id ) {
					$id = 'opt-' . $opt_i;
				}
				$normalized_options[] = array(
					'id'      => $id,
					'label'   => $label,
					'correct' => ! empty( $option['correct'] ),
				);
			}

			if ( count( $normalized_options ) < 2 ) {
				continue;
			}

			$has_correct = false;
			foreach ( $normalized_options as $option ) {
				if ( ! empty( $option['correct'] ) ) {
					$has_correct = true;
					break;
				}
			}
			if ( ! $has_correct ) {
				continue;
			}

			$feedback = array();
			if ( isset( $item['feedback'] ) && is_array( $item['feedback'] ) ) {
				$feedback = $item['feedback'];
			}

			$id = trim( (string) ( $item['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = $slug . '-' . ( $index + 1 );
			}

			$out[] = array(
				'id'       => $id,
				'question' => $question,
				'options'  => $normalized_options,
				'feedback' => array(
					'correct'   => trim( (string) ( $feedback['correct'] ?? '' ) ),
					'incorrect' => trim( (string) ( $feedback['incorrect'] ?? '' ) ),
				),
			);
		}

		return $out;
	}
}
