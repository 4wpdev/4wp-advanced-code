=== 4WP Advanced Code ===
Contributors: 4wpdev, anatolikkk
Tags: code, syntax highlighting, gutenberg, json-ld, developer
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhanced Code block for Gutenberg with syntax highlighting, copy and share controls, and optional SoftwareSourceCode JSON-LD.

== Description ==

**4WP Advanced Code** enhances **core/code** blocks and provides a dedicated **Advanced Code** block with syntax highlighting, copy and share controls, optional notes, and SoftwareSourceCode JSON-LD for technical content.

A plugin by [4wp.dev](https://4wp.dev/). **4WP** is our project brand; the letters "WP" appear only as part of that brand name, not as a reference to WordPress. This plugin is not affiliated with, endorsed, or sponsored by WordPress.

Source code: [github.com/4wpdev/4wp-advanced-code](https://github.com/4wpdev/4wp-advanced-code)

= Key features =

* **Syntax highlighting** — bundled Highlight.js (no CDN on the frontend)
* **core/code wrapper** — upgrade existing Code blocks without re-entering content
* **Advanced Code block** — per-block language, theme, copy/share, and SEO fields
* **JSON-LD** — optional SoftwareSourceCode structured data
* **Settings** — global defaults under Settings → 4WP Advanced Code

= Privacy =

Copy and share actions run in the visitor browser. When share opens Twitter or LinkedIn, the visitor leaves your site to those third-party services. No personal data is sent to 4wp.dev.

== Blocks ==

This plugin provides 1 block.

* **Advanced Code** — syntax-highlighted code with copy, share, and SEO controls.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/4wp-advanced-code/` or install from the Plugins screen.
2. Activate **4WP Advanced Code**.
3. Open **Settings → 4WP Advanced Code** to set defaults.
4. Add a **Code** or **Advanced Code** block in the editor.

== Frequently Asked Questions ==

= Does this replace core/code? =

No. Existing **Code** blocks keep working. When advanced features are enabled, the plugin wraps their output on the frontend.

= Is Highlight.js loaded on every page? =

Only on singular posts/pages that contain a Code or Advanced Code block.

= Can I disable JSON-LD? =

Yes — globally in Settings or per block in the block sidebar.

== Screenshots ==

1. Advanced Code block in the editor with sidebar controls.
2. Frontend code block with copy and share actions.
3. Settings screen — defaults for language, theme, and SEO.

== Changelog ==

= 1.0.1 =
* Review T1: upgrade bundled Highlight.js to 11.11.1; safe JSON-LD output via wp_json_encode (no script-breakout flags).

= 1.0.0 =
* First submission: core/code wrapper, Advanced Code block, bundled Highlight.js, JSON-LD, settings.

== Upgrade Notice ==

= 1.0.1 =
Review fixes: Highlight.js update and JSON-LD escaping.

= 1.0.0 =
First public release.
