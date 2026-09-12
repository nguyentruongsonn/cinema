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
        escapeHtml(value) {
            if (value == null) return '';
            return String(value).replace(/[&<>"']/g, (char) => htmlEscapeMap[char] || char);
        },

        escapeAttr(value) {
            if (value == null) return '';
            return String(value).replace(/[&"']/g, (char) => attrEscapeMap[char] || char);
        },

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

        setTextContent(element, text) {
            if (!element) return;
            element.textContent = text == null ? '' : String(text);
        },

        setInnerHTML(element, html, trusted = false) {
            if (!element) return;
            element.innerHTML = trusted ? String(html || '') : SecurityUtils.sanitizeHtml(html);
        },

        createElement(tagName, text = '', className = '') {
            const element = document.createElement(tagName);
            if (className) element.className = className;
            SecurityUtils.setTextContent(element, text);
            return element;
        },

        clearElement(element) {
            if (!element) return;
            while (element.firstChild) {
                element.removeChild(element.firstChild);
            }
        },

        appendChildren(parent, ...children) {
            children.forEach((child) => {
                if (child) parent.appendChild(child);
            });
        },

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
            }

            return fallback;
        },

        safePathSegment(value) {
            return encodeURIComponent(String(value ?? ''));
        }
    };

    window.SecurityUtils = SecurityUtils;
})(window);
