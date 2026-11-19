<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use Mpdf\Barcode\Code128;

/**
 * A Code 128 barcode as inline SVG (owner, 2026-11-19), for the printed invoice's tracking code: mPDF's encoder gives
 * the bars, drawn here so the browser prints them sharp at any size.
 */
final class Code128Svg
{
    public static function for(string $code, int $height = 48, float $module = 1.6): string
    {
        $data = (new Code128($code, 'B'))->getData();
        $x = 0.0;
        $bars = '';

        foreach ($data['bcode'] as $bar) {
            $width = $bar['w'] * $module;

            if ($bar['t']) {
                $bars .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d"/>', $x, $width, $height);
            }

            $x += $width;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.2f" height="%d" viewBox="0 0 %.2f %d" role="img" aria-label="%s"><g fill="#000">%s</g></svg>',
            $x, $height, $x, $height, e($code), $bars,
        );
    }
}
