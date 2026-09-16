/**
 * Security Utilities - Standalone Version
 * Provides safe HTML/DOM/URL helpers without ES module imports.
 *
 * Usage:
 *   <script src="/js/utils/security-standalone.js"></script>
 *   SecurityUtils.escapeHtml(userInput)
 */
(function (window) {
    'use strict';

    const htmlEscapeMap = {
        '&': '\u0026amp;',
        '<': '\u0026lt;',
        '>': '\u0026gt;',
        '"': '\u0026quot;',
        "'": '\u0026#039;'
    };

    const attrEscapeMap = {
        '&': '\u0026amp;',
        '"': '\u0026quot;',
        "'": '\u0026#039;'
    };

    const allowedHtmlTags = ['b', 'i', 'em', 'strong', 'br', 'p', 'span', 'div', 'a', 'ul', 'ol', 'li'];

    const SecurityUtils = {
        /**
         * Escape HTML special characters to prevent XSS.
         * Use when interpolating text into innerHTML templates.
         *
         * @param {*} value
         * @returns {string}
         */
        escapeHtml(value) {
            if (value == null) return '';
            return String(value).replace(/[&<>"']/g, (char) => htmlEscapeMap[char] || char);
        },

        /**
         * Escape HTML attribute values.
         * Use only when dynamic attribute interpolation cannot be avoided.
         *
         * @param {*} value
         * @returns {string}
         */
        escapeAttr(value) {
            if (value == null) return '';
            return String(value).replace(/[&"']/g, (char) => attrEscapeMap[char] || char);
        },

        /**
         * Remove dangerous tags/attributes from limited trusted HTML.
         * Prefer textContent over this for normal user data.
         *
         * @param {string} html
         * @returns {string}
         */
        sanitizeHtml(html) {
            if (!html) return '';

            const doc = new DOMParser().parseFromString(String(html), 'text/html');

            doc.querySelectorAll('script, iframe, object, embed, link, meta, style').forEach((element) => {
                element.remove();
            });

            doc.querySelectorAll('*').forEach((element) => {
                const tagName = element.tagName.toLowerCase();

                if (!allowedHtmlTags.includes(tagName)) {
                    element.replaceWith(...element.childNodes);
                    return;
                }

                Array.from(element.attributes).forEach((attr) => {
                    const name = attr.name.toLowerCase();
                    const value = attr.value || '';

                    if (name.startsWith('on')) {
                        element.removeAttribute(attr.name);
                        return;
                    }

                    if (name === 'href') {
                        const safeHref = SecurityUtils.sanitizeUrl(value, '');
                        if (safeHref) {
                            element.setAttribute('href', safeHref);
                            element.setAttribute('rel', 'noopener noreferrer');
                        } else {
                            element.removeAttribute(attr.name);
                        }
                        return;
                    }

                    if (!['class', 'title', 'aria-label'].includes(name)) {
                        element.removeAttribute(attr.name);
                    }
                });
            });

            return doc.body.innerHTML;
        },

        /**
         * Safely set text content. This is preferred over innerHTML.
         *
         * @param {HTMLElement|null} element
         * @param {*} text
         */
        setTextContent(element, text) {
            if (!element) return;
            element.textContent = text == null ? '' : String(text);
        },

        /**
         * Safely set sanitized HTML.
         * Use trusted=true only for static developer-authored markup.
         *
         * @param {HTMLElement|null} element
         * @param {string} html
         * @param {boolean} trusted
         */
        setInnerHTML(element, html, trusted = false) {
            if (!element) return;
            element.innerHTML = trusted ? String(html || '') : SecurityUtils.sanitizeHtml(html);
        },

        /**
         * Create an element with optional class and safe text content.
         *
         * @param {string} tagName
         * @param {*} text
         * @param {string} className
         * @returns {HTMLElement}
         */
        createElement(tagName, text = '', className = '') {
            const element = document.createElement(tagName);
            if (className) element.className = className;
            SecurityUtils.setTextContent(element, text);
            return element;
        },

        /**
         * Remove all children from an element.
         *
         * @param {HTMLElement|null} element
         */
        clearElement(element) {
            if (!element) return;
            while (element.firstChild) {
                element.removeChild(element.firstChild);
            }
        },

        /**
         * Append multiple children safely, ignoring null/undefined values.
         *
         * @param {HTMLElement} parent
         * @param {...(Node|null|undefined)} children
         */
        appendChildren(parent, ...children) {
            children.forEach((child) => {
                if (child) parent.appendChild(child);
            });
        },

        /**
         * Validate and sanitize URLs.
         * Allows http/https/mailto absolute URLs and root-relative URLs.
         * Blocks javascript:, data:, vbscript:, protocol-relative URLs, and malformed URLs.
         *
         * @param {*} url
         * @param {string} fallback
         * @returns {string}
         */
        sanitizeUrl(url, fallback = '') {
            if (!url) return fallback;

            const urlString = String(url).trim();

            if (urlString.startsWith('/') && !urlString.startsWith('//')) {
                return SecurityUtils.escapeAttr(urlString);
            }

            try {
                const parsed = new URL(urlString, window.location.origin);
                const allowedProtocols = ['http:', 'https:', 'mailto:'];

                if (allowedProtocols.includes(parsed.protocol)) {
                    return SecurityUtils.escapeAttr(parsed.href);
                }
            } catch (error) {
                // Invalid URLs fall through to fallback.
            }

            return fallback;
        },

        /**
         * Safely encode a dynamic URL path segment.
         *
         * @param {*} value
         * @returns {string}
         */
        safePathSegment(value) {
            return encodeURIComponent(String(value ?? ''));
        }
    };

    window.SecurityUtils = SecurityUtils;
})(window);
