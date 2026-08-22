<?php
/**
 * Plugin settings screen.
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings and admin functionality.
 */
class Settings {

	public const MENU_SLUG        = 'forwp-advanced-code';
	public const SETTINGS_SLUG    = 'forwp-advanced-code';
	public const CODE_SETTINGS_SLUG = 'forwp-advanced-code-code';

	private const SETTINGS_PAGE = 'forwp-advanced-code';

	/**
	 * Initialize settings.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_admin_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/**
	 * Top-level menu: Settings (modules) + submenus.
	 */
	public static function add_admin_menu(): void {
		add_menu_page(
			__( '4WP Advanced Code', '4wp-advanced-code' ),
			__( '4WP Adv. Code', '4wp-advanced-code' ),
			'manage_options',
			self::MENU_SLUG,
			array( self::class, 'render_modules_page' ),
			'dashicons-editor-code',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', '4wp-advanced-code' ),
			__( 'Settings', '4wp-advanced-code' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( self::class, 'render_modules_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Code Block Settings', '4wp-advanced-code' ),
			__( 'Code Block', '4wp-advanced-code' ),
			'manage_options',
			self::CODE_SETTINGS_SLUG,
			array( self::class, 'render_code_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_PAGE,
			Modules::OPTION_CODE,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			self::SETTINGS_PAGE,
			Modules::OPTION_TERMINAL,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			self::SETTINGS_PAGE,
			Modules::OPTION_IDE,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		add_settings_section(
			'forwp_ac_modules',
			__( 'Modules', '4wp-advanced-code' ),
			array( self::class, 'render_modules_section_intro' ),
			self::SETTINGS_PAGE
		);

		foreach ( Modules::definitions() as $key => $module ) {
			add_settings_field(
				$module['option'],
				$module['label'],
				function () use ( $key, $module ) {
					self::render_module_field( $key, $module );
				},
				self::SETTINGS_PAGE,
				'forwp_ac_modules'
			);
		}

		register_setting(
			self::CODE_SETTINGS_SLUG,
			'forwp_advanced_code_default_language',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_language' ),
				'default'           => 'auto',
			)
		);

		register_setting(
			self::CODE_SETTINGS_SLUG,
			'forwp_advanced_code_theme',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_theme' ),
				'default'           => 'light',
			)
		);

		register_setting(
			self::CODE_SETTINGS_SLUG,
			'forwp_advanced_code_seo_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		add_settings_section(
			'forwp_advanced_code_general',
			__( 'Display defaults', '4wp-advanced-code' ),
			null,
			self::CODE_SETTINGS_SLUG
		);

		add_settings_field(
			'forwp_advanced_code_default_language',
			__( 'Default Language', '4wp-advanced-code' ),
			array( self::class, 'render_language_field' ),
			self::CODE_SETTINGS_SLUG,
			'forwp_advanced_code_general'
		);

		add_settings_field(
			'forwp_advanced_code_theme',
			__( 'Default Theme', '4wp-advanced-code' ),
			array( self::class, 'render_theme_field' ),
			self::CODE_SETTINGS_SLUG,
			'forwp_advanced_code_general'
		);

		add_settings_section(
			'forwp_advanced_code_seo',
			__( 'SEO', '4wp-advanced-code' ),
			null,
			self::CODE_SETTINGS_SLUG
		);

		add_settings_field(
			'forwp_advanced_code_seo_enabled',
			__( 'Enable SEO Snippets', '4wp-advanced-code' ),
			array( self::class, 'render_seo_enabled_field' ),
			self::CODE_SETTINGS_SLUG,
			'forwp_advanced_code_seo'
		);
	}

	/**
	 * Settings landing: enable/disable modules.
	 */
	public static function render_modules_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', '4wp-advanced-code' ); ?></h1>
			<p><? esc_html_e( 'Enable the interactive modules you need. Disabled modules are hidden from the block inserter and do not load on the frontend.', '4wp-advanced-code' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_PAGE );
				do_settings_sections( self::SETTINGS_PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Code Block module options.
	 */
	public static function render_code_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Modules::is_code_enabled() ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Code Block', '4wp-advanced-code' ); ?></h1>
				<div class="notice notice-warning">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: Settings admin link */
								__( 'Code Block module is disabled. Enable it on the %s page.', '4wp-advanced-code' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', '4wp-advanced-code' ) . '</a>'
							)
						);
						?>
					</p>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Code Block', '4wp-advanced-code' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::CODE_SETTINGS_SLUG );
				do_settings_sections( self::CODE_SETTINGS_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Modules section intro.
	 */
	public static function render_modules_section_intro(): void {
		echo '<p>' . esc_html__( 'Three pillars of 4WP Advanced Code:', '4wp-advanced-code' ) . '</p>';
	}

	/**
	 * Render one module toggle.
	 *
	 * @param string               $key    Module key.
	 * @param array<string,string> $module Module definition.
	 */
	public static function render_module_field( string $key, array $module ): void {
		$option  = $module['option'];
		$enabled = (bool) get_option( $option, 'ide' === $key ? false : true );

		if ( Modules::OPTION_CODE === $option && null === get_option( $option, null ) ) {
			$enabled = Modules::is_code_enabled();
		}

		$disabled = ( 'ide' === $key );
		?>
		<label for="<?php echo esc_attr( $option ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $option ); ?>"
				name="<?php echo esc_attr( $option ); ?>"
				value="1"
				<?php checked( $enabled ); ?>
				<?php disabled( $disabled ); ?>
			/>
			<?php echo esc_html( $module['description'] ); ?>
		</label>
		<?php if ( $disabled ) : ?>
			<p class="description"><?php esc_html_e( 'Not available yet — enable after IDE block migration.', '4wp-advanced-code' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render language field.
	 */
	public static function render_language_field(): void {
		$language  = get_option( 'forwp_advanced_code_default_language', 'auto' );
		$languages = self::get_supported_languages();
		?>
		<select id="forwp_advanced_code_default_language" name="forwp_advanced_code_default_language">
			<?php foreach ( $languages as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $language, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render theme field.
	 */
	public static function render_theme_field(): void {
		$theme  = get_option( 'forwp_advanced_code_theme', 'light' );
		$themes = array(
			'light'    => __( 'Light Theme', '4wp-advanced-code' ),
			'dark'     => __( 'Dark Theme', '4wp-advanced-code' ),
			'terminal' => __( 'Terminal Theme', '4wp-advanced-code' ),
		);
		?>
		<select id="forwp_advanced_code_theme" name="forwp_advanced_code_theme">
			<?php foreach ( $themes as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $theme, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render SEO enabled field.
	 */
	public static function render_seo_enabled_field(): void {
		$enabled = (bool) get_option( 'forwp_advanced_code_seo_enabled', true );
		?>
		<label for="forwp_advanced_code_seo_enabled">
			<input type="checkbox" id="forwp_advanced_code_seo_enabled" name="forwp_advanced_code_seo_enabled" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Generate JSON-LD structured data for code blocks', '4wp-advanced-code' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize checkbox option.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_checkbox( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Sanitize language option.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_language( $value ): string {
		$value = sanitize_key( (string) $value );

		return array_key_exists( $value, self::get_supported_languages() ) ? $value : 'auto';
	}

	/**
	 * Sanitize theme option.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_theme( $value ): string {
		$value  = sanitize_key( (string) $value );
		$themes = array( 'light', 'dark', 'terminal' );

		return in_array( $value, $themes, true ) ? $value : 'light';
	}

	/**
	 * Supported languages.
	 *
	 * @return array<string, string>
	 */
	private static function get_supported_languages(): array {
		return array(
			'auto'       => __( 'Auto-detect', '4wp-advanced-code' ),
			'php'        => 'PHP',
			'javascript' => 'JavaScript',
			'html'       => 'HTML',
			'css'        => 'CSS',
			'scss'       => 'SCSS',
			'python'     => 'Python',
			'json'       => 'JSON',
			'sql'        => 'SQL',
			'bash'       => 'Bash',
		);
	}
}
