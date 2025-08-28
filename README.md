# 4WP Advanced Code

The ultimate SEO & UX-enhanced Code Block for WordPress

## Overview

4WP Advanced Code extends WordPress core/code blocks with advanced features while maintaining full compatibility with existing content. Built with a wrapper pattern approach, it enhances the user experience and search engine optimization without breaking existing functionality.

## ✨ Features

### 🎨 Enhanced User Experience
- **Syntax Highlighting** - Automatic language detection with Highlight.js
- **Interactive Controls** - Copy to clipboard and share functionality  
- **Custom Themes** - Light, dark, and terminal styling options
- **Additional Notes** - Context and explanations above/below code blocks
- **Direct Linking** - Anchor generation for specific code snippets

### 🔍 SEO Optimization
- **JSON-LD Structured Data** - Schema.org SoftwareSourceCode markup
- **Search Engine Discovery** - Enhanced visibility for code snippets
- **Rich Snippets** - Improved search result presentation
- **Technical Content SEO** - Optimized for developer audience

### ⚙️ Flexible Configuration
- **Global Settings** - Default language, theme, and SEO preferences
- **Per-Block Controls** - Individual block customization options
- **WordPress Integration** - Native settings API implementation
- **Backward Compatibility** - Works with existing core/code blocks

## 🚀 Quick Start

1. **Install the plugin** in your WordPress plugins directory
2. **Activate** 4WP Advanced Code
3. **Configure** global settings in WordPress Admin → Settings → 4WP Advanced Code
4. **Start using** enhanced code blocks in your posts and pages

## 🛠 Technical Architecture

### Core Technologies
- **WordPress Block API** - Native Gutenberg integration
- **Highlight.js** - Syntax highlighting and language detection
- **Schema.org** - Structured data implementation
- **PHP 8.0+** - Modern PHP features and performance

### Plugin Structure
```
4wp-advanced-code/
├── 4wp-advanced-code.php          # Main plugin file
├── includes/
│   ├── class-block-wrapper.php    # Core block extension logic
│   ├── class-seo-handler.php      # JSON-LD generation
│   └── class-settings.php         # Admin settings
├── assets/                        # Frontend assets
├── build/                         # Compiled editor assets
└── docs/                          # Development documentation
```

### Key Features Implementation
- **Wrapper Pattern** - Extends core/code via `render_block` filter
- **ForWP\Bundle Namespace** - Professional PSR-4 structure  
- **Hook-Based Architecture** - WordPress action/filter integration
- **Singleton Pattern** - Efficient plugin instance management

## 📋 Supported Languages

- PHP
- JavaScript 
- HTML/CSS
- SCSS
- Python
- JSON
- SQL
- Bash
- Auto-detection for unknown languages

## 🎯 SEO Benefits

### Structured Data Output
```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareSourceCode",
  "programmingLanguage": "php",
  "codeSampleType": "example",
  "text": "<?php echo 'Hello World'; ?>",
  "description": "Simple PHP greeting",
  "author": {
    "@type": "Person", 
    "name": "Author Name"
  }
}
```

### Search Engine Advantages
- Enhanced code snippet discovery
- Rich results in search pages
- Technical content categorization
- Developer audience targeting

## ⚡ Performance

- **Minimal Impact** - Only loads assets when code blocks are present
- **Efficient Rendering** - Optimized block processing pipeline
- **Cached Assets** - Browser-friendly resource loading
- **No Breaking Changes** - Maintains core block compatibility

## 🤝 Contributing

This project serves as both a functional plugin and educational resource for WordPress block development. Focus areas include:

- Core block extension techniques
- SEO-first development approach  
- WordPress integration best practices
- Modern PHP and JavaScript patterns

## 📄 License

MIT License - see LICENSE file for details

## 🔗 Links

- [GitHub Repository](https://github.com/4wpdev/4wp-advanced-code)
- [WordPress Plugin Directory](https://wordpress.org/plugins/4wp-advanced-code) *(coming soon)*
- [Documentation](docs/)

---

**Built with ❤️ for WordPress developers who value both user experience and search engine optimization.**