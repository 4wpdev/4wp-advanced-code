<?php
/**
 * Admin UI: Terminal profile JSON files (one file = one profile).
 *
 * @package ForWP\AdvancedCode
 */

namespace ForWP\AdvancedCode;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level admin page: Terminal Profiles.
 */
class Terminal_Admin {

	public const PAGE_SLUG       = 'forwp-advanced-code-terminal';
	public const SETTINGS_GROUP  = 'forwp-ac-terminal-settings';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu_page' ), 11 );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Register Terminal Profiles under 4WP Advanced Code menu.
	 */
	public static function register_menu_page(): void {
		add_submenu_page(
			Settings::MENU_SLUG,
			__( 'Terminal Profiles', '4wp-advanced-code' ),
			__( 'Terminal Profiles', '4wp-advanced-code' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Register Terminal storage settings.
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			Terminal_Profiles::OPTION_PROFILES_DIR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Terminal_Profiles::class, 'sanitize_profiles_dir_option' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Enqueue admin script on Terminal Profiles page only.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		unset( $hook_suffix );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		wp_enqueue_style(
			'forwp-ac-terminal-profiles-admin',
			FORWP_ADVANCED_CODE_URL . 'assets/admin-terminal-profiles.css',
			array(),
			FORWP_ADVANCED_CODE_VERSION
		);

		wp_enqueue_script( 'wp-api-fetch' );

		wp_enqueue_script(
			'forwp-ac-terminal-profiles-admin',
			FORWP_ADVANCED_CODE_URL . 'assets/admin-terminal-profiles.js',
			array( 'wp-api-fetch' ),
			FORWP_ADVANCED_CODE_VERSION,
			true
		);

		wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( %s ) ); wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( %s ) );',
				wp_json_encode( esc_url_raw( rest_url() ) ),
				wp_json_encode( wp_create_nonce( 'wp_rest' ) )
			),
			'after'
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = Terminal_Profiles::sanitize_admin_tab( (string) ( $_GET['tab'] ?? Terminal_Profiles::TAB_INSTRUCTIONS ) );

		wp_localize_script(
			'forwp-ac-terminal-profiles-admin',
			'forwpAcTerminalAdmin',
			array(
				'restNamespace'      => 'forwp-advanced-code/v1',
				'activeTab'        => $active_tab,
				'schemaHint'         => Terminal_Profiles::SCHEMA_HINT,
				'schemaHintManual'   => Terminal_Profiles::SCHEMA_HINT,
				'schemaHintChallenge'=> Terminal_Profiles::SCHEMA_HINT_CHALLENGE,
				'profilesDir'        => Terminal_Profiles::get_custom_dir_display(),
				'importPostTypes'    => Terminal_Post_Meta::get_post_type_labels(),
				'i18n'               => array(
					'saved'          => __( 'Profile file saved.', '4wp-advanced-code' ),
					'saveFailed'     => __( 'Could not save profile file.', '4wp-advanced-code' ),
					'invalidJson'    => __( 'Invalid JSON syntax.', '4wp-advanced-code' ),
					'needSlug'       => __( 'Enter a profile slug (filename without .json).', '4wp-advanced-code' ),
					'loaded'         => __( 'Profile file loaded into editor.', '4wp-advanced-code' ),
					'loadFailed'     => __( 'Could not load profile file.', '4wp-advanced-code' ),
					'deleted'        => __( 'Profile file deleted.', '4wp-advanced-code' ),
					'deleteFailed'   => __( 'Could not delete profile file.', '4wp-advanced-code' ),
					'duplicated'     => __( 'New profile file created from template.', '4wp-advanced-code' ),
					'duplicateFail'  => __( 'Could not duplicate template.', '4wp-advanced-code' ),
					'needNewSlug'    => __( 'Enter a new slug for the copied profile.', '4wp-advanced-code' ),
					'confirmDelete'  => __( 'Delete this profile file?', '4wp-advanced-code' ),
					'editing'        => __( 'Editing file:', '4wp-advanced-code' ),
					'newProfile'     => __( 'New profile file', '4wp-advanced-code' ),
					'newInstruction' => __( 'New instruction profile', '4wp-advanced-code' ),
					'newPracticeCase'=> __( 'New practice case profile', '4wp-advanced-code' ),
					'previewTitle'   => __( 'Live preview', '4wp-advanced-code' ),
					'previewNote'    => __( 'Updates as you edit JSON — same structure as the Terminal block on the site.', '4wp-advanced-code' ),
					'previewInvalid' => __( 'Fix JSON syntax to see preview.', '4wp-advanced-code' ),
					'previewEmpty'   => __( 'Add commands[] and categories[] to see sidebar groups.', '4wp-advanced-code' ),
					'previewChallenge'=> __( 'Challenge profile — preview shows metadata; steps run in challenge mode on the site.', '4wp-advanced-code' ),
					'import'         => __( 'Import', '4wp-advanced-code' ),
					'importTitle'    => __( 'Import practice case into a post', '4wp-advanced-code' ),
					'importTarget'   => __( 'Destination post type', '4wp-advanced-code' ),
					'importForce'    => __( 'Update existing post if this profile was imported before', '4wp-advanced-code' ),
					'importSubmit'   => __( 'Import now', '4wp-advanced-code' ),
					'importCancel'   => __( 'Cancel', '4wp-advanced-code' ),
					'importSuccess'  => __( 'Profile imported. Terminal data is now stored on the post.', '4wp-advanced-code' ),
					'importFailed'   => __( 'Import failed.', '4wp-advanced-code' ),
					'importedTo'     => __( 'Imported to', '4wp-advanced-code' ),
					'notImported'    => __( 'Not imported yet', '4wp-advanced-code' ),
				),
			)
		);
	}

	/**
	 * Render Terminal Profiles admin page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = Terminal_Profiles::sanitize_admin_tab( (string) ( $_GET['tab'] ?? Terminal_Profiles::TAB_INSTRUCTIONS ) );

		$storage             = Terminal_Profiles::get_storage_info();
		$instruction_profiles = Terminal_Profiles::list_profiles_by_type( Terminal_Profiles::TYPE_MANUAL );
		$practice_profiles   = Terminal_Profiles::list_profiles_by_type( Terminal_Profiles::TYPE_CHALLENGE );
		$dir_option          = get_option( Terminal_Profiles::OPTION_PROFILES_DIR, '' );
		$dir_locked          = Terminal_Profiles::is_profiles_dir_locked();
		$page_url            = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap forwp-ac-terminal-admin">
			<h1><?php esc_html_e( 'Terminal Profiles', '4wp-advanced-code' ); ?></h1>

			<p class="forwp-ac-terminal-admin__lede">
				<?php esc_html_e( 'JSON files are the first source. Import copies a challenge profile into a post — runtime data lives in post meta. The Terminal block reads that snapshot on the frontend; only this admin list remembers which post received each import.', '4wp-advanced-code' ); ?>
			</p>

			<?php self::render_tabs_nav( $page_url, $active_tab ); ?>

			<?php if ( Terminal_Profiles::TAB_SETTINGS === $active_tab ) : ?>
				<?php self::render_tab_settings( $storage, $dir_option, $dir_locked ); ?>
			<?php elseif ( Terminal_Profiles::TAB_PRACTICE_CASES === $active_tab ) : ?>
				<?php self::render_tab_practice_cases( $practice_profiles, $storage ); ?>
			<?php else : ?>
				<?php self::render_tab_instructions( $instruction_profiles, $storage ); ?>
			<?php endif; ?>

			<?php self::render_profile_editor( $storage ); ?>
			<?php self::render_import_dialog(); ?>
		</div>
		<?php
	}

	/**
	 * Tab navigation.
	 *
	 * @param string $page_url   Base admin URL.
	 * @param string $active_tab Active tab slug.
	 */
	private static function render_tabs_nav( string $page_url, string $active_tab ): void {
		?>
		<nav class="nav-tab-wrapper forwp-ac-terminal-admin__tabs" aria-label="<? esc_attr_e( 'Terminal profile sections', '4wp-advanced-code' ); ?>">
			<?php foreach ( Terminal_Profiles::get_admin_tab_labels() as $tab => $label ) : ?>
				<a
					href="<?php echo esc_url( add_query_arg( 'tab', $tab, $page_url ) ); ?>"
					class="nav-tab<?php echo $active_tab === $tab ? ' nav-tab-active' : ''; ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Storage settings tab.
	 *
	 * @param array<string, mixed> $storage    Storage summary.
	 * @param mixed                $dir_option Saved directory option.
	 * @param bool                 $dir_locked Whether path is locked via constant.
	 */
	private static function render_tab_settings( array $storage, $dir_option, bool $dir_locked ): void {
		?>
		<div class="forwp-ac-terminal-admin__panel">
			<h2><?php esc_html_e( 'Profile storage', '4wp-advanced-code' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Bundled templates stay in the plugin (read-only). Custom profiles are saved to a writable directory — required for Bedrock/Roots and similar deploy setups.', '4wp-advanced-code' ); ?>
			</p>

			<form method="post" action="options.php" class="forwp-ac-terminal-admin__storage-form">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="forwp-ac-terminal-profiles-dir"><?php esc_html_e( 'Custom profiles directory', '4wp-advanced-code' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="forwp-ac-terminal-profiles-dir"
								name="<?php echo esc_attr( Terminal_Profiles::OPTION_PROFILES_DIR ); ?>"
								value="<?php echo esc_attr( is_string( $dir_option ) ? $dir_option : '' ); ?>"
								class="large-text code"
								placeholder="<?php echo esc_attr( Terminal_Profiles::default_custom_dir() ); ?>"
								<?php disabled( $dir_locked ); ?>
							/>
							<p class="description">
								<?php esc_html_e( 'Absolute server path. Leave empty for the default uploads folder. One .json file per profile slug.', '4wp-advanced-code' ); ?>
							</p>
							<?php if ( $dir_locked ) : ?>
								<p class="description">
									<?php esc_html_e( 'Locked by FORWP_AC_TERMINAL_PROFILES_DIR in wp-config.php (recommended for Bedrock/Roots).', '4wp-advanced-code' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active storage', '4wp-advanced-code' ); ?></th>
						<td>
							<p style="margin:0.25em 0;">
								<strong><?php esc_html_e( 'Writable profiles:', '4wp-advanced-code' ); ?></strong>
								<code><?php echo esc_html( (string) $storage['profiles_dir_display'] ); ?></code>
							</p>
							<p style="margin:0.25em 0;">
								<strong><?php esc_html_e( 'Templates (read-only):', '4wp-advanced-code' ); ?></strong>
								<code><?php echo esc_html( (string) $storage['templates_label'] ); ?></code>
							</p>
						</td>
					</tr>
				</table>

				<?php if ( ! $dir_locked ) : ?>
					<?php submit_button( __( 'Save storage settings', '4wp-advanced-code' ) ); ?>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Instructions (manual) profiles tab.
	 *
	 * @param array<int, array<string, mixed>> $profiles Profile rows.
	 * @param array<string, mixed>             $storage  Storage summary.
	 */
	private static function render_tab_instructions( array $profiles, array $storage ): void {
		?>
		<div class="forwp-ac-terminal-admin__panel">
			<h2><?php esc_html_e( 'Instruction profiles', '4wp-advanced-code' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Reference terminals with categories and commands (manual mode). Use for documentation pages — compile into a page when ready.', '4wp-advanced-code' ); ?>
			</p>

			<?php self::render_storage_notice( $storage ); ?>

			<p>
				<button type="button" class="button button-primary forwp-ac-terminal-add-new" data-forwp-profile-type="<?php echo esc_attr( Terminal_Profiles::TYPE_MANUAL ); ?>">
					<?php esc_html_e( 'Add instruction profile', '4wp-advanced-code' ); ?>
				</button>
			</p>

			<?php
			self::render_profiles_table(
				$profiles,
				array(
					'file'       => __( 'File', '4wp-advanced-code' ),
					'slug'       => __( 'Slug', '4wp-advanced-code' ),
					'title'      => __( 'Title', '4wp-advanced-code' ),
					'commands'   => __( 'Commands', '4wp-advanced-code' ),
					'categories' => __( 'Categories', '4wp-advanced-code' ),
					'storage'    => __( 'Storage', '4wp-advanced-code' ),
					'actions'    => __( 'Actions', '4wp-advanced-code' ),
				),
				'instructions'
			);
			?>
		</div>
		<?php
	}

	/**
	 * Practice case (challenge) profiles tab.
	 *
	 * @param array<int, array<string, mixed>> $profiles Profile rows.
	 * @param array<string, mixed>             $storage  Storage summary.
	 */
	private static function render_tab_practice_cases( array $profiles, array $storage ): void {
		?>
		<div class="forwp-ac-terminal-admin__panel">
			<h2><?php esc_html_e( 'Practice case profiles', '4wp-advanced-code' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Challenge JSON files (type: challenge). Import copies the full profile into post meta and block content. The post runs independently — only this list keeps a note of where each profile was imported.', '4wp-advanced-code' ); ?>
			</p>

			<?php self::render_storage_notice( $storage ); ?>

			<p>
				<button type="button" class="button button-primary forwp-ac-terminal-add-new" data-forwp-profile-type="<?php echo esc_attr( Terminal_Profiles::TYPE_CHALLENGE ); ?>">
					<?php esc_html_e( 'Add practice case profile', '4wp-advanced-code' ); ?>
				</button>
			</p>

			<?php
			self::render_profiles_table(
				$profiles,
				array(
					'file'       => __( 'File', '4wp-advanced-code' ),
					'slug'       => __( 'Profile slug', '4wp-advanced-code' ),
					'case_slug'  => __( 'Case slug', '4wp-advanced-code' ),
					'title'      => __( 'Title', '4wp-advanced-code' ),
					'steps'      => __( 'Steps', '4wp-advanced-code' ),
					'difficulty' => __( 'Difficulty', '4wp-advanced-code' ),
					'imported'   => __( 'Imported to', '4wp-advanced-code' ),
					'storage'    => __( 'Storage', '4wp-advanced-code' ),
					'actions'    => __( 'Actions', '4wp-advanced-code' ),
				),
				'practice-cases'
			);
			?>
		</div>
		<?php
	}

	/**
	 * Compact storage summary for profile tabs.
	 *
	 * @param array<string, mixed> $storage Storage summary.
	 */
	private static function render_storage_notice( array $storage ): void {
		?>
		<div class="forwp-ac-terminal-admin__storage notice notice-info inline">
			<p style="margin:0.4em 0;">
				<strong><?php esc_html_e( 'Templates:', '4wp-advanced-code' ); ?></strong>
				<code><?php echo esc_html( (string) $storage['templates_label'] ); ?></code>
				— <?php echo esc_html( (string) $storage['templates_count'] ); ?>
				&nbsp;|&nbsp;
				<strong><?php esc_html_e( 'Custom:', '4wp-advanced-code' ); ?></strong>
				<code><?php echo esc_html( (string) $storage['profiles_dir_display'] ); ?></code>
				— <?php echo esc_html( (string) $storage['custom_count'] ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a profile list table.
	 *
	 * @param array<int, array<string, mixed>> $profiles Profile rows.
	 * @param array<string, string>            $columns  Column headers keyed by column id.
	 * @param string                             $context  instructions|practice-cases.
	 */
	private static function render_profiles_table( array $profiles, array $columns, string $context ): void {
		$colspan = count( $columns );
		?>
		<table class="widefat striped forwp-ac-terminal-files">
			<thead>
				<tr>
					<?php foreach ( $columns as $label ) : ?>
						<th><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $profiles ) ) : ?>
					<tr>
						<td colspan="<?php echo esc_attr( (string) $colspan ); ?>">
							<?php esc_html_e( 'No profile files in this section yet.', '4wp-advanced-code' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $profiles as $profile ) : ?>
						<tr data-forwp-profile-row="<?php echo esc_attr( $profile['slug'] ); ?>">
							<td><code><?php echo esc_html( $profile['file'] ); ?></code></td>
							<td><code><?php echo esc_html( $profile['slug'] ); ?></code></td>
							<?php if ( 'practice-cases' === $context ) : ?>
								<td>
									<?php if ( ! empty( $profile['case_slug'] ) ) : ?>
										<code><?php echo esc_html( (string) $profile['case_slug'] ); ?></code>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
							<?php endif; ?>
							<td><?php echo esc_html( $profile['title'] ); ?></td>
							<?php if ( 'instructions' === $context ) : ?>
								<td><?php echo esc_html( (string) ( $profile['commands_count'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $profile['categories_count'] ?? 0 ) ); ?></td>
							<?php else : ?>
								<td><?php echo esc_html( (string) ( $profile['steps_count'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( ! empty( $profile['difficulty'] ) ? (string) $profile['difficulty'] : '—' ); ?></td>
							<?php endif; ?>
							<?php if ( 'practice-cases' === $context ) : ?>
								<td><?php self::render_import_cell( $profile ); ?></td>
							<?php endif; ?>
							<td><?php self::render_storage_badge( $profile ); ?></td>
							<td><?php self::render_row_actions( $profile, $context ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Storage badge for list table.
	 *
	 * @param array<string, mixed> $profile Profile row.
	 */
	private static function render_storage_badge( array $profile ): void {
		if ( 'custom' === ( $profile['source'] ?? '' ) ) {
			if ( ! empty( $profile['overrides_template'] ) ) {
				esc_html_e( 'custom (override)', '4wp-advanced-code' );
			} else {
				esc_html_e( 'custom', '4wp-advanced-code' );
			}
			return;
		}

		esc_html_e( 'template', '4wp-advanced-code' );
	}

	/**
	 * Import destination cell for practice case profiles.
	 *
	 * @param array<string, mixed> $profile Profile row.
	 */
	private static function render_import_cell( array $profile ): void {
		$import = isset( $profile['import'] ) && is_array( $profile['import'] ) ? $profile['import'] : null;

		if ( empty( $import['post_id'] ) ) {
			echo '<span class="description">' . esc_html__( 'Not imported yet', '4wp-advanced-code' ) . '</span>';
			return;
		}

		$title = ! empty( $import['title'] ) ? (string) $import['title'] : '#' . (int) $import['post_id'];
		$links = array();

		if ( ! empty( $import['edit_url'] ) ) {
			$links[] = '<a href="' . esc_url( (string) $import['edit_url'] ) . '">' . esc_html( $title ) . '</a>';
		} else {
			$links[] = esc_html( $title );
		}

		if ( ! empty( $import['permalink'] ) ) {
			$links[] = '<a href="' . esc_url( (string) $import['permalink'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', '4wp-advanced-code' ) . '</a>';
		}

		echo wp_kses_post( implode( ' · ', $links ) );
	}

	/**
	 * Action buttons for one profile row.
	 *
	 * @param array<string, mixed> $profile Profile row.
	 * @param string               $context instructions|practice-cases.
	 */
	private static function render_row_actions( array $profile, string $context = 'instructions' ): void {
		$slug = (string) ( $profile['slug'] ?? '' );
		?>
		<button type="button" class="button button-small forwp-ac-terminal-load-profile" data-forwp-profile="<?php echo esc_attr( $slug ); ?>" data-forwp-profile-type="<?php echo esc_attr( (string) ( $profile['profile_type'] ?? Terminal_Profiles::TYPE_MANUAL ) ); ?>">
			<?php esc_html_e( 'Edit', '4wp-advanced-code' ); ?>
		</button>
		<?php if ( 'practice-cases' === $context ) : ?>
			<button type="button" class="button button-small button-primary forwp-ac-terminal-import-profile" data-forwp-profile="<?php echo esc_attr( $slug ); ?>" data-forwp-profile-title="<?php echo esc_attr( (string) ( $profile['title'] ?? $slug ) ); ?>">
				<?php esc_html_e( 'Import', '4wp-advanced-code' ); ?>
			</button>
		<?php endif; ?>
		<?php if ( 'template' === ( $profile['source'] ?? '' ) ) : ?>
			<button type="button" class="button button-small forwp-ac-terminal-duplicate-profile" data-forwp-profile="<?php echo esc_attr( $slug ); ?>" data-forwp-profile-type="<?php echo esc_attr( (string) ( $profile['profile_type'] ?? Terminal_Profiles::TYPE_MANUAL ) ); ?>">
				<?php esc_html_e( 'Copy as new', '4wp-advanced-code' ); ?>
			</button>
		<?php else : ?>
			<button type="button" class="button button-small forwp-ac-terminal-delete-profile" data-forwp-profile="<?php echo esc_attr( $slug ); ?>">
				<?php esc_html_e( 'Delete file', '4wp-advanced-code' ); ?>
			</button>
		<?php endif; ?>
		<?php
	}

	/**
	 * Shared JSON editor + preview panel.
	 *
	 * @param array<string, mixed> $storage Storage summary.
	 */
	private static function render_profile_editor( array $storage ): void {
		?>
		<div class="forwp-ac-terminal-profile-editor" id="forwp-ac-terminal-editor" hidden>
			<h2 id="forwp-ac-terminal-editor-title"><?php esc_html_e( 'Profile editor', '4wp-advanced-code' ); ?></h2>
			<p id="forwp-ac-terminal-editor-hint" class="description"></p>

			<div class="forwp-ac-terminal-admin__editor-grid">
				<div class="forwp-ac-terminal-admin__editor-main">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="forwp-ac-terminal-slug"><?php esc_html_e( 'File slug', '4wp-advanced-code' ); ?></label>
							</th>
							<td>
								<input type="text" id="forwp-ac-terminal-slug" class="regular-text" placeholder="my-profile-slug" pattern="[a-z0-9-]+" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: profiles directory path */
										esc_html__( 'Becomes {slug}.json in %s. The "profile" field inside JSON is set automatically to match this slug on save.', '4wp-advanced-code' ),
										esc_html( (string) $storage['profiles_dir_display'] )
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="forwp-ac-terminal-json"><?php esc_html_e( 'File contents (JSON)', '4wp-advanced-code' ); ?></label>
							</th>
							<td>
								<textarea id="forwp-ac-terminal-json" rows="22" class="large-text code" spellcheck="false"></textarea>
								<p class="description" id="forwp-ac-terminal-json-status" aria-live="polite"></p>
							</td>
						</tr>
					</table>

					<details style="margin-bottom:1em;">
						<summary><?php esc_html_e( 'JSON structure reference', '4wp-advanced-code' ); ?></summary>
						<pre id="forwp-ac-terminal-schema-hint" style="background:#f6f7f7;padding:12px;overflow:auto;font-size:12px;"><?php echo esc_html( Terminal_Profiles::SCHEMA_HINT ); ?></pre>
					</details>

					<p>
						<button type="button" class="button button-primary" id="forwp-ac-terminal-save">
							<?php esc_html_e( 'Save .json file', '4wp-advanced-code' ); ?>
						</button>
						<button type="button" class="button" id="forwp-ac-terminal-cancel">
							<?php esc_html_e( 'Cancel', '4wp-advanced-code' ); ?>
						</button>
					</p>
				</div>

				<aside class="forwp-ac-terminal-admin__preview-wrap">
					<h3><?php esc_html_e( 'Live preview', '4wp-advanced-code' ); ?></h3>
					<p class="forwp-ac-terminal-admin__preview-note" id="forwp-ac-terminal-preview-note">
						<?php esc_html_e( 'Updates as you edit JSON — same structure as the Terminal block on the site.', '4wp-advanced-code' ); ?>
					</p>
					<div id="forwp-ac-terminal-preview" class="forwp-ac-terminal-admin__preview" aria-live="polite"></div>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Import dialog (practice cases tab).
	 */
	private static function render_import_dialog(): void {
		$post_types = Terminal_Post_Meta::get_post_type_labels();
		?>
		<div id="forwp-ac-terminal-import-dialog" class="forwp-ac-terminal-admin__import-dialog" hidden>
			<div class="forwp-ac-terminal-admin__import-dialog-inner">
				<h2><?php esc_html_e( 'Import practice case into a post', '4wp-advanced-code' ); ?></h2>
				<p class="description" id="forwp-ac-terminal-import-profile-label"></p>
				<p class="description">
					<?php esc_html_e( 'Full terminal JSON is copied into post meta. The post does not reference this JSON file — only this admin list remembers the import destination.', '4wp-advanced-code' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="forwp-ac-terminal-import-post-type"><?php esc_html_e( 'Destination post type', '4wp-advanced-code' ); ?></label>
						</th>
						<td>
							<select id="forwp-ac-terminal-import-post-type">
								<?php foreach ( $post_types as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"<?php selected( 'practice_case', $type ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Existing import', '4wp-advanced-code' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="forwp-ac-terminal-import-force" value="1" />
								<?php esc_html_e( 'Update existing post if this profile was imported before', '4wp-advanced-code' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<p class="forwp-ac-terminal-admin__import-status" id="forwp-ac-terminal-import-status" aria-live="polite"></p>
				<p>
					<button type="button" class="button button-primary" id="forwp-ac-terminal-import-submit">
						<?php esc_html_e( 'Import now', '4wp-advanced-code' ); ?>
					</button>
					<button type="button" class="button" id="forwp-ac-terminal-import-cancel">
						<?php esc_html_e( 'Cancel', '4wp-advanced-code' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}
}
