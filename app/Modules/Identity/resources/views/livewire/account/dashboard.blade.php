{{-- Account home (roadmap B9.9): see App\Modules\Identity\Livewire\Account\Dashboard. --}}
@use('App\Modules\Shared\Support\PersianDigits')

<x-identity::account-shell active="dashboard" title="پیشخوان">
    <div class="rounded-card border border-line-200 bg-white p-4 md:p-6">
        <p class="text-lg font-bold text-ink-900">
            سلام{{ $customer->getFirstName() ? '، '.$customer->getFirstName() : '' }}!
        </p>
        <p class="mt-1 text-sm text-ink-500">از اینجا سفارش‌ها، آدرس‌ها و علاقه‌مندی‌های خود را مدیریت کنید.</p>
        @if ($customer->getFirstName() === null)
            <a href="{{ route('account.profile') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary-700 hover:underline">
                <x-ts-icon icon="pencil-square" class="size-4" />
                تکمیل اطلاعات حساب
            </a>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ route('account.orders') }}" wire:navigate class="rounded-card border border-line-200 bg-white p-4 hover:border-primary-200 focus-visible:outline-2 focus-visible:outline-primary-700">
            <x-ts-icon icon="shopping-bag" class="size-6 text-primary-700" />
            <p class="mt-2 text-sm text-ink-500">سفارش‌ها</p>
            <p class="text-xl font-bold text-ink-900">{{ PersianDigits::toPersian(0) }}</p>
        </a>
        <a href="{{ route('account.addresses') }}" wire:navigate class="rounded-card border border-line-200 bg-white p-4 hover:border-primary-200 focus-visible:outline-2 focus-visible:outline-primary-700">
            <x-ts-icon icon="map-pin" class="size-6 text-primary-700" />
            <p class="mt-2 text-sm text-ink-500">آدرس‌ها</p>
            <p class="text-xl font-bold text-ink-900">{{ PersianDigits::toPersian($addressCount) }}</p>
        </a>
        <a href="{{ route('account.wishlist') }}" wire:navigate class="rounded-card border border-line-200 bg-white p-4 hover:border-primary-200 focus-visible:outline-2 focus-visible:outline-primary-700">
            <x-ts-icon icon="heart" class="size-6 text-primary-700" />
            <p class="mt-2 text-sm text-ink-500">علاقه‌مندی‌ها</p>
            <p class="text-xl font-bold text-ink-900">{{ PersianDigits::toPersian($wishlistCount) }}</p>
        </a>
    </div>

    <div class="rounded-card border border-line-200 bg-white p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold text-ink-900">آدرس پیش‌فرض</h2>
            <a href="{{ route('account.addresses') }}" wire:navigate class="min-h-11 content-center text-sm text-primary-700 hover:underline">مدیریت آدرس‌ها</a>
        </div>
        @if ($defaultAddress)
            <p class="mt-2 text-sm font-medium text-ink-900">{{ $defaultAddress->getTitle() }} · {{ $defaultAddress->getReceiverName() }}</p>
            <p class="mt-1 text-sm leading-7 text-ink-500">{{ $cityLabel }}، {{ PersianDigits::toPersian($defaultAddress->getFullAddress()) }}، پلاک {{ PersianDigits::toPersian($defaultAddress->getPlaque()) }}</p>
        @else
            <p class="mt-2 text-sm text-ink-500">هنوز آدرسی ثبت نکرده‌اید.</p>
        @endif
    </div>
</x-identity::account-shell>
