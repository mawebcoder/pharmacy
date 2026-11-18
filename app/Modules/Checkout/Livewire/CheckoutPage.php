<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Livewire;

use App\Modules\Cart\Actions\ApplyCouponAtCheckout;
use App\Modules\Cart\Data\CheckoutQuoteData;
use App\Modules\Cart\Data\CouponCodeData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Http\Requests\CouponCodeRequest;
use App\Modules\Cart\Queries\CheckoutQuote;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Orders\Actions\PlaceOrder;
use App\Modules\Orders\Data\PlaceOrderData;
use App\Modules\Orders\Exceptions\CheckoutRefused;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Checkout step 2, «بازبینی و پرداخت» (roadmap B9.7, D13.4, S23). Everything is worked out on the fly from the cart
 * now and nothing is written until «پرداخت»: the address (picked, else default; «تغییر» goes back), the shipping
 * options with the one picked (S34), the items, the coupon (applied codes are only logged), the totals. «پرداخت» runs PlaceOrder,
 * which checks it all again and creates the order; then the customer goes to the gateway.
 */
final class CheckoutPage extends Component
{
    public const string COUPON_SESSION_KEY = 'checkout.coupon_code';

    public string $couponInput = '';

    public string $note = '';

    /** The shipping option picked (S34); empty or no longer offered means the cheapest. */
    public string $shippingOption = '';

    /** Why the last «پرداخت» did not go through (prices, stock, coupon or address changed). */
    #[Locked]
    public ?string $notice = null;

    /** The total shown on this render, which «پرداخت» must still match. */
    #[Locked]
    public int $shownTotal = 0;

    public function mount(CheckoutQuote $quotes): void
    {
        $quote = $quotes->forCustomer($this->customerId(), null);

        if (! $quote instanceof CheckoutQuoteData || $quote->cart->isEmpty()) {
            $this->redirectRoute('cart', navigate: true);
        } elseif (! $quote->address instanceof AddressSnapshotData) {
            $this->redirectRoute('checkout.address', navigate: true);
        }
    }

    public function applyCoupon(ApplyCouponAtCheckout $applyCoupon, CheckoutQuote $quotes): void
    {
        $this->resetErrorBag('couponInput');
        $this->couponInput = CouponCodeData::normalize($this->couponInput);
        $messages = [];

        foreach (CouponCodeRequest::validationMessages() as $rule => $message) {
            $messages[str_replace('code.', 'couponInput.', $rule)] = $message;
        }

        $this->validate(['couponInput' => CouponCodeRequest::baseRules()['code']], $messages);

        $quote = $quotes->forCustomer($this->customerId(), null);

        if (! $quote instanceof CheckoutQuoteData) {
            return;
        }

        try {
            $applied = $applyCoupon(CouponCodeData::fromArray(['cart_id' => $quote->cartId, 'code' => $this->couponInput, 'customer_id' => $this->customerId()]));
        } catch (CouponRefused $refused) {
            $this->addError('couponInput', $refused->getMessage());

            return;
        }

        session()->put(self::COUPON_SESSION_KEY, $applied->code);
        $this->couponInput = '';
    }

    public function removeCoupon(): void
    {
        session()->forget(self::COUPON_SESSION_KEY);
    }

    public function pay(PlaceOrder $placeOrder): void
    {
        $this->notice = null;
        $this->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $placed = $placeOrder(new PlaceOrderData(
                customerId: $this->customerId(),
                couponCode: $this->couponCode(),
                shownTotal: $this->shownTotal,
                customerNote: trim($this->note) === '' ? null : trim($this->note),
                shippingOptionKey: $this->shippingOption === '' ? null : $this->shippingOption,
            ));
        } catch (CheckoutRefused $refused) {
            $this->notice = $refused->getMessage();

            return;
        }

        session()->forget(self::COUPON_SESSION_KEY);
        $this->dispatch('cart-updated', items: [], limits: [], count: 0, total: 0);

        if ($placed->paymentUrl !== null) {
            $this->redirect($placed->paymentUrl);

            return;
        }

        $this->redirectRoute('checkout.result', $placed->orderId);
    }

    public function render(CheckoutQuote $quotes): View
    {
        $quote = $quotes->forCustomer($this->customerId(), $this->couponCode(), $this->shippingOption === '' ? null : $this->shippingOption);
        $this->shownTotal = $quote?->total() ?? 0;
        $this->shippingOption = $quote?->shippingOption->key ?? '';

        return view('checkout::livewire.checkout-page', ['quote' => $quote])->title('بازبینی و پرداخت');
    }

    private function couponCode(): ?string
    {
        $code = session(self::COUPON_SESSION_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function customerId(): int
    {
        return (int) auth('customer')->user()?->getAuthIdentifier();
    }
}
