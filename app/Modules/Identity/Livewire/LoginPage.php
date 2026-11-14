<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire;

use App\Modules\Identity\Actions\SendOtp;
use App\Modules\Identity\Actions\UpdateCustomerName;
use App\Modules\Identity\Actions\VerifyOtp;
use App\Modules\Identity\Exceptions\OtpRefused;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Shared\Rules\IranianMobile;
use App\Modules\Shared\Support\IranMobile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sign in or sign up with a one-time SMS code (roadmap B9.10, D13.1): mobile → 5-digit code → (first sign-in only)
 * an optional name. Afterwards the customer returns to the page they came from, or the home page.
 */
final class LoginPage extends Component
{
    public const string STEP_MOBILE = 'mobile';

    public const string STEP_CODE = 'code';

    public const string STEP_NAME = 'name';

    #[Locked]
    public string $step = self::STEP_MOBILE;

    public string $mobile = '';

    #[Locked]
    public string $verifiedMobile = '';

    public string $code = '';

    /** Unix time when «ارسال مجدد» is allowed again. */
    #[Locked]
    public int $resendAvailableAt = 0;

    /** A message from the server shown above the code boxes (e.g. «the code sent earlier is still valid»). */
    #[Locked]
    public ?string $notice = null;

    public string $firstName = '';

    public string $lastName = '';

    public function mount(): void
    {
        $previous = url()->previous();

        if (! session()->has('url.intended') && Str::startsWith($previous, url('/')) && ! Str::startsWith($previous, route('login'))) {
            session()->put('url.intended', $previous);
        }
    }

    public function sendCode(SendOtp $sendOtp): void
    {
        $this->resetErrorBag();
        $this->validate(['mobile' => ['required', new IranianMobile]], ['mobile.required' => 'شماره موبایل را وارد کنید.']);

        $this->notice = null;

        try {
            $this->verifiedMobile = $sendOtp($this->mobile, request()->ip());
        } catch (OtpRefused $refused) {
            if ($refused->liveCodeSeconds === null) {
                $this->addError('mobile', $refused->getMessage());

                return;
            }

            // No new SMS: the customer enters the code they already have.
            $this->verifiedMobile = (string) IranMobile::normalize($this->mobile);
            $this->notice = $refused->getMessage();
            $this->showCodeStep($refused->liveCodeSeconds);

            return;
        }

        $this->showCodeStep(SendOtp::CODE_TTL_SECONDS);
    }

    public function resend(SendOtp $sendOtp): void
    {
        $this->resetErrorBag();

        $this->notice = null;

        try {
            $this->verifiedMobile = $sendOtp($this->verifiedMobile, request()->ip());
        } catch (OtpRefused $refused) {
            if ($refused->liveCodeSeconds === null) {
                $this->addError('code', $refused->getMessage());

                return;
            }

            $this->notice = $refused->getMessage();
            $this->showCodeStep($refused->liveCodeSeconds);
            $this->dispatch('otp-resent');

            return;
        }

        $this->showCodeStep(SendOtp::CODE_TTL_SECONDS);
        $this->dispatch('otp-resent');
    }

    private function showCodeStep(int $validForSeconds): void
    {
        $this->code = '';
        $this->step = self::STEP_CODE;
        $this->resendAvailableAt = now()->addSeconds($validForSeconds)->getTimestamp();
    }

    public function editMobile(): void
    {
        $this->resetErrorBag();
        $this->notice = null;
        $this->code = '';
        $this->step = self::STEP_MOBILE;
    }

    public function verify(VerifyOtp $verifyOtp): void
    {
        $this->resetErrorBag();

        try {
            $customer = $verifyOtp($this->verifiedMobile, $this->code);
        } catch (OtpRefused $refused) {
            $this->code = '';
            $this->addError('code', $refused->getMessage());
            $this->dispatch('otp-rejected');

            return;
        }

        session()->regenerate();

        if ($customer->getFirstName() === null) {
            $this->step = self::STEP_NAME;

            return;
        }

        $this->finish();
    }

    public function saveName(UpdateCustomerName $updateName): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:60'],
            'lastName' => ['required', 'string', 'max:60'],
        ], attributes: ['firstName' => 'نام', 'lastName' => 'نام خانوادگی']);

        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Customer) {
            $updateName($customer, $this->firstName, $this->lastName);
        }

        $this->finish();
    }

    public function finish(): void
    {
        $this->redirectIntended(default: route('home'));
    }

    public function render(): View
    {
        return view('identity::livewire.login-page', [
            'bypassCode' => OtpCode::bypassCode(),
        ])->title('ورود یا ثبت‌نام');
    }
}
