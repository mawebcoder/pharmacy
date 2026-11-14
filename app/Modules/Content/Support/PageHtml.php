<?php

declare(strict_types=1);

namespace App\Modules\Content\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans a static page body (roadmap D16), on save and again when shown. First strip_tags() keeps only the tags the
 * page editor makes; then Symfony's HtmlSanitizer keeps only the listed attributes per tag, so event handlers
 * (onclick, onerror, …), javascript:/data: URLs, iframes, forms and scripts never survive. Links get
 * rel="noopener noreferrer"; inline style is reduced to text alignment.
 */
final class PageHtml
{
    /**
     * Tag => attributes it may keep.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'p' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'a' => ['href', 'target'],
        'ul' => [],
        'ol' => ['start'],
        'li' => ['style'],
        'blockquote' => [],
        'hr' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan', 'style'],
        'td' => ['colspan', 'rowspan', 'style'],
        'img' => ['src', 'alt', 'width', 'height', 'data-id'],
    ];

    private const int MAX_LENGTH = 500_000;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Elements whose content is code or embedded documents go with their content, not just their tags.
        $html = (string) preg_replace('#<(script|style|iframe|object|embed|noscript|template|svg|math|textarea|select)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $html = strip_tags($html, array_keys(self::ALLOWED));
        $html = self::sanitizer()->sanitize($html);
        $html = self::keepOnlyTextAlign($html);

        return trim(strip_tags($html, ['img'])) === '' && ! str_contains($html, '<img') ? null : $html;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::ALLOWED as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        return new HtmlSanitizer($config);
    }

    /**
     * style="text-align: center; color: red" → style="text-align: center"; any other style is dropped.
     */
    private static function keepOnlyTextAlign(string $html): string
    {
        return (string) preg_replace_callback(
            '/\sstyle="([^"]*)"/i',
            static fn (array $match): string => preg_match('/text-align:\s*(left|right|center|justify|start|end)\b/i', $match[1], $align) === 1
                ? ' style="text-align: '.strtolower($align[1]).'"'
                : '',
            $html,
        );
    }
}
