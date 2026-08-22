<?php
/**
 * Post meta storage for compiled terminal profile data (runtime source).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Terminal profile snapshot stored on posts after import.
 */
class Terminal_Post_Meta {

	public const META_DATA        = '_forwp_ac_terminal_data';
	public const META_COMPILED_AT = '_forwp_ac_terminal_compiled_at';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_meta' ), 25 );
	}

	/**
	 * Post types that support terminal import.
	 *
	 * @return array<int, string>
	 */
	public static function get_supported_post_types(): array {
		$types = array( 'post', 'page' );

		if ( post_type_exists( 'practice_case' ) ) {
			$types[] = 'practice_case';
		}

		/**
		 * Filter post types available for practice case / terminal import.
		 *
		 * @param array<int, string> $types Post type names.
		 */
		return apply_filters( 'forwp_ac_terminal_import_post_types', $types );
	}

	/**
	 * Human labels for import targets.
	 *
	 * @return array<string, string>
	 */
	public static function get_post_type_labels(): array {
		$labels = array();

		foreach ( self::get_supported_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );
			$labels[ $post_type ] = $object instanceof \WP_Post_Type
				? (string) $object->labels->singular_name
				: $post_type;
		}

		return $labels;
	}

	/**
	 * Register meta keys for supported post types.
	 */
	public static function register_meta(): void {
		foreach ( self::get_supported_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_DATA,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( self::class, 'can_edit_meta' ),
					'sanitize_callback' => array( self::class, 'sanitize_data_meta' ),
				)
			);

			register_post_meta(
				$post_type,
				self::META_COMPILED_AT,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( self::class, 'can_edit_meta' ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}
	}

	/**
	 * @param mixed  $allowed   Whether the user can add this meta key.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 */
	public static function can_edit_meta( $allowed, string $meta_key, int $object_id ): bool {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * @param mixed $value Raw meta value.
	 */
	public static function sanitize_data_meta( $value ): string {
		if ( is_array( $value ) ) {
			return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded )
			? wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			: '';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_data( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, self::META_DATA, true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Terminal profile snapshot.
	 */
	public static function set_data( int $post_id, array $data ): void {
		update_post_meta(
			$post_id,
			self::META_DATA,
			wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
		update_post_meta( $post_id, self::META_COMPILED_AT, gmdate( 'c' ) );
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public static function has_data( int $post_id ): bool {
		return null !== self::get_data( $post_id );
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public static function clear_data( int $post_id ): void {
		delete_post_meta( $post_id, self::META_DATA );
		delete_post_meta( $post_id, self::META_COMPILED_AT );
	}
}
