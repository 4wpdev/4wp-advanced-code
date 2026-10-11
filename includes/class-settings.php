<?php
/**
 * Plugin settings screen (4WP admin shell).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings and admin functionality.
 */
class Settings {

	public const MENU_SLUG             = 'forwp-advanced-code';
	public const SETTINGS_SLUG         = 'forwp-advanced-code';
	public const CODE_SETTINGS_SLUG    = 'forwp-advanced-code-code';
	public const DIAGRAMS_SETTINGS_SLUG = 'forwp-advanced-code-diagrams';

	public const TAB_MODULES        = 'modules';
	public const TAB_DOCUMENTATION  = 'documentation';

	private const SETTINGS_PAGE  = 'forwp-advanced-code';
	private const SAVE_MODULES   = 'forwp_ac_save_modules';
	private const SAVE_CODE      = 'forwp_ac_save_code';
	private const SAVE_DIAGRAMS  = 'forwp_ac_save_diagrams';

	/**
	 * Initialize settings.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'add_admin_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_init', array( self::class, 'handle_saves' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Top-level menu: Settings + module submenus (only when that module is On).
	 */
	public static function add_admin_menu(): void {
		add_menu_page(
			__( '4WP Advanced Code', '4wp-advanced-code' ),
			__( '4WP Adv. Code', '4wp-advanced-code' ),
			'manage_options',
			self::MENU_SLUG,
			array( self::class, 'render_settings_page' ),
			'dashicons-editor-code',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', '4wp-advanced-code' ),
			__( 'Settings', '4wp-advanced-code' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( self::class, 'render_settings_page' )
		);

		if ( Modules::is_code_enabled() ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Code Block Settings', '4wp-advanced-code' ),
				__( 'Code Block', '4wp-advanced-code' ),
				'manage_options',
				self::CODE_SETTINGS_SLUG,
				array( self::class, 'render_code_settings_page' )
			);
		}

		if ( Modules::is_diagrams_enabled() ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Diagrams Settings', '4wp-advanced-code' ),
				__( 'Diagrams', '4wp-advanced-code' ),
				'manage_options',
				self::DIAGRAMS_SETTINGS_SLUG,
				array( self::class, 'render_diagrams_settings_page' )
			);
		}
	}

	/**
	 * Register options (still available to WP / REST).
	 */
	public static function register_settings(): void {
		foreach ( Modules::definitions() as $module ) {
			register_setting(
				self::SETTINGS_PAGE,
				$module['option'],
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
					'default'           => true,
				)
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
	}

	/**
	 * Persist module / code settings from shell forms.
	 */
	public static function handle_saves(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! empty( $_POST[ self::SAVE_MODULES ] ) ) {
			check_admin_referer( 'forwp_ac_settings', 'forwp_ac_settings_nonce' );

			foreach ( Modules::definitions() as $module ) {
				$option = $module['option'];
				$on     = ! empty( $_POST[ $option ] );
				update_option( $option, $on ? 1 : 0 );
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::SETTINGS_SLUG,
						'updated' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( ! empty( $_POST[ self::SAVE_CODE ] ) ) {
			check_admin_referer( 'forwp_ac_settings', 'forwp_ac_settings_nonce' );

			$language = isset( $_POST['forwp_advanced_code_default_language'] )
				? self::sanitize_language( wp_unslash( $_POST['forwp_advanced_code_default_language'] ) )
				: 'auto';
			$theme    = isset( $_POST['forwp_advanced_code_theme'] )
				? self::sanitize_theme( wp_unslash( $_POST['forwp_advanced_code_theme'] ) )
				: 'light';

			update_option( 'forwp_advanced_code_default_language', $language );
			update_option( 'forwp_advanced_code_theme', $theme );
			update_option( 'forwp_advanced_code_seo_enabled', ! empty( $_POST['forwp_advanced_code_seo_enabled'] ) ? 1 : 0 );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::CODE_SETTINGS_SLUG,
						'updated' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( ! empty( $_POST[ self::SAVE_DIAGRAMS ] ) ) {
			check_admin_referer( 'forwp_ac_settings', 'forwp_ac_settings_nonce' );

			update_option( Diagram_Settings::OPTION_CREDIT, ! empty( $_POST[ Diagram_Settings::OPTION_CREDIT ] ) ? 1 : 0 );
			update_option( Diagram_Settings::OPTION_SHOW_SOURCE, ! empty( $_POST[ Diagram_Settings::OPTION_SHOW_SOURCE ] ) ? 1 : 0 );
			$align = isset( $_POST[ Diagram_Settings::OPTION_CAPTION_ALIGN ] )
				? Diagram_Settings::sanitize_caption_align( wp_unslash( $_POST[ Diagram_Settings::OPTION_CAPTION_ALIGN ] ) )
				: 'center';
			update_option( Diagram_Settings::OPTION_CAPTION_ALIGN, $align );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::DIAGRAMS_SETTINGS_SLUG,
						'updated' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'forwp-advanced-code' ) ) {
			return;
		}

		$css = FORWP_ADVANCED_CODE_PATH . 'assets/css/admin-shell.css';
		$js  = FORWP_ADVANCED_CODE_PATH . 'assets/js/admin-shell.js';

		wp_enqueue_style(
			'forwp-ac-admin-shell',
			FORWP_ADVANCED_CODE_URL . 'assets/css/admin-shell.css',
			array(),
			(string) filemtime( $css )
		);

		wp_enqueue_script(
			'forwp-ac-admin-shell',
			FORWP_ADVANCED_CODE_URL . 'assets/js/admin-shell.js',
			array(),
			(string) filemtime( $js ),
			true
		);

		wp_localize_script(
			'forwp-ac-admin-shell',
			'forwpAcNotices',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'forwp_ac_dismiss_notice' ),
			)
		);
	}

	/**
	 * Settings: Modules + Documentation tabs.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = self::get_settings_tab();
		?>
		<div class="wrap forwp-ac-admin-shell">
			<?php self::render_flash(); ?>
			<?php self::render_heading(); ?>
			<p class="forwp-ac-admin-lead">
				<?php esc_html_e( 'Enable modules and read short documentation for each one.', '4wp-advanced-code' ); ?>
			</p>

			<?php self::render_settings_tabs( $tab ); ?>

			<div class="forwp-ac-admin-app">
				<?php
				if ( self::TAB_DOCUMENTATION === $tab ) {
					self::render_documentation_tab();
				} else {
					self::render_modules_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * @return string
	 */
	private static function get_settings_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : self::TAB_MODULES;

		return in_array( $tab, array( self::TAB_MODULES, self::TAB_DOCUMENTATION ), true )
			? $tab
			: self::TAB_MODULES;
	}

	/**
	 * @param string $active Active tab slug.
	 */
	private static function render_settings_tabs( string $active ): void {
		$base = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
		$tabs = array(
			self::TAB_MODULES       => __( 'Modules', '4wp-advanced-code' ),
			self::TAB_DOCUMENTATION => __( 'Documentation', '4wp-advanced-code' ),
		);
		?>
		<nav class="nav-tab-wrapper forwp-ac-settings-tabs" aria-label="<?php esc_attr_e( 'Settings sections', '4wp-advanced-code' ); ?>">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a
					href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base ) ); ?>"
					class="nav-tab<?php echo $active === $slug ? ' nav-tab-active' : ''; ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Enable/disable modules (provider-style cards).
	 */
	private static function render_modules_tab(): void {
		?>
		<form method="post">
			<?php wp_nonce_field( 'forwp_ac_settings', 'forwp_ac_settings_nonce' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::SAVE_MODULES ); ?>" value="1" />

			<div class="forwp-ac-intro-card">
				<h2 class="forwp-ac-intro-card__title"><?php esc_html_e( 'Modules', '4wp-advanced-code' ); ?></h2>
				<p class="forwp-ac-intro-card__text">
					<?php esc_html_e( 'Click a card to toggle On / Off, then save. An enabled module gets its own submenu for settings.', '4wp-advanced-code' ); ?>
				</p>
			</div>

			<h3 class="forwp-ac-admin-section-title"><?php esc_html_e( 'Available modules', '4wp-advanced-code' ); ?></h3>
			<p class="forwp-ac-admin-muted"><?php esc_html_e( 'Code, Terminal, IDE, and Diagrams.', '4wp-advanced-code' ); ?></p>

			<div class="forwp-ac-module-grid">
				<?php foreach ( Modules::definitions() as $key => $module ) : ?>
					<?php self::render_module_card( $key, $module ); ?>
				<?php endforeach; ?>
			</div>

			<div class="forwp-ac-form-actions">
				<?php submit_button( __( 'Save modules', '4wp-advanced-code' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Short docs for every module — always listed, On or Off.
	 */
	private static function render_documentation_tab(): void {
		$sections = self::get_documentation_sections();
		?>
		<div class="forwp-ac-intro-card">
			<h2 class="forwp-ac-intro-card__title"><?php esc_html_e( 'Documentation', '4wp-advanced-code' ); ?></h2>
			<p class="forwp-ac-intro-card__text">
				<?php esc_html_e( 'How each module works in the editor. Available whether the module is On or Off.', '4wp-advanced-code' ); ?>
			</p>
		</div>

		<?php foreach ( $sections as $key => $section ) : ?>
			<div class="forwp-ac-panel" id="forwp-ac-docs-<?php echo esc_attr( $key ); ?>">
				<h3 class="forwp-ac-admin-section-title">
					<?php echo esc_html( $section['title'] ); ?>
					<?php if ( ! empty( $section['enabled'] ) ) : ?>
						<span class="forwp-ac-badge forwp-ac-badge--on"><?php esc_html_e( 'On', '4wp-advanced-code' ); ?></span>
					<?php else : ?>
						<span class="forwp-ac-badge forwp-ac-badge--off"><?php esc_html_e( 'Off', '4wp-advanced-code' ); ?></span>
					<?php endif; ?>
				</h3>
				<?php if ( ! empty( $section['lead'] ) ) : ?>
					<p class="forwp-ac-admin-muted"><?php echo esc_html( $section['lead'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $section['steps'] ) ) : ?>
					<ol class="forwp-ac-howto-list">
						<?php foreach ( $section['steps'] as $step ) : ?>
							<li><?php echo esc_html( $step ); ?></li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
				<?php if ( ! empty( $section['code'] ) ) : ?>
					<pre class="forwp-ac-howto-code"><?php echo esc_html( $section['code'] ); ?></pre>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * @return array<string, array{title:string,lead:string,steps:array<int,string>,code?:string,enabled:bool}>
	 */
	private static function get_documentation_sections(): array {
		return array(
			'code'     => array(
				'title'   => __( 'Code Block', '4wp-advanced-code' ),
				'lead'    => __( 'Enhances the core Code block with highlighting, copy/share, and SEO JSON-LD.', '4wp-advanced-code' ),
				'steps'   => array(
					__( 'Insert a Code block (or convert core/code).', '4wp-advanced-code' ),
					__( 'Pick language and theme in the sidebar.', '4wp-advanced-code' ),
					__( 'Optional: SEO title / description for the snippet.', '4wp-advanced-code' ),
				),
				'enabled' => Modules::is_code_enabled(),
			),
			'terminal' => array(
				'title'   => __( 'Terminal', '4wp-advanced-code' ),
				'lead'    => __( 'Interactive CLI simulator driven by JSON profiles (bundled + custom).', '4wp-advanced-code' ),
				'steps'   => array(
					__( 'Insert → 4WP Terminal.', '4wp-advanced-code' ),
					__( 'Choose a profile in the block sidebar.', '4wp-advanced-code' ),
					__( 'Manage custom profiles under the Terminal submenu.', '4wp-advanced-code' ),
				),
				'enabled' => Modules::is_terminal_enabled(),
			),
			'ide'      => array(
				'title'   => __( 'IDE', '4wp-advanced-code' ),
				'lead'    => __( 'VS Code–style tour: one WordPress hook at a time, with a practice editor.', '4wp-advanced-code' ),
				'steps'   => array(
					__( 'Insert → 4WP IDE Wizard.', '4wp-advanced-code' ),
					__( 'Pick hook type and slug in the sidebar.', '4wp-advanced-code' ),
					__( 'Learners step through the tour and practice add_action / add_filter.', '4wp-advanced-code' ),
				),
				'enabled' => Modules::is_ide_enabled(),
			),
			'diagrams' => array(
				'title'   => __( 'Diagrams', '4wp-advanced-code' ),
				'lead'    => __( 'Mermaid diagrams: live preview, inline SVG, PNG for SEO, optional step playback.', '4wp-advanced-code' ),
				'steps'   => array(
					__( 'Insert → 4WP Diagram.', '4wp-advanced-code' ),
					__( 'Paste Mermaid. Optional line: %% steps: A,B,C', '4wp-advanced-code' ),
					__( 'Set Title / Description / Caption, then Update the post.', '4wp-advanced-code' ),
				),
				'code'    => "flowchart TD\n    %% steps: A,B,C\n    A[Start] --> B{Go?}\n    B -->|Yes| C[Done]",
				'enabled' => Modules::is_diagrams_enabled(),
			),
		);
	}

	/**
	 * Code Block module options.
	 */
	public static function render_code_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Modules::is_code_enabled() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) );
			exit;
		}

		$language = get_option( 'forwp_advanced_code_default_language', 'auto' );
		$theme    = get_option( 'forwp_advanced_code_theme', 'light' );
		$seo_on   = (bool) get_option( 'forwp_advanced_code_seo_enabled', true );
		?>
		<div class="wrap forwp-ac-admin-shell">
			<?php self::render_flash(); ?>
			<?php self::render_heading( __( 'Code Block', '4wp-advanced-code' ) ); ?>
			<p class="forwp-ac-admin-lead">
				<?php esc_html_e( 'Defaults for the Advanced Code block: language, theme, and SEO snippets.', '4wp-advanced-code' ); ?>
			</p>

			<div class="forwp-ac-admin-app">
				<form method="post">
					<?php wp_nonce_field( 'forwp_ac_settings', 'forwp_ac_settings_nonce' ); ?>
					<input type="hidden" name="<?php echo esc_attr( self::SAVE_CODE ); ?>" value="1" />

					<div class="forwp-ac-intro-card">
						<h2 class="forwp-ac-intro-card__title"><?php esc_html_e( 'Display defaults', '4wp-advanced-code' ); ?></h2>
						<p class="forwp-ac-intro-card__text">
							<?php esc_html_e( 'Used when a block does not override language or theme.', '4wp-advanced-code' ); ?>
						</p>
					</div>

					<div class="forwp-ac-panel">
						<div class="forwp-ac-field">
							<label class="forwp-ac-field__label" for="forwp_advanced_code_default_language"><?php esc_html_e( 'Default language', '4wp-advanced-code' ); ?></label>
							<select id="forwp_advanced_code_default_language" name="forwp_advanced_code_default_language">
								<?php foreach ( self::get_supported_languages() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $language, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="forwp-ac-field">
							<label class="forwp-ac-field__label" for="forwp_advanced_code_theme"><?php esc_html_e( 'Default theme', '4wp-advanced-code' ); ?></label>
							<select id="forwp_advanced_code_theme" name="forwp_advanced_code_theme">
								<?php
								$themes = array(
									'light'    => __( 'Light', '4wp-advanced-code' ),
									'dark'     => __( 'Dark', '4wp-advanced-code' ),
									'terminal' => __( 'Terminal', '4wp-advanced-code' ),
								);
								foreach ( $themes as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $theme, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="forwp-ac-panel">
						<h2 class="forwp-ac-admin-section-title"><?php esc_html_e( 'SEO', '4wp-advanced-code' ); ?></h2>
						<label class="forwp-ac-toggle-row">
							<input type="checkbox" name="forwp_advanced_code_seo_enabled" value="1" <?php checked( $seo_on ); ?> />
							<span>
								<strong><?php esc_html_e( 'Enable SEO snippets', '4wp-advanced-code' ); ?></strong><br />
								<span class="forwp-ac-admin-muted"><?php esc_html_e( 'JSON-LD for code blocks, diagrams (ImageObject), Terminal and IDE learning resources.', '4wp-advanced-code' ); ?></span>
							</span>
						</label>
					</div>

					<div class="forwp-ac-form-actions">
						<?php submit_button( __( 'Save settings', '4wp-advanced-code' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Diagrams module page: options, build examples, future patterns / AI stubs.
	 */
	public static function render_diagrams_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Modules::is_diagrams_enabled() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) );
			exit;
		}

		$credit      = Diagram_Settings::is_credit_enabled();
		$show_source = Diagram_Settings::is_source_visible();
		$caption_align = Diagram_Settings::caption_align();
		$examples    = self::get_diagram_build_examples();
		$align_opts  = array(
			'left'   => array(
				'label' => __( 'Left', '4wp-advanced-code' ),
				'icon'  => 'dashicons-editor-alignleft',
			),
			'center' => array(
				'label' => __( 'Center', '4wp-advanced-code' ),
				'icon'  => 'dashicons-editor-aligncenter',
			),
			'right'  => array(
				'label' => __( 'Right', '4wp-advanced-code' ),
				'icon'  => 'dashicons-editor-alignright',
			),
		);
		?>
		<div class="wrap forwp-ac-admin-shell">
			<?php self::render_flash(); ?>
			<?php self::render_heading( __( 'Diagrams', '4wp-advanced-code' ) ); ?>
			<p class="forwp-ac-admin-lead">
				<?php esc_html_e( 'Mermaid diagrams in posts: options, build examples, and room for patterns later.', '4wp-advanced-code' ); ?>
			</p>

			<div class="forwp-ac-admin-app">
				<form method="post">
					<?php wp_nonce_field( 'forwp_ac_settings', 'forwp_ac_settings_nonce' ); ?>
					<input type="hidden" name="<?php echo esc_attr( self::SAVE_DIAGRAMS ); ?>" value="1" />

					<div class="forwp-ac-intro-card">
						<h2 class="forwp-ac-intro-card__title"><?php esc_html_e( 'Personalization', '4wp-advanced-code' ); ?></h2>
						<p class="forwp-ac-intro-card__text">
							<?php esc_html_e( 'Brand mark on exported images, schema credit, and optional front-end source.', '4wp-advanced-code' ); ?>
						</p>
					</div>

					<div class="forwp-ac-panel">
						<label class="forwp-ac-toggle-row">
							<input type="checkbox" name="<?php echo esc_attr( Diagram_Settings::OPTION_CREDIT ); ?>" value="1" <?php checked( $credit ); ?> />
							<span>
								<strong><?php esc_html_e( 'Brand watermark (Site Identity)', '4wp-advanced-code' ); ?></strong><br />
								<span class="forwp-ac-admin-muted"><?php esc_html_e( 'Draw the site name on new diagram PNGs (corner mark). Same name goes into ImageObject creator / creditText. Code-block images will use this later. Off by default — re-save a diagram after enabling.', '4wp-advanced-code' ); ?></span>
							</span>
						</label>
						<label class="forwp-ac-toggle-row">
							<input type="checkbox" name="<?php echo esc_attr( Diagram_Settings::OPTION_SHOW_SOURCE ); ?>" value="1" <?php checked( $show_source ); ?> />
							<span>
								<strong><?php esc_html_e( 'Show diagram code on the front end', '4wp-advanced-code' ); ?></strong><br />
								<span class="forwp-ac-admin-muted"><?php esc_html_e( 'Adds a collapsible “Diagram code” block with the Mermaid source under each diagram. Off by default.', '4wp-advanced-code' ); ?></span>
							</span>
						</label>

						<div class="forwp-ac-field forwp-ac-field--align">
							<span class="forwp-ac-field__label"><?php esc_html_e( 'Caption alignment', '4wp-advanced-code' ); ?></span>
							<p class="forwp-ac-admin-muted"><?php esc_html_e( 'Default position of figcaption under every diagram.', '4wp-advanced-code' ); ?></p>
							<div class="forwp-ac-align-buttons" role="group" aria-label="<?php esc_attr_e( 'Caption alignment', '4wp-advanced-code' ); ?>">
								<?php foreach ( $align_opts as $value => $opt ) : ?>
									<label class="forwp-ac-align-btn<?php echo $caption_align === $value ? ' is-active' : ''; ?>">
										<input
											type="radio"
											name="<?php echo esc_attr( Diagram_Settings::OPTION_CAPTION_ALIGN ); ?>"
											value="<?php echo esc_attr( $value ); ?>"
											<?php checked( $caption_align, $value ); ?>
										/>
										<span class="dashicons <?php echo esc_attr( $opt['icon'] ); ?>" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php echo esc_html( $opt['label'] ); ?></span>
										<span class="forwp-ac-align-btn__label" aria-hidden="true"><?php echo esc_html( $opt['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="forwp-ac-form-actions">
						<?php submit_button( __( 'Save settings', '4wp-advanced-code' ), 'primary', 'submit', false ); ?>
					</div>
				</form>

				<div class="forwp-ac-panel forwp-ac-panel--muted">
					<h2 class="forwp-ac-admin-section-title"><?php esc_html_e( 'Patterns & AI drafts', '4wp-advanced-code' ); ?></h2>
					<p class="forwp-ac-admin-muted">
						<?php esc_html_e( 'Coming later: reusable Mermaid patterns and optional AI-generated starters. This page is the home for that.', '4wp-advanced-code' ); ?>
					</p>
					<p class="forwp-ac-placeholder-note">
						<?php esc_html_e( 'Placeholder — nothing to configure yet.', '4wp-advanced-code' ); ?>
					</p>
				</div>

				<div class="forwp-ac-intro-card">
					<h2 class="forwp-ac-intro-card__title"><?php esc_html_e( 'How to build', '4wp-advanced-code' ); ?></h2>
					<p class="forwp-ac-intro-card__text">
						<?php esc_html_e( 'Copy an example into the Diagram block. Optional: %% steps: id1,id2 for front-end playback.', '4wp-advanced-code' ); ?>
					</p>
				</div>

				<?php foreach ( $examples as $example ) : ?>
					<div class="forwp-ac-panel">
						<h3 class="forwp-ac-admin-section-title"><?php echo esc_html( $example['title'] ); ?></h3>
						<?php if ( ! empty( $example['note'] ) ) : ?>
							<p class="forwp-ac-admin-muted"><?php echo esc_html( $example['note'] ); ?></p>
						<?php endif; ?>
						<pre class="forwp-ac-howto-code"><?php echo esc_html( $example['code'] ); ?></pre>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Mermaid starters shown on the Diagrams admin page.
	 *
	 * @return array<int, array{title:string,note:string,code:string}>
	 */
	private static function get_diagram_build_examples(): array {
		return array(
			array(
				'title' => __( 'Flowchart + steps', '4wp-advanced-code' ),
				'note'  => __( 'Node ids in %% steps: drive the play / pause animation on the front end.', '4wp-advanced-code' ),
				'code'  => "flowchart TD\n    %% steps: A,B,C\n    A[Start] --> B{Go?}\n    B -->|Yes| C[Done]\n    B -->|No| A",
			),
			array(
				'title' => __( 'Sequence diagram', '4wp-advanced-code' ),
				'note'  => __( 'Good for request / response flows between actors.', '4wp-advanced-code' ),
				'code'  => "sequenceDiagram\n    participant U as User\n    participant W as WordPress\n    participant D as Diagram\n    U->>W: Insert Diagram block\n    W->>D: Render Mermaid\n    D-->>U: SVG + PNG",
			),
			array(
				'title' => __( 'State diagram', '4wp-advanced-code' ),
				'note'  => __( 'Simple state machine for content or plugin lifecycle.', '4wp-advanced-code' ),
				'code'  => "stateDiagram-v2\n    [*] --> Draft\n    Draft --> Review\n    Review --> Published\n    Published --> [*]",
			),
		);
	}

	/**
	 * @param string               $key    Module key.
	 * @param array<string,string> $module Module definition.
	 */
	private static function render_module_card( string $key, array $module ): void {
		$option  = $module['option'];
		$enabled = self::is_module_option_enabled( $option );
		$classes = 'forwp-ac-module-card' . ( $enabled ? ' is-enabled' : '' );
		?>
		<div
			class="<?php echo esc_attr( $classes ); ?>"
			role="button"
			tabindex="0"
			data-module="<?php echo esc_attr( $key ); ?>"
			aria-pressed="<?php echo $enabled ? 'true' : 'false'; ?>"
		>
			<input
				class="forwp-ac-module-card__check"
				type="checkbox"
				id="<?php echo esc_attr( $option ); ?>"
				name="<?php echo esc_attr( $option ); ?>"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<div class="forwp-ac-module-card__head">
				<div>
					<div class="forwp-ac-module-card__label"><?php echo esc_html( $module['label'] ); ?></div>
					<div class="forwp-ac-module-card__slug"><code><?php echo esc_html( $key ); ?></code></div>
				</div>
				<div class="forwp-ac-module-card__badges">
					<span class="forwp-ac-badge forwp-ac-badge--on" <?php echo $enabled ? '' : 'hidden'; ?>><?php esc_html_e( 'On', '4wp-advanced-code' ); ?></span>
					<span class="forwp-ac-badge forwp-ac-badge--off" <?php echo $enabled ? 'hidden' : ''; ?>><?php esc_html_e( 'Off', '4wp-advanced-code' ); ?></span>
				</div>
			</div>
			<p class="forwp-ac-module-card__status"><?php echo esc_html( $module['description'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param string $option Module option key.
	 */
	private static function is_module_option_enabled( string $option ): bool {
		if ( Modules::OPTION_CODE === $option ) {
			return Modules::is_code_enabled();
		}
		if ( Modules::OPTION_TERMINAL === $option ) {
			return Modules::is_terminal_enabled();
		}
		if ( Modules::OPTION_IDE === $option ) {
			return Modules::is_ide_enabled();
		}
		if ( Modules::OPTION_DIAGRAMS === $option ) {
			return Modules::is_diagrams_enabled();
		}

		return (bool) get_option( $option, true );
	}

	/**
	 * @param string $part Optional section after the plugin name (e.g. Diagrams).
	 */
	private static function render_heading( string $part = '' ): void {
		$plugin  = __( '4WP Advanced Code', '4wp-advanced-code' );
		$version = defined( 'FORWP_ADVANCED_CODE_VERSION' ) ? FORWP_ADVANCED_CODE_VERSION : '';
		$part    = trim( $part );
		?>
		<h1 class="forwp-ac-admin-heading">
			<span class="forwp-ac-admin-heading__icon" aria-hidden="true"><span class="dashicons dashicons-editor-code"></span></span>
			<span class="forwp-ac-admin-heading__text">
				<span class="forwp-ac-admin-heading__plugin"><?php echo esc_html( $plugin ); ?></span>
				<?php if ( '' !== $part ) : ?>
					<span class="forwp-ac-admin-heading__sep" aria-hidden="true">·</span>
					<span class="forwp-ac-admin-heading__part"><?php echo esc_html( $part ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $version ) : ?>
					<span class="forwp-ac-admin-heading__version"><?php echo esc_html( 'v' . $version ); ?></span>
				<?php endif; ?>
			</span>
		</h1>
		<?php
	}

	/**
	 * Saved flash notice.
	 */
	private static function render_flash(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flash flag.
		if ( empty( $_GET['updated'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', '4wp-advanced-code' ) . '</p></div>';
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
			'auto'        => __( 'Auto-detect', '4wp-advanced-code' ),
			'php'         => 'PHP',
			'javascript'  => 'JavaScript',
			'typescript'  => 'TypeScript',
			'html'        => 'HTML',
			'css'         => 'CSS',
			'scss'        => 'SCSS',
			'python'      => 'Python',
			'json'        => 'JSON',
			'sql'         => 'SQL',
			'bash'        => 'Bash / Shell',
			'powershell'  => 'PowerShell',
			'yaml'        => 'YAML',
			'xml'         => 'XML',
			'markdown'    => 'Markdown',
			'plaintext'   => 'Plain text',
			'mermaid'     => 'Mermaid',
		);
	}
}
