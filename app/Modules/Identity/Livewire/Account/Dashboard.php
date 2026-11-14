<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire\Account;

use App\Modules\Catalog\Queries\CustomerWishlist;
use App\Modules\Identity\Livewire\Concerns\InteractsWithCustomer;
use App\Modules\Identity\Models\Address;
use App\Modules\Locations\Queries\DeliveryLocations;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Account home (roadmap B9.9, S18): greeting, default address, saved products, and (on phones) the account menu.
 */
final class Dashboard extends Component
{
    use InteractsWithCustomer;

    public function render(CustomerWishlist $wishlist, DeliveryLocations $locations): View
    {
        $customer = $this->customer();
        $defaultAddress = Address::query()->ownedBy($customer->getId())->where(Address::COLUMN_IS_DEFAULT, true)->first();

        return view('identity::livewire.account.dashboard', [
            'customer' => $customer,
            'defaultAddress' => $defaultAddress,
            'cityLabel' => $defaultAddress === null ? null : ($locations->cityLabels([$defaultAddress->getCityId()])[$defaultAddress->getCityId()] ?? null),
            'addressCount' => Address::query()->ownedBy($customer->getId())->count(),
            'wishlistCount' => $wishlist->count($customer->getId()),
        ])->title('حساب من');
    }
}
