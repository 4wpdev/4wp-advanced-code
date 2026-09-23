<?php
/**
 * REST API: IDE hook wizards.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * IDE REST endpoints and script localization.
 */
class Ide_Rest {

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'init', array( self::class, 'register_highlight_dependency' ), 21 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'localize_view_script' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'localize_editor_script' ), 20 );
	}

	/**
	 * Reuse bundled highlight.js for the wizard editor overlay.
	 * Does not change Code Block enqueue or frontend.js.
	 */
	public static function register_highlight_dependency(): void {
		if ( ! Modules::is_ide_enabled() ) {
			return;
		}

		if ( ! wp_script_is( 'forwp-advanced-code-highlight', 'registered' ) ) {
			wp_register_script(
				'forwp-advanced-code-highlight',
				FORWP_ADVANCED_CODE_URL . 'assets/highlight.min.js',
				array(),
				'11.11.1',
				true
			);
		}

		$scripts = wp_scripts();
		$handles = array(
			'forwp-advanced-code-ide-view-script',
			'forwp-advanced-code-ide-editor-script',
		);

		foreach ( $handles as $handle ) {
			if ( ! isset( $scripts->registered[ $handle ] ) ) {
				continue;
			}

			$deps = $scripts->registered[ $handle ]->deps;
			if ( ! in_array( 'forwp-advanced-code-highlight', $deps, true ) ) {
				$scripts->registered[ $handle ]->deps[] = 'forwp-advanced-code-highlight';
			}
		}
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'forwp-advanced-code/v1',
			'/ide/hooks',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'list_hooks' ),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/ide/hooks/(?P<type>action|filter)/(?P<slug>[a-zA-Z0-9_]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'get_wizard' ),
				'args'                => array(
					'type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Ide_Catalog::class, 'sanitize_type' ),
					),
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Ide_Catalog::class, 'sanitize_slug' ),
					),
				),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/ide/hooks-group/(?P<type>action|filter)/(?P<slug>[a-zA-Z0-9_]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'get_group' ),
				'args'                => array(
					'type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Ide_Catalog::class, 'sanitize_type' ),
					),
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( Ide_Catalog::class, 'sanitize_slug' ),
					),
				),
			)
		);

		register_rest_route(
			'forwp-advanced-code/v1',
			'/ide/hooks-set',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'get_set' ),
				'args'                => array(
					'hooks' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * List published hooks for the block inspector.
	 *
	 * @return \WP_REST_Response
	 */
	public static function list_hooks(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'hooks' => Ide_Catalog::list_hooks(),
			)
		);
	}

	/**
	 * Compiled wizard for one hook.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_wizard( \WP_REST_Request $request ) {
		$type = (string) $request->get_param( 'type' );
		$slug = (string) $request->get_param( 'slug' );
		$data = Ide_Catalog::get_wizard( $type, $slug );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Current hook plus related_hooks as compiled wizards.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_group( \WP_REST_Request $request ) {
		$type = (string) $request->get_param( 'type' );
		$slug = (string) $request->get_param( 'slug' );
		$data = Ide_Catalog::get_group( $type, $slug );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Author-picked hook set compiled as wizards.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_set( \WP_REST_Request $request ) {
		$data = Ide_Catalog::get_set( (string) $request->get_param( 'hooks' ) );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response( $data );
	}

	/**
	 * REST URL for one hook wizard.
	 */
	public static function get_wizard_rest_url( string $type, string $slug ): string {
		$type = Ide_Catalog::sanitize_type( $type );
		$slug = Ide_Catalog::sanitize_slug( $slug );

		return rest_url( 'forwp-advanced-code/v1/ide/hooks/' . $type . '/' . $slug );
	}

	/**
	 * REST URL for an author-picked hook set.
	 *
	 * @param array<int, array<string, string>> $refs Sanitized refs.
	 */
	public static function get_set_rest_url( array $refs ): string {
		$refs  = Ide_Catalog::sanitize_hook_refs( $refs );
		$parts = array();
		foreach ( $refs as $ref ) {
			$parts[] = $ref['type'] . ':' . $ref['slug'];
		}

		return add_query_arg(
			'hooks',
			implode( ',', $parts ),
			rest_url( 'forwp-advanced-code/v1/ide/hooks-set' )
		);
	}

	/**
	 * REST URL for a group tour (current hook + related_hooks).
	 */
	public static function get_group_rest_url( string $type, string $slug ): string {
		$type = Ide_Catalog::sanitize_type( $type );
		$slug = Ide_Catalog::sanitize_slug( $slug );

		return rest_url( 'forwp-advanced-code/v1/ide/hooks-group/' . $type . '/' . $slug );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_script_config(): array {
		return array(
			'restBase' => esc_url_raw( rest_url( 'forwp-advanced-code/v1/ide/hooks' ) ),
			'restSet'  => esc_url_raw( rest_url( 'forwp-advanced-code/v1/ide/hooks-set' ) ),
			'labels'   => array(
				'back'         => __( 'Back', '4wp-advanced-code' ),
				'next'         => __( 'Next', '4wp-advanced-code' ),
				'done'         => __( 'Done', '4wp-advanced-code' ),
				'check'        => __( 'Check', '4wp-advanced-code' ),
				'restart'      => __( 'Restart', '4wp-advanced-code' ),
				'loadExample'  => __( 'Load example into editor', '4wp-advanced-code' ),
				'resetStarter' => __( 'Reset starter', '4wp-advanced-code' ),
				'looksGood'     => __( 'Looks good. Continue when you are ready.', '4wp-advanced-code' ),
				'stepOf'        => __( 'Step %1$s of %2$s', '4wp-advanced-code' ),
				'hookOf'        => __( 'Hook %1$s of %2$s', '4wp-advanced-code' ),
				'nextHook'      => __( 'Next hook', '4wp-advanced-code' ),
				'quizIncorrect' => __( 'Not quite — Next is still available.', '4wp-advanced-code' ),
				'quizHint'      => __( 'Think and click. Next is never blocked.', '4wp-advanced-code' ),
				'groupTour'     => __( 'Group tour', '4wp-advanced-code' ),
				'hookQuiz'      => __( 'Hook quiz', '4wp-advanced-code' ),
				'hookWizard'    => __( 'Hook wizard', '4wp-advanced-code' ),
				'loadError'    => __( 'Could not load this hook wizard. Check that the theme hooks JSON exists.', '4wp-advanced-code' ),
				'emptyCatalog' => __( 'No published hooks in the catalog yet.', '4wp-advanced-code' ),
				'fullView'     => __( 'Full view', '4wp-advanced-code' ),
			),
		);
	}

	/**
	 * Pass REST base to the IDE view script.
	 */
	public static function localize_view_script(): void {
		if ( ! Modules::is_ide_enabled() ) {
			return;
		}

		$handle = 'forwp-advanced-code-ide-view-script';
		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_localize_script( $handle, 'forwpAdvancedCodeIde', self::get_script_config() );
	}

	/**
	 * Pass REST base to the IDE editor script.
	 */
	public static function localize_editor_script(): void {
		if ( ! Modules::is_ide_enabled() ) {
			return;
		}

		$handle = 'forwp-advanced-code-ide-editor-script';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		wp_localize_script( $handle, 'forwpAdvancedCodeIde', self::get_script_config() );
	}
}
