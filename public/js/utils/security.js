/**
 * Security utilities for safe HTML manipulation
 * Prevents XSS attacks by sanitizing user input
 */
export const Security = {
    /**
     * Escape HTML special characters to prevent XSS
     * @param {*} unsafe - The unsafe string to escape
     * @returns {string} Escaped string safe for HTML insertion
     */
    escapeHtml(unsafe) {
        if (unsafe == null) return '';

        return String(unsafe)
            .replace(/&/g, "\u0026amp;")
            .replace(/</g, "\u0026lt;")
            .replace(/>/g, "\u0026gt;")
            .replace(/"/g, "\u0026quot;")
            .replace(/'/g, "\u0026#039;");
    },

    /**
     * Sanitize HTML - allow only safe tags
     * Removes script tags and event handlers
     * @param {string} html - The HTML string to sanitize
     * @returns {string} Sanitized HTML
     */
    sanitizeHtml(html) {
        if (!html) return '';

        const allowedTags = ['b', 'i', 'em', 'strong', 'br', 'p', 'span', 'div', 'a', 'ul', 'ol', 'li'];
        const doc = new DOMParser().parseFromString(html, 'text/html');

        // Remove script tags and their content
        const scripts = doc.querySelectorAll('script');
        scripts.forEach(s => s.remove());

        // Remove all elements with disallowed tags and event handlers
        const allElements = doc.querySelectorAll('*');
        allElements.forEach(el => {
            // Remove disallowed tags
            if (!allowedTags.includes(el.tagName.toLowerCase())) {
                el.replaceWith(...el.childNodes);
                return;
            }

            // Remove all event handler attributes
            Array.from(el.attributes).forEach(attr => {
                if (attr.name.startsWith('on')) {
                    el.removeAttribute(attr.name);
                }
            });

            // Sanitize href attributes (only allow safe protocols)
            if (el.tagName.toLowerCase() === 'a' && el.hasAttribute('href')) {
                const href = el.getAttribute('href');
                if (href && !href.match(/^(https?:\/\/|\/|#)/i)) {
                    el.removeAttribute('href');
                }
            }
        });

        return doc.body.innerHTML;
    },

    /**
     * Safe DOM insertion using textContent (no HTML)
     * @param {HTMLElement} element - Target element
     * @param {string} text - Text content to set
     */
    setTextContent(element, text) {
        if (!element) return;
        element.textContent = text || '';
    },

    /**
     * Safe HTML insertion - sanitizes unless explicitly trusted
     * @param {HTMLElement} element - Target element
     * @param {string} html - HTML content to set
     * @param {boolean} trusted - Whether to skip sanitization (use carefully!)
     */
    setInnerHTML(element, html, trusted = false) {
        if (!element) return;

        if (trusted) {
            element.innerHTML = html;
        } else {
            element.innerHTML = this.sanitizeHtml(html);
        }
    },

    /**
     * Create a safe element with text content
     * @param {string} tagName - Tag name for the element
     * @param {string} text - Text content
     * @param {string} className - Optional CSS class
     * @returns {HTMLElement} Created element
     */
    createElement(tagName, text, className = '') {
        const el = document.createElement(tagName);
        if (className) el.className = className;
        this.setTextContent(el, text);
        return el;
    },

    /**
     * Escape HTML attribute values
     * Prevents XSS when inserting dynamic values into HTML attributes
     * @param {*} value - Value to escape for HTML attributes
     * @returns {string} Escaped string safe for attributes
     */
    escapeAttr(value) {
        if (value == null) return '';
        const escapeMap = {
            '&': '&',
            '"': '"',
            "'": '&#039;'
        };
        return String(value).replace(/[&"']/g, (char) => escapeMap[char] || char);
    },

    /**
     * Safely encode path segment for URLs
     * Use this for dynamic URL components like IDs or slugs
     * @param {*} value - Value to encode
     * @returns {string} URL-encoded string
     * @example SecurityUtils.safePathSegment(movieId) // "123"
     * @example SecurityUtils.safePathSegment("hello world") // "hello%20world"
     */
    safePathSegment(value) {
        return encodeURIComponent(String(value ?? ''));
    },

    /**
     * Validate and sanitize URL (enhanced version with fallback)
     * Blocks dangerous protocols like javascript:, data:, etc.
     * @param {string} url - URL to validate
     * @param {string} fallback - Fallback URL if invalid (default: empty string)
     * @returns {string} Safe URL or fallback
     * @example SecurityUtils.sanitizeUrl(userUrl, '/default-image.jpg')
     */
    sanitizeUrl(url, fallback = '') {
        if (!url) return fallback;

        const urlStr = String(url).trim();

        // Allow safe relative URLs and root-relative paths
        if (urlStr.startsWith('/') && !urlStr.startsWith('//')) {
            return this.escapeAttr(urlStr);
        }

        try {
            const parsed = new URL(urlStr, window.location.origin);
            const allowedProtocols = ['http:', 'https:', 'mailto:'];

            if (allowedProtocols.includes(parsed.protocol)) {
                return this.escapeAttr(parsed.href);
            }
        } catch (e) {
            // Invalid URL falls through to fallback
        }

        return fallback;
    }
};

/**
 * Example usage:
 *
 * // Escape user input before displaying
 * const userName = Security.escapeHtml(userInput);
 * element.innerHTML = `<p>Hello ${userName}</p>`;
 *
 * // Or use setTextContent (preferred)
 * Security.setTextContent(element, userInput);
 *
 * // Sanitize HTML from rich text editor
 * const cleanHtml = Security.sanitizeHtml(editorContent);
 * element.innerHTML = cleanHtml;
 */
