<?php

declare(strict_types=1);

namespace App\Modules\Seo\Data;

use App\Modules\Seo\Models\SeoRedirect;

/**
 * Input for a redirect as typed; SaveRedirect normalises the addresses (SeoPath).
 */
final readonly class SeoRedirectData
{
    public function __construct(
        public string $fromPath,
        public string $toPath,
        public int $statusCode,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the seo_redirects columns (SeoRedirect::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromPath: trim((string) ($data[SeoRedirect::COLUMN_FROM_PATH] ?? '')),
            toPath: trim((string) ($data[SeoRedirect::COLUMN_TO_PATH] ?? '')),
            statusCode: in_array((int) ($data[SeoRedirect::COLUMN_STATUS_CODE] ?? 301), SeoRedirect::STATUS_CODES, true) ? (int) $data[SeoRedirect::COLUMN_STATUS_CODE] : 301,
        );
    }
}
