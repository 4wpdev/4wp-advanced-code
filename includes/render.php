<?php
/**
 * Advanced Code Block Render Template
 * 
 * @package ForWP\Bundle
 */

namespace ForWP\Bundle;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract attributes
$content = $attributes['content'] ?? '';
$language = $attributes['language'] ?? 'auto';

// Detect language if set to auto
if ($language === 'auto') {
    $language = \ForWP\Bundle\SeoHandler::detectLanguage($content);
}

$theme = $attributes['theme'] ?: get_option('4wp_advanced_code_theme', 'light');
$show_copy = $attributes['showCopy'] ?? true;
$show_share = $attributes['showShare'] ?? true;
$note = $attributes['note'] ?? '';
$seo_enabled = $attributes['seoEnabled'] ?? true;

// Check if advanced features are enabled
$global_enabled = get_option('4wp_advanced_code_enabled', true);
$advanced_enabled = $attributes['advancedEnabled'] ?? true;

if (!$global_enabled || !$advanced_enabled) {
    // Fallback to basic core/code output
    return sprintf('<pre class="wp-block-code"><code>%s</code></pre>', esc_html($content));
}

// Generate unique block ID
$block_id = 'code-block-' . wp_generate_uuid4();

// Build enhanced output
ob_start();
?>
<div class="wp-block-code-advanced" data-theme="<?php echo esc_attr($theme); ?>">
    <?php if ($note): ?>
        <div class="code-note"><?php echo wp_kses_post($note); ?></div>
    <?php endif; ?>
    
    <div class="code-container">
        <div class="code-header">
            <span class="code-language"><?php echo esc_html(ucfirst($language)); ?></span>
            <div class="code-actions">
                <?php if ($show_copy): ?>
                    <button class="code-copy-btn" data-code="<?php echo esc_attr($content); ?>">
                        Copy
                    </button>
                <?php endif; ?>
                
                <?php if ($show_share): ?>
                    <button class="code-share-btn" data-url="<?php echo esc_url(get_permalink() . '#' . $block_id); ?>">
                        Share
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <pre id="<?php echo esc_attr($block_id); ?>"><code class="<?php echo $language !== 'auto' ? 'language-' . esc_attr($language) : ''; ?>"><?php echo esc_html($content); ?></code></pre>
    </div>
</div>
<?php

return ob_get_clean();
