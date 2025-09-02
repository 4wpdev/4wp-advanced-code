/**
 * 4WP Advanced Code - Frontend JavaScript
 * 
 * Handles syntax highlighting, copy functionality, and share features
 */

(function() {
    'use strict';

    /**
     * Initialize when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initializeHighlighting();
        initializeCopyButtons();
        initializeShareButtons();
    });

    /**
     * Initialize syntax highlighting
     */
    function initializeHighlighting() {
        // Check if highlight.js is loaded
        if (typeof hljs === 'undefined') {
            console.warn('4WP Advanced Code: Highlight.js not loaded');
            return;
        }

        // Configure highlight.js
        hljs.configure({
            languages: ['php', 'javascript', 'html', 'css', 'scss', 'python', 'json', 'sql', 'bash']
        });

        // Highlight all code blocks
        document.querySelectorAll('.wp-block-code-advanced pre code').forEach(function(block) {
            // Auto-detect language
            const result = hljs.highlightAuto(block.textContent);
            
            // Update the language indicator
            const languageSpan = block.closest('.code-container').querySelector('.code-language');
            if (languageSpan) {
                languageSpan.textContent = result.language ? result.language.toUpperCase() : 'TEXT';
            }
            
            // Apply highlighting
            block.innerHTML = result.value;
        });
    }

    /**
     * Initialize copy to clipboard functionality
     */
    function initializeCopyButtons() {
        document.querySelectorAll('.code-copy-btn').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const codeText = button.getAttribute('data-code');
                
                if (navigator.clipboard) {
                    // Modern clipboard API
                    navigator.clipboard.writeText(codeText).then(function() {
                        showCopyFeedback(button, 'Copied!');
                    }).catch(function() {
                        fallbackCopyToClipboard(codeText, button);
                    });
                } else {
                    // Fallback for older browsers
                    fallbackCopyToClipboard(codeText, button);
                }
            });
        });
    }

    /**
     * Fallback copy method for older browsers
     */
    function fallbackCopyToClipboard(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopyFeedback(button, 'Copied!');
        } catch (err) {
            showCopyFeedback(button, 'Copy failed');
        }
        
        document.body.removeChild(textArea);
    }

    /**
     * Show copy feedback to user
     */
    function showCopyFeedback(button, message) {
        const originalText = button.textContent;
        button.textContent = message;
        button.disabled = true;
        
        setTimeout(function() {
            button.textContent = originalText;
            button.disabled = false;
        }, 2000);
    }

    /**
     * Initialize share functionality
     */
    function initializeShareButtons() {
        document.querySelectorAll('.code-share-btn').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const shareUrl = button.getAttribute('data-url');
                
                if (navigator.share) {
                    // Native Web Share API
                    navigator.share({
                        title: 'Code Snippet',
                        url: shareUrl
                    }).catch(function(err) {
                        // Fallback to copying URL
                        fallbackShare(shareUrl, button);
                    });
                } else {
                    // Fallback for browsers without Web Share API
                    fallbackShare(shareUrl, button);
                }
            });
        });
    }

    /**
     * Fallback share method - show share options
     */
    function fallbackShare(url, button) {
        // Create share options modal or dropdown
        showShareOptions(url, button);
    }
    
    /**
     * Show share options for the code snippet
     */
    function showShareOptions(url, button) {
        // Create share options container
        const shareContainer = document.createElement('div');
        shareContainer.className = 'share-options';
        shareContainer.innerHTML = `
            <div class="share-options-content">
                <h4>Share Code Snippet</h4>
                <div class="share-options-list">
                    <button class="share-option" data-action="copy-url">
                        📋 Copy Link
                    </button>
                    <button class="share-option" data-action="copy-text">
                        📄 Copy as Text
                    </button>
                    <button class="share-option" data-action="twitter">
                        🐦 Share on Twitter
                    </button>
                    <button class="share-option" data-action="linkedin">
                        💼 Share on LinkedIn
                    </button>
                </div>
                <button class="share-close">✕ Close</button>
            </div>
        `;
        
        // Add styles
        shareContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
        
        const content = shareContainer.querySelector('.share-options-content');
        content.style.cssText = `
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
        `;
        
        // Add event listeners
        shareContainer.querySelectorAll('.share-option').forEach(option => {
            option.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                handleShareAction(action, url, button);
                document.body.removeChild(shareContainer);
            });
        });
        
        shareContainer.querySelector('.share-close').addEventListener('click', function() {
            document.body.removeChild(shareContainer);
        });
        
        // Close on background click
        shareContainer.addEventListener('click', function(e) {
            if (e.target === shareContainer) {
                document.body.removeChild(shareContainer);
            }
        });
        
        document.body.appendChild(shareContainer);
    }
    
    /**
     * Handle different share actions
     */
    function handleShareAction(action, url, button) {
        const codeText = button.closest('.code-container').querySelector('code').textContent;
        
        switch(action) {
            case 'copy-url':
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function() {
                        showCopyFeedback(button, 'Link copied!');
                    });
                } else {
                    fallbackCopyToClipboard(url, button);
                }
                break;
                
            case 'copy-text':
                const shareText = `Check out this code snippet:\n\n${url}\n\nCode:\n${codeText}`;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(shareText).then(function() {
                        showCopyFeedback(button, 'Text copied!');
                    });
                } else {
                    fallbackCopyToClipboard(shareText, button);
                }
                break;
                
            case 'twitter':
                const twitterUrl = `https://twitter.com/intent/tweet?text=Check out this code snippet&url=${encodeURIComponent(url)}`;
                window.open(twitterUrl, '_blank', 'width=600,height=400');
                break;
                
            case 'linkedin':
                const linkedinUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
                window.open(linkedinUrl, '_blank', 'width=600,height=400');
                break;
        }
    }

})();
