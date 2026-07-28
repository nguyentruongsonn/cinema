<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'blockquote', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'a',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form',
    ];

    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitized-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $document->getElementById('sanitized-root');
        if (! $root) {
            return '';
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            $this->sanitizeAttributes($node, $tag);
            $this->sanitizeChildren($node);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim((string) $element->getAttribute('href')) : '';

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($tag !== 'a') {
            return;
        }

        if ($href === '') {
            return;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        if ($scheme !== '' && ! in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return;
        }

        if ($scheme === '' && ! str_starts_with($href, '/')) {
            return;
        }

        $element->setAttribute('href', $href);
        $element->setAttribute('rel', 'noopener noreferrer');
    }
}
