<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use Rhukster\DomSanitizer\DOMSanitizer;

/**
 * Cleans uploaded SVG (roadmap D16): scripts, event handlers, external references and foreign content are removed,
 * so the file is safe to serve from our own domain.
 */
final class SvgSanitizer
{
    public function sanitize(string $svg): string
    {
        $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);

        return $sanitizer->sanitize($svg, [
            'remove-namespaces' => false,
            'remove-php-tags' => true,
            'remove-html-tags' => true,
            'remove-xml-tags' => true,
            'compress-output' => false,
        ]);
    }
}
