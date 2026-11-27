<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Media\Actions\AttachImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Throwable;

/**
 * Downloads a catalog import's image URLs into the product's gallery (owner, 2026-11-27), in order, after its current
 * photos. A URL already imported for the product is skipped. Only http(s) to public addresses, at most 10 MB each;
 * the file must pass the product image preset (AttachImage). Returns the problems, one per failed URL.
 */
final readonly class ImportProductImages
{
    public const string PROPERTY_SOURCE_URL = 'source_url';

    private const int MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private AttachImage $attachImage) {}

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    public function __invoke(Product $product, array $urls): array
    {
        $imported = $product->getMedia(Product::IMAGE_GALLERY)
            ->map(fn (SpatieMedia $media): string => (string) $media->getCustomProperty(self::PROPERTY_SOURCE_URL))
            ->all();
        $problems = [];

        foreach ($urls as $url) {
            if (in_array($url, $imported, true)) {
                continue;
            }

            $problem = $this->import($product, $url);

            if ($problem !== null) {
                $problems[] = $url.': '.$problem;
            }
        }

        return $problems;
    }

    private function import(Product $product, string $url): ?string
    {
        if (! self::isPublic($url)) {
            return 'نشانی عمومی نیست.';
        }

        try {
            $response = Http::timeout(20)->connectTimeout(5)->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]])->get($url);
        } catch (Throwable) {
            return 'دانلود نشد.';
        }

        if (! $response->successful()) {
            return 'دانلود نشد (کد '.$response->status().').';
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return 'فایل خالی یا بزرگ‌تر از ۱۰ مگابایت است.';
        }

        $path = tempnam(sys_get_temp_dir(), 'catalog-image-');
        file_put_contents($path, $body);

        try {
            $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image';
            $media = ($this->attachImage)($product, Product::IMAGE_GALLERY, new UploadedFile($path, $name, null, null, true), $product->getNameFa());
            $media->setCustomProperty(self::PROPERTY_SOURCE_URL, $url)->save();
        } catch (ValidationException $invalid) {
            return (string) collect($invalid->errors())->flatten()->first();
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return null;
    }

    /**
     * An http(s) URL whose host is not a private, loopback or reserved address.
     */
    public static function isPublic(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            return false;
        }

        $addresses = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false ? [trim($host, '[]')] : (gethostbynamel($host) ?: []);

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
