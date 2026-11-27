{{-- «آدرس‌ها» (roadmap B9.9, D3.3): see App\Modules\Identity\Livewire\Account\Addresses. --}}
@use('App\Modules\Identity\Models\Address')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $fieldClass = 'h-11 w-full rounded-card border bg-card px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700 disabled:bg-surface-50 disabled:text-ink-500';
    // Required fields, as SaveAddressRequest validates them, get a red «*» on their label (owner, 2026-11-27).
    $requiredFields = array_keys(array_filter(
        \App\Modules\Identity\Http\Requests\SaveAddressRequest::baseRules([]),
        fn (array $rules): bool => in_array('required', $rules, true),
    ));
    $field = function (string $key, string $label, array $attributes = []) use ($errors, $fieldClass, $requiredFields): array {
        $required = in_array($key, $requiredFields, true);

        return ['key' => $key, 'label' => $label, 'required' => $required, 'attributes' => $required ? [...$attributes, 'aria-required' => 'true'] : $attributes, 'class' => $fieldClass.' '.($errors->has('form.'.$key) ? 'border-danger-600' : 'border-line-300')];
    };
@endphp

{{-- One root element: the address book is nested in the account page «آدرس‌ها» (Profile) and the checkout address step (S23). --}}
<div class="space-y-4">
    @include('identity::livewire.account.partials.addresses-content')
</div>
