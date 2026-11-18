<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use App\Modules\Catalog\Queries\CustomerWishlist;
use App\Modules\Identity\Livewire\Concerns\InteractsWithCustomer;
use App\Modules\Identity\Queries\CustomerAddresses;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Account home (roadmap B9.9, S20): greeting, default address, saved products, and (on phones) the account menu.
 */
final class Dashboard extends Component
{
    use InteractsWithCustomer;

    public function render(CustomerWishlist $wishlist, CustomerAddresses $addresses): View
    {
        $customer = $this->customer();

        return view('profile::livewire.dashboard', [
            'firstName' => $customer->getFirstName(),
            'defaultAddress' => $addresses->defaultSnapshot($customer->getId()),
            'addressCount' => $addresses->count($customer->getId()),
            'wishlistCount' => $wishlist->count($customer->getId()),
        ])->title('حساب من');
    }
}
