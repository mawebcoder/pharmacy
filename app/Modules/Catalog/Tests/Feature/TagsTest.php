<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\DeleteTag;
use App\Modules\Catalog\Actions\SaveTag;
use App\Modules\Catalog\Data\TagData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;

it('builds the tag slug from the English name, else the Persian name', function (): void {
    $iron = app(SaveTag::class)(TagData::fromArray([Tag::COLUMN_NAME_FA => 'آهن', Tag::COLUMN_NAME_EN => 'Iron', Tag::COLUMN_TYPE => 'nutrient']));
    $insomnia = app(SaveTag::class)(TagData::fromArray([Tag::COLUMN_NAME_FA => 'بی‌خوابی', Tag::COLUMN_TYPE => 'need']));

    expect($iron->getSlug())->toBe('iron')
        ->and($insomnia->getSlug())->toBe('بی-خوابی');
});

it('removes a deleted tag from its products and keeps its slug reserved', function (): void {
    $tag = Tag::factory()->create([Tag::COLUMN_SLUG => 'iron']);
    $product = Product::factory()->create();
    $product->tags()->attach($tag);

    app(DeleteTag::class)($tag);
    $again = app(SaveTag::class)(TagData::fromArray([Tag::COLUMN_NAME_FA => 'آهن', Tag::COLUMN_NAME_EN => 'Iron', Tag::COLUMN_TYPE => 'nutrient']));

    expect($product->tags()->count())->toBe(0)
        ->and($again->getSlug())->toBe('iron-2');
});

it('shows the featured tags on the home page in their order, linking to /t/{slug}', function (): void {
    Tag::factory()->featured(2)->create([Tag::COLUMN_NAME_FA => 'ویتامین D', Tag::COLUMN_SLUG => 'vitamin-d']);
    Tag::factory()->featured(1)->create([Tag::COLUMN_NAME_FA => 'آهن', Tag::COLUMN_SLUG => 'iron']);
    Tag::factory()->create([Tag::COLUMN_NAME_FA => 'برچسب عادی']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['آهن', 'ویتامین D'])
        ->assertSee('href="'.route('tags.show', 'iron').'"', escape: false)
        ->assertDontSee('برچسب عادی');
});

it('shows the tag page with the current chip selected', function (): void {
    Tag::factory()->featured()->create([Tag::COLUMN_NAME_FA => 'آهن', Tag::COLUMN_SLUG => 'iron']);

    $this->get('/t/iron')
        ->assertOk()
        ->assertSee('<title>آهن', escape: false)
        ->assertSee('aria-current="page"', escape: false);
});

it('does not show deleted or unknown tags', function (Closure $makeTag): void {
    $makeTag();

    $this->get('/t/iron')->assertNotFound();
})->with([
    'deleted' => [fn () => Tag::factory()->create([Tag::COLUMN_SLUG => 'iron'])->delete()],
    'unknown' => [fn (): null => null],
]);
