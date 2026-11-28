<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire\Account;

use App\Modules\Identity\Actions\UpdateCustomerProfile;
use App\Modules\Identity\Data\CustomerProfileData;
use App\Modules\Identity\Enums\Gender;
use App\Modules\Identity\Http\Requests\UpdateCustomerProfileRequest;
use App\Modules\Identity\Livewire\Concerns\InteractsWithCustomer;
use App\Modules\Identity\Models\Customer;
use App\Modules\Shared\Support\PersianDigits;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The «اطلاعات حساب» form (roadmap B9.9, S20), framed by the Profile module's account page: name, national id, email,
 * birth date (Jalali picker, stored Gregorian) and gender; the mobile is the sign-in identity and cannot be changed
 * here.
 */
final class Profile extends Component
{
    use Interactions;
    use InteractsWithCustomer;

    /**
     * Keyed by the customers columns (Customer::COLUMN_*); birth_date is a Gregorian Y-m-d.
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    public function mount(): void
    {
        $customer = $this->customer();
        $this->form = [
            Customer::COLUMN_FIRST_NAME => $customer->getFirstName() ?? '',
            Customer::COLUMN_LAST_NAME => $customer->getLastName() ?? '',
            Customer::COLUMN_NATIONAL_ID => PersianDigits::toPersian($customer->getNationalId() ?? ''),
            Customer::COLUMN_EMAIL => $customer->getEmail() ?? '',
            Customer::COLUMN_BIRTH_DATE => $customer->getBirthDate()?->format('Y-m-d'),
            Customer::COLUMN_GENDER => $customer->getGender()?->value,
        ];
    }

    public function save(UpdateCustomerProfile $updateProfile): void
    {
        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $this->form);
        $validator = validator($input, UpdateCustomerProfileRequest::baseRules($input), [], UpdateCustomerProfileRequest::attributeNames());

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())
                ->mapWithKeys(fn (array $messages, string $key): array => ['form.'.$key => $messages])
                ->all());
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();
        $updateProfile($this->customer(), CustomerProfileData::fromArray($validated));

        $this->toast()->success('اطلاعات حساب ذخیره شد')->send();
    }

    public function render(): View
    {
        return view('identity::livewire.account.profile', [
            'mobile' => $this->customer()->getMobile(),
            'genders' => Gender::options(),
        ]);
    }
}
