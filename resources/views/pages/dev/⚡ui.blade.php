<?php

use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Design-system gallery (roadmap B10, B13), registered only when APP_ENV=local.
 * Every storefront building block in one place, for checking at 375 / 768 / 1280 px.
 */
new #[Title('اجزای رابط کاربری')] class extends Component
{
    use Interactions;

    public string $search = '';

    public ?string $brand = null;

    public int $quantity = 1;

    public bool $isSubscribed = true;

    public ?string $deliveryDate = null;

    public function addToCart(): void
    {
        $this->toast()
            ->success('به سبد اضافه شد', "{$this->quantity} عدد ویتامین D3 ۱۰۰۰ واحد به سبد خرید شما اضافه شد.")
            ->send();
    }

    public function confirmRemove(): void
    {
        $this->dialog()
            ->question('حذف از سبد؟', 'این کالا از سبد خرید شما حذف می‌شود.')
            ->confirm('حذف کالا', 'remove')
            ->cancel('انصراف')
            ->send();
    }

    public function remove(): void
    {
        $this->toast()->warning('حذف شد', 'کالا از سبد خرید حذف شد.')->send();
    }

    public function with(): array
    {
        return [
            'now' => Carbon::now(),
            'brands' => [
                ['label' => 'نیچرز بونتی', 'value' => 'natures-bounty'],
                ['label' => 'ویتابیوتیکس', 'value' => 'vitabiotics'],
                ['label' => 'یوروویتال', 'value' => 'eurho-vital'],
            ],
            'tokens' => [
                'primary-700' => 'bg-primary-700', 'primary-800' => 'bg-primary-800', 'primary-50' => 'bg-primary-50',
                'accent-500' => 'bg-accent-500', 'ink-900' => 'bg-ink-900', 'ink-500' => 'bg-ink-500',
                'line-200' => 'bg-line-200', 'surface-50' => 'bg-surface-50', 'success-600' => 'bg-success-600',
                'warning-500' => 'bg-warning-500', 'danger-600' => 'bg-danger-600', 'rx-600' => 'bg-rx-600',
            ],
        ];
    }
};
?>

<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-ink-900">اجزای رابط کاربری</h1>
        <p class="mt-1 text-sm text-ink-500">
            @jalali($now, 'l j F Y') — ساعت @jalali($now, 'H:i') (تهران)
        </p>
    </header>

    <section class="space-y-3 rounded-card border border-line-200 bg-white p-4 md:p-6">
        <h2 class="font-bold">رنگ‌ها (B3)</h2>
        <ul class="grid grid-cols-3 gap-3 md:grid-cols-6">
            @foreach ($tokens as $name => $class)
                <li class="text-xs text-ink-500">
                    <span @class(['mb-1 block h-10 rounded-md ring-1 ring-line-200', $class])></span>
                    <span dir="ltr">{{ $name }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="space-y-4 rounded-card border border-line-200 bg-white p-4 md:p-6">
        <h2 class="font-bold">قیمت (B9.4)</h2>
        <div class="flex flex-wrap items-end gap-8">
            <x-ui.price :price="4_850_000" />
            <x-ui.price :price="12_500_000" :old="15_000_000" />
            <x-ui.price :price="12_500_000" :old="15_000_000" size="lg" />
            <x-ui.price :price="0" />
        </div>
    </section>

    <section class="space-y-4 rounded-card border border-line-200 bg-white p-4 md:p-6">
        <h2 class="font-bold">نشان‌ها و دکمه‌ها (B10)</h2>
        <div class="flex flex-wrap gap-2">
            <x-ts-badge text="۲۰٪ تخفیف" color="red" />
            <x-ts-badge text="زودمصرف" color="amber" light />
            <x-ts-badge text="کج‌جعبه" color="gray" light />
            <x-ts-badge text="نسخه‌ای" color="violet" light />
            <x-ts-badge text="اصالت کالا" icon="check-badge" />
        </div>
        <div class="flex flex-wrap gap-3">
            <x-ts-button wire:click="addToCart" icon="shopping-cart">افزودن به سبد</x-ts-button>
            <x-ts-button color="primary" outline x-on:click="$tsui.open.modal('ask-pharmacist')" icon="chat-bubble-left-right">از داروساز بپرسید</x-ts-button>
            <x-ts-button color="secondary" flat x-on:click="$tsui.open.slide('cart')" icon="shopping-bag">سبد خرید (کشویی)</x-ts-button>
            <x-ts-button color="red" outline wire:click="confirmRemove" icon="trash">حذف</x-ts-button>
        </div>
        <x-ts-rating :rate="4" static text="۴ از ۵ (۱۲۸ نظر)" />
    </section>

    <section class="space-y-4 rounded-card border border-line-200 bg-white p-4 md:p-6">
        <h2 class="font-bold">ورودی‌ها</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <x-ts-input label="جستجو" hint="نام محصول، برند یا ماده مؤثره" icon="magnifying-glass" wire:model.live="search" clearable />
            <x-ts-select.styled label="برند" :options="$brands" select="label:label|value:value" wire:model.live="brand" searchable />
            <x-ts-number label="تعداد" wire:model.live="quantity" min="1" max="10" />
            <x-jalali-date label="تاریخ تحویل" hint="از امروز تا ۳۰ روز آینده" wire:model.live="deliveryDate" :min="$now" :max="$now->copy()->addDays(30)" />
        </div>
        <x-ts-toggle label="یادآوری خرید مجدد" wire:model.live="isSubscribed" />
    </section>

    <section class="space-y-3 rounded-card border border-line-200 bg-white p-4 md:p-6">
        <h2 class="font-bold">پیشرفت (تا ارسال رایگان)</h2>
        <x-ts-progress percent="70" without-text />
        <p class="text-sm text-ink-500">@toman(1_500_000) تا ارسال رایگان</p>
    </section>

    <x-ts-modal id="ask-pharmacist" title="از داروساز بپرسید">
        <x-ts-textarea label="سؤال شما" placeholder="سؤال خود را درباره این محصول بنویسید..." />
        <x-slot:footer>
            <x-ts-button x-on:click="$tsui.close.modal('ask-pharmacist')">ارسال سؤال</x-ts-button>
        </x-slot:footer>
    </x-ts-modal>

    <x-ts-slide id="cart" title="سبد خرید">
        <div class="flex items-center justify-between gap-4">
            <p>ویتامین D3 ۱۰۰۰ واحد × {{ $quantity }}</p>
            <x-ui.price :price="4_850_000 * $quantity" size="sm" />
        </div>
    </x-ts-slide>
</div>
