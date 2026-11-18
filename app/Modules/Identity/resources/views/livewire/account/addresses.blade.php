{{-- «آدرس‌ها» (roadmap B9.9, D3.3): see App\Modules\Identity\Livewire\Account\Addresses. --}}
@use('App\Modules\Identity\Models\Address')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $fieldClass = 'h-11 w-full rounded-card border bg-card px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700 disabled:bg-surface-50 disabled:text-ink-500';
    $field = function (string $key, string $label, array $attributes = []) use ($errors, $fieldClass): array {
        return ['key' => $key, 'label' => $label, 'attributes' => $attributes, 'class' => $fieldClass.' '.($errors->has('form.'.$key) ? 'border-danger-600' : 'border-line-300')];
    };
@endphp

{{-- One root element: the list is also nested in the checkout address step (S23). --}}
<div>
    @if ($forCheckout)
        <div class="space-y-4">
            @include('identity::livewire.account.partials.addresses-content')
        </div>
    @else
        <x-identity::account-shell active="addresses" title="آدرس‌ها">
            @include('identity::livewire.account.partials.addresses-content')
        </x-identity::account-shell>
    @endif
</div>
