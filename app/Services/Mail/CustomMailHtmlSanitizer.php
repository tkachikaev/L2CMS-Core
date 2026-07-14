<?php

namespace App\Services\Mail;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class CustomMailHtmlSanitizer
{
    public const MAX_LENGTH = 200000;

    /** @var array<int, string> */
    private const DROP_WITH_CONTENT = [
        'script', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'textarea', 'select', 'option',
        'canvas', 'svg', 'math', 'video', 'audio', 'source', 'track',
    ];

    /** @return array<int, string> */
    public function violations(string $html): array
    {
        $checks = [
            'PHP code' => '/<\?(?:php|=)?/iu',
            'Blade directives' => '/(?:@php\b|@endphp\b|\{!!|!!\}|\{\{)/iu',
            'scripts' => '/<\s*script\b/iu',
            'embedded frames or objects' => '/<\s*(?:iframe|object|embed|applet)\b/iu',
            'forms and interactive controls' => '/<\s*(?:form|input|button|textarea|select|option)\b/iu',
            'JavaScript event attributes' => '/\son[a-z0-9_-]+\s*=/iu',
            'unsafe URL schemes' => '/(?:javascript|vbscript)\s*:/iu',
            'unsafe CSS expressions' => '/(?:expression\s*\(|behavior\s*:|-moz-binding\s*:)/iu',
            'document base override' => '/<\s*base\b/iu',
            'automatic redirect' => '/<\s*meta\b[^>]*http-equiv\s*=\s*["\']?refresh\b/iu',
        ];

        $found = [];

        foreach ($checks as $label => $pattern) {
            if (preg_match($pattern, $html) === 1) {
                $found[] = $label;
            }
        }

        return $found;
    }

    public function sanitize(string $html): string
    {
        if (! class_exists(DOMDocument::class)) {
            throw new RuntimeException('The PHP DOM extension is required to sanitize custom email HTML.');
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
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            $xpath = new DOMXPath($document);
            $nodes = [];
            foreach ($xpath->query('//*') ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $nodes[] = $node;
                }
            }

            foreach ($nodes as $element) {
                if ($element->parentNode === null) {
                    continue;
                }

                $tag = strtolower($element->tagName);

                if (in_array($tag, self::DROP_WITH_CONTENT, true) || $tag === 'base') {
                    $element->parentNode->removeChild($element);

                    continue;
                }

                if ($tag === 'meta' && strtolower(trim($element->getAttribute('http-equiv'))) === 'refresh') {
                    $element->parentNode->removeChild($element);

                    continue;
                }

                if ($tag === 'style') {
                    $css = $this->sanitizeCss((string) $element->textContent);
                    $element->nodeValue = $css;
                }

                $this->sanitizeAttributes($element);
            }

            $root = $document->documentElement;
            if (! $root instanceof DOMElement) {
                return '';
            }

            $result = $document->saveHTML($root) ?: '';

            return '<!doctype html>'.trim($result);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function plainText(string $html): string
    {
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/isu', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/iu', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(?:p|div|h[1-6]|li|tr|table|section|article)>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $names = [];
        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            if (str_starts_with($lower, 'on') || in_array($lower, ['srcdoc', 'formaction', 'xlink:href'], true)) {
                $element->removeAttribute($name);

                continue;
            }

            if ($lower === 'style') {
                $css = $this->sanitizeCss($element->getAttribute($name));
                $css === '' ? $element->removeAttribute($name) : $element->setAttribute($name, $css);

                continue;
            }

            if (in_array($lower, ['href', 'src', 'background', 'poster'], true)) {
                $url = $this->sanitizeUrl($element->getAttribute($name), $lower === 'src');
                $url === null ? $element->removeAttribute($name) : $element->setAttribute($name, $url);
            }
        }
    }

    private function sanitizeCss(string $css): string
    {
        $css = preg_replace('/expression\s*\([^)]*\)/iu', '', $css) ?? '';
        $css = preg_replace('/(?:behavior|-moz-binding)\s*:[^;}]*/iu', '', $css) ?? $css;
        $css = preg_replace('/url\s*\(\s*["\']?\s*(?:(?:javascript|vbscript)\s*:|data\s*:\s*text\/html)[^)]*\)/iu', '', $css) ?? $css;
        $css = preg_replace('/@import\s+[^;]+;/iu', '', $css) ?? $css;

        return trim($css);
    }

    private function sanitizeUrl(string $url, bool $allowImageData): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/[\x00-\x1F\x7F]+/u', '', $url) ?? '';

        if ($url === '') {
            return null;
        }

        if ($allowImageData && preg_match('~^data:image/(?:png|jpe?g|gif|webp);base64,[a-z0-9+/=\r\n]+$~i', $url) === 1) {
            return $url;
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return $url;
        }

        if (! preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'cid'], true) ? $url : null;
    }
}
