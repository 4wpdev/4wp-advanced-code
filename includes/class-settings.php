<?php
/**
 * Settings Class
 * 
 * Manages plugin settings and admin interface
 *
 * @package ForWP\Bundle
 */

namespace ForWP\Bundle;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin settings and admin functionality
 */
class Settings
{
    /**
     * Settings page slug
     */
    private const SETTINGS_PAGE = '4wp-advanced-code';

    /**
     * Initialize settings
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addSettingsPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    /**
     * Add settings page to admin menu
     */
    public static function addSettingsPage(): void
    {
        add_options_page(
            '4WP Advanced Code Settings',
            '4WP Advanced Code',
            'manage_options',
            self::SETTINGS_PAGE,
            [self::class, 'renderSettingsPage']
        );
    }

    /**
     * Register plugin settings
     */
    public static function registerSettings(): void
    {
        // General settings section
        add_settings_section(
            '4wp_general_settings',
            'General Settings',
            null,
            self::SETTINGS_PAGE
        );

        // Enable advanced features
        register_setting(self::SETTINGS_PAGE, '4wp_advanced_code_enabled');
        add_settings_field(
            '4wp_advanced_code_enabled',
            'Enable Advanced Features',
            [self::class, 'renderEnabledField'],
            self::SETTINGS_PAGE,
            '4wp_general_settings'
        );

        // Default language
        register_setting(self::SETTINGS_PAGE, '4wp_advanced_code_default_language');
        add_settings_field(
            '4wp_advanced_code_default_language',
            'Default Language',
            [self::class, 'renderLanguageField'],
            self::SETTINGS_PAGE,
            '4wp_general_settings'
        );

        // Default theme
        register_setting(self::SETTINGS_PAGE, '4wp_advanced_code_theme');
        add_settings_field(
            '4wp_advanced_code_theme',
            'Default Theme',
            [self::class, 'renderThemeField'],
            self::SETTINGS_PAGE,
            '4wp_general_settings'
        );

        // SEO settings section
        add_settings_section(
            '4wp_seo_settings',
            'SEO Settings',
            null,
            self::SETTINGS_PAGE
        );

        // Enable SEO snippets
        register_setting(self::SETTINGS_PAGE, '4wp_advanced_code_seo_enabled');
        add_settings_field(
            '4wp_advanced_code_seo_enabled',
            'Enable SEO Snippets',
            [self::class, 'renderSeoEnabledField'],
            self::SETTINGS_PAGE,
            '4wp_seo_settings'
        );
    }

    /**
     * Render settings page
     */
    public static function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1>4WP Advanced Code Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::SETTINGS_PAGE);
                do_settings_sections(self::SETTINGS_PAGE);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render enabled field
     */
    public static function renderEnabledField(): void
    {
        $enabled = get_option('4wp_advanced_code_enabled', true);
        ?>
        <input type="checkbox" 
               id="4wp_advanced_code_enabled" 
               name="4wp_advanced_code_enabled" 
               value="1" 
               <?php checked($enabled); ?> />
        <label for="4wp_advanced_code_enabled">
            Enable advanced features for Code blocks
        </label>
        <?php
    }

    /**
     * Render language field
     */
    public static function renderLanguageField(): void
    {
        $language = get_option('4wp_advanced_code_default_language', 'auto');
        $languages = self::getSupportedLanguages();
        ?>
        <select id="4wp_advanced_code_default_language" name="4wp_advanced_code_default_language">
            <?php foreach ($languages as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($language, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render theme field
     */
    public static function renderThemeField(): void
    {
        $theme = get_option('4wp_advanced_code_theme', 'light');
        $themes = [
            'light' => 'Light Theme',
            'dark' => 'Dark Theme',
            'terminal' => 'Terminal Theme',
        ];
        ?>
        <select id="4wp_advanced_code_theme" name="4wp_advanced_code_theme">
            <?php foreach ($themes as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($theme, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render SEO enabled field
     */
    public static function renderSeoEnabledField(): void
    {
        $enabled = get_option('4wp_advanced_code_seo_enabled', true);
        ?>
        <input type="checkbox" 
               id="4wp_advanced_code_seo_enabled" 
               name="4wp_advanced_code_seo_enabled" 
               value="1" 
               <?php checked($enabled); ?> />
        <label for="4wp_advanced_code_seo_enabled">
            Generate JSON-LD structured data for code blocks
        </label>
        <?php
    }

    /**
     * Get supported languages
     */
    private static function getSupportedLanguages(): array
    {
        return [
            'auto' => 'Auto-detect',
            'php' => 'PHP',
            'javascript' => 'JavaScript',
            'html' => 'HTML',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'python' => 'Python',
            'json' => 'JSON',
            'sql' => 'SQL',
            'bash' => 'Bash',
        ];
    }
}
