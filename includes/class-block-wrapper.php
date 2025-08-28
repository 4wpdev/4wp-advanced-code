<?php
/**
 * Block Wrapper Class
 * 
 * Handles the core block extension and rendering logic
 *
 * @package ForWP\Bundle
 */

namespace ForWP\Bundle;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core block wrapper functionality
 */
class BlockWrapper
{
    /**
     * Initialize wrapper
     */
    public static function init(): void
    {
        // Hook into block rendering
        add_filter('render_block', [self::class, 'renderAdvancedCode'], 10, 2);
    }

    /**
     * Render enhanced code block
     */
    public static function renderAdvancedCode(string $blockContent, array $block): string
    {
        // Only process core/code blocks
        if ($block['blockName'] !== 'core/code') {
            return $blockContent;
        }

        // Check if advanced features are enabled
        if (!self::isAdvancedEnabled($block)) {
            return $blockContent;
        }

        // Extract code content
        $code = self::extractCodeContent($blockContent);
        
        // Build enhanced wrapper
        return self::buildEnhancedBlock($code, $block);
    }

    /**
     * Check if advanced features are enabled for this block
     */
    private static function isAdvancedEnabled(array $block): bool
    {
        // Check global setting
        $globalEnabled = get_option('4wp_advanced_code_enabled', true);
        
        // Check per-block setting
        $blockEnabled = $block['attrs']['advancedEnabled'] ?? true;
        
        return $globalEnabled && $blockEnabled;
    }

    /**
     * Extract code content from block HTML
     */
    private static function extractCodeContent(string $blockContent): string
    {
        // Parse code from <pre><code> tags
        preg_match('/<code[^>]*>(.*?)<\/code>/s', $blockContent, $matches);
        return $matches[1] ?? '';
    }

    /**
     * Build enhanced code block HTML
     */
    private static function buildEnhancedBlock(string $code, array $block): string
    {
        $attrs = $block['attrs'] ?? [];
        
        // Get block settings
        $language = $attrs['language'] ?? 'auto';
        $theme = $attrs['theme'] ?? get_option('4wp_advanced_code_theme', 'light');
        $showCopy = $attrs['showCopy'] ?? true;
        $showShare = $attrs['showShare'] ?? true;
        $note = $attrs['note'] ?? '';

        // Build block ID for linking
        $blockId = 'code-block-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="wp-block-code-advanced" data-theme="<?php echo esc_attr($theme); ?>">
            <?php if ($note): ?>
                <div class="code-note"><?php echo wp_kses_post($note); ?></div>
            <?php endif; ?>
            
            <div class="code-container">
                <div class="code-header">
                    <span class="code-language"><?php echo esc_html($language); ?></span>
                    <div class="code-actions">
                        <?php if ($showCopy): ?>
                            <button class="code-copy-btn" data-code="<?php echo esc_attr($code); ?>">
                                Copy
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($showShare): ?>
                            <button class="code-share-btn" data-url="<?php echo esc_url(get_permalink() . '#' . $blockId); ?>">
                                Share
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <pre id="<?php echo esc_attr($blockId); ?>"><code class="language-<?php echo esc_attr($language); ?>"><?php echo esc_html($code); ?></code></pre>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
