<?php
/**
 * REST API: Terminal command profiles (JSON datasets).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Terminal profile REST endpoints.
 */
class Terminal_Rest {

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'localize_view_script' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'localize_editor_script' ), 20 );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'forwp-advanced-code/v1',
			'/terminal-profiles',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'list_profiles' ),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/terminal/(?P<profile>[a-z0-9-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( self::class, 'get_profile' ),
					'args'                => array(
						'profile' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_slug' ),
						),
						'post_id' => array(
							'required' => false,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => array( self::class, 'can_manage_profiles' ),
					'callback'            => array( self::class, 'save_profile' ),
					'args'                => array(
						'profile' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_slug' ),
						),
						'json'    => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'permission_callback' => array( self::class, 'can_manage_profiles' ),
					'callback'            => array( self::class, 'delete_profile' ),
					'args'                => array(
						'profile' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_slug' ),
						),
					),
				),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/terminal/(?P<profile>[a-z0-9-]+)/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( self::class, 'can_manage_profiles' ),
				'callback'            => array( self::class, 'import_profile' ),
				'args'                => array(
					'profile'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_slug' ),
					),
					'post_type' => array(
						'required' => true,
						'type'     => 'string',
					),
					'force'     => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
					'post_id'   => array(
						'required' => false,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/terminal/(?P<profile>[a-z0-9-]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( self::class, 'can_manage_profiles' ),
				'callback'            => array( self::class, 'duplicate_profile' ),
				'args'                => array(
					'profile'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_slug' ),
					),
					'new_slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_slug' ),
					),
				),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/posts/(?P<post_id>\d+)/terminal-data',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( self::class, 'can_read_post_terminal_data' ),
				'callback'            => array( self::class, 'get_post_terminal_data' ),
				'args'                => array(
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage_profiles(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_practice_cases' );
	}

	/**
	 * List available terminal profiles.
	 *
	 * @return \WP_REST_Response
	 */
	public static function list_profiles(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'storage'  => Terminal_Profiles::get_storage_info(),
				'profiles' => Terminal_Profiles::list_profiles(),
			)
		);
	}

	/**
	 * Return terminal profile JSON by slug.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_profile( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( $post_id && Terminal_Post_Meta::has_data( $post_id ) ) {
			$data = Terminal_Post_Meta::get_data( $post_id );
			if ( null !== $data ) {
				return rest_ensure_response( $data );
			}
		}

		$profile = (string) $request->get_param( 'profile' );
		$data    = Terminal_Profiles::load( $profile );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Save custom terminal profile JSON to uploads.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_profile( \WP_REST_Request $request ) {
		$slug = (string) $request->get_param( 'profile' );
		$raw  = (string) $request->get_param( 'json' );

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_json',
				__( 'Invalid JSON syntax.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$result = Terminal_Profiles::save_custom( $slug, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'slug'    => Terminal_Profiles::sanitize_slug( $slug ),
				'file'    => Terminal_Profiles::sanitize_slug( $slug ) . '.json',
				'path'    => Terminal_Profiles::get_custom_dir_display() . Terminal_Profiles::sanitize_slug( $slug ) . '.json',
			)
		);
	}

	/**
	 * Delete custom profile file.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_profile( \WP_REST_Request $request ) {
		$slug   = (string) $request->get_param( 'profile' );
		$result = Terminal_Profiles::delete_custom( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'slug'    => Terminal_Profiles::sanitize_slug( $slug ),
			)
		);
	}

	/**
	 * Duplicate template profile into a new custom file.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function duplicate_profile( \WP_REST_Request $request ) {
		$source = (string) $request->get_param( 'profile' );
		$new    = (string) $request->get_param( 'new_slug' );
		$data   = Terminal_Profiles::duplicate_template( $source, $new );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'slug'    => Terminal_Profiles::sanitize_slug( $new ),
				'file'    => Terminal_Profiles::sanitize_slug( $new ) . '.json',
				'profile' => $data,
			)
		);
	}

	/**
	 * Import challenge profile JSON into a post (runtime data → post meta).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function import_profile( \WP_REST_Request $request ) {
		$profile   = (string) $request->get_param( 'profile' );
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		$force     = rest_sanitize_boolean( $request->get_param( 'force' ) );
		$post_id   = absint( $request->get_param( 'post_id' ) );

		$result = Terminal_Compiler::import(
			$profile,
			$post_type,
			array(
				'force'   => $force,
				'post_id' => $post_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function can_read_post_terminal_data( \WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'post_id' ) );

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Return compiled terminal data stored on a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_post_terminal_data( \WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$data    = Terminal_Post_Meta::get_data( $post_id );

		if ( null === $data ) {
			return new \WP_Error(
				'forwp_ac_terminal_post_data_missing',
				__( 'This post has no compiled terminal data.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Build REST URL for a terminal profile.
	 *
	 * @param string $profile Profile slug.
	 */
	public static function get_profile_rest_url( string $profile ): string {
		$safe = Terminal_Profiles::sanitize_slug( $profile );

		return rest_url( 'forwp-advanced-code/v1/terminal/' . $safe );
	}

	/**
	 * Shared config for terminal frontend/editor scripts.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_script_config(): array {
		$account_url = '';

		if ( class_exists( '\ForWP\Account\Account\AccountMenu' ) ) {
			$account_url = \ForWP\Account\Account\AccountMenu::get_account_page_url();
		}

		return array(
			'restBase'   => rest_url( 'forwp-advanced-code/v1/terminal' ),
			'accountUrl' => $account_url,
			'isLoggedIn' => is_user_logged_in(),
			'i18n'       => array(
				'fullView'        => __( 'Full view', '4wp-advanced-code' ),
				'sticky'          => __( 'Sticky', '4wp-advanced-code' ),
				'signIn'          => __( 'Sign in', '4wp-advanced-code' ),
				'authorize'       => __( 'Authorize', '4wp-advanced-code' ),
				'account'         => __( 'Account', '4wp-advanced-code' ),
				'saveProgress'        => __( 'Save', '4wp-advanced-code' ),
				'saveProgressAria'    => __( 'Save progress', '4wp-advanced-code' ),
				'comingSoon'          => __( 'Coming Soon', '4wp-advanced-code' ),
				'retakeChallenge'     => __( 'Retake', '4wp-advanced-code' ),
				'retakeChallengeAria' => __( 'Retake challenge', '4wp-advanced-code' ),
			),
		);
	}

	/**
	 * Pass REST base URL to the terminal view script on the frontend.
	 */
	public static function localize_view_script(): void {
		if ( ! Modules::is_terminal_enabled() ) {
			return;
		}

		$handle = 'forwp-advanced-code-terminal-view-script';

		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			'forwpAdvancedCodeTerminal',
			self::get_script_config()
		);
	}

	/**
	 * Pass account URL and labels to the terminal block editor script.
	 */
	public static function localize_editor_script(): void {
		if ( ! Modules::is_terminal_enabled() ) {
			return;
		}

		$handle = 'forwp-advanced-code-terminal-editor-script';

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			'forwpAdvancedCodeTerminal',
			self::get_script_config()
		);
	}
}
