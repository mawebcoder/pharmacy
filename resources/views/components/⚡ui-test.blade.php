<?php

use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Temporary page for checking TallStackUI components in RTL / Persian before adopting the kit.
 */
new #[Layout('layouts::app')] #[Title('تست رابط کاربری')] class extends Component
{
    use Interactions;

    public string $search = '';

    public ?string $brand = null;

    public int $quantity = 1;

    public bool $isSubscribed = true;

    public ?string $deliveryDate = null;

    public ?string $birthDate = null;

    public function saveDates(): void
    {
        $today = now(config('app.display_timezone'))->toDateString();

        $this->validate([
            'deliveryDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'birthDate' => ['nullable', 'date_format:Y-m-d', 'before:'.$today],
        ], attributes: [
            'deliveryDate' => 'تاریخ تحویل',
            'birthDate' => 'تاریخ تولد',
        ]);

        $this->toast()->success('ذخیره شد', 'تحویل: '.Carbon::parse($this->deliveryDate)->toJalali('l j F Y'))->send();
    }

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
            ->confirm('بله، حذف کن', 'remove')
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
            'today' => Carbon::now(),
            'brands' => [
                ['label' => 'نیچرز بونتی', 'value' => 'natures-bounty'],
                ['label' => 'ویتابیوتیکس', 'value' => 'vitabiotics'],
                ['label' => 'یوروویتال', 'value' => 'eurho-vital'],
                ['label' => 'هلث اید', 'value' => 'health-aid'],
            ],
        ];
    }
};
?>

<div class="mx-auto max-w-3xl space-y-8 px-4 py-8">
    <header class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary-700">تست TallStackUI در حالت راست‌به‌چپ</h1>
            <p class="mt-1 text-sm text-muted">
                امروز: {{ $today->toJalali('l j F Y') }} — ساعت {{ $today->toJalali('H:i') }} (تهران)
            </p>
        </div>

        <x-ts-dropdown icon="user-circle" text="حساب من">
            <x-ts-dropdown.items icon="shopping-bag" text="سفارش‌های من" />
            <x-ts-dropdown.items icon="heart" text="علاقه‌مندی‌ها" />
            <x-ts-dropdown.items icon="arrow-left-on-rectangle" text="خروج" separator />
        </x-ts-dropdown>
    </header>

    <x-ts-alert title="ارسال رایگان" :text="'برای سفارش‌های بالای '.Number::rial(5_000_000).' ارسال رایگان است.'" icon="truck" light />

    <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="font-bold text-ink">فرم و ورودی‌ها</h2>

        <x-ts-input label="جستجو" hint="نام محصول، برند یا ماده مؤثره" icon="magnifying-glass" wire:model.live="search" clearable placeholder="مثلاً ویتامین D" />

        <x-ts-select.styled label="برند" :options="$brands" select="label:label|value:value" wire:model.live="brand" searchable />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ts-number label="تعداد" wire:model.live="quantity" min="1" max="10" />
            <x-ts-input label="قیمت" suffix="ریال" :value="Number::rial(4_850_000, withUnit: false)" readonly />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-jalali-date label="تاریخ تحویل" hint="از امروز تا ۳۰ روز آینده" wire:model.live="deliveryDate" :min="$today" :max="$today->copy()->addDays(30)" />
            <x-jalali-date label="تاریخ تولد" wire:model.live="birthDate" :max="$today->copy()->subDay()" />
        </div>

        <x-ts-toggle label="عضویت در خبرنامه و یادآوری خرید مجدد" wire:model.live="isSubscribed" />

        <p class="text-sm text-muted">
            برند: {{ $brand ?? '—' }} · تعداد: {{ $quantity }} · تاریخ انتخاب‌شده (شمسی):
            {{ $deliveryDate ? Carbon::parse($deliveryDate)->toJalali('Y/m/d') : '—' }}
            · مقدار ذخیره‌شده: <code dir="ltr">{{ $deliveryDate ?? 'null' }}</code>
        </p>

        <x-ts-button wire:click="saveDates" icon="check">ثبت تاریخ‌ها</x-ts-button>
    </section>

    <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="font-bold text-ink">کارت محصول و نشان‌ها</h2>

        <div class="flex flex-wrap gap-2">
            <x-ts-badge text="۲۰٪ تخفیف" color="red" />
            <x-ts-badge text="موجود" color="green" light />
            <x-ts-badge text="زودمصرف" color="amber" light />
            <x-ts-badge text="نسخه‌ای" color="violet" light />
            <x-ts-badge text="اصالت کالا" icon="check-badge" />
        </div>

        <x-ts-rating :rate="4" static text="۴ از ۵ (۱۲۸ نظر)" />

        <div class="flex flex-wrap gap-3">
            <x-ts-button wire:click="addToCart" icon="shopping-cart">افزودن به سبد</x-ts-button>
            <x-ts-button color="red" outline wire:click="confirmRemove" icon="trash">حذف</x-ts-button>
            <x-ts-button color="secondary" outline x-on:click="$tsui.open.modal('ask-pharmacist')" icon="chat-bubble-left-right">از داروساز بپرسید</x-ts-button>
            <x-ts-button color="secondary" outline x-on:click="$tsui.open.slide('cart')" icon="shopping-bag">سبد خرید (کشویی)</x-ts-button>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm">
        <x-ts-tab selected="description">
            <x-ts-tab.items tab="description" title="توضیحات">
                ویتامین D3 به جذب کلسیم و سلامت استخوان‌ها کمک می‌کند.
            </x-ts-tab.items>
            <x-ts-tab.items tab="usage" title="نحوه مصرف">
                روزانه یک عدد همراه با غذا مصرف شود.
            </x-ts-tab.items>
            <x-ts-tab.items tab="warnings" title="هشدارها">
                در صورت مصرف داروهای دیگر با پزشک یا داروساز مشورت کنید.
            </x-ts-tab.items>
        </x-ts-tab>
    </section>

    <x-ts-modal id="ask-pharmacist" title="از داروساز بپرسید">
        <x-ts-textarea label="سؤال شما" placeholder="سؤال خود را درباره این محصول بنویسید..." />
        <x-slot:footer>
            <x-ts-button x-on:click="$tsui.close.modal('ask-pharmacist')">ارسال سؤال</x-ts-button>
        </x-slot:footer>
    </x-ts-modal>

    <x-ts-slide id="cart" title="سبد خرید">
        <p>ویتامین D3 ۱۰۰۰ واحد × {{ $quantity }}</p>
        <x-ts-progress percent="70" />
        <p class="mt-2 text-sm text-muted">{{ Number::rial(1_500_000) }} تا ارسال رایگان</p>
    </x-ts-slide>
</div>
