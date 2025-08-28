# 4wp-advanced-code Development Checklist

## 1. Basic Framework ✅

- [x] Create 4wp-advanced-code plugin structure
- [x] Register block wrapper over core/code
- [x] Add render_callback for frontend PHP control
- [x] Set up ForWP\Bundle namespace architecture

## 2. Frontend — UX & UI

- [ ] **Auto-detect language** (Highlight.js integration)
- [ ] **Manual language selection** (via inspector controls)
- [ ] **Copy to clipboard button**
- [ ] **Share code functionality** (link + social media)
- [ ] **Additional note field** (above or below block)
- [ ] **Custom styling themes:**
  - [ ] Light theme
  - [ ] Dark theme  
  - [ ] Terminal-style theme (optional)

## 3. Global Settings (WordPress Admin)

- [ ] **Toggle**: Enable Advanced Features for Code block
- [ ] **Default language** dropdown
- [ ] **Default theme** selection (light/dark/terminal)
- [ ] **Enable SEO snippets** checkbox

## 4. Editor Settings (Per-Block)

- [ ] **Allow/disallow advanced features** for specific blocks
- [ ] **Override style** selection (light/dark/terminal)
- [ ] **Override language** dropdown
- [ ] **SEO snippet settings:**
  - [ ] Title field
  - [ ] Description field
  - [ ] Type selection (example/full)

## 5. SEO Implementation

- [ ] **Generate JSON-LD SoftwareSourceCode** for each block
- [ ] **Inline script approach** (MVP - after each block)
- [ ] **Advanced option**: Collect all snippets in `<head>`
- [ ] **JSON-LD fields implementation:**
  - [ ] `programmingLanguage`
  - [ ] `codeSampleType` (example/full)
  - [ ] `text` (code content)
  - [ ] `description` (from individual settings)
  - [ ] `author` (global setting)

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

## 9. Documentation

- [x] Course notes documentation
- [x] Roadmap planning
- [x] Development checklist
- [ ] README.md for GitHub
- [ ] Code comments in English
- [ ] Installation instructions

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
