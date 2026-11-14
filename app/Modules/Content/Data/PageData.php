<?php

declare(strict_types=1);

namespace App\Modules\Content\Data;

use App\Modules\Content\Models\Page;
use App\Modules\Content\Support\PageHtml;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for creating or updating a static page. The body is cleaned (PageHtml); title and SEO texts are plain text
 * with tags stripped (Blade escapes them when shown); the slug is lower-cased; empty SEO fields are null.
 */
final readonly class PageData
{
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $body,
        public bool $isActive,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the pages columns (Page::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): string => PersianNormalizer::normalize(trim(strip_tags((string) ($data[$key] ?? ''))));
        $optional = static fn (string $key): ?string => ($value = $text($key)) === '' ? null : $value;
        $body = $data[Page::COLUMN_BODY] ?? null;

        return new self(
            title: $text(Page::COLUMN_TITLE),
            slug: mb_strtolower(PersianNormalizer::normalize(trim((string) ($data[Page::COLUMN_SLUG] ?? '')))),
            body: is_string($body) ? PageHtml::clean($body) : null,
            isActive: (bool) ($data[Page::COLUMN_IS_ACTIVE] ?? false),
            seoTitle: $optional(Page::COLUMN_SEO_TITLE),
            seoDescription: $optional(Page::COLUMN_SEO_DESCRIPTION),
        );
    }
}
