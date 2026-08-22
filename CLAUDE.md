# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands

```bash
npm run build    # Production build (outputs to build/)
npm start        # Watch mode for development
```

There is no test suite and no lint script configured in `package.json`.

## Plugin Architecture

**4WP Advanced Code** is a WordPress plugin in the `ForWP\AdvancedCode` namespace. All PHP classes use static methods only with an `::init()` bootstrapping pattern — they are never instantiated.

**Main entry point**: `4wp-advanced-code.php` requires all class files and calls `::init()` on each. It also registers the root `forwp/advanced-code` block and enqueues frontend assets conditionally (only on singular posts containing code blocks).

### Module System

`Modules` (`includes/class-modules.php`) controls three feature flags via WordPress options:
- `forwp_ac_module_code` → Code Block module (default: enabled)
- `forwp_ac_module_terminal` → Terminal module (default: enabled)
- `forwp_ac_module_ide` → IDE module (default: disabled, not implemented)

`Blocks::register_blocks()` auto-discovers `build/blocks/*/block.json` and skips any folder whose module is disabled.

### Two Distinct Block Approaches

**1. `forwp/advanced-code` (Advanced Code block)**
- Registered from root `block.json` via `register_block_type()` in the main plugin file
- Editor JS compiled from `src/index.js` → `build/index.js`
- Server-side rendering via `includes/render.php`, which delegates to `Block_Wrapper::build_enhanced_block()`
- Save function returns `null` (dynamic block)

**2. `forwp-advanced-code/terminal` (Terminal block)**
- Registered via `Blocks::register_blocks()` from `build/blocks/terminal/block.json`
- Source in `src/blocks/terminal/`, compiled to `build/blocks/terminal/`
- Has a separate `view.js` (frontend interactive script) in addition to `index.js` (editor)
- The view script receives `forwpAdvancedCodeTerminal.restBase` via `wp_localize_script`

### core/code Block Wrapping

`Block_Wrapper` hooks into `render_block` to intercept `core/code` blocks on the frontend and replace their output with the enhanced markup. This means the plugin enhances both its own `forwp/advanced-code` blocks **and** any existing `core/code` blocks on the site, without requiring content migration.

The render path: `render_block` filter → `Block_Wrapper::render_advanced_code()` → `build_enhanced_block()` → shared HTML output. Both `render.php` and `Block_Wrapper` call `build_enhanced_block()` for a consistent output structure.

### SEO Handler

`Seo_Handler` hooks into `the_content` (priority 5) to parse blocks and collect structured data before output. It then emits a single `<script type="application/ld+json">` in `wp_head` (priority 20) containing a `WebPage` entity with `mainEntity` listing all `SoftwareSourceCode` entries from the page.

Language auto-detection (`Seo_Handler::detect_language()`) is a simple PHP regex check (PHP → JSON → SQL → JS → plaintext). Highlight.js on the frontend does the real visual highlighting.

### Admin Settings

Admin menu slug: `forwp-advanced-code`. Submenus:
- **Settings** (`forwp-advanced-code`) — module toggles
- **Code Block** (`forwp-advanced-code-code`) — default language/theme/SEO toggle
- **Terminal Profiles** (`forwp-advanced-code-terminal`) — JSON profile file manager

Key WordPress options:
- `forwp_ac_module_code`, `forwp_ac_module_terminal`, `forwp_ac_module_ide`
- `forwp_advanced_code_default_language` (default: `auto`)
- `forwp_advanced_code_theme` (default: `light`; values: `light`, `dark`, `terminal`)
- `forwp_advanced_code_seo_enabled` (default: `true`)

### Terminal Profile System

Profiles are JSON files. Two storage tiers:
- **Bundled templates** (read-only): `assets/data/terminal/*.json`
- **Custom profiles** (writable): uploads dir by default (`wp-content/uploads/forwp-advanced-code/terminal/`), configurable via:
  1. `FORWP_AC_TERMINAL_PROFILES_DIR` constant in `wp-config.php` (highest priority, locks the UI field)
  2. `forwp_ac_terminal_profiles_dir` filter
  3. Plugin settings option
  4. Default uploads path

Custom profiles override bundled templates of the same slug. The REST API serves profiles at:
- `GET /wp-json/forwp-advanced-code/v1/terminal-profiles` — list all profiles
- `GET /wp-json/forwp-advanced-code/v1/terminal/{slug}` — load one profile
- `POST /wp-json/forwp-advanced-code/v1/terminal/{slug}` — save custom profile (requires `manage_options`)
- `DELETE /wp-json/forwp-advanced-code/v1/terminal/{slug}` — delete custom profile
- `POST /wp-json/forwp-advanced-code/v1/terminal/{slug}/duplicate` — copy template to new slug

### Challenge / Practice-Case Profile Format

Challenge profiles have `"type": "challenge"` and use a `steps[]` array instead of (or alongside) `commands[]`. Each step:

```json
{
  "id": 1,
  "hint": "Human-readable description of what to do",
  "accepted": ["wp core download", "wp core download --locale=en_US"],
  "output": "Text shown after a correct command",
  "feedback_wrong": "Shown after an incorrect attempt",
  "feedback_correct": "Shown after the step is completed",
  "reveal_after_attempts": 2,
  "command_hint": "wp core download"
}
```

The profile also carries top-level `completion.perfect[]`, `completion.optimize[]`, and `completion.next[]` arrays shown at the end of the challenge. The `related_page` field links to a documentation page on the site.

Regular (non-challenge) profiles use `commands[]` with either `"command"` (exact match) or `"pattern"` (prefix match with `{param}` placeholder in `output`) keys.
