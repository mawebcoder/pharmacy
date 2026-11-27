<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Turns a product, brand, category or subcategory description into safe HTML for the storefront. Descriptions written
 * in the admin's rich editor are HTML: only the tags its toolbar makes survive (DescriptionEditor), and Symfony's
 * HtmlSanitizer drops event handlers and javascript:/data: URLs. Older descriptions (and CSV imports) are plain text:
 * they are escaped, a blank line starts a new paragraph and a single line break stays a line break.
 */
final class DescriptionHtml
{
    /**
     * Tag => attributes it may keep.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'p' => [],
        'br' => [],
        'h2' => [],
        'h3' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'a' => ['href', 'target'],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'blockquote' => [],
    ];

    private const int MAX_LENGTH = 100_000;

    public static function toHtml(?string $description): string
    {
        if ($description === null || trim($description) === '') {
            return '';
        }

        if ($description === strip_tags($description)) {
            return self::fromPlainText($description);
        }

        // Elements whose content is code or embedded documents go with their content, not just their tags.
        $html = (string) preg_replace('#<(script|style|iframe|object|embed|noscript|template|svg|math|textarea|select)\b[^>]*>.*?</\1\s*>#is', '', $description);
        $html = strip_tags($html, array_keys(self::ALLOWED));

        return self::sanitizer()->sanitize($html);
    }

    private static function fromPlainText(string $text): string
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim($text)) ?: [];

        return implode('', array_map(
            fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>',
            $paragraphs,
        ));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::ALLOWED as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        return new HtmlSanitizer($config);
    }
}
