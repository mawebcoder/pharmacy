<?php

declare(strict_types=1);

namespace App\Modules\Seo\Data;

/**
 * What a page tells search engines and link previews beyond its title (roadmap S38): the description, the share image,
 * the Open Graph type, whether to keep it out of search results, and its structured data (JSON-LD blocks). Pages pass it
 * to the layout as `seo` (->layoutData(['seo' => …])); <x-seo::head> renders it. Every field is optional.
 */
final readonly class SeoMeta
{
    /**
     * @param  list<array<string, mixed>>  $structuredData
     */
    public function __construct(
        public ?string $description = null,
        public ?string $image = null,
        public string $type = 'website',
        public bool $noindex = false,
        public array $structuredData = [],
    ) {}
}
