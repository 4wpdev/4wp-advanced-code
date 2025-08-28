# 4wp-advanced-code Roadmap

## 🛠 Weekend Challenge Goals

### Phase 1: Core Foundation
- [ ] Create wrapper over core/code block via block.json → render_callback
- [ ] Global toggle "Enable advanced features for Code block"
- [ ] Basic plugin architecture with ForWP\Bundle namespace

### Phase 2: Frontend Features
- [ ] Auto language detection (Highlight.js integration)
- [ ] Copy button functionality
- [ ] Share button with social media integration
- [ ] Custom styling themes (light/dark/terminal)
- [ ] Additional note display above/below blocks

### Phase 3: Settings System
- [ ] Global settings in WordPress admin
  - [ ] Default language selection
  - [ ] Default theme configuration
  - [ ] Enable/disable SEO snippets
- [ ] Per-block settings in editor
  - [ ] Style override options
  - [ ] Manual language selection
  - [ ] SEO snippet configuration (title, description, type)

### Phase 4: SEO Implementation
- [ ] JSON-LD SoftwareSourceCode generation
- [ ] Inline script output after blocks (MVP approach)
- [ ] Advanced: Aggregate all snippets in `<head>`
- [ ] Schema.org structured data fields:
  - [ ] `programmingLanguage`
  - [ ] `codeSampleType` (example/full)
  - [ ] `text` (code content)
  - [ ] `description` (from settings)
  - [ ] `author` (global configuration)

## 🎯 Bonus Features (Time Permitting)
- [ ] Expand/Collapse for long code snippets
- [ ] Line numbers toggle
- [ ] Copy with formatting (markdown/html)
- [ ] Auto-generated slug for direct linking
- [ ] Code block variations/presets

## 📋 Technical Priorities
1. **Compatibility First** - Don't break existing core/code blocks
2. **Performance** - Minimal impact on page load
3. **Accessibility** - Proper ARIA labels and keyboard navigation
4. **SEO Value** - Meaningful structured data output
5. **User Experience** - Intuitive controls and feedback

## 🔄 Implementation Strategy
- Start with basic wrapper functionality
- Add features incrementally
- Test each component independently
- Focus on SEO integration early
- Optimize performance last
