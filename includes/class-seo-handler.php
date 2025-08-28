<?php
/**
 * SEO Handler Class
 * 
 * Manages JSON-LD generation for code blocks
 *
 * @package ForWP\Bundle
 */

namespace ForWP\Bundle;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO and structured data functionality
 */
class SeoHandler
{
    /**
     * Collected code blocks for JSON-LD
     */
    private static array $codeBlocks = [];

    /**
     * Initialize SEO handler
     */
    public static function init(): void
    {
        // Collect code blocks during page parsing
        add_action('wp_head', [self::class, 'outputJsonLd'], 1);
        add_filter('the_content', [self::class, 'collectCodeBlocks'], 5);
    }

    /**
     * Collect code blocks from content
     */
    public static function collectCodeBlocks(string $content): string
    {
        // Parse blocks from content
        $blocks = parse_blocks($content);
        
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/code') {
                self::processCodeBlock($block);
            }
        }

        return $content;
    }

    /**
     * Process individual code block for SEO
     */
    private static function processCodeBlock(array $block): void
    {
        $attrs = $block['attrs'] ?? [];
        
        // Skip if SEO is disabled for this block
        if (!($attrs['seoEnabled'] ?? true)) {
            return;
        }

        // Extract code content
        $code = $block['innerHTML'] ?? '';
        $code = wp_strip_all_tags($code);
        
        // Build structured data
        $structuredData = [
            '@type' => 'SoftwareSourceCode',
            'codeRepository' => get_permalink(),
            'codeSampleType' => $attrs['seoType'] ?? 'example',
            'programmingLanguage' => $attrs['language'] ?? 'text',
            'text' => $code,
        ];

        // Add optional fields
        if (!empty($attrs['seoTitle'])) {
            $structuredData['name'] = $attrs['seoTitle'];
        }

        if (!empty($attrs['seoDescription'])) {
            $structuredData['description'] = $attrs['seoDescription'];
        }

        // Add author information
        $author = get_the_author_meta('display_name');
        if ($author) {
            $structuredData['author'] = [
                '@type' => 'Person',
                'name' => $author,
            ];
        }

        self::$codeBlocks[] = $structuredData;
    }

    /**
     * Output JSON-LD structured data in head
     */
    public static function outputJsonLd(): void
    {
        // Skip if no code blocks or SEO disabled globally
        if (empty(self::$codeBlocks) || !get_option('4wp_advanced_code_seo_enabled', true)) {
            return;
        }

        // Build main JSON-LD structure
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'mainEntity' => self::$codeBlocks,
        ];

        // Output JSON-LD
        echo '<script type="application/ld+json">';
        echo wp_json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '</script>' . PHP_EOL;

        // Reset for next request
        self::$codeBlocks = [];
    }

    /**
     * Generate code block permalink anchor
     */
    public static function generateCodeAnchor(array $block): string
    {
        $attrs = $block['attrs'] ?? [];
        
        // Use custom slug if provided
        if (!empty($attrs['slug'])) {
            return sanitize_title($attrs['slug']);
        }

        // Generate from language and content hash
        $language = $attrs['language'] ?? 'code';
        $code = wp_strip_all_tags($block['innerHTML'] ?? '');
        $hash = substr(md5($code), 0, 8);

        return sanitize_title($language . '-' . $hash);
    }
}
