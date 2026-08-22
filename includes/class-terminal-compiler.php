<?php
/**
 * Import challenge JSON profiles into posts (runtime = post meta + blocks).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Compiles a terminal profile file into a post. The post stores the full profile
 * snapshot in meta; block content follows the practice case TPL structure.
 */
class Terminal_Compiler {

	/**
	 * Import a challenge profile into a post.
	 *
	 * @param string               $profile_slug Source JSON profile slug.
	 * @param string               $post_type    Target post type.
	 * @param array<string, mixed> $args         force, post_id, post_status.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function import( string $profile_slug, string $post_type, array $args = array() ) {
		$slug = Terminal_Profiles::sanitize_slug( $profile_slug );
		if ( '' === $slug ) {
			return new \WP_Error(
				'forwp_ac_terminal_import_invalid_slug',
				__( 'Invalid profile slug.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $post_type, Terminal_Post_Meta::get_supported_post_types(), true ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_import_invalid_post_type',
				__( 'This post type cannot receive a terminal import.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$data = Terminal_Profiles::load( $slug );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( Terminal_Profiles::TYPE_CHALLENGE !== Terminal_Profiles::detect_profile_type( $data, $slug ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_import_not_challenge',
				__( 'Only challenge (practice case) profiles can be imported with this action.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$validated = Terminal_Profiles::validate_challenge( $data, $slug );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$force       = ! empty( $args['force'] );
		$target_id   = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		$post_status = isset( $args['post_status'] ) && is_string( $args['post_status'] )
			? $args['post_status']
			: 'publish';

		if ( ! $target_id ) {
			$registry = Terminal_Import_Registry::get( $slug );
			if ( $registry && ! empty( $registry['post_id'] ) ) {
				$target_id = (int) $registry['post_id'];
			}
		}

		if ( $target_id && ! Terminal_Import_Registry::is_valid_destination( $target_id, $post_type ) ) {
			Terminal_Import_Registry::clear( $slug );
			$target_id = 0;
		}

		if ( $target_id && ! $force ) {
			$registry = Terminal_Import_Registry::get( $slug );
			$edit_url = is_array( $registry ) && ! empty( $registry['edit_url'] ) ? (string) $registry['edit_url'] : '';

			return new \WP_Error(
				'forwp_ac_terminal_import_exists',
				__( 'This profile was already imported. Enable “Update existing” to overwrite the destination post.', '4wp-advanced-code' ),
				array(
					'status'   => 409,
					'post_id'  => $target_id,
					'edit_url' => $edit_url,
					'registry' => $registry,
				)
			);
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_import_forbidden',
				__( 'You are not allowed to create or edit posts in the selected post type.', '4wp-advanced-code' ),
				array( 'status' => 403 )
			);
		}

		$postarr = self::build_post_array( $data, $post_type, $post_status, $target_id );
		$post_id = $target_id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		Terminal_Post_Meta::set_data( $post_id, $data );
		self::apply_post_type_meta( $post_id, $post_type, $data );
		self::apply_taxonomies( $post_id, $post_type, $data );

		$registry = Terminal_Import_Registry::record( $slug, $post_id );

		/**
		 * Fires after a terminal profile is imported into a post.
		 *
		 * @param int                  $post_id      Destination post ID.
		 * @param string               $profile_slug Source profile slug.
		 * @param array<string, mixed> $data         Profile snapshot written to post meta.
		 * @param string               $post_type    Destination post type.
		 */
		do_action( 'forwp_ac_terminal_profile_imported', $post_id, $slug, $data, $post_type );

		$edit_url = get_edit_post_link( $post_id, 'raw' );

		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'post_type'    => $post_type,
			'edit_url'     => is_string( $edit_url ) ? $edit_url : '',
			'permalink'    => (string) get_permalink( $post_id ),
			'registry'     => $registry,
			'profile_slug' => $slug,
			'updated'      => (bool) $target_id,
		);
	}

	/**
	 * @param array<string, mixed> $data        Profile JSON.
	 * @param string               $post_type   Target post type.
	 * @param string               $post_status Post status.
	 * @param int                  $post_id     Existing post ID for updates.
	 * @return array<string, mixed>
	 */
	private static function build_post_array( array $data, string $post_type, string $post_status, int $post_id ): array {
		$title   = isset( $data['title'] ) && is_string( $data['title'] ) ? $data['title'] : __( 'Practice case', '4wp-advanced-code' );
		$excerpt = self::build_excerpt( $data, $post_type );

		$postarr = array(
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => self::build_post_content( $data, $post_type ),
			'post_status'  => $post_status,
			'post_type'    => $post_type,
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
		} else {
			$postarr['post_name'] = self::build_post_name( $data, $post_type );
		}

		return $postarr;
	}

	/**
	 * @param array<string, mixed> $data      Profile JSON.
	 * @param string               $post_type Target post type.
	 */
	private static function build_excerpt( array $data, string $post_type ): string {
		if ( 'practice_case' === $post_type && class_exists( '\ForWP\LMS\Content\PracticeCaseTemplate' ) ) {
			return \ForWP\LMS\Content\PracticeCaseTemplate::buildExcerpt( $data );
		}

		return isset( $data['description'] ) && is_string( $data['description'] ) ? $data['description'] : '';
	}

	/**
	 * @param array<string, mixed> $data      Profile JSON.
	 * @param string               $post_type Target post type.
	 */
	private static function build_post_name( array $data, string $post_type ): string {
		$case_key = isset( $data['slug'] ) && is_string( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';

		if ( 'practice_case' === $post_type && '' !== $case_key ) {
			return sanitize_title( 'practice-case-' . $case_key );
		}

		$title = isset( $data['title'] ) && is_string( $data['title'] ) ? $data['title'] : 'practice-case';

		return sanitize_title( $title );
	}

	/**
	 * Build post content for import via practice case TPL.
	 *
	 * @param array<string, mixed> $data      Profile JSON.
	 * @param string               $post_type Target post type.
	 */
	private static function build_post_content( array $data, string $post_type ): string {
		if ( 'practice_case' === $post_type && class_exists( '\ForWP\LMS\Content\PracticeCaseTemplate' ) ) {
			return \ForWP\LMS\Content\PracticeCaseTemplate::buildPostShellFromImport( $data );
		}

		return self::build_challenge_content( $data );
	}

	/**
	 * Fallback Gutenberg content for non-practice_case imports.
	 *
	 * @param array<string, mixed> $data Profile JSON.
	 */
	public static function build_challenge_content( array $data ): string {
		if ( class_exists( '\ForWP\LMS\Content\PracticeCaseTemplate' ) ) {
			return \ForWP\LMS\Content\PracticeCaseTemplate::buildContent( $data );
		}

		$welcome = isset( $data['instructions'] ) && is_string( $data['instructions'] )
			? $data['instructions']
			: ( isset( $data['description'] ) && is_string( $data['description'] ) ? $data['description'] : '' );

		$terminal_attrs = wp_json_encode(
			array(
				'profile'        => 'embedded',
				'welcomeMessage' => $welcome,
				'className'      => 'forwp-practice-case__terminal',
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return sprintf( '<!-- wp:forwp-advanced-code/terminal %s /-->', $terminal_attrs );
	}

	/**
	 * @param int                  $post_id   Post ID.
	 * @param string               $post_type Post type.
	 * @param array<string, mixed> $data      Profile JSON.
	 */
	private static function apply_post_type_meta( int $post_id, string $post_type, array $data ): void {
		if ( 'practice_case' !== $post_type ) {
			return;
		}

		if ( class_exists( '\ForWP\LMS\Content\PracticeCaseTemplate' ) ) {
			\ForWP\LMS\Content\PracticeCaseTemplate::applyMeta( $post_id, $data );
			\ForWP\LMS\Content\PracticeCaseTemplate::saveSections( $post_id, $data );
			return;
		}

		if ( ! class_exists( '\ForWP\LMS\PostTypes\PracticeCase' ) ) {
			return;
		}

		$practice_case = \ForWP\LMS\PostTypes\PracticeCase::class;
		$case_key      = isset( $data['slug'] ) && is_string( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';

		if ( '' !== $case_key ) {
			update_post_meta( $post_id, $practice_case::META_CASE_KEY, $case_key );
		}

		delete_post_meta( $post_id, $practice_case::META_TERMINAL_PROFILE );
	}

	/**
	 * @param int                  $post_id   Post ID.
	 * @param string               $post_type Post type.
	 * @param array<string, mixed> $data      Profile JSON.
	 */
	private static function apply_taxonomies( int $post_id, string $post_type, array $data ): void {
		if ( 'practice_case' !== $post_type || ! class_exists( '\ForWP\LMS\Taxonomies\PracticeCaseTaxonomies' ) ) {
			return;
		}

		$taxonomies = class_exists( '\ForWP\LMS\Content\PracticeCaseTemplate' )
			? \ForWP\LMS\Content\PracticeCaseTemplate::resolveTaxonomies( $data )
			: array();

		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy => $term_slug ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_string( $term_slug ) || '' === $term_slug ) {
				continue;
			}

			$term = get_term_by( 'slug', $term_slug, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy, false );
			}
		}

		self::maybe_refresh_practice_post_slug( $post_id );
	}

	/**
	 * Regenerate flat practice case URL after taxonomy terms are assigned.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function maybe_refresh_practice_post_slug( int $post_id ): void {
		if ( ! class_exists( '\ForWP\LMS\PostTypes\PracticeCase' ) || ! class_exists( '\ForWP\LMS\Taxonomies\PracticeCaseTaxonomies' ) ) {
			return;
		}

		$practice_case = \ForWP\LMS\PostTypes\PracticeCase::class;
		$taxonomies    = \ForWP\LMS\Taxonomies\PracticeCaseTaxonomies::class;
		$post          = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || $practice_case::POST_TYPE !== $post->post_type ) {
			return;
		}

		$case_slug = (string) get_post_meta( $post_id, $practice_case::META_CASE_SLUG, true );
		$tool      = $taxonomies::getPrimaryTermSlug( $post_id, $taxonomies::TAX_TOOL );
		$category  = $taxonomies::getPrimaryTermSlug( $post_id, $taxonomies::TAX_CATEGORY );

		if ( '' === $case_slug || '' === $tool || '' === $category ) {
			return;
		}

		$new_slug = sanitize_title(
			$practice_case::SLUG_PREFIX . '-' . $tool . '-' . $category . '-' . $case_slug
		);

		if ( '' === $new_slug || $new_slug === $post->post_name ) {
			return;
		}

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $new_slug,
			)
		);
	}
}
