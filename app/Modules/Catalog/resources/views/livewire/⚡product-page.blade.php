<?php

use App\Modules\Catalog\Enums\ProductRelationType;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductRelation;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Settings\Queries\Pharmacists;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Product page (roadmap B5 /products/{slug}, B9.5; layout built ahead of S13 by the owner, 2026-11-14): gallery with
 * swipe, thumbnails, hover and full-screen zoom; info (brand, name, badges, key attributes); a buy box with the SKU
 * picker (only when there is more than one SKU), price, shelf-life guarantee, stock, shipping and add to cart, sticky on desktop
 * and repeated as a bottom bar on phones and tablets once scrolled past; tabs that show one section at a time
 * (description, ingredients, usage, side effects, drug interactions, warnings, questions); «سؤال خود را اینجا
 * بپرسید» in the buy box (WhatsApp or Telegram from «تنظیمات فروشگاه», after a confirmation); related products. Stock alerts, question submission
 * and reviews stay in S13. Only active products and SKUs are shown.
 */
new class extends Component
{
    public Product $product;

    public int $variantId = 0;

    public function mount(string $slug): void
    {
        $this->product = Product::query()
            ->active()
            ->with(['brand', 'primaryCategory', 'primarySubcategory', 'media', 'faqs', 'attributeValues.attribute', 'variants' => fn ($variants) => $variants->where(ProductVariant::COLUMN_IS_ACTIVE, true)])
            ->where(Product::COLUMN_SLUG, $slug)
            ->firstOrFail();
        $this->variantId = $this->product->getDefaultVariant()?->getId() ?? 0;
    }

    /**
     * Only the product's own active SKUs can be picked; anything else returns to the default.
     */
    public function updatedVariantId(): void
    {
        if ($this->selectedVariant()?->getId() !== $this->variantId) {
            $this->variantId = $this->product->getDefaultVariant()?->getId() ?? 0;
        }

        unset($this->selectedVariant);
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        $variant = $this->product->getVariants()->first(fn (ProductVariant $variant): bool => $variant->getId() === $this->variantId)
            ?? $this->product->getDefaultVariant();

        return $variant?->setRelation('product', $this->product);
    }

    /**
     * The admin's «مرتبط» products that are active, in the admin's order (at most 12).
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function relatedProducts(): Collection
    {
        $ids = ProductRelation::query()
            ->where(ProductRelation::COLUMN_PRODUCT_ID, $this->product->getId())
            ->where(ProductRelation::COLUMN_TYPE, ProductRelationType::Related)
            ->orderBy(ProductRelation::COLUMN_ID)
            ->limit(12)
            ->pluck(ProductRelation::COLUMN_RELATED_PRODUCT_ID)
            ->all();

        return Product::query()->active()->with(ProductListing::CARD_RELATIONS)->whereKey($ids)->get()
            ->sortBy(fn (Product $product): int|false => array_search($product->getId(), $ids, true))
            ->values();
    }

    public function render(StoreSettings $settings, Pharmacists $pharmacists): \Illuminate\Contracts\View\View
    {
        return $this->view([
            'pharmacist' => $pharmacists->responsible(),
            'shippingRate' => $settings->shippingFlatRate(),
            'freeShippingThreshold' => $settings->freeShippingThreshold(),
            'chatMessage' => $settings->productChatMessage(
                $this->product->getNameFa(),
                $this->product->getVariants()->count() > 1 ? $this->selectedVariant?->getTitleFa() : null,
                route('products.show', $this->product->getSlug()),
            ),
        ])->title($this->product->getSeoTitle() ?? $this->product->getNameFa());
    }
};
?>

@use('App\Modules\Catalog\Enums\StockStatus')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $category = $product->getPrimaryCategory();
    // Breadcrumb: the category, then the main subcategory when there is one (owner, 2026-11-15).
    $subcategory = $product->getPrimarySubcategory()?->setRelation('category', $category);
    $trail = array_values(array_filter([$category, $subcategory]));
    $images = $product->images($product::IMAGE_GALLERY);
    $variants = $product->getVariants();
    $variant = $this->selectedVariant;
    $isRx = $product->requiresPrescription() || ! $product->isSellableType();
    $keyAttributes = $product->getAttributeValues()
        ->groupBy(fn ($value): string => $value->getAttributeModel()?->getNameFa() ?? '')
        ->filter(fn ($values, string $name): bool => $name !== '')
        ->map(fn ($values): string => $values->map->getValueFa()->join('، '));
    $texts = array_filter([
        'description' => ['توضیحات', $product->getDescription()],
        'ingredients' => ['ترکیبات', $product->getIngredients()],
        'usage' => ['نحوه مصرف', $product->getUsage()],
        'side-effects' => ['عوارض جانبی', $product->getSideEffects()],
        'interactions' => ['تداخلات دارویی', $product->getInteractions()],
        'warnings' => ['هشدارها', $product->getWarnings()],
    ], fn (array $tab): bool => filled($tab[1]));
    $tabs = array_map(fn (array $tab): string => $tab[0], $texts) + ($product->getFaqs()->isNotEmpty() ? ['questions' => 'پرسش‌ها'] : []);
    $firstTab = array_key_first($tabs);
@endphp

<div class="space-y-6 pb-20 xl:pb-0" x-data="{ pastBuyBox: false, phone: window.matchMedia('(max-width: 767px)').matches }" x-init="window.matchMedia('(max-width: 767px)').addEventListener('change', (event) => (phone = event.matches))">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            @foreach ($trail as $node)
                <li aria-hidden="true">/</li>
                <li><a href="{{ $node->url() }}" wire:navigate class="hover:text-primary-700">{{ $node->getNameFa() }}</a></li>
            @endforeach
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="line-clamp-1 text-ink-900">{{ $product->getNameFa() }}</li>
        </ol>
    </nav>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-12 xl:gap-8">
        {{-- Gallery: swipe with dots on phones, thumbnails below on tablets, beside (start side) on desktop. --}}
        <div class="min-w-0 xl:col-span-5" wire:ignore x-data="productGallery({{ count($images) }})">
            @if ($images === [])
                <div class="grid aspect-square place-items-center rounded-card border border-line-200 bg-media text-ink-500">
                    <x-ts-icon icon="photo" class="size-12" />
                </div>
            @else
                <div class="flex flex-col gap-3 xl:flex-row-reverse">
                    <div class="min-w-0 flex-1">
                        <div class="relative">
                        <div x-ref="slider" class="swiper overflow-hidden rounded-card border border-line-200 bg-media" tabindex="0" aria-roledescription="گالری" aria-label="تصاویر {{ $product->getNameFa() }}">
                            <ul class="swiper-wrapper">
                                @foreach ($images as $index => $image)
                                    <li class="swiper-slide">
                                        <button type="button" x-on:click="open()" x-on:mousemove="lens($event)" x-on:mouseleave="unlens($event)" class="block aspect-square w-full cursor-zoom-in overflow-hidden" aria-label="نمایش تمام‌صفحه تصویر {{ $index + 1 }}">
                                            <x-media::picture :media="$image" slot-name="pdp-main" :alt="$product->getNameFa()" :priority="$index === 0" class="size-full object-contain transition-transform duration-150 motion-reduce:transition-none" />
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @if (count($images) > 1)
                            <span class="pointer-events-none absolute end-3 bottom-3 z-10 rounded-full bg-black/60 px-2 text-xs leading-6 text-white" x-text="counter()" aria-hidden="true"></span>
                        @endif
                        </div>
                        @if (count($images) > 1)
                            <div class="mt-3 flex justify-center gap-2 md:hidden">
                                @foreach ($images as $index => $image)
                                    <button type="button" x-on:click="show({{ $index }})" class="grid size-6 place-items-center" aria-label="تصویر {{ $index + 1 }}" x-bind:aria-current="active === {{ $index }}">
                                        <span class="h-2 rounded-full transition-all" x-bind:class="active === {{ $index }} ? 'w-5 bg-primary-700' : 'w-2 bg-line-300'"></span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if (count($images) > 1)
                        <ul class="scrollbar-none hidden gap-2 overflow-x-auto md:flex xl:max-h-[34rem] xl:flex-col xl:overflow-x-visible xl:overflow-y-auto">
                            @foreach ($images as $index => $image)
                                <li class="shrink-0">
                                    <button type="button" x-on:click="show({{ $index }})" class="block size-16 overflow-hidden rounded-md border-2 bg-media xl:size-20" x-bind:class="active === {{ $index }} ? 'border-primary-700' : 'border-line-200 hover:border-line-300'" aria-label="تصویر {{ $index + 1 }}" x-bind:aria-current="active === {{ $index }}">
                                        <x-media::picture :media="$image" slot-name="cart-thumb" alt="" class="size-full object-contain" />
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Full-screen view: arrows, Escape, tap to zoom. Its large photos load only once it opens. --}}
                <template x-teleport="body">
                    <div x-show="fullscreen" x-cloak x-trap.noscroll="fullscreen" x-on:keydown.escape.window="fullscreen && close()" x-on:keydown.left.window="fullscreen && step(1)" x-on:keydown.right.window="fullscreen && step(-1)" class="fixed inset-0 z-[60] flex flex-col bg-black/95" role="dialog" aria-modal="true" aria-label="تصاویر {{ $product->getNameFa() }}" dir="rtl">
                        <div class="flex items-center justify-between p-3 text-white">
                            <span class="text-sm" x-text="counter()"></span>
                            <button type="button" x-on:click="close()" class="grid size-11 place-items-center rounded-full hover:bg-white/10" aria-label="بستن">
                                <x-ts-icon icon="x-mark" class="size-6" />
                            </button>
                        </div>
                        <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden">
                            <template x-if="fullscreen">
                                <div class="size-full">
                                    @foreach ($images as $index => $image)
                                        <div x-show="active === {{ $index }}" class="grid size-full place-items-center bg-media">
                                            <button type="button" x-on:click="toggleZoom($event)" class="size-full transition-transform duration-200 motion-reduce:transition-none" x-bind:class="zoomed ? 'scale-200 cursor-zoom-out' : 'cursor-zoom-in'" aria-label="بزرگ‌نمایی">
                                                <x-media::picture :media="$image" slot-name="pdp-zoom" :alt="$product->getNameFa()" class="size-full object-contain" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </template>
                            @if (count($images) > 1)
                                <button type="button" x-on:click="step(-1)" class="absolute start-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80" aria-label="تصویر قبلی">
                                    <x-ts-icon icon="chevron-right" class="size-6" />
                                </button>
                                <button type="button" x-on:click="step(1)" class="absolute end-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80" aria-label="تصویر بعدی">
                                    <x-ts-icon icon="chevron-left" class="size-6" />
                                </button>
                            @endif
                        </div>
                    </div>
                </template>
            @endif
        </div>

        {{-- Info --}}
        <div class="min-w-0 space-y-4 xl:col-span-4">
            @if ($product->getBrand())
                <a href="{{ route('brands.show', $product->getBrand()) }}" wire:navigate class="text-sm font-medium text-primary-700 hover:underline">{{ $product->getBrand()->getNameFa() }}</a>
            @endif
            <div class="flex items-start justify-between gap-3">
                <h1 class="text-xl leading-9 font-bold text-ink-900 xl:text-2xl xl:leading-10">{{ $product->getNameFa() }}</h1>
                <x-catalog::wishlist-button :product-id="$product->getId()" class="shrink-0 border border-line-200 shadow-none" />
            </div>
            @if ($product->getNameEn())
                <p dir="ltr" class="text-end text-sm text-ink-500">{{ $product->getNameEn() }}</p>
            @endif

            <ul class="flex flex-wrap gap-2 text-xs font-medium">
                <li class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 leading-7 text-primary-700"><x-ts-icon icon="shield-check" class="size-4" />اصالت کالا</li>
                @if ($isRx)
                    <li class="inline-flex items-center gap-1 rounded-full border border-rx-600 px-2.5 leading-6 text-rx-600"><x-ts-icon icon="document-text" class="size-4" />نسخه‌ای</li>
                @endif
                @if ($variant?->isNearExpiry())
                    <li class="inline-flex items-center gap-1 rounded-full bg-warning-500 px-2.5 leading-7 text-ink-900">زودمصرف</li>
                @endif
                @if ($product->getIrcCode())
                    <li class="inline-flex items-center gap-1 rounded-full border border-line-300 px-2.5 leading-6 text-ink-500">IRC <bdi dir="ltr">{{ $product->getIrcCode() }}</bdi></li>
                @endif
            </ul>

            {{-- Why it is discounted, with the exact expiry date, whenever the selected SKU's supplier stock is «زودمصرف»
                 (owner, 2026-11-17). --}}
            @if ($variant?->isNearExpiry())
                <p class="flex items-start gap-2 rounded-card border border-warning-500 bg-warning-500/10 p-3 text-sm leading-7 text-ink-900" role="note">
                    <x-ts-icon icon="clock" class="mt-1 size-5 shrink-0 text-warning-500" />
                    @if ($variant->getExpiryDate())
                        <span><strong>زودمصرف:</strong> تاریخ انقضای این کالا <strong>@jalali($variant->getExpiryDate())</strong> است و به همین دلیل با تخفیف عرضه می‌شود.</span>
                    @else
                        <span><strong>زودمصرف:</strong> تاریخ انقضای این کالا نزدیک است و به همین دلیل با تخفیف عرضه می‌شود.</span>
                    @endif
                </p>
            @endif

            @if ($product->getShortDescription())
                <p class="leading-8 text-ink-900">{{ $product->getShortDescription() }}</p>
            @endif

            @if ($keyAttributes->isNotEmpty())
                <section aria-labelledby="key-attributes">
                    <h2 id="key-attributes" class="mb-2 text-sm font-bold text-ink-900">ویژگی‌های اصلی</h2>
                    <dl class="divide-y divide-line-200 rounded-card border border-line-200 bg-card text-sm">
                        @foreach ($keyAttributes->take(6) as $name => $value)
                            <div class="flex gap-3 px-3 py-2">
                                <dt class="w-28 shrink-0 text-ink-500">{{ $name }}</dt>
                                <dd class="text-ink-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        {{-- Buy box: sticky on desktop. --}}
        <div class="min-w-0 md:col-span-2 xl:col-span-3">
            <div class="space-y-4 rounded-card border border-line-200 bg-card p-4 xl:sticky xl:top-[calc(var(--header-height)+1rem)]" x-intersect:leave="pastBuyBox = $el.getBoundingClientRect().top < 0" x-intersect:enter="pastBuyBox = false">
                @if ($variants->count() > 1)
                    <fieldset>
                        <legend class="mb-2 text-sm font-bold text-ink-900">انتخاب نوع</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($variants as $option)
                                <label wire:key="variant-{{ $option->getId() }}" @class([
                                    'flex min-h-11 cursor-pointer items-center rounded-card border px-3 text-sm has-focus-visible:outline-2 has-focus-visible:outline-primary-700',
                                    'border-2 border-primary-700 bg-primary-50 font-bold text-primary-700' => $option->getId() === $variant?->getId(),
                                    'border-line-300 text-ink-900 hover:border-primary-700' => $option->getId() !== $variant?->getId(),
                                    'opacity-60' => $option->getStockStatus() !== StockStatus::Available,
                                ])>
                                    <input type="radio" name="variant" value="{{ $option->getId() }}" wire:model.live="variantId" class="sr-only">
                                    {{ $option->getTitleFa() }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if ($variant)
                    <div class="flex items-end justify-between gap-3">
                        <p @class(['text-sm font-medium', 'text-success-600' => $variant->getStockStatus() === StockStatus::Available, 'text-ink-500' => $variant->getStockStatus() !== StockStatus::Available])>
                            {{ $variant->getStockStatus()->label() }}
                        </p>
                        <x-ui.price :price="$variant->currentPrice()" :old="$variant->compareAtPrice()" size="lg" />
                    </div>
                    {{-- «زودمصرف»: that supplier's stock has a known expiry date, shown (owner, 2026-11-17). Otherwise goods are bought
                         after the order, so no exact date is promised: only a minimum (owner, 2026-11-16). --}}
                    @if ($variant->isNearExpiry() && $variant->getExpiryDate())
                        <p class="inline-flex items-center gap-1.5 text-sm text-ink-500">
                            <x-ts-icon icon="calendar-days" class="size-5 text-warning-500" />
                            تاریخ انقضا: <span class="font-bold text-ink-900">@jalali($variant->getExpiryDate())</span>
                        </p>
                    @elseif ($product->getMinShelfLifeMonths())
                        <p class="inline-flex items-center gap-1.5 text-sm text-ink-500">
                            <x-ts-icon icon="calendar-days" class="size-5 text-primary-700" />
                            تضمین اعتبار: <span class="font-medium text-ink-900">حداقل {{ PersianDigits::toPersian($product->getMinShelfLifeMonths()) }} ماه</span>
                        </p>
                    @endif

                    @if ($isRx)
                        <p class="rounded-card border border-rx-600 bg-card p-3 text-sm leading-7 text-rx-600" role="note">فروش آنلاین این دارو به‌زودی</p>
                    @elseif ($variant->canAddToCart())
                        {{-- On phones the bottom bar is the add-to-cart (owner, 2026-11-15). --}}
                        <x-cart::add-button wire:key="buy-{{ $variant->getId() }}" :variant-id="$variant->getId()" :label="$product->getNameFa().' '.$variant->getTitleFa()" wide class="hidden md:block" />
                    @endif
                @endif

                <ul class="space-y-2 border-t border-line-200 pt-3 text-sm text-ink-500">
                    <li class="flex gap-2"><x-ts-icon icon="truck" class="size-5 shrink-0 text-primary-700" /><span>هزینه ارسال @toman($shippingRate)؛ رایگان برای خرید بالای @toman($freeShippingThreshold)</span></li>
                    <li class="flex gap-2"><x-ts-icon icon="shield-check" class="size-5 shrink-0 text-primary-700" /><span>خرید مستقیم از شرکت‌های پخش معتبر</span></li>
                    {{-- B2 rule 3: the pharmacist in charge on every product (S30). --}}
                    @if ($pharmacist !== null)
                        <li class="flex gap-2">
                            <x-ts-icon icon="user-circle" class="size-5 shrink-0 text-primary-700" />
                            <a href="{{ route('pharmacist') }}" wire:navigate class="hover:text-primary-700 hover:underline">زیر نظر {{ $pharmacist['name'] }}، شماره نظام پزشکی <span dir="ltr">{{ PersianDigits::toPersian($pharmacist['licenseNumber']) }}</span></a>
                        </li>
                    @endif
                </ul>
                <x-ui.pharmacist-chat wire:key="chat-{{ $variant?->getId() }}" :text="$chatMessage" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-card border border-primary-700 px-4 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700" />
            </div>
        </div>
    </div>

    @if ($tabs !== [])
        {{--
            Tabs (roadmap B9.5): one section at a time. On phones the tab bar scrolls sideways and sticks under the
            header; the picked tab scrolls into view; arrow keys move between tabs (left = next in RTL).
        --}}
        <section
            x-data="{
                tab: @js($firstTab),
                pick(id) {
                    this.tab = id;
                    this.$nextTick(() => {
                        this.$refs['tab-' + id].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                        if (this.$refs.tabbar.getBoundingClientRect().top <= document.querySelector('header').offsetHeight + 1) {
                            this.$root.scrollIntoView({ block: 'start' });
                        }
                    });
                },
                move(by) {
                    const ids = @js(array_keys($tabs));
                    const next = ids[(ids.indexOf(this.tab) + by + ids.length) % ids.length];
                    this.pick(next);
                    this.$refs['tab-' + next].focus();
                },
            }"
            class="scroll-mt-[var(--header-height)]"
            aria-label="اطلاعات محصول"
        >
            <div x-ref="tabbar" class="sticky top-[var(--header-height)] z-20 -mx-4 border-b border-line-200 bg-surface-50 px-4 md:-mx-6 md:px-6 xl:-mx-8 xl:px-8">
                <div role="tablist" aria-label="بخش‌های اطلاعات محصول" class="scrollbar-none -mb-px flex gap-1 overflow-x-auto" x-on:keydown.left.prevent="move(1)" x-on:keydown.right.prevent="move(-1)">
                    @foreach ($tabs as $id => $label)
                        <button
                            type="button"
                            role="tab"
                            id="tab-{{ $id }}"
                            x-ref="tab-{{ $id }}"
                            aria-controls="panel-{{ $id }}"
                            x-bind:aria-selected="tab === @js($id)"
                            x-bind:tabindex="tab === @js($id) ? 0 : -1"
                            x-on:click="pick(@js($id))"
                            @class(['min-h-12 shrink-0 border-b-2 px-4 text-sm whitespace-nowrap focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700', 'border-primary-700 font-bold text-primary-700' => $id === $firstTab, 'border-transparent text-ink-500' => $id !== $firstTab])
                            x-bind:class="tab === @js($id) ? 'border-primary-700 font-bold text-primary-700' : 'border-transparent text-ink-500 hover:text-ink-900'"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 rounded-card border border-line-200 bg-card p-4 md:p-6">
                @foreach ($texts as $id => [$label, $text])
                    <div id="panel-{{ $id }}" role="tabpanel" aria-labelledby="tab-{{ $id }}" tabindex="0" x-show="tab === @js($id)" @if ($id !== $firstTab) x-cloak @endif>
                        <h2 class="mb-3 text-lg font-bold text-ink-900">{{ $label }}</h2>
                        @if ($id === 'description')
                            {{-- Written in the admin's rich editor (DescriptionHtml keeps only safe tags). --}}
                            <div class="page-content max-w-3xl">{!! \App\Modules\Catalog\Support\DescriptionHtml::toHtml($text) !!}</div>
                        @else
                            <p class="max-w-3xl leading-8 whitespace-pre-line text-ink-900">{{ $text }}</p>
                        @endif
                    </div>
                @endforeach

                @if ($product->getFaqs()->isNotEmpty())
                    <div id="panel-questions" role="tabpanel" aria-labelledby="tab-questions" tabindex="0" x-show="tab === 'questions'" @if ($firstTab !== 'questions') x-cloak @endif>
                        <h2 class="mb-3 text-lg font-bold text-ink-900">پرسش‌های متداول</h2>
                        <div class="max-w-3xl divide-y divide-line-200">
                            @foreach ($product->getFaqs() as $faq)
                                <details class="group py-1">
                                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 font-medium text-ink-900">
                                        {{ $faq->getQuestion() }}
                                        <x-ts-icon icon="chevron-down" class="size-5 shrink-0 text-ink-500 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <p class="pb-3 leading-8 whitespace-pre-line text-ink-500">{{ $faq->getAnswer() }}</p>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif


    @if ($this->relatedProducts->isNotEmpty())
        <section aria-labelledby="related-products">
            <h2 id="related-products" class="mb-3 text-lg font-bold text-ink-900">محصولات مرتبط</h2>
            <div class="relative" wire:ignore x-data="slider({ slidesPerView: 2.15, spaceBetween: 12, breakpoints: { 768: { slidesPerView: 3, spaceBetween: 16 }, 1280: { slidesPerView: 5, spaceBetween: 16 } } })">
                <div x-ref="slider" class="swiper">
                    <ul class="swiper-wrapper">
                        @foreach ($this->relatedProducts as $related)
                            <li wire:key="related-{{ $related->getId() }}" class="swiper-slide h-auto! w-[45%] md:w-[calc((100%-32px)/3)] xl:w-[calc((100%-64px)/5)]">
                                <x-catalog::product-card :product="$related" />
                            </li>
                        @endforeach
                    </ul>
                </div>
                <x-content::slider-arrows previous="محصولات قبلی" next="محصولات بعدی" />
            </div>
        </section>
    @endif

    {{-- Bottom buy bar above the mobile nav: always on phones, like the cart's (owner, 2026-11-15): the price beside the
         button; on tablets once the buy box has scrolled out of view, with the name too. --}}
    @if ($variant)
        <div x-show="phone || pastBuyBox" x-cloak x-transition.opacity class="fixed inset-x-0 bottom-[calc(4rem+1px+env(safe-area-inset-bottom))] z-30 border-t border-line-200 bg-card p-3 shadow-card md:bottom-0 md:px-4 md:py-2 xl:hidden">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                <div class="min-w-0 shrink-0 md:shrink">
                    <p class="hidden truncate text-sm text-ink-900 md:block">{{ $product->getNameFa() }}</p>
                    <x-ui.price :price="$variant->currentPrice()" :old="$variant->compareAtPrice()" size="sm" />
                </div>
                @if ($isRx)
                    <p class="shrink-0 text-sm font-medium text-rx-600">فروش آنلاین به‌زودی</p>
                @elseif ($variant->canAddToCart())
                    <div class="min-w-0 flex-1 md:w-40 md:flex-none">
                        <x-cart::add-button wire:key="bar-{{ $variant->getId() }}" :variant-id="$variant->getId()" :label="$product->getNameFa().' '.$variant->getTitleFa()" wide />
                    </div>
                @else
                    <p class="shrink-0 text-sm font-medium text-ink-500">ناموجود</p>
                @endif
            </div>
        </div>
    @endif
</div>
