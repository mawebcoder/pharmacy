<?php

declare(strict_types=1);

use App\Modules\Catalog\Support\DescriptionHtml;

it('keeps the formatting the rich editor makes', function (): void {
    $html = DescriptionHtml::toHtml('<h2>مزایا</h2><p><strong>آبرسان</strong> و <em>ملایم</em></p><ul><li>بدون عطر</li></ul><a href="https://hutana.ir/brands">برندها</a>');

    expect($html)
        ->toContain('<h2>مزایا</h2>')
        ->toContain('<strong>آبرسان</strong>')
        ->toContain('<em>ملایم</em>')
        ->toContain('<ul><li>بدون عطر</li></ul>')
        ->toContain('<a href="https://hutana.ir/brands" rel="noopener noreferrer">برندها</a>');
});

it('removes scripts, event handlers, javascript links and tags the editor does not make', function (): void {
    $html = DescriptionHtml::toHtml('<p onclick="steal()">متن</p><script>alert(1)</script><a href="javascript:alert(1)">لینک</a><iframe src="https://evil.test"></iframe><img src="x" onerror="alert(1)">');

    expect($html)
        ->toContain('<p>متن</p>')
        ->toContain('لینک')
        ->not->toContain('onclick')
        ->not->toContain('script')
        ->not->toContain('alert')
        ->not->toContain('javascript:')
        ->not->toContain('iframe')
        ->not->toContain('<img');
});

it('turns a plain-text description into escaped paragraphs that keep their line breaks', function (): void {
    $html = DescriptionHtml::toHtml("خط اول\nخط دوم\n\nپاراگراف دوم با «قیمت > ۱۰۰»");

    expect($html)->toBe('<p>خط اول<br>'."\n".'خط دوم</p><p>پاراگراف دوم با «قیمت &gt; ۱۰۰»</p>');
});

it('returns nothing for an empty description', function (?string $description): void {
    expect(DescriptionHtml::toHtml($description))->toBe('');
})->with([null, '', "  \n "]);
