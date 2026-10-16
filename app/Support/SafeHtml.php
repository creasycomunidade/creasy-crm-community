<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Conservative HTML allow-list for administrator-authored public content.
 */
final class SafeHtml
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'blockquote' => ['class'],
        'br' => [],
        'code' => ['class'],
        'del' => ['class'],
        'div' => ['class'],
        'em' => ['class'],
        'h1' => ['class'],
        'h2' => ['class'],
        'h3' => ['class'],
        'h4' => ['class'],
        'h5' => ['class'],
        'h6' => ['class'],
        'hr' => ['class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class', 'loading'],
        'li' => ['class'],
        'ol' => ['class'],
        'p' => ['class'],
        'pre' => ['class'],
        'span' => ['class'],
        'strong' => ['class'],
        'table' => ['class'],
        'tbody' => ['class'],
        'td' => ['class', 'colspan', 'rowspan'],
        'th' => ['class', 'colspan', 'rowspan', 'scope'],
        'thead' => ['class'],
        'tr' => ['class'],
        'u' => ['class'],
        'ul' => ['class'],
    ];

    private const DROP_WITH_CONTENTS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'textarea', 'select', 'option', 'svg', 'math', 'template',
        'link', 'meta', 'base', 'noscript',
    ];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="safe-html-root">'.$html.'</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="safe-html-root"]')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        $nodes = [];
        foreach ($xpath->query('.//*', $root) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        foreach (array_reverse($nodes) as $element) {
            $tag = strtolower($element->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENTS, true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                self::unwrap($element);

                continue;
            }

            $allowedAttributes = self::ALLOWED[$tag];
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (! in_array(strtolower($attribute->name), $allowedAttributes, true)) {
                    $element->removeAttribute($attribute->name);
                }
            }

            if ($element->hasAttribute('href') && ! self::safeLink($element->getAttribute('href'))) {
                $element->removeAttribute('href');
            }
            if ($element->hasAttribute('src') && ! self::safeImage($element->getAttribute('src'))) {
                $element->removeAttribute('src');
            }
            if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent instanceof DOMNode) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function safeLink(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '#') || str_starts_with($value, '/')) {
            return true;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function safeImage(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '/')) {
            return true;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
