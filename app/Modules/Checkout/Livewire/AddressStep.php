<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Livewire;

use App\Modules\Cart\Actions\PickCartAddress;
use App\Modules\Cart\Data\CheckoutQuoteData;
use App\Modules\Cart\Queries\CheckoutQuote;
use App\Modules\Identity\Queries\CustomerAddresses;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Checkout step 1, «آدرس ارسال» (roadmap B9.7, owner 2026-11-14): always shown after the cart. The customer's
 * addresses (Identity's address list in checkout mode: add, edit, make default) with «ارسال به این آدرس»; the choice
 * is kept on the cart. Without a choice checkout uses the default address.
 */
final class AddressStep extends Component
{
    /** The address the order goes to now: picked on the cart, else the default. */
    #[Locked]
    public ?int $chosenId = null;

    public function mount(CheckoutQuote $quotes): void
    {
        $quote = $quotes->forCustomer($this->customerId(), null);

        if (! $quote instanceof CheckoutQuoteData || $quote->cart->isEmpty()) {
            $this->redirectRoute('cart', navigate: true);

            return;
        }

        $this->chosenId = $quote->address?->addressId;
    }

    #[On('address-picked')]
    public function picked(int $addressId, CustomerAddresses $addresses, PickCartAddress $pickAddress): void
    {
        $customerId = $this->customerId();

        if ($addresses->chosenId($customerId, $addressId) !== $addressId) {
            return;
        }

        $pickAddress($customerId, $addressId);
        $this->redirectRoute('checkout', navigate: true);
    }

    public function proceed(CustomerAddresses $addresses): void
    {
        if ($addresses->chosenId($this->customerId(), null) === null) {
            $this->addError('address', 'ابتدا یک آدرس اضافه کنید.');

            return;
        }

        $this->redirectRoute('checkout', navigate: true);
    }

    public function render(): View
    {
        return view('checkout::livewire.address-step')->title('آدرس ارسال');
    }

    private function customerId(): int
    {
        return (int) auth('customer')->user()?->getAuthIdentifier();
    }
}
