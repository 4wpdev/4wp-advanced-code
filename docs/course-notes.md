# 4wp-advanced-code: Course Notes

## What are Core Blocks?

Core blocks are the fundamental building blocks of WordPress Gutenberg editor. They provide basic functionality like paragraphs, headings, images, and code blocks.

### How to Build a Plugin That Modifies Core Blocks?

The key approach is creating a **wrapper** around existing core blocks rather than replacing them entirely:

1. Register a new block that extends core functionality
2. Use `render_callback` to control frontend output
3. Maintain compatibility with existing core block data
4. Override frontend rendering while preserving editor experience

### Specifics of Block Groups (core/code example)

Core/code block structure:
- Simple textarea input in editor
- Plain `<pre><code>` output on frontend
- Minimal styling and no interactive features
- No language detection or syntax highlighting

## How Can We Extend Block Capabilities?

### Core Block Extension Pattern
The key is creating a **wrapper** that enhances existing functionality without breaking compatibility:

1. **Hook into `render_block` filter** - Intercept core block output
2. **Preserve original data structure** - Maintain editor compatibility
3. **Add custom attributes** - Extend block configuration
4. **Override frontend rendering** - Replace output with enhanced version

### Frontend Enhancement Techniques
- **Syntax Highlighting**: Highlight.js integration with auto-detection
- **Interactive Elements**: Copy/Share buttons with JavaScript
- **Custom Styling**: Theme system (light/dark/terminal)
- **Additional Content**: Notes and metadata display
- **Direct Linking**: Anchor generation for specific blocks

### Editor Integration Strategies
- **Extend Inspector Controls** - Add settings panels
- **Custom Attributes** - Store enhancement configuration
- **Live Preview** - Real-time syntax highlighting in editor
- **Block Variations** - Different presets for common use cases

## SEO: Why Always Consider SEO Perspective?

### Technical Content Discovery
- Code snippets are valuable search content
- Developers frequently search for specific code solutions
- Structured data helps search engines understand code context

### JSON-LD Implementation
- `SoftwareSourceCode` schema markup
- Programming language specification
- Code description and context
- Author attribution

### Search Engine Benefits
- Enhanced snippets in search results
- Better categorization of technical content
- Improved visibility for developer-focused queries
- Rich results for code examples

### Content Strategy
- Code blocks become discoverable assets
- Technical documentation gains search visibility
- Developer audience targeting through structured markup
- Long-tail keyword optimization through code examples

## Technical Implementation Details

### Plugin Architecture Pattern
- **Namespace**: `ForWP\Bundle` - Professional PSR-4 compliant structure
- **Singleton Pattern**: Single plugin instance management
- **Hook-Based**: WordPress filter/action integration
- **Modular Design**: Separate classes for specific functionality

### Core Block Wrapper Implementation
```php
// Hook into WordPress block rendering pipeline
add_filter('render_block', [BlockWrapper::class, 'renderAdvancedCode'], 10, 2);

// Process only core/code blocks
if ($block['blockName'] !== 'core/code') {
    return $blockContent;
}
```

### SEO Integration Strategy
- **JSON-LD in `<head>`**: Centralized structured data output
- **Block Collection**: Parse content before rendering
- **Schema.org SoftwareSourceCode**: Proper code markup
- **Search Engine Optimization**: Enhanced discoverability

### Settings Architecture
- **WordPress Settings API**: Native admin integration
- **Global + Per-Block**: Flexible configuration levels
- **Default Values**: Sensible fallbacks for all options

## Course Focus Areas

1. **Core Block Extension Techniques** - Wrapper pattern mastery
2. **Frontend Enhancement Strategies** - UX improvements
3. **SEO-First Development Approach** - Structured data implementation
4. **WordPress Integration Best Practices** - Hook usage and compatibility

---

*Note: All code comments will be in English throughout the course.*
