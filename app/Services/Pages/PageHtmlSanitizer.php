<?php

namespace App\Services\Pages;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

final class PageHtmlSanitizer
{
    private const MAX_LENGTH = 200000;

    private const ALLOWED_ELEMENTS = [
        'p', 'br', 'strong', 'em', 'u', 's',
        'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'blockquote', 'a', 'hr',
        'figure', 'img', 'figcaption', 'span',
        'pre', 'code',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'svg', 'math', 'form', 'input', 'button', 'textarea', 'select',
        'video', 'audio', 'source', 'track', 'canvas', 'template', 'noscript',
        'meta', 'link', 'base', 'head', 'title',
    ];

    private const ALIGNABLE_ELEMENTS = ['p', 'h2', 'h3', 'h4', 'blockquote', 'figure'];

    private const COLORS = ['default', 'gold', 'red', 'green', 'blue', 'muted'];

    private const ALIGNMENTS = ['left', 'center', 'right'];

    public function sanitize(string $html): string
    {
        if (! class_exists(DOMDocument::class)) {
            throw new RuntimeException('The PHP DOM extension is required to sanitize page HTML.');
        }

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (strlen($html) > self::MAX_LENGTH) {
            $html = substr($html, 0, self::MAX_LENGTH);
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body><div id="l2forge-page-root">'.$html.'</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            $xpath = new DOMXPath($document);
            $root = $xpath->query('//*[@id="l2forge-page-root"]')->item(0);
            if (! $root instanceof DOMElement) {
                return '';
            }

            $this->sanitizeChildren($root);

            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $document->saveHTML($child) ?: '';
            }

            return trim($result);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function plainText(string $html): string
    {
        $safe = $this->sanitize($html);
        $safe = preg_replace('/<br\s*\/?>/i', "\n", $safe) ?? $safe;
        $safe = preg_replace('/<\/(p|h2|h3|h4|li|blockquote|figcaption|pre)>/i', "\n", $safe) ?? $safe;
        $safe = html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safe = preg_replace('/[\t ]+/u', ' ', $safe) ?? $safe;
        $safe = preg_replace('/\n{3,}/u', "\n\n", $safe) ?? $safe;

        return trim($safe);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMComment) {
                $parent->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($child);

                continue;
            }

            $this->sanitizeChildren($child);

            if (! in_array($tag, self::ALLOWED_ELEMENTS, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->sanitizeAttributes($child, $tag);
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'title'],
            'img' => ['src', 'alt'],
            'span' => ['data-color'],
            default => in_array($tag, self::ALIGNABLE_ELEMENTS, true) ? ['data-align'] : [],
        };

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $name) {
            if (! in_array(strtolower($name), $allowed, true)) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a') {
            $href = $this->sanitizeLink($element->getAttribute('href'));
            if ($href === null) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('href', $href);
            }

            if ($element->hasAttribute('title')) {
                $title = $this->cleanTextAttribute($element->getAttribute('title'), 255);
                $title === '' ? $element->removeAttribute('title') : $element->setAttribute('title', $title);
            }
        }

        if ($tag === 'img') {
            $src = $this->sanitizeImageSource($element->getAttribute('src'));
            if ($src === null) {
                $element->parentNode?->removeChild($element);

                return;
            }

            $element->setAttribute('src', $src);
            $element->setAttribute('alt', $this->cleanTextAttribute($element->getAttribute('alt'), 255));
            $element->setAttribute('loading', 'lazy');
            $element->setAttribute('decoding', 'async');
        }

        if ($tag === 'span') {
            $color = strtolower($element->getAttribute('data-color'));
            if (! in_array($color, self::COLORS, true) || $color === 'default') {
                $element->removeAttribute('data-color');
            } else {
                $element->setAttribute('data-color', $color);
            }
        }

        if (in_array($tag, self::ALIGNABLE_ELEMENTS, true)) {
            $alignment = strtolower($element->getAttribute('data-align'));
            if (! in_array($alignment, self::ALIGNMENTS, true) || $alignment === 'left') {
                $element->removeAttribute('data-align');
            } else {
                $element->setAttribute('data-align', $alignment);
            }
        }
    }

    private function sanitizeLink(string $href): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $href = preg_replace('/[\x00-\x1F\x7F]+/u', '', $href) ?? '';

        if ($href === '' || str_starts_with($href, '//') || str_contains($href, '\\')) {
            return null;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/') || str_starts_with($href, '?')) {
            return $href;
        }

        if (! preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
            return $href;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $href : null;
    }

    private function sanitizeImageSource(string $src): ?string
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $src = preg_replace('/[\x00-\x1F\x7F]+/u', '', $src) ?? '';

        if (! preg_match('~^/uploads/pages/content/\d{4}/\d{2}/[a-f0-9-]+\.(?:jpe?g|png|webp)$~i', $src)) {
            return null;
        }

        return $src;
    }

    private function cleanTextAttribute(string $value, int $limit): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_substr(trim($value), 0, $limit);
    }
}
