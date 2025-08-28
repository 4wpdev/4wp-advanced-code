# 4wp-advanced-code Development Checklist

## 1. Basic Framework ✅

- [x] Create 4wp-advanced-code plugin structure
- [x] Register block wrapper over core/code
- [x] Add render_callback for frontend PHP control
- [x] Set up ForWP\Bundle namespace architecture
- [x] Download and integrate Highlight.js (11.9.0)
- [x] Create block.json for proper block registration
- [x] Implement server-side rendering with render.php

## 2. Frontend — UX & UI ✅

- [x] **Auto-detect language** (Highlight.js integration)
- [x] **Manual language selection** (via inspector controls)
- [x] **Copy to clipboard button**
- [x] **Share code functionality** (link + social media)
- [x] **Additional note field** (above or below block)
- [x] **Custom styling themes:**
  - [x] Light theme
  - [x] Dark theme  
  - [x] Terminal-style theme
- [x] **Frontend JavaScript** (frontend.js with all functionality)
- [x] **Responsive CSS** (frontend.css with mobile support)

## 3. Global Settings (WordPress Admin) ✅

- [x] **Toggle**: Enable Advanced Features for Code block
- [x] **Default language** dropdown
- [x] **Default theme** selection (light/dark/terminal)
- [x] **Enable SEO snippets** checkbox
- [x] **WordPress Settings API** integration
- [x] **Settings page** in Admin → Settings → 4WP Advanced Code

## 4. Editor Settings (Per-Block) ✅

- [x] **Allow/disallow advanced features** for specific blocks
- [x] **Override style** selection (light/dark/terminal)
- [x] **Override language** dropdown
- [x] **SEO snippet settings:**
  - [x] Title field
  - [x] Description field
  - [x] Type selection (example/full)
- [x] **Inspector Controls** with organized panels
- [x] **Editor component** (src/index.js) with full UI
- [x] **Editor styles** (src/editor.scss) with theme preview

## 5. SEO Implementation ✅

- [x] **Generate JSON-LD SoftwareSourceCode** for each block
- [x] **Advanced approach**: Collect all snippets in `<head>`
- [x] **JSON-LD fields implementation:**
  - [x] `programmingLanguage`
  - [x] `codeSampleType` (example/full)
  - [x] `text` (code content)
  - [x] `description` (from individual settings)
  - [x] `author` (global setting)
- [x] **SeoHandler class** with complete implementation
- [x] **Schema.org WebPage** structure with mainEntity

## 6. Bonus Features (Time Permitting)

- [ ] **Expand/Collapse button** (for very long code snippets)
- [ ] **Line numbers toggle**
- [ ] **Copy with formatting** (markdown/html options)
- [ ] **Auto-generate snippet slug** (for direct linking capability)

## 7. Technical Requirements

- [ ] **WordPress compatibility** (6.0+)
- [ ] **PHP 8.0+ support**
- [ ] **No JavaScript errors** in browser console
- [ ] **Accessibility compliance** (ARIA labels, keyboard navigation)
- [ ] **Performance optimization** (minimal asset loading)

## 8. Testing Checklist

- [ ] **Core functionality** works without advanced features enabled
- [ ] **Editor experience** doesn't break existing workflows  
- [ ] **Frontend display** renders correctly across themes
- [ ] **SEO markup** validates with Google's Rich Results Test
- [ ] **Settings persistence** across page reloads
- [ ] **Multisite compatibility** (if applicable)

## 9. Documentation ✅

- [x] Course notes documentation
- [x] Roadmap planning
- [x] Development checklist
- [x] README.md for GitHub
- [x] Code comments in English
- [x] Plugin metadata headers
- [x] Complete docs/ folder structure

## 10. Deployment Preparation

- [ ] **Version numbering** (semantic versioning)
- [ ] **License compliance** (MIT)
- [ ] **Security review** (input sanitization, nonces)
- [ ] **Performance testing** (large pages with multiple code blocks)
- [ ] **Cross-browser testing** (Chrome, Firefox, Safari, Edge)

---

**Priority Order:**
1. Core wrapper functionality
2. Basic frontend enhancements
3. SEO implementation
4. Settings system
5. Bonus features
6. Polish and optimization
