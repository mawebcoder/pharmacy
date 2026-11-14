<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\SendOtp;
use App\Modules\Identity\Actions\VerifyOtp;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Identity\Exceptions\OtpRefused;
use App\Modules\Identity\Livewire\LoginPage;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Shared\Support\IranMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Pest\Expectation;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Queue::fake();
    RateLimiter::clear('otp:mobile-day:09123456789');
    RateLimiter::clear('otp:ip:10.0.0.1');
});

/**
 * Requests a code and returns it, read from the queued SMS job (the only place the plain code exists).
 */
function requestOtp(string $mobile = '09123456789', string $ip = '10.0.0.1'): string
{
    $sent = app(SendOtp::class)($mobile, $ip);

    $job = Queue::pushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->mobile === $sent)->last();

    return $job->message->args[0];
}

it('normalises Iranian mobile numbers however they are typed', function (string $input, ?string $expected): void {
    expect(IranMobile::normalize($input))->toBe($expected);
})->with([
    'persian digits and spaces' => ['۰۹۱۲ ۳۴۵ ۶۷۸۹', '09123456789'],
    '+98' => ['+989123456789', '09123456789'],
    '0098' => ['00989123456789', '09123456789'],
    'without zero' => ['9123456789', '09123456789'],
    'irancell 0935' => ['09351234567', '09351234567'],
    'rightel 0921' => ['09211234567', '09211234567'],
    'mci 0990' => ['09901234567', '09901234567'],
    'landline' => ['02112345678', null],
    'unused prefix 0906' => ['09061234567', null],
    'unused prefix 0923' => ['09231234567', null],
    'too long' => ['091234567890', null],
    'too short' => ['0912345678', null],
]);

it('queues the code on the sms queue and stores only its hash', function (): void {
    $code = requestOtp('۰۹۱۲۳۴۵۶۷۸۹');

    $otp = OtpCode::query()->sole();
    Queue::assertPushedOn('sms', SendSmsJob::class);
    expect($code)->toMatch('/^\d{5}$/')
        ->and($otp->getMobile())->toBe('09123456789')
        ->and($otp->getCodeHash())->toBe(OtpCode::hash($code))
        ->and($otp->getCodeHash())->not->toContain($code)
        ->and($otp->getExpiresAt()->timestamp)->toBe(now()->addSeconds(120)->timestamp);
});

it('refuses an invalid mobile with its code', function (): void {
    expect(fn (): string => app(SendOtp::class)('12345', '10.0.0.1'))
        ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('invalid_mobile'));
});

it('sends no new code while the last one is still valid and says how long it lasts', function (): void {
    requestOtp();
    $this->travel(50)->seconds();

    try {
        requestOtp();
        $this->fail('A second code was sent while the first was valid.');
    } catch (OtpRefused $refused) {
        expect($refused->errorCode())->toBe('otp_live_code_exists')
            ->and($refused->liveCodeSeconds)->toBe(70);
    }

    Queue::assertPushed(SendSmsJob::class, 1);

    $this->travel(71)->seconds();
    requestOtp();
    Queue::assertPushed(SendSmsJob::class, 2);
});

it('limits codes to 10 a day per mobile and 5 an hour per IP', function (): void {
    foreach (range(1, 5) as $index) {
        requestOtp('0912000000'.$index, '10.0.0.1');
    }

    expect(fn (): string => requestOtp('09120000009', '10.0.0.1'))
        ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('otp_too_many_requests'));

    foreach (range(1, 10) as $index) {
        requestOtp('09123456789', '10.1.0.'.$index);
        $this->travel(121)->seconds();
    }

    expect(fn (): string => requestOtp('09123456789', '10.2.0.1'))
        ->toThrow(OtpRefused::class);
});

it('never gives two live codes the same value', function (): void {
    $code = requestOtp();

    expect(OtpCode::isLive($code))->toBeTrue();

    $this->travel(121)->seconds();
    expect(OtpCode::isLive($code))->toBeFalse();
});

it('signs a new customer in with the right code, once', function (): void {
    Event::fake([CustomerLoggedIn::class]);
    $code = requestOtp();

    $customer = app(VerifyOtp::class)('09123456789', $code);

    expect(Auth::guard('customer')->id())->toBe($customer->getId())
        ->and($customer->getMobile())->toBe('09123456789')
        ->and($customer->getLastLoginAt())->not->toBeNull()
        ->and(OtpCode::query()->count())->toBe(0);
    Event::assertDispatched(CustomerLoggedIn::class, fn (CustomerLoggedIn $event): bool => $event->customerId === $customer->getId() && $event->isNewCustomer);

    expect(fn (): Customer => app(VerifyOtp::class)('09123456789', $code))
        ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('otp_expired'));
});

it('signs an existing customer in without creating another', function (): void {
    Event::fake([CustomerLoggedIn::class]);
    $existing = Customer::factory()->create([Customer::COLUMN_MOBILE => '09123456789']);

    $customer = app(VerifyOtp::class)('09123456789', requestOtp());

    expect($customer->getId())->toBe($existing->getId())
        ->and(Customer::query()->count())->toBe(1);
    Event::assertDispatched(CustomerLoggedIn::class, fn (CustomerLoggedIn $event): bool => ! $event->isNewCustomer);
});

it('accepts a code typed in Persian digits', function (): void {
    $code = requestOtp();

    $customer = app(VerifyOtp::class)('09123456789', strtr($code, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']));

    expect($customer->getMobile())->toBe('09123456789');
});

it('allows five tries per code and then refuses even the right one', function (): void {
    $code = requestOtp();
    $wrong = $code === '11111' ? '22222' : '11111';

    foreach (range(1, 4) as $try) {
        expect(fn (): Customer => app(VerifyOtp::class)('09123456789', $wrong))
            ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('otp_wrong_code'));
    }

    expect(fn (): Customer => app(VerifyOtp::class)('09123456789', $wrong))
        ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('otp_too_many_attempts'))
        ->and(fn (): Customer => app(VerifyOtp::class)('09123456789', $code))
        ->toThrow(OtpRefused::class);
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('refuses an expired code', function (): void {
    $code = requestOtp();
    $this->travel(121)->seconds();

    expect(fn (): Customer => app(VerifyOtp::class)('09123456789', $code))
        ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('otp_expired'));
});

it('refuses a blocked customer', function (): void {
    Customer::factory()->blocked()->create([Customer::COLUMN_MOBILE => '09123456789']);

    expect(fn (): Customer => app(VerifyOtp::class)('09123456789', requestOtp()))
        ->toThrow(fn (OtpRefused $refused): Expectation => expect($refused->errorCode())->toBe('customer_blocked')->and($refused->status())->toBe(403));
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('renders a refusal as JSON with message and code', function (): void {
    $request = Request::create('/api/login', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = OtpRefused::tooManyRequests()->render($request);

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getData(true))->toBe([
            'message' => 'درخواست‌های زیادی ثبت شده است؛ بعداً دوباره تلاش کنید.',
            'code' => 'otp_too_many_requests',
        ]);
});

it('walks a new customer through mobile, code and name back to where they came from', function (): void {
    session()->put('url.intended', url('/search?q=ژل'));

    $page = livewire(LoginPage::class)
        ->set('mobile', '۰۹۱۲۳۴۵۶۷۸۹')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('step', LoginPage::STEP_CODE)
        ->assertSee('۰۹۱۲۳۴۵۶۷۸۹');

    $code = Queue::pushed(SendSmsJob::class)->last()->message->args[0];

    $page->set('code', $code)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSet('step', LoginPage::STEP_NAME)
        ->set('firstName', 'سارا')
        ->set('lastName', 'رضایی')
        ->call('saveName')
        ->assertRedirect(url('/search?q=ژل'));

    $customer = Customer::query()->sole();
    expect(Auth::guard('customer')->id())->toBe($customer->getId())
        ->and($customer->displayName())->toBe('سارا رضایی');
});

it('sends a returning customer with a name straight on', function (): void {
    Customer::factory()->named()->create([Customer::COLUMN_MOBILE => '09123456789']);
    $page = livewire(LoginPage::class)->set('mobile', '09123456789')->call('sendCode');

    $page->set('code', Queue::pushed(SendSmsJob::class)->last()->message->args[0])
        ->call('verify')
        ->assertRedirect(route('home'));
});

it('shows the server message on the code step when a live code exists', function (): void {
    requestOtp();

    livewire(LoginPage::class)
        ->set('mobile', '09123456789')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('step', LoginPage::STEP_CODE)
        ->assertSee('کد تأیید قبلاً برای این شماره ارسال شده');

    Queue::assertPushed(SendSmsJob::class, 1);
});

it('shows the refusal under the field for a wrong code or mobile', function (): void {
    livewire(LoginPage::class)
        ->set('mobile', '09061234567')
        ->call('sendCode')
        ->assertHasErrors('mobile')
        ->assertSee('شماره موبایل معتبر نیست')
        ->assertSet('step', LoginPage::STEP_MOBILE);
    Queue::assertNothingPushed();

    $code = requestOtp();

    livewire(LoginPage::class)
        ->set('mobile', '09123456789')
        ->call('sendCode')
        ->set('code', $code === '11111' ? '22222' : '11111')
        ->call('verify')
        ->assertHasErrors('code')
        ->assertSee('کد واردشده درست نیست.');
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('shows guests a sign-in link and customers their name with sign-out', function (): void {
    $this->get(route('home'))->assertOk()->assertSee(route('login'))->assertDontSee('خروج');

    $customer = Customer::factory()->named()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('سارا رضایی')
        ->assertSee('خروج');
});

it('sends signed-in customers away from the login page and signs them out', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')->get(route('login'))->assertRedirect();

    $this->post(route('logout'))->assertRedirect(route('home'));
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('accepts the bypass code on the local environment only', function (string $environment, bool $accepted): void {
    config(['auth.otp.bypass.enabled' => true, 'auth.otp.bypass.code' => '11111']);
    $code = requestOtp();
    $this->app['env'] = $environment;

    $verify = fn (): Customer => app(VerifyOtp::class)('09123456789', $code === '11111' ? '11111' : '۱۱۱۱۱');

    $accepted ? expect($verify())->toBeInstanceOf(Customer::class) : expect($verify)->toThrow(OtpRefused::class);
})->with([
    'local' => ['local', true],
    'production' => ['production', false],
]);

it('refuses the bypass code when the flag is off, even locally', function (): void {
    config(['auth.otp.bypass.enabled' => false]);
    $code = requestOtp();
    $this->app['env'] = 'local';

    expect(fn (): Customer => app(VerifyOtp::class)('09123456789', $code === '11111' ? '22222' : '11111'))
        ->toThrow(OtpRefused::class);
});
