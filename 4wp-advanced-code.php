<?php
/**
 * Plugin Name: 4WP Advanced Code
 * Plugin URI: https://github.com/4wpdev/4wp-advanced-code
 * Description: The ultimate SEO & UX-enhanced Code Block for WordPress. Extends core/code blocks with syntax highlighting, copy/share functionality, and JSON-LD structured data.
 * Tags: blocks, code, syntax highlighting, seo, gutenberg, developer, json-ld
 * Version: 0.1.0
 * Author: 4wp.dev
 * Author URI: https://4wp.dev
 * Text Domain: 4wp-advanced-code
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Network: false
 *
 * @package ForWP\Bundle
 */

namespace ForWP\Bundle;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FORWP_ADVANCED_CODE_VERSION', '1.0.0');
define('FORWP_ADVANCED_CODE_PATH', plugin_dir_path(__FILE__));
define('FORWP_ADVANCED_CODE_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class AdvancedCodePlugin
{
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init(): void
    {
        // Load dependencies
        $this->loadDependencies();

        // Initialize components
        BlockWrapper::init();
        SeoHandler::init();
        Settings::init();

        // Register hooks
        add_action('init', [$this, 'registerBlock']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * Load plugin dependencies
     */
    private function loadDependencies(): void
    {
        require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-block-wrapper.php';
        require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-seo-handler.php';
        require_once FORWP_ADVANCED_CODE_PATH . 'includes/class-settings.php';
    }

    /**
     * Register the advanced code block
     */
    public function registerBlock(): void
    {
        register_block_type(FORWP_ADVANCED_CODE_PATH . 'block.json', [
            'render_callback' => [BlockWrapper::class, 'renderBlock']
        ]);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        // Only load on pages with code blocks
        if (!$this->hasAdvancedCodeBlocks()) {
            return;
        }

        // Enqueue Highlight.js from CDN
        wp_enqueue_script(
            '4wp-highlight-js',
            'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js',
            [],
            '11.11.1',
            true
        );

        // Enqueue frontend functionality
        wp_enqueue_script(
            '4wp-advanced-code-frontend',
            FORWP_ADVANCED_CODE_URL . 'assets/frontend.js',
            ['4wp-highlight-js'],
            FORWP_ADVANCED_CODE_VERSION,
            true
        );

        // Enqueue base styles
        wp_enqueue_style(
            '4wp-advanced-code-frontend',
            FORWP_ADVANCED_CODE_URL . 'assets/frontend.css',
            [],
            FORWP_ADVANCED_CODE_VERSION
        );

        // Enqueue theme-specific Highlight.js styles
        $theme = get_option('4wp_advanced_code_theme', 'light');
        $this->enqueueHighlightTheme($theme);
    }

    /**
     * Enqueue Highlight.js theme styles
     */
    private function enqueueHighlightTheme(string $theme): void
    {
        // Use official Highlight.js themes from CDN
        $themeFile = match($theme) {
            'dark' => 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css',
            'terminal' => 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css',
            default => 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github.min.css'
        };

        wp_enqueue_style(
            '4wp-highlight-theme',
            $themeFile,
            ['4wp-advanced-code-frontend'],
            '11.11.1'
        );
    }

    /**
     * Check if current page has advanced code blocks
     */
    private function hasAdvancedCodeBlocks(): bool
    {
        // Simple check - can be optimized later
        global $post;
        
        if (!$post || !has_blocks($post->post_content)) {
            return false;
        }

        // Check if any core/code blocks exist
        return has_block('core/code', $post);
    }

    /**
     * Enqueue editor assets
     */
    public function enqueueEditorAssets(): void
    {
        wp_enqueue_script(
            '4wp-advanced-code-editor',
            FORWP_ADVANCED_CODE_URL . 'build/index.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
            FORWP_ADVANCED_CODE_VERSION,
            true
        );

        wp_enqueue_style(
            '4wp-advanced-code-editor',
            FORWP_ADVANCED_CODE_URL . 'build/style.css',
            [],
            FORWP_ADVANCED_CODE_VERSION
        );
    }
}

// Initialize plugin
AdvancedCodePlugin::getInstance();
