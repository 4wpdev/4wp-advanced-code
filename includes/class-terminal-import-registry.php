<?php
/**
 * Tracks where a JSON profile was imported (source-side link only).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Profile slug → destination post mapping (stored in options, not on the post).
 */
class Terminal_Import_Registry {

	public const OPTION = 'forwp_ac_terminal_import_registry';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all(): array {
		$map = get_option( self::OPTION, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * @param string $profile_slug Profile file slug.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $profile_slug ): ?array {
		$safe = Terminal_Profiles::sanitize_slug( $profile_slug );
		$map  = self::get_all();

		if ( ! isset( $map[ $safe ] ) || ! is_array( $map[ $safe ] ) ) {
			return null;
		}

		$entry   = $map[ $safe ];
		$post_id = isset( $entry['post_id'] ) ? absint( $entry['post_id'] ) : 0;
		$type    = isset( $entry['post_type'] ) && is_string( $entry['post_type'] ) ? $entry['post_type'] : '';

		if ( $post_id <= 0 || ! self::is_valid_destination( $post_id, $type ) ) {
			self::clear( $profile_slug );

			return null;
		}

		return self::refresh_entry( $entry, $post_id );
	}

	/**
	 * Whether a registry destination post still exists and is usable.
	 *
	 * @param int    $post_id   Destination post ID.
	 * @param string $post_type Expected post type, or empty to skip type check.
	 */
	public static function is_valid_destination( int $post_id, string $post_type = '' ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( '' !== $post_type && $post->post_type !== $post_type ) {
			return false;
		}

		return ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );
	}

	/**
	 * Record or refresh import destination for a profile slug.
	 *
	 * @param string $profile_slug Source profile slug.
	 * @param int    $post_id      Destination post ID.
	 * @return array<string, mixed>
	 */
	public static function record( string $profile_slug, int $post_id ): array {
		$safe = Terminal_Profiles::sanitize_slug( $profile_slug );
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$entry = self::refresh_entry(
			array(
				'imported_at' => gmdate( 'c' ),
			),
			$post_id
		);

		$map         = self::get_all();
		$map[ $safe ] = $entry;
		update_option( self::OPTION, $map, false );

		return $entry;
	}

	/**
	 * Refresh cached registry fields from the live post.
	 *
	 * @param array<string, mixed> $entry   Existing registry row.
	 * @param int                  $post_id Destination post ID.
	 * @return array<string, mixed>
	 */
	private static function refresh_entry( array $entry, int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return $entry;
		}

		$edit_url = get_edit_post_link( $post_id, 'raw' );

		$entry['post_id']   = $post_id;
		$entry['post_type'] = $post->post_type;
		$entry['title']     = get_the_title( $post_id );
		$entry['edit_url']  = is_string( $edit_url ) ? $edit_url : '';
		$entry['permalink'] = (string) get_permalink( $post_id );

		if ( empty( $entry['imported_at'] ) ) {
			$entry['imported_at'] = gmdate( 'c' );
		}

		return $entry;
	}

	/**
	 * @param string $profile_slug Profile slug.
	 */
	public static function clear( string $profile_slug ): void {
		$safe = Terminal_Profiles::sanitize_slug( $profile_slug );
		$map  = self::get_all();

		unset( $map[ $safe ] );
		update_option( self::OPTION, $map, false );
	}
}
