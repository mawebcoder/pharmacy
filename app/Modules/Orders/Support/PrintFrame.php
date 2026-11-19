<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

/**
 * «چاپ فاکتور» without a new tab (owner, 2026-11-19): loads the page into a hidden frame and opens the browser's print
 * dialog on it, as Ctrl+P would; the frame goes away after printing.
 */
final class PrintFrame
{
    /**
     * Alpine / plain JavaScript for a click handler.
     */
    public static function script(string $url): string
    {
        return sprintf(
            "(() => { const f = document.createElement('iframe'); f.setAttribute('aria-hidden', 'true'); f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'; f.src = %s; f.onload = () => { f.contentWindow.addEventListener('afterprint', () => setTimeout(() => f.remove(), 100)); f.contentWindow.focus(); f.contentWindow.print(); }; document.body.appendChild(f); })()",
            json_encode($url, JSON_UNESCAPED_SLASHES),
        );
    }
}
