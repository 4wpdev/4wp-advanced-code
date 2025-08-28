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
            // Auto-detect language if not specified
            if (!block.className.includes('language-')) {
                hljs.highlightElement(block);
            } else {
                // Use specified language
                hljs.highlightElement(block);
            }
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
     * Fallback share method - copy URL to clipboard
     */
    function fallbackShare(url, button) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                showCopyFeedback(button, 'Link copied!');
            });
        } else {
            // Show share options or copy URL
            const shareText = `Check out this code snippet: ${url}`;
            fallbackCopyToClipboard(shareText, button);
        }
    }

})();
