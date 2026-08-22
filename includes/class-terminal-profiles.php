<?php
/**
 * Terminal profile storage: bundled JSON + custom uploads.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves, lists, validates, and saves terminal profile JSON files.
 */
class Terminal_Profiles {

	public const OPTION_PROFILES_DIR = 'forwp_ac_terminal_profiles_dir';

	public const TYPE_MANUAL    = 'manual';
	public const TYPE_CHALLENGE = 'challenge';

	/**
	 * Admin tab slugs.
	 */
	public const TAB_SETTINGS       = 'settings';
	public const TAB_INSTRUCTIONS   = 'instructions';
	public const TAB_PRACTICE_CASES = 'practice-cases';

	/**
	 * Example profile slugs (bundled or author-created).
	 */
	public const EXAMPLE_PROFILES = array(
		'wp-cli'               => 'WP-CLI',
		'server-configuration' => 'Server Configuration',
		'code-review-cli'      => 'Code Review via CLI',
	);

	/**
	 * JSON schema hint for admin UI (not enforced verbatim).
	 */
	public const SCHEMA_HINT = '{
  "version": 1,
  "profile": "your-slug",
  "title": "My CLI profile",
  "prompt": "$",
  "docBase": "https://example.com/docs/wp-cli",
  "docLabel": "Documentation",
  "categories": [
    {
      "id": "core",
      "name": "Core",
      "color": "#569cd6",
      "commands": [
        { "cmd": "wp core version", "desc": "Show WordPress version" }
      ]
    }
  ],
  "commands": [
    {
      "command": "help",
      "slug": "help",
      "output": "Type any command…",
      "tip": "Optional tip shown after output.",
      "doc": "https://example.com/docs/wp-cli/help",
      "docLabel": "Documentation"
    },
    {
      "pattern": "wp plugin activate",
      "defaultParam": "akismet",
      "slug": "plugin-activate",
      "output": "Plugin \'{param}\' activated.",
      "tip": "Use {param} placeholder in output."
    }
  ]
}';

	/**
	 * JSON schema hint for challenge / practice case profiles.
	 */
	public const SCHEMA_HINT_CHALLENGE = '{
  "version": 1,
  "type": "challenge",
  "profile": "challenge-your-case-slug",
  "slug": "your-case-slug",
  "title": "Practice case title",
  "prompt": "$",
  "description": "What the learner completes.",
  "instructions": "Shown in the challenge terminal.",
  "difficulty": "beginner",
  "estimated_time": "5 min",
  "steps": [
    {
      "id": 1,
      "hint": "Describe the action — not the exact command.",
      "accepted": ["wp example command"],
      "output": "Simulated terminal output.",
      "feedback_wrong": "Try again.",
      "feedback_correct": "Correct.",
      "reveal_after_attempts": 2,
      "command_hint": "wp example command"
    }
  ],
  "completion": {
    "perfect": ["Well done."],
    "optimize": ["Optional tip."],
    "next": ["Suggested next case."]
  }
}';

	/**
	 * Directory for bundled read-only profiles (plugin templates).
	 */
	public static function bundled_dir(): string {
		$default = FORWP_ADVANCED_CODE_PATH . 'assets/data/terminal/';

		if ( defined( 'FORWP_AC_TERMINAL_TEMPLATES_DIR' ) && FORWP_AC_TERMINAL_TEMPLATES_DIR ) {
			return trailingslashit( wp_normalize_path( FORWP_AC_TERMINAL_TEMPLATES_DIR ) );
		}

		/**
		 * Filter bundled terminal template directory (read-only JSON shipped with the site).
		 *
		 * @param string $dir Absolute directory path.
		 */
		$filtered = apply_filters( 'forwp_ac_terminal_templates_dir', $default );

		return trailingslashit( wp_normalize_path( (string) $filtered ) );
	}

	/**
	 * Default writable directory when no custom path is configured.
	 */
	public static function default_custom_dir(): string {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . 'forwp-advanced-code/terminal/';
		}

		return trailingslashit( wp_normalize_path( $upload['basedir'] ) ) . 'forwp-advanced-code/terminal/';
	}

	/**
	 * Whether profile storage path is locked via wp-config constant.
	 */
	public static function is_profiles_dir_locked(): bool {
		return defined( 'FORWP_AC_TERMINAL_PROFILES_DIR' ) && FORWP_AC_TERMINAL_PROFILES_DIR;
	}

	/**
	 * How the writable profiles directory was resolved.
	 *
	 * @return string constant|filter|option|default
	 */
	public static function get_profiles_dir_source(): string {
		if ( self::is_profiles_dir_locked() ) {
			return 'constant';
		}

		$filtered = apply_filters( 'forwp_ac_terminal_profiles_dir', null );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return 'filter';
		}

		$option = get_option( self::OPTION_PROFILES_DIR, '' );
		if ( is_string( $option ) && '' !== trim( $option ) ) {
			return 'option';
		}

		return 'default';
	}

	/**
	 * Resolve writable directory for custom profile JSON files.
	 *
	 * Priority: FORWP_AC_TERMINAL_PROFILES_DIR constant → filter → option → uploads default.
	 */
	public static function resolve_custom_dir(): string {
		if ( self::is_profiles_dir_locked() ) {
			return trailingslashit( wp_normalize_path( (string) FORWP_AC_TERMINAL_PROFILES_DIR ) );
		}

		/**
		 * Filter writable terminal profiles directory.
		 *
		 * @param string|null $dir Absolute directory path, or null to use plugin settings/default.
		 */
		$filtered = apply_filters( 'forwp_ac_terminal_profiles_dir', null );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return trailingslashit( wp_normalize_path( $filtered ) );
		}

		$option = get_option( self::OPTION_PROFILES_DIR, '' );
		if ( is_string( $option ) && '' !== trim( $option ) ) {
			return trailingslashit( wp_normalize_path( $option ) );
		}

		return self::default_custom_dir();
	}

	/**
	 * Directory for custom writable profiles.
	 */
	public static function custom_dir(): string {
		return self::resolve_custom_dir();
	}

	/**
	 * Human-readable path label for admin UI.
	 */
	public static function get_custom_dir_display(): string {
		$dir     = self::custom_dir();
		$content = wp_normalize_path( WP_CONTENT_DIR );
		$root    = wp_normalize_path( ABSPATH );

		if ( 0 === strpos( $dir, $content ) ) {
			return 'wp-content/' . ltrim( substr( $dir, strlen( $content ) ), '/' );
		}

		if ( 0 === strpos( $dir, $root ) ) {
			return ltrim( substr( $dir, strlen( $root ) ), '/' );
		}

		return $dir;
	}

	/**
	 * Admin label for current storage source.
	 */
	public static function get_profiles_dir_source_label(): string {
		switch ( self::get_profiles_dir_source() ) {
			case 'constant':
				return __( 'wp-config.php constant FORWP_AC_TERMINAL_PROFILES_DIR', '4wp-advanced-code' );
			case 'filter':
				return __( 'forwp_ac_terminal_profiles_dir filter', '4wp-advanced-code' );
			case 'option':
				return __( 'path saved in plugin settings', '4wp-advanced-code' );
			default:
				return __( 'WordPress uploads directory (default)', '4wp-advanced-code' );
		}
	}

	/**
	 * Sanitize profiles directory option from settings screen.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_profiles_dir_option( $value ): string {
		if ( self::is_profiles_dir_locked() ) {
			$existing = get_option( self::OPTION_PROFILES_DIR, '' );
			return is_string( $existing ) ? $existing : '';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$path = wp_normalize_path( $value );

		if ( false !== strpos( $path, '..' ) ) {
			add_settings_error(
				self::OPTION_PROFILES_DIR,
				'forwp_ac_terminal_dir_traversal',
				__( 'Profile storage path cannot contain "..".', '4wp-advanced-code' )
			);

			$existing = get_option( self::OPTION_PROFILES_DIR, '' );
			return is_string( $existing ) ? $existing : '';
		}

		if ( ! wp_is_absolute_path( $path ) ) {
			add_settings_error(
				self::OPTION_PROFILES_DIR,
				'forwp_ac_terminal_dir_relative',
				__( 'Profile storage path must be an absolute server path.', '4wp-advanced-code' )
			);

			$existing = get_option( self::OPTION_PROFILES_DIR, '' );
			return is_string( $existing ) ? $existing : '';
		}

		$path = trailingslashit( $path );

		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			add_settings_error(
				self::OPTION_PROFILES_DIR,
				'forwp_ac_terminal_dir_create',
				__( 'Could not create the profile storage directory.', '4wp-advanced-code' )
			);

			$existing = get_option( self::OPTION_PROFILES_DIR, '' );
			return is_string( $existing ) ? $existing : '';
		}

		if ( ! is_writable( $path ) ) {
			add_settings_error(
				self::OPTION_PROFILES_DIR,
				'forwp_ac_terminal_dir_writable',
				__( 'Profile storage directory is not writable.', '4wp-advanced-code' )
			);

			$existing = get_option( self::OPTION_PROFILES_DIR, '' );
			return is_string( $existing ) ? $existing : '';
		}

		return $path;
	}

	/**
	 * Sanitize profile slug.
	 *
	 * @param string $slug Raw slug.
	 */
	public static function sanitize_slug( string $slug ): string {
		return preg_replace( '/[^a-z0-9-]/', '', strtolower( $slug ) ) ?? '';
	}

	/**
	 * Resolve readable path for a profile (custom overrides bundled).
	 *
	 * @param string $profile Profile slug.
	 */
	public static function get_path( string $profile ): string {
		$safe = self::sanitize_slug( $profile );

		if ( '' === $safe ) {
			return '';
		}

		$custom = self::custom_dir() . $safe . '.json';
		if ( is_readable( $custom ) ) {
			return $custom;
		}

		return self::bundled_dir() . $safe . '.json';
	}

	/**
	 * Whether profile file lives in uploads (editable).
	 *
	 * @param string $profile Profile slug.
	 */
	public static function is_custom( string $profile ): bool {
		$safe   = self::sanitize_slug( $profile );
		$custom = self::custom_dir() . $safe . '.json';

		return is_readable( $custom );
	}

	/**
	 * List every profile JSON file (bundled templates + custom uploads).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_profiles(): array {
		$map = array();

		foreach ( glob( self::bundled_dir() . '*.json' ) ?: array() as $path ) {
			$slug = basename( $path, '.json' );
			$map[ $slug ] = self::build_list_item( $path, $slug, 'template' );
		}

		if ( is_dir( self::custom_dir() ) ) {
			foreach ( glob( self::custom_dir() . '*.json' ) ?: array() as $path ) {
				$slug = basename( $path, '.json' );
				$map[ $slug ] = self::build_list_item( $path, $slug, 'custom' );
			}
		}

		ksort( $map );

		return array_values( $map );
	}

	/**
	 * Filter listed profiles by detected JSON type.
	 *
	 * @param string $type manual|challenge.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_profiles_by_type( string $type ): array {
		return array_values(
			array_filter(
				self::list_profiles(),
				static function ( array $item ) use ( $type ): bool {
					return ( $item['profile_type'] ?? self::TYPE_MANUAL ) === $type;
				}
			)
		);
	}

	/**
	 * Detect profile type from JSON (manual reference vs challenge practice case).
	 *
	 * @param array<string, mixed> $data Decoded JSON.
	 * @param string               $slug File slug.
	 */
	public static function detect_profile_type( array $data, string $slug ): string {
		if ( ! empty( $data['type'] ) && self::TYPE_CHALLENGE === (string) $data['type'] ) {
			return self::TYPE_CHALLENGE;
		}

		if ( str_starts_with( $slug, 'challenge-' ) ) {
			return self::TYPE_CHALLENGE;
		}

		return self::TYPE_MANUAL;
	}

	/**
	 * Challenge fields required by the frontend quiz runtime.
	 *
	 * Excludes content{}, seo{}, and other import-only blocks so profileData
	 * stays small when embedded in HTML data attributes.
	 *
	 * @param array<string, mixed> $data Full profile JSON.
	 * @return array<string, mixed>
	 */
	public static function extract_challenge_runtime( array $data ): array {
		$keys = array(
			'version',
			'type',
			'profile',
			'slug',
			'prompt',
			'title',
			'description',
			'instructions',
			'steps',
			'completion',
		);

		$runtime = array();

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$runtime[ $key ] = $data[ $key ];
			}
		}

		if ( empty( $runtime['type'] ) ) {
			$runtime['type'] = self::TYPE_CHALLENGE;
		}

		return $runtime;
	}

	/**
	 * Admin tab labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_admin_tab_labels(): array {
		return array(
			self::TAB_SETTINGS       => __( 'Storage', '4wp-advanced-code' ),
			self::TAB_INSTRUCTIONS   => __( 'Instructions', '4wp-advanced-code' ),
			self::TAB_PRACTICE_CASES => __( 'Practice Cases', '4wp-advanced-code' ),
		);
	}

	/**
	 * Sanitize active admin tab slug.
	 *
	 * @param string $tab Raw tab from query string.
	 */
	public static function sanitize_admin_tab( string $tab ): string {
		$tab = sanitize_key( $tab );
		$allowed = array_keys( self::get_admin_tab_labels() );

		return in_array( $tab, $allowed, true ) ? $tab : self::TAB_INSTRUCTIONS;
	}

	/**
	 * Storage summary for admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_storage_info(): array {
		$profiles = self::list_profiles();
		$custom   = array_filter(
			$profiles,
			static function ( array $item ): bool {
				return 'custom' === ( $item['source'] ?? '' );
			}
		);
		$templates = array_filter(
			$profiles,
			static function ( array $item ): bool {
				return 'template' === ( $item['source'] ?? '' );
			}
		);

		return array(
			'templates_dir'       => self::bundled_dir(),
			'profiles_dir'        => self::custom_dir(),
			'profiles_dir_display'=> self::get_custom_dir_display(),
			'profiles_dir_source' => self::get_profiles_dir_source(),
			'profiles_dir_locked' => self::is_profiles_dir_locked(),
			'templates_label'     => self::get_bundled_dir_display(),
			'profiles_source_label' => self::get_profiles_dir_source_label(),
			'total'               => count( $profiles ),
			'templates_count'     => count( $templates ),
			'custom_count'        => count( $custom ),
		);
	}

	/**
	 * Human-readable bundled templates path for admin UI.
	 */
	public static function get_bundled_dir_display(): string {
		$dir     = self::bundled_dir();
		$plugin  = wp_normalize_path( FORWP_ADVANCED_CODE_PATH );
		$content = wp_normalize_path( WP_CONTENT_DIR );

		if ( 0 === strpos( $dir, $plugin ) ) {
			return 'wp-content/plugins/' . basename( $plugin ) . '/' . ltrim( substr( $dir, strlen( $plugin ) ), '/' );
		}

		if ( 0 === strpos( $dir, $content ) ) {
			return 'wp-content/' . ltrim( substr( $dir, strlen( $content ) ), '/' );
		}

		return $dir;
	}

	/**
	 * Load and decode profile data.
	 *
	 * @param string $profile Profile slug.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function load( string $profile ) {
		$path = self::get_path( $profile );

		if ( ! is_readable( $path ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_profile_missing',
				__( 'Terminal profile not found.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		$raw  = file_get_contents( $path );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_profile_invalid',
				__( 'Terminal profile data is invalid JSON.', '4wp-advanced-code' ),
				array( 'status' => 500 )
			);
		}

		return $data;
	}

	/**
	 * Ensure JSON "profile" matches the file slug before save.
	 *
	 * @param array<string, mixed> $data Profile data.
	 * @param string               $slug File slug.
	 * @return array<string, mixed>
	 */
	public static function sync_profile_slug( array $data, string $slug ): array {
		$data['profile'] = self::sanitize_slug( $slug );

		return $data;
	}

	/**
	 * Validate profile array structure.
	 *
	 * @param array<string, mixed> $data   Decoded JSON.
	 * @param string               $slug   Expected profile slug from filename.
	 * @return true|\WP_Error
	 */
	public static function validate( array $data, string $slug ) {
		$type = self::detect_profile_type( $data, $slug );

		if ( self::TYPE_CHALLENGE === $type ) {
			return self::validate_challenge( $data, $slug );
		}

		if ( ! isset( $data['version'] ) || ! is_numeric( $data['version'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_version',
				__( 'Profile must include numeric "version" (use 1).', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $data['profile'] ) || ! is_string( $data['profile'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_profile',
				__( 'Profile must include string "profile" (slug).', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( self::sanitize_slug( (string) $data['profile'] ) !== $slug ) {
			return new \WP_Error(
				'forwp_ac_terminal_slug_mismatch',
				__( 'The "profile" field must match the profile slug you are saving.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( ! isset( $data['commands'] ) || ! is_array( $data['commands'] ) || empty( $data['commands'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_commands',
				__( 'Profile must include a non-empty "commands" array.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		foreach ( $data['commands'] as $index => $command ) {
			if ( ! is_array( $command ) ) {
				return new \WP_Error(
					'forwp_ac_terminal_invalid_command',
					sprintf(
						/* translators: %d: command index */
						__( 'Command at index %d must be an object.', '4wp-advanced-code' ),
						(int) $index
					),
					array( 'status' => 400 )
				);
			}

			$has_command = ! empty( $command['command'] ) && is_string( $command['command'] );
			$has_pattern = ! empty( $command['pattern'] ) && is_string( $command['pattern'] );

			if ( ! $has_command && ! $has_pattern ) {
				return new \WP_Error(
					'forwp_ac_terminal_command_keys',
					sprintf(
						/* translators: %d: command index */
						__( 'Command at index %d needs "command" or "pattern".', '4wp-advanced-code' ),
						(int) $index
					),
					array( 'status' => 400 )
				);
			}

			if ( empty( $command['output'] ) || ! is_string( $command['output'] ) ) {
				return new \WP_Error(
					'forwp_ac_terminal_command_output',
					sprintf(
						/* translators: %d: command index */
						__( 'Command at index %d needs string "output".', '4wp-advanced-code' ),
						(int) $index
					),
					array( 'status' => 400 )
				);
			}
		}

		if ( isset( $data['categories'] ) && ! is_array( $data['categories'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_categories',
				__( '"categories" must be an array when present.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate challenge / practice case profile structure.
	 *
	 * @param array<string, mixed> $data Decoded JSON.
	 * @param string               $slug Expected profile slug from filename.
	 * @return true|\WP_Error
	 */
	public static function validate_challenge( array $data, string $slug ) {
		if ( ! isset( $data['version'] ) || ! is_numeric( $data['version'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_version',
				__( 'Profile must include numeric "version" (use 1).', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $data['profile'] ) || ! is_string( $data['profile'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_profile',
				__( 'Profile must include string "profile" (slug).', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( self::sanitize_slug( (string) $data['profile'] ) !== $slug ) {
			return new \WP_Error(
				'forwp_ac_terminal_slug_mismatch',
				__( 'The "profile" field must match the profile slug you are saving.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $data['steps'] ) || ! is_array( $data['steps'] ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_steps',
				__( 'Challenge profiles must include a non-empty "steps" array.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		foreach ( $data['steps'] as $index => $step ) {
			if ( ! is_array( $step ) ) {
				return new \WP_Error(
					'forwp_ac_terminal_invalid_step',
					sprintf(
						/* translators: %d: step index */
						__( 'Step at index %d must be an object.', '4wp-advanced-code' ),
						(int) $index
					),
					array( 'status' => 400 )
				);
			}

			if ( empty( $step['hint'] ) || ! is_string( $step['hint'] ) ) {
				return new \WP_Error(
					'forwp_ac_terminal_step_hint',
					sprintf(
						/* translators: %d: step index */
						__( 'Step at index %d needs string "hint".', '4wp-advanced-code' ),
						(int) $index
					),
					array( 'status' => 400 )
				);
			}

			if ( empty( $step['accepted'] ) || ! is_array( $step['accepted'] ) ) {
				return new \WP_Error(
					'forwp_ac_terminal_step_accepted',
					sprintf(
						/* translators: %d: step index */
						__( 'Step at index %d needs non-empty "accepted" array.', '4wp-advanced-code' ),
						(int) $index
					),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Delete a custom profile file (uploads only).
	 *
	 * @param string $slug Profile slug.
	 * @return true|\WP_Error
	 */
	public static function delete_custom( string $slug ) {
		$safe = self::sanitize_slug( $slug );

		if ( '' === $safe ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_slug',
				__( 'Invalid profile slug.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$path = self::custom_dir() . $safe . '.json';

		if ( ! is_readable( $path ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_profile_missing',
				__( 'Custom profile file not found.', '4wp-advanced-code' ),
				array( 'status' => 404 )
			);
		}

		if ( ! wp_delete_file( $path ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_delete',
				__( 'Could not delete profile file.', '4wp-advanced-code' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a custom terminal profile file is deleted.
		 *
		 * @param string $slug Profile slug.
		 */
		do_action( 'forwp_ac_terminal_profile_deleted', $safe );

		return true;
	}

	/**
	 * Create a new custom profile file from bundled template JSON.
	 *
	 * @param string $source_slug Template slug to copy from.
	 * @param string $new_slug    New profile slug / filename.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function duplicate_template( string $source_slug, string $new_slug ) {
		$new_safe = self::sanitize_slug( $new_slug );
		if ( '' === $new_safe ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_slug',
				__( 'Profile slug may only contain lowercase letters, numbers, and hyphens.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$custom_path = self::custom_dir() . $new_safe . '.json';
		if ( is_readable( $custom_path ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_exists',
				__( 'A profile file with this slug already exists.', '4wp-advanced-code' ),
				array( 'status' => 409 )
			);
		}

		$source = self::load( $source_slug );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$source['profile'] = $new_safe;
		if ( empty( $source['title'] ) || ! is_string( $source['title'] ) ) {
			$source['title'] = ucwords( str_replace( '-', ' ', $new_safe ) );
		}

		$result = self::save_custom( $new_safe, $source );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::load( $new_safe );
	}

	/**
	 * Save custom profile JSON to the configured writable directory.
	 *
	 * @param string               $slug Profile slug (filename).
	 * @param array<string, mixed> $data Validated profile data.
	 * @return true|\WP_Error
	 */
	public static function save_custom( string $slug, array $data ) {
		$safe = self::sanitize_slug( $slug );

		if ( '' === $safe ) {
			return new \WP_Error(
				'forwp_ac_terminal_invalid_slug',
				__( 'Profile slug may only contain lowercase letters, numbers, and hyphens.', '4wp-advanced-code' ),
				array( 'status' => 400 )
			);
		}

		$data = self::sync_profile_slug( $data, $safe );

		$valid = self::validate( $data, $safe );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$dir = self::custom_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_dir',
				__( 'Could not create terminal profiles directory.', '4wp-advanced-code' ),
				array( 'status' => 500 )
			);
		}

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return new \WP_Error(
				'forwp_ac_terminal_encode',
				__( 'Could not encode profile JSON.', '4wp-advanced-code' ),
				array( 'status' => 500 )
			);
		}

		$path = $dir . $safe . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $path, $json . "\n" ) ) {
			return new \WP_Error(
				'forwp_ac_terminal_write',
				__( 'Could not write profile file.', '4wp-advanced-code' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a custom terminal profile is saved.
		 *
		 * @param string               $slug Profile slug.
		 * @param array<string, mixed> $data Profile data.
		 */
		do_action( 'forwp_ac_terminal_profile_saved', $safe, $data );

		return true;
	}

	/**
	 * Build list row for one JSON file.
	 *
	 * @param string $path   Absolute file path.
	 * @param string $slug   Profile slug.
	 * @param string $source template|custom.
	 * @return array<string, mixed>
	 */
	private static function build_list_item( string $path, string $slug, string $source ): array {
		$raw  = is_readable( $path ) ? file_get_contents( $path ) : '';
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		$data = is_array( $data ) ? $data : array();

		$meta            = self::read_meta( $path, $slug, $data );
		$profile_type    = self::detect_profile_type( $data, $slug );
		$commands_count  = isset( $data['commands'] ) && is_array( $data['commands'] ) ? count( $data['commands'] ) : 0;
		$categories_count = isset( $data['categories'] ) && is_array( $data['categories'] ) ? count( $data['categories'] ) : 0;
		$steps_count     = isset( $data['steps'] ) && is_array( $data['steps'] ) ? count( $data['steps'] ) : 0;

		return array_merge(
			$meta,
			array(
				'file'              => $slug . '.json',
				'source'            => $source,
				'profile_type'      => $profile_type,
				'case_slug'         => isset( $data['slug'] ) && is_string( $data['slug'] ) ? $data['slug'] : '',
				'difficulty'        => isset( $data['difficulty'] ) && is_string( $data['difficulty'] ) ? $data['difficulty'] : '',
				'editable'          => 'custom' === $source,
				'overrides_template'=> 'custom' === $source && is_readable( self::bundled_dir() . $slug . '.json' ),
				'commands_count'    => $commands_count,
				'categories_count'  => $categories_count,
				'steps_count'       => $steps_count,
				'import'            => Terminal_Import_Registry::get( $slug ),
			)
		);
	}

	/**
	 * Read label/title from profile file without full validation.
	 *
	 * @param string               $path File path.
	 * @param string               $slug Profile slug fallback.
	 * @param array<string, mixed> $data Pre-decoded JSON when available.
	 * @return array{slug:string,label:string,title:string}
	 */
	private static function read_meta( string $path, string $slug, array $data = array() ): array {
		$label = self::EXAMPLE_PROFILES[ $slug ] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$title = $label;

		if ( empty( $data ) ) {
			$raw  = file_get_contents( $path );
			$data = json_decode( $raw, true );
			$data = is_array( $data ) ? $data : array();
		}

		if ( is_array( $data ) && ! empty( $data['title'] ) && is_string( $data['title'] ) ) {
			$title = $data['title'];
		}

		return array(
			'slug'  => $slug,
			'label' => $label,
			'title' => $title,
		);
	}
}
