<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Identity\Exceptions\OtpRefused;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Support\Facades\Auth;

/**
 * Checks a sign-in code and signs the customer in on the «customer» guard (roadmap D13.1, S17), creating the
 * account on first sign-in. A code allows 5 tries and is used once. On APP_ENV=local the bypass code
 * (OTP_BYPASS_CODE) is accepted in place of the sent one. Blocked customers are refused. The caller
 * regenerates the session.
 */
final readonly class VerifyOtp
{
    public const int MAX_ATTEMPTS = 5;

    /**
     * @throws OtpRefused
     */
    #[\NoDiscard]
    public function __invoke(string $mobile, string $codeInput): Customer
    {
        $code = PersianNormalizer::digits(trim($codeInput));

        $otp = OtpCode::query()
            ->where(OtpCode::COLUMN_MOBILE, $mobile)
            ->where(OtpCode::COLUMN_PURPOSE, OtpCode::PURPOSE_LOGIN)
            ->latest(OtpCode::COLUMN_ID)
            ->first();

        if ($otp === null || $otp->getExpiresAt()->isPast()) {
            throw OtpRefused::expired();
        }

        if ($otp->getAttempts() >= self::MAX_ATTEMPTS) {
            $otp->delete();

            throw OtpRefused::tooManyAttempts();
        }

        $bypassCode = OtpCode::bypassCode();
        $isBypass = $bypassCode !== null && hash_equals($bypassCode, $code);

        if (! $isBypass && ! hash_equals($otp->getCodeHash(), OtpCode::hash($code))) {
            $attempts = $otp->getAttempts() + 1;
            $otp->setAttempts($attempts)->save();

            throw $attempts >= self::MAX_ATTEMPTS ? OtpRefused::tooManyAttempts() : OtpRefused::wrongCode();
        }

        $otp->delete();

        $customer = Customer::query()->where(Customer::COLUMN_MOBILE, $mobile)->first();
        $isNewCustomer = $customer === null;
        $customer ??= (new Customer)->setMobile($mobile);

        if ($customer->isBlocked()) {
            throw OtpRefused::blocked();
        }

        $customer->setLastLoginAt(now())->save();

        Auth::guard('customer')->login($customer, remember: true);

        event(new CustomerLoggedIn($customer->getId(), $isNewCustomer));

        return $customer;
    }
}
