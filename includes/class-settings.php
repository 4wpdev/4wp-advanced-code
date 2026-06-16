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

	private const SETTINGS_PAGE = 'forwp-advanced-code';

	/**
	 * Initialize settings.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_settings_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu.
	 */
	public static function add_settings_page(): void {
		add_options_page(
			__( '4WP Advanced Code Settings', '4wp-advanced-code' ),
			__( '4WP Advanced Code', '4wp-advanced-code' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( self::class, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings(): void {
		add_settings_section(
			'forwp_advanced_code_general',
			__( 'General Settings', '4wp-advanced-code' ),
			null,
			self::SETTINGS_PAGE
		);

		register_setting(
			self::SETTINGS_PAGE,
			'forwp_advanced_code_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			self::SETTINGS_PAGE,
			'forwp_advanced_code_default_language',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_language' ),
				'default'           => 'auto',
			)
		);

		register_setting(
			self::SETTINGS_PAGE,
			'forwp_advanced_code_theme',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_theme' ),
				'default'           => 'light',
			)
		);

		add_settings_field(
			'forwp_advanced_code_enabled',
			__( 'Enable Advanced Features', '4wp-advanced-code' ),
			array( self::class, 'render_enabled_field' ),
			self::SETTINGS_PAGE,
			'forwp_advanced_code_general'
		);

		add_settings_field(
			'forwp_advanced_code_default_language',
			__( 'Default Language', '4wp-advanced-code' ),
			array( self::class, 'render_language_field' ),
			self::SETTINGS_PAGE,
			'forwp_advanced_code_general'
		);

		add_settings_field(
			'forwp_advanced_code_theme',
			__( 'Default Theme', '4wp-advanced-code' ),
			array( self::class, 'render_theme_field' ),
			self::SETTINGS_PAGE,
			'forwp_advanced_code_general'
		);

		add_settings_section(
			'forwp_advanced_code_seo',
			__( 'SEO Settings', '4wp-advanced-code' ),
			null,
			self::SETTINGS_PAGE
		);

		register_setting(
			self::SETTINGS_PAGE,
			'forwp_advanced_code_seo_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		add_settings_field(
			'forwp_advanced_code_seo_enabled',
			__( 'Enable SEO Snippets', '4wp-advanced-code' ),
			array( self::class, 'render_seo_enabled_field' ),
			self::SETTINGS_PAGE,
			'forwp_advanced_code_seo'
		);
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '4WP Advanced Code Settings', '4wp-advanced-code' ); ?></h1>
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
	 * Render enabled field.
	 */
	public static function render_enabled_field(): void {
		$enabled = (bool) get_option( 'forwp_advanced_code_enabled', true );
		?>
		<label for="forwp_advanced_code_enabled">
			<input type="checkbox" id="forwp_advanced_code_enabled" name="forwp_advanced_code_enabled" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Enable advanced features for Code blocks', '4wp-advanced-code' ); ?>
		</label>
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
