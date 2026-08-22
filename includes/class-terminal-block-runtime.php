<?php
/**
 * Inject compiled terminal data into block output (frontend runtime).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Embeds post meta profile JSON into Terminal block markup so the frontend
 * does not fetch from the JSON file layer.
 */
class Terminal_Block_Runtime {

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_filter( 'render_block', array( self::class, 'render_block' ), 10, 2 );
	}

	/**
	 * Resolve the post ID for a terminal block render pass.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private static function resolve_post_id( array $block ): int {
		if ( ! empty( $block['context']['postId'] ) ) {
			return (int) $block['context']['postId'];
		}

		$queried = get_queried_object_id();
		if ( $queried > 0 ) {
			return (int) $queried;
		}

		$post_id = get_the_ID();

		return $post_id ? (int) $post_id : 0;
	}

	/**
	 * @param string               $content Block HTML.
	 * @param array<string, mixed> $block   Parsed block.
	 */
	public static function render_block( string $content, array $block ): string {
		if ( 'forwp-advanced-code/terminal' !== ( $block['blockName'] ?? '' ) ) {
			return $content;
		}

		$post_id = self::resolve_post_id( $block );
		if ( ! $post_id ) {
			return $content;
		}

		$data = Terminal_Post_Meta::get_data( $post_id );
		if ( null === $data ) {
			$data = self::resolve_profile_fallback( $post_id );
		}

		if ( null === $data ) {
			return $content;
		}

		$data = self::merge_completion_from_sections( $post_id, $data );
		$profile_data = Terminal_Profiles::extract_challenge_runtime( $data );

		if ( $profile_data === [] ) {
			return $content;
		}

		$welcome = '';
		if ( ! empty( $block['attrs']['welcomeMessage'] ) && is_string( $block['attrs']['welcomeMessage'] ) ) {
			$welcome = $block['attrs']['welcomeMessage'];
		} elseif ( ! empty( $data['instructions'] ) && is_string( $data['instructions'] ) ) {
			$welcome = $data['instructions'];
		}

		$config = array(
			'source'      => 'post-meta',
			'profileData' => $profile_data,
		);

		if ( '' !== $welcome ) {
			$config['welcomeMessage'] = $welcome;
		}

		$config_json = esc_attr( wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

		if ( preg_match( '/data-forwp-config="[^"]*"/', $content ) ) {
			$content = preg_replace(
				'/data-forwp-config="[^"]*"/',
				'data-forwp-config="' . $config_json . '"',
				$content,
				1
			);
		} else {
			$content = preg_replace(
				'/(<div[^>]*class="[^"]*forwp-ac-terminal[^"]*"[^>]*)>/',
				'$1 data-forwp-config="' . $config_json . '">',
				$content,
				1
			);
		}

		if ( preg_match( '/data-profile="[^"]*"/', $content ) ) {
			$content = preg_replace( '/data-profile="[^"]*"/', 'data-profile="embedded"', $content, 1 );
		}

		if ( ! empty( $data['title'] ) && is_string( $data['title'] ) ) {
			$title = esc_html( $data['title'] );
			$content = preg_replace(
				'/(<span class="forwp-ac-terminal__title">)(.*?)(<\/span>)/',
				'$1' . $title . '$3',
				$content,
				1
			);
		}

		return $content;
	}

	/**
	 * Load challenge JSON when post meta is missing (legacy imports).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_profile_fallback( int $post_id ): ?array {
		foreach ( Terminal_Import_Registry::get_all() as $slug => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( (int) ( $entry['post_id'] ?? 0 ) === $post_id ) {
				$loaded = Terminal_Profiles::load( (string) $slug );

				return is_wp_error( $loaded ) ? null : $loaded;
			}
		}

		if ( class_exists( '\ForWP\LMS\PostTypes\PracticeCase' ) ) {
			$case_key = (string) get_post_meta( $post_id, \ForWP\LMS\PostTypes\PracticeCase::META_CASE_KEY, true );

			if ( '' !== $case_key ) {
				$candidates = array( 'challenge-' . sanitize_title( $case_key ) );

				foreach ( $candidates as $slug ) {
					$loaded = Terminal_Profiles::load( $slug );

					if ( ! is_wp_error( $loaded ) ) {
						return $loaded;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Backfill completion from section meta when terminal snapshot is stale.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Terminal profile snapshot.
	 * @return array<string, mixed>
	 */
	private static function merge_completion_from_sections( int $post_id, array $data ): array {
		if ( ! empty( $data['completion'] ) || ! class_exists( '\ForWP\LMS\Content\PracticeCaseContent' ) ) {
			return $data;
		}

		$sections = \ForWP\LMS\Content\PracticeCaseContent::get( $post_id );
		if ( ! is_array( $sections ) || empty( $sections['completion'] ) || ! is_array( $sections['completion'] ) ) {
			return $data;
		}

		$data['completion'] = $sections['completion'];

		return $data;
	}
}
