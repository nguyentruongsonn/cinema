export const Security = {
    escapeHtml(unsafe) {
        if (unsafe == null) return '';

        return String(unsafe)
            .replace(/&/g, "\u0026amp;")
            .replace(/</g, "\u0026lt;")
            .replace(/>/g, "\u0026gt;")
            .replace(/"/g, "\u0026quot;")
            .replace(/'/g, "\u0026#039;");
    },

    sanitizeHtml(html) {
        if (!html) return '';

        const allowedTags = ['b', 'i', 'em', 'strong', 'br', 'p', 'span', 'div', 'a', 'ul', 'ol', 'li'];
        const doc = new DOMParser().parseFromString(html, 'text/html');

        const scripts = doc.querySelectorAll('script');
        scripts.forEach(s => s.remove());

        const allElements = doc.querySelectorAll('*');
        allElements.forEach(el => {
            if (!allowedTags.includes(el.tagName.toLowerCase())) {
                el.replaceWith(...el.childNodes);
                return;
            }

            Array.from(el.attributes).forEach(attr => {
                if (attr.name.startsWith('on')) {
                    el.removeAttribute(attr.name);
                }
            });

            if (el.tagName.toLowerCase() === 'a' && el.hasAttribute('href')) {
                const href = el.getAttribute('href');
                if (href && !href.match(/^(https?:\/\/|\/|#)/i)) {
                    el.removeAttribute('href');
                }
            }
        });

        return doc.body.innerHTML;
    },

    setTextContent(element, text) {
        if (!element) return;
        element.textContent = text || '';
    },

    setInnerHTML(element, html, trusted = false) {
        if (!element) return;

        if (trusted) {
            element.innerHTML = html;
        } else {
            element.innerHTML = this.sanitizeHtml(html);
        }
    },

    createElement(tagName, text, className = '') {
        const el = document.createElement(tagName);
        if (className) el.className = className;
        this.setTextContent(el, text);
        return el;
    },

    escapeAttr(value) {
        if (value == null) return '';
        const escapeMap = {
            '&': '&',
            '"': '"',
            "'": '&#039;'
        };
        return String(value).replace(/[&"']/g, (char) => escapeMap[char] || char);
    },

    safePathSegment(value) {
        return encodeURIComponent(String(value ?? ''));
    },

    sanitizeUrl(url, fallback = '') {
        if (!url) return fallback;

        const urlStr = String(url).trim();

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
        }

        return fallback;
    }
};
