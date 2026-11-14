<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire\Account;

use App\Modules\Identity\Actions\DeleteAddress;
use App\Modules\Identity\Actions\SaveAddress;
use App\Modules\Identity\Actions\SetDefaultAddress;
use App\Modules\Identity\Data\AddressData;
use App\Modules\Identity\Exceptions\AddressRefused;
use App\Modules\Identity\Http\Requests\SaveAddressRequest;
use App\Modules\Identity\Livewire\Concerns\InteractsWithCustomer;
use App\Modules\Identity\Models\Address;
use App\Modules\Locations\Queries\DeliveryLocations;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * «آدرس‌ها» (roadmap B9.9, D3.3, S18): the customer's addresses as cards (at most 10) with add / edit in a modal,
 * «پیش‌فرض شود» and delete. Provinces and cities are Tipax's, limited to cities with delivery on (admin «شهرها»).
 * Writes go through the address actions.
 *
 * @property-read Collection<int, Address> $addresses
 * @property-read array<int, string> $cityLabels
 */
final class Addresses extends Component
{
    use Interactions;
    use InteractsWithCustomer;

    public bool $showForm = false;

    public ?int $editingId = null;

    /**
     * The form, keyed by the addresses columns (Address::COLUMN_*).
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * @return Collection<int, Address>
     */
    #[Computed]
    public function addresses(): Collection
    {
        return Address::query()
            ->ownedBy($this->customer()->getId())
            ->orderByDesc(Address::COLUMN_IS_DEFAULT)
            ->latest(Address::COLUMN_ID)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function cityLabels(): array
    {
        return app(DeliveryLocations::class)->cityLabels(array_values($this->addresses->map(fn (Address $address): int => $address->getCityId())->all()));
    }

    public function create(): void
    {
        $customer = $this->customer();
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            Address::COLUMN_TITLE => $this->addresses->isEmpty() ? 'خانه' : '',
            Address::COLUMN_RECEIVER_NAME => trim(($customer->getFirstName() ?? '').' '.($customer->getLastName() ?? '')),
            Address::COLUMN_RECEIVER_MOBILE => $customer->getMobile(),
            Address::COLUMN_PROVINCE_ID => null,
            Address::COLUMN_CITY_ID => null,
            Address::COLUMN_FULL_ADDRESS => '',
            Address::COLUMN_PLAQUE => '',
            Address::COLUMN_UNIT => '',
            Address::COLUMN_FLOOR => '',
            Address::COLUMN_POSTAL_CODE => '',
            Address::COLUMN_IS_DEFAULT => $this->addresses->isEmpty(),
        ];
        $this->showForm = true;
    }

    public function edit(int $addressId): void
    {
        $address = $this->ownAddress($addressId);
        $this->resetValidation();
        $this->editingId = $address->getId();
        $this->form = [
            Address::COLUMN_TITLE => $address->getTitle(),
            Address::COLUMN_RECEIVER_NAME => $address->getReceiverName(),
            Address::COLUMN_RECEIVER_MOBILE => $address->getReceiverMobile(),
            Address::COLUMN_PROVINCE_ID => $address->getProvinceId(),
            Address::COLUMN_CITY_ID => $address->getCityId(),
            Address::COLUMN_FULL_ADDRESS => $address->getFullAddress(),
            Address::COLUMN_PLAQUE => $address->getPlaque(),
            Address::COLUMN_UNIT => $address->getUnit() ?? '',
            Address::COLUMN_FLOOR => $address->getFloor() ?? '',
            Address::COLUMN_POSTAL_CODE => $address->getPostalCode(),
            Address::COLUMN_IS_DEFAULT => $address->isDefault(),
        ];
        $this->showForm = true;
    }

    /**
     * A new province empties the city.
     */
    public function updated(string $property): void
    {
        if ($property === 'form.'.Address::COLUMN_PROVINCE_ID) {
            $this->form[Address::COLUMN_CITY_ID] = null;
        }
    }

    public function save(SaveAddress $saveAddress): void
    {
        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $this->form);
        $validator = validator($input, SaveAddressRequest::baseRules($input), [], SaveAddressRequest::attributeNames());

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())
                ->mapWithKeys(fn (array $messages, string $key): array => ['form.'.$key => $messages])
                ->all());
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        try {
            $saved = $saveAddress($this->customer(), AddressData::fromArray($validated), $this->editingId === null ? null : $this->ownAddress($this->editingId));
        } catch (AddressRefused $refused) {
            $this->addError('form.'.Address::COLUMN_TITLE, $refused->getMessage());

            return;
        }

        $this->showForm = false;
        unset($this->addresses, $this->cityLabels);
        $this->toast()->success($this->editingId === null ? 'آدرس اضافه شد' : 'آدرس ذخیره شد', $saved->getTitle())->send();
    }

    public function makeDefault(int $addressId, SetDefaultAddress $setDefault): void
    {
        $setDefault($this->customer(), $this->ownAddress($addressId));
        unset($this->addresses);
        $this->toast()->success('آدرس پیش‌فرض تغییر کرد')->send();
    }

    public function delete(int $addressId, DeleteAddress $deleteAddress): void
    {
        $deleteAddress($this->customer(), $this->ownAddress($addressId));
        unset($this->addresses, $this->cityLabels);
        $this->toast()->success('آدرس حذف شد')->send();
    }

    public function render(DeliveryLocations $locations): View
    {
        $provinces = $locations->provinces();

        return view('identity::livewire.account.addresses', [
            'provinces' => $provinces,
            'cities' => $locations->cities(filled($this->form[Address::COLUMN_PROVINCE_ID] ?? null) ? (int) $this->form[Address::COLUMN_PROVINCE_ID] : null),
            'canDeliver' => $provinces !== [],
            'maxAddresses' => Address::MAX_PER_CUSTOMER,
        ])->title('آدرس‌ها');
    }

    /**
     * Another customer's address is not found (404), never shown or changed.
     */
    private function ownAddress(int $addressId): Address
    {
        return Address::query()->ownedBy($this->customer()->getId())->findOrFail($addressId);
    }
}
