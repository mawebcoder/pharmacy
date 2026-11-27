<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductFaq;
use App\Modules\Catalog\Models\ProductRelation;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->product = Product::factory()->active()->create([
        Product::COLUMN_NAME_FA => 'کرم اوره',
        Product::COLUMN_SLUG => 'urea-cream',
        Product::COLUMN_USAGE => 'روزی دو بار بمالید.',
        Product::COLUMN_WARNINGS => 'دور از دسترس کودکان.',
        Product::COLUMN_SIDE_EFFECTS => 'قرمزی خفیف پوست.',
        Product::COLUMN_INTERACTIONS => 'همراه رتینول مصرف نشود.',
        Product::COLUMN_INGREDIENTS => null,
    ]);
});

it('shows the buy box, shipping line and a tab for each section that has content', function (): void {
    ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_PRICE => 250_000, ProductVariant::COLUMN_OFFICIAL_PRICE => 250_000]);
    ProductFaq::factory()->for($this->product)->create([ProductFaq::COLUMN_QUESTION => 'برای پوست حساس مناسب است؟']);

    $this->get('/products/urea-cream')
        ->assertOk()
        ->assertSee('۲۵٬۰۰۰ تومان')
        ->assertSee('هزینه ارسال')
        ->assertSee(['id="tab-usage"', 'id="tab-side-effects"', 'id="tab-interactions"', 'id="tab-warnings"', 'id="tab-questions"'], false)
        ->assertSee(['قرمزی خفیف پوست.', 'همراه رتینول مصرف نشود.', 'برای پوست حساس مناسب است؟'])
        ->assertDontSee('id="tab-ingredients"', false)
        ->assertDontSee('انتخاب نوع');
});

it('switches the buy box to the picked SKU and ignores SKUs of other products', function (): void {
    $small = ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_TITLE_FA => '۵۰ گرمی', ProductVariant::COLUMN_PRICE => 200_000, ProductVariant::COLUMN_OFFICIAL_PRICE => 200_000]);
    $large = ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_TITLE_FA => '۱۰۰ گرمی', ProductVariant::COLUMN_PRICE => 350_000, ProductVariant::COLUMN_OFFICIAL_PRICE => 350_000]);
    $other = ProductVariant::factory()->create();

    livewire('catalog::product-page', ['slug' => 'urea-cream'])
        ->assertSet('variantId', $small->getId())
        ->assertSee('انتخاب نوع')
        ->set('variantId', $large->getId())
        ->assertSee('۳۵٬۰۰۰ تومان')
        // No exact expiry date is promised (owner, 2026-11-16).
        ->assertDontSee('تاریخ انقضا')
        ->set('variantId', $other->getId())
        ->assertSet('variantId', $small->getId());
});

it('shows the product\'s shelf-life guarantee in months, and nothing when it has none', function (): void {
    ProductVariant::factory()->for($this->product)->create();

    livewire('catalog::product-page', ['slug' => 'urea-cream'])->assertDontSee('تضمین اعتبار');

    $this->product->setMinShelfLifeMonths(6)->save();

    livewire('catalog::product-page', ['slug' => 'urea-cream'])->assertSee(['تضمین اعتبار', 'حداقل ۶ ماه']);
});

it('replaces add to cart with the coming-soon note on prescription products', function (): void {
    $rx = Product::factory()->active()->rx()->create([Product::COLUMN_SLUG => 'rx-drug']);
    ProductVariant::factory()->for($rx)->create();

    $this->get('/products/rx-drug')->assertOk()->assertSee('فروش آنلاین این دارو به‌زودی')->assertDontSee('به سبد خرید"', false);
});

it('lists only active related products', function (): void {
    ProductVariant::factory()->for($this->product)->create();
    $shown = Product::factory()->active()->withVariants()->create([Product::COLUMN_NAME_FA => 'ضدآفتاب مرتبط']);
    $draft = Product::factory()->withVariants()->create([Product::COLUMN_NAME_FA => 'محصول پیش‌نویس']);

    foreach ([$shown, $draft] as $related) {
        (new ProductRelation)->setProductId($this->product->getId())->setRelatedProductId($related->getId())->setType(ProductRelationType::Related)->save();
    }

    $this->get('/products/urea-cream')->assertSee(['محصولات مرتبط', 'ضدآفتاب مرتبط'])->assertDontSee('محصول پیش‌نویس');
});

it('offers the WhatsApp question button only once the admin has set a number', function (): void {
    ProductVariant::factory()->for($this->product)->create();

    $this->get('/products/urea-cream')->assertDontSee('https://wa.me/', false);

    app(SaveStoreSettings::class)([StoreSettings::WHATSAPP_NUMBER => '09123456789']);

    $this->get('/products/urea-cream')
        ->assertSee(['سؤال خود را اینجا بپرسید', 'ادامه در واتساپ؟', 'مشاوره با داروساز'])
        ->assertSee('https://wa.me/989123456789?text='.rawurlencode('سلام، درباره «کرم اوره» سؤال دارم: '.route('products.show', 'urea-cream')), false)
        ->assertDontSee('https://t.me/', false);
});

it('drafts the admin\'s message with the picked SKU and offers Telegram too', function (): void {
    $small = ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_TITLE_FA => '۵۰ گرمی']);
    $large = ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_TITLE_FA => '۱۰۰ گرمی']);
    app(SaveStoreSettings::class)([
        StoreSettings::WHATSAPP_NUMBER => '09123456789',
        StoreSettings::TELEGRAM_USERNAME => 'pharmacy_support',
        StoreSettings::CHAT_MESSAGE_PRODUCT => 'موجودی {product} {variant} را می‌خواهم',
    ]);

    livewire('catalog::product-page', ['slug' => 'urea-cream'])
        ->set('variantId', $large->getId())
        ->assertSee('https://t.me/pharmacy_support?text='.rawurlencode('موجودی کرم اوره (۱۰۰ گرمی) را می‌خواهم'), false)
        ->assertSee('https://wa.me/989123456789?text='.rawurlencode('موجودی کرم اوره (۱۰۰ گرمی) را می‌خواهم'), false)
        ->assertSee('گفت‌وگو در کدام پیام‌رسان باز شود؟');

    expect($small->getId())->not->toBe($large->getId());
});

it('shows the description with its rich-editor formatting and without scripts', function (): void {
    $this->product->forceFill([Product::COLUMN_DESCRIPTION => '<p><strong>آبرسان قوی</strong></p><ul><li>بدون عطر</li></ul><script>alert(1)</script>'])->save();

    $this->get('/products/urea-cream')
        ->assertOk()
        ->assertSee(['<strong>آبرسان قوی</strong>', '<li>بدون عطر</li>'], false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
