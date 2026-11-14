<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire\Concerns;

use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The signed-in customer for account pages (the routes require the «customer» guard).
 */
trait InteractsWithCustomer
{
    protected function customer(): Customer
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer instanceof Customer) {
            throw new HttpException(401);
        }

        return $customer;
    }
}
